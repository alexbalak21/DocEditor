<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;
use App\Services\RememberService;

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
        $next     = self::safeNext((string) $request->input('next', ''));

        $result = (new AuthService())->attempt($email, $password, Request::ip());

        if (!$result['ok']) {
            Session::flash('login_error', $result['error']);
            Session::flash('login_email', trim($email));
            Response::redirectTo(BASE_URL . '/login' . ($next ? '?next=' . rawurlencode($next) : ''));
        }

        Auth::login($result['user'], (string) $request->input('remember', '') !== '');
        Response::redirectTo($next ?? BASE_URL . '/documents');
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect('/login');
    }

    /**
     * Keep-alive, called every few minutes by the page (and when the tab wakes up).
     * Going through the router's guard already restored a lost session from the remember-me cookie;
     * here the cookie's validator is refreshed when due, and the page gets a current CSRF token.
     */
    public function ping(): void
    {
        (new RememberService())->rotateIfDue(Auth::deviceId());
        Response::ok(['token' => Csrf::token()]);
    }

    /** Fresh CSRF token for the current session (used by the JS fetch wrapper after a 419). */
    public function csrf(): void
    {
        Response::ok(['token' => Csrf::token()]);
    }

    /**
     * Only allow redirecting back to a page of THIS app (prevents open redirects).
     */
    private static function safeNext(string $next): ?string
    {
        if ($next === '' || preg_match('/[\x00-\x1F\x7F\\\\]/', $next)) return null;

        $prefix = BASE_URL . '/';
        if (!str_starts_with($next, $prefix)) return null;

        $rest = substr($next, strlen($prefix));
        if ($rest !== '' && ($rest[0] === '/' || $rest[0] === '\\')) return null;   // "//evil.com"

        $path = (string) parse_url($next, PHP_URL_PATH);
        $path = BASE_URL !== '' && str_starts_with($path, BASE_URL) ? substr($path, strlen(BASE_URL)) : $path;
        if (in_array(rtrim($path, '/'), ['/login', '/logout'], true)) return null;

        return $next;
    }
}
