<?php

namespace App\Core;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\RememberService;

/**
 * Auth — who is logged in for this request.
 *
 * 1. The PHP session stores the user id, an "auth version" and (for remembered devices) the device id.
 *    The user row is loaded on every request, so a deleted user or a changed password ends other sessions,
 *    and a revoked device ends its own session within REMEMBER_DEVICE_CHECK_SECONDS.
 * 2. If there is no valid session (browser restarted, idle timeout...) the remember-me cookie signs the
 *    user back in silently — see App\Services\RememberService.
 */
class Auth
{
    private static ?User $user = null;
    private static bool $resolved = false;

    public static function user(): ?User
    {
        if (self::$resolved) return self::$user;
        self::$resolved = true;

        Session::start();

        if ($user = self::fromSession()) return self::$user = $user;

        $restored = (new RememberService())->authenticate();
        if ($restored) {
            self::establish($restored['user']);
            $_SESSION['did']   = $restored['device_id'];
            $_SESSION['_dchk'] = time();
            return self::$user = $restored['user'];
        }
        return null;
    }

    private static function fromSession(): ?User
    {
        $id = $_SESSION['uid'] ?? null;
        if (!$id) return null;

        $user = (new UserRepository())->findById((int) $id);
        if (!$user || $user->authVersion !== (int) ($_SESSION['auth_v'] ?? 0)) {
            unset($_SESSION['uid'], $_SESSION['auth_v'], $_SESSION['did']);
            return null;
        }

        // Session of a remembered device: make sure that device was not revoked / did not expire.
        if (!empty($_SESSION['did'])) {
            $due = (time() - (int) ($_SESSION['_dchk'] ?? 0)) >= RememberService::deviceCheckSeconds();
            if ($due) {
                if (!(new RememberService())->isDeviceActive((int) $_SESSION['did'], (int) $user->id)) {
                    unset($_SESSION['uid'], $_SESSION['auth_v'], $_SESSION['did']);
                    return null;
                }
                $_SESSION['_dchk'] = time();
            }
        }
        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Id of the remembered device behind the current session (0 = session-only login). */
    public static function deviceId(): int
    {
        self::user();
        return (int) ($_SESSION['did'] ?? 0);
    }

    private static function establish(User $user): void
    {
        session_regenerate_id(true);      // prevents session fixation
        Csrf::rotate();
        $_SESSION['uid']    = $user->id;
        $_SESSION['auth_v'] = $user->authVersion;
        self::$user     = $user;
        self::$resolved = true;
    }

    public static function login(User $user, bool $remember = false): void
    {
        Session::start();
        self::establish($user);
        unset($_SESSION['did']);

        $svc = new RememberService();
        if ($remember) {
            $_SESSION['did']   = $svc->issue($user);
            $_SESSION['_dchk'] = time();
        } else {
            $svc->forget();               // signing in without "keep me signed in" drops any older cookie of this browser
        }
    }

    /** Keep the current session valid after the user's auth_version changed (password change). */
    public static function refresh(User $user): void
    {
        Session::start();
        session_regenerate_id(true);
        $_SESSION['uid']    = $user->id;
        $_SESSION['auth_v'] = $user->authVersion;
        self::$user     = $user;
        self::$resolved = true;
    }

    public static function logout(): void
    {
        Session::start();
        (new RememberService())->forget();
        Session::destroy();
        self::$user     = null;
        self::$resolved = true;
    }
}
