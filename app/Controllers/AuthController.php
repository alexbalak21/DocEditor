<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;
use App\Services\RememberService;
use App\Services\TwoFactorService;

class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) Response::redirect('/documents');

        $request = new Request();
        Response::view('auth.login', [
            'title' => 'Sign in',
            'next'  => (string) $request->query('next', ''),
            'error' => Session::pull('login_error'),
            'email' => (string) Session::pull('login_email', ''),
        ], layout: 'auth');
    }

    public function login(): void
    {
        $request  = new Request();
        $email    = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');
        $remember = (string) $request->input('remember', '') !== '';
        $next     = self::safeNext((string) $request->input('next', ''));

        $result = (new AuthService())->attempt($email, $password, Request::ip());

        if (!$result['ok']) {
            Session::flash('login_error', $result['error']);
            Session::flash('login_email', trim($email));
            Response::redirectTo(BASE_URL . '/login' . ($next ? '?next=' . rawurlencode($next) : ''));
            return;
        }

        // Credentials OK — check if 2FA is enabled
        if (TwoFactorService::isEnabled()) {
            try {
                $svc       = new TwoFactorService();
                $pendingId = $svc->issue($result['user'], Request::ip());

                // Store state needed for the verify step (NOT the user object — not logged in yet)
                Session::start();
                $_SESSION['2fa_pending']  = $pendingId;
                $_SESSION['2fa_uid']      = $result['user']->id;
                $_SESSION['2fa_remember'] = $remember;
                $_SESSION['2fa_next']     = $next;

                Response::redirectTo(BASE_URL . '/login/verify');
            } catch (\Throwable $e) {
                // If email sending fails, log the error but allow login anyway (fail open)
                Logger::error('2fa.email_failed', ['uid' => $result['user']->id, 'error' => $e->getMessage()]);
                Auth::login($result['user'], $remember);
                Response::redirectTo($next ?? BASE_URL . '/documents');
            }
            return;
        }

        // 2FA disabled — log in directly
        Auth::login($result['user'], $remember);
        Response::redirectTo($next ?? BASE_URL . '/documents');
    }

    // ── 2FA: show code entry form ────────────────────────────────
    public function showVerify(): void
    {
        if (Auth::check()) { Response::redirect('/documents'); return; }

        Session::start();
        if (empty($_SESSION['2fa_pending'])) {
            Response::redirect('/login');
            return;
        }

        Response::view('auth.verify', [
            'title'   => 'Two-step verification',
            'error'   => Session::pull('2fa_error'),
            'pending' => (int) $_SESSION['2fa_pending'],
        ], layout: 'auth');
    }

    // ── 2FA: verify submitted code ───────────────────────────────
    public function verify(): void
    {
        Session::start();
        $pending = (int) ($_SESSION['2fa_pending'] ?? 0);

        if (!$pending) {
            Response::redirect('/login');
            return;
        }

        $request = new Request();
        $code    = (string) $request->input('code', '');
        $result  = (new TwoFactorService())->verify($pending, $code, Request::ip());

        if (!$result['ok']) {
            Session::flash('2fa_error', $result['error']);

            // If error is unrecoverable (expired / max attempts), clear the session
            if (str_contains($result['error'], 'expired') || str_contains($result['error'], 'sign in again')) {
                unset($_SESSION['2fa_pending'], $_SESSION['2fa_uid'], $_SESSION['2fa_remember'], $_SESSION['2fa_next']);
                Response::redirect('/login');
                return;
            }

            Response::redirect('/login/verify');
            return;
        }

        // Success — complete the login
        $remember = (bool) ($_SESSION['2fa_remember'] ?? false);
        $next     = $_SESSION['2fa_next'] ?? null;
        unset($_SESSION['2fa_pending'], $_SESSION['2fa_uid'], $_SESSION['2fa_remember'], $_SESSION['2fa_next']);

        Auth::login($result['user'], $remember);
        Response::redirectTo($next ?? BASE_URL . '/documents');
    }

    // ── 2FA: resend code ─────────────────────────────────────────
    public function resendCode(): void
    {
        Session::start();
        $pending = (int) ($_SESSION['2fa_pending'] ?? 0);

        if (!$pending) {
            Response::redirect('/login');
            return;
        }

        $result = (new TwoFactorService())->resend($pending, Request::ip());

        if ($result['ok']) {
            $_SESSION['2fa_pending'] = $result['id'];
            Session::flash('2fa_error', 'A new code has been sent to your email.');
        } else {
            Session::flash('2fa_error', $result['error']);
            unset($_SESSION['2fa_pending'], $_SESSION['2fa_uid'], $_SESSION['2fa_remember'], $_SESSION['2fa_next']);
            Response::redirect('/login');
            return;
        }

        Response::redirect('/login/verify');
    }

    public function logout(): void
    {
        $user = Auth::user();
        Logger::info('logout', [
            'ip'    => Request::ip(),
            'email' => $user?->email ?? 'unknown',
            'uid'   => $user?->id,
        ]);
        Auth::logout();
        Response::redirect('/login');
    }

    public function ping(): void
    {
        (new RememberService())->rotateIfDue(Auth::deviceId());
        Response::ok(['token' => Csrf::token()]);
    }

    public function csrf(): void
    {
        Response::ok(['token' => Csrf::token()]);
    }

    private static function safeNext(string $next): ?string
    {
        if ($next === '' || preg_match('/[\x00-\x1F\x7F\\\\]/', $next)) return null;

        $prefix = BASE_URL . '/';
        if (!str_starts_with($next, $prefix)) return null;

        $rest = substr($next, strlen($prefix));
        if ($rest !== '' && ($rest[0] === '/' || $rest[0] === '\\')) return null;

        $path = (string) parse_url($next, PHP_URL_PATH);
        $path = BASE_URL !== '' && str_starts_with($path, BASE_URL) ? substr($path, strlen(BASE_URL)) : $path;
        if (in_array(rtrim($path, '/'), ['/login', '/logout'], true)) return null;

        return $next;
    }
}
