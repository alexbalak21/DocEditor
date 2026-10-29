<?php

namespace App\Services;

use App\Core\Env;
use App\Core\Request;
use App\Core\UserAgent;
use App\Models\User;
use App\Repositories\AuthDeviceRepository;
use App\Repositories\UserRepository;

/**
 * Remembered devices — "stay signed in" without keeping a password or a never-ending session.
 *
 * The cookie is  selector.validator  (random, 12 + 32 bytes). The database stores only
 * sha256(validator), so a database leak cannot sign anybody in.
 *
 *  - ROTATION    the validator is replaced every time the cookie is used to restore a lost session
 *                and at least every REMEMBER_ROTATE_MINUTES while the app is open. The previous value
 *                stays valid for a short grace period so two tabs/requests racing each other don't fail.
 *  - THEFT       a rotated-out validator presented AFTER the grace period can only come from a copied
 *                cookie: that device is revoked at once (the thief and the owner both have to sign in again).
 *  - FINGERPRINT the browser family + OS recorded at sign-in must match on every use (versions ignored).
 *                The IP is stored and shown to you but only enforced with REMEMBER_BIND_IP=true,
 *                because phones and laptops legitimately change network all the time.
 *  - SLIDING     every use pushes the expiry REMEMBER_DAYS ahead, so an account that is used never has to
 *                sign in again; REMEMBER_MAX_DAYS (0 = off) adds an absolute lifetime if you want one.
 *  - REVOCATION  devices can be revoked from the profile page; a password change revokes all others.
 */
class RememberService
{
    public const COOKIE = 'doceditor_remember';
    private const MAX_DEVICES = 20;

    public function __construct(
        private AuthDeviceRepository $devices = new AuthDeviceRepository(),
        private UserRepository       $users   = new UserRepository(),
    ) {}

    // ── configuration (.env) ──────────────────────────────────
    public static function days(): int          { return max(1, (int) Env::get('REMEMBER_DAYS', 180)); }
    public static function maxDays(): int       { return max(0, (int) Env::get('REMEMBER_MAX_DAYS', 0)); }
    public static function rotateSeconds(): int { return max(0, (int) Env::get('REMEMBER_ROTATE_MINUTES', 60)) * 60; }
    public static function graceSeconds(): int  { return max(0, (int) Env::get('REMEMBER_GRACE_SECONDS', 60)); }
    public static function bindIp(): bool       { return Env::get('REMEMBER_BIND_IP', 'false') === 'true'; }
    /** How often a live session re-checks that its device was not revoked (seconds). */
    public static function deviceCheckSeconds(): int { return max(0, (int) Env::get('REMEMBER_DEVICE_CHECK_SECONDS', 30)); }

    // ── sign-in: create the device + cookie ───────────────────
    /** @return int id of the new device (store it in the session as 'did') */
    public function issue(User $user): int
    {
        $this->forget();                                   // drop a stale cookie/row from an earlier sign-in
        $this->devices->deleteExpired();
        $this->devices->trimToLimit((int) $user->id, self::MAX_DEVICES - 1);

        $ua       = self::userAgent();
        $fp       = UserAgent::parse($ua);
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));

        $id = $this->devices->create([
            'user_id'      => (int) $user->id,
            'selector'     => $selector,
            'token_hash'   => hash('sha256', $validator),
            'auth_version' => $user->authVersion,
            'browser'      => $fp['browser'],
            'os'           => $fp['os'],
            'user_agent'   => $ua,
            'ip'           => Request::ip(),
        ], self::days() * 86400, self::maxDays() * 86400);

        self::setCookie($selector . '.' . $validator);
        return $id;
    }

    // ── silent sign-in from the cookie ────────────────────────
    /** @return array{user:User, device_id:int}|null */
    public function authenticate(): ?array
    {
        $parsed = self::readCookie();
        if ($parsed === null) {
            if (isset($_COOKIE[self::COOKIE])) self::clearCookie();   // garbage cookie
            return null;
        }
        [$selector, $validator] = $parsed;

        $row = $this->devices->findBySelector($selector);
        if (!$row) { self::clearCookie(); return null; }
        $id = (int) $row['id'];

        if (!(int) $row['alive'] || !(int) $row['hard_ok']) return $this->reject($id, 'expired');

        $hash      = hash('sha256', $validator);
        $isCurrent = hash_equals((string) $row['token_hash'], $hash);
        $isPrev    = !$isCurrent && (int) $row['prev_ok'] && hash_equals((string) $row['prev_hash'], $hash);
        if (!$isCurrent && !$isPrev) {
            // A validator that was already replaced, used after the grace period: the cookie was copied.
            return $this->reject($id, 'REUSED rotated token (possible cookie theft)');
        }

        $user = $this->users->findById((int) $row['user_id']);
        if (!$user || $user->authVersion !== (int) $row['auth_version']) return $this->reject($id, 'password changed or user gone');

        $fp = UserAgent::parse(self::userAgent());
        if ($fp['browser'] !== $row['browser'] || $fp['os'] !== $row['os']) {
            return $this->reject($id, "fingerprint mismatch ({$row['browser']}/{$row['os']} -> {$fp['browser']}/{$fp['os']})");
        }

        $ip = Request::ip();
        if (self::bindIp() && !self::sameNetwork($ip, (string) $row['ip_last'])) {
            return $this->reject($id, "network changed ({$row['ip_last']} -> $ip)");
        }

        if ($isCurrent) $this->rotateRow($id, $selector, $hash, $ip);
        else            $this->devices->touch($id, $ip);

        return ['user' => $user, 'device_id' => $id];
    }

    /** Called by the keep-alive ping: swap in a fresh validator if the current one is older than the rotation interval. */
    public function rotateIfDue(int $sessionDeviceId): void
    {
        $parsed = self::readCookie();
        if ($parsed === null || $sessionDeviceId <= 0) return;
        [$selector, $validator] = $parsed;

        $row = $this->devices->findBySelector($selector);
        if (!$row || (int) $row['id'] !== $sessionDeviceId) return;

        $hash = hash('sha256', $validator);
        if (hash_equals((string) $row['token_hash'], $hash) && (int) $row['age'] >= self::rotateSeconds()) {
            $this->rotateRow($sessionDeviceId, $selector, $hash, Request::ip());
        } else {
            $this->devices->touch($sessionDeviceId, Request::ip());
        }
    }

    // ── sign-out / revocation ─────────────────────────────────
    /** Forget THIS browser: delete its device row and cookie. */
    public function forget(): void
    {
        $parsed = self::readCookie();
        if ($parsed !== null) $this->devices->deleteBySelector($parsed[0]);
        if (!empty($_SESSION['did'])) $this->devices->delete((int) $_SESSION['did']);
        unset($_SESSION['did']);
        if (isset($_COOKIE[self::COOKIE])) self::clearCookie();
    }

    public function isDeviceActive(int $deviceId, int $userId): bool
    {
        return $this->devices->isActive($deviceId, $userId);
    }

    public function revoke(int $userId, int $deviceId): void
    {
        $this->devices->deleteForUser($deviceId, $userId);
    }

    /**
     * Sign out everything except the current browser: bumps the user's auth_version (ends every other
     * live session, remembered or not) and keeps only the current device, re-stamped with the new version.
     */
    public function signOutOthers(User $user, int $currentDeviceId): User
    {
        $version = $this->users->bumpAuthVersion((int) $user->id);
        $this->keepOnly((int) $user->id, $currentDeviceId, $version);
        return $this->users->findById((int) $user->id);
    }

    /** After a password change (auth_version already bumped): every device except the current one is revoked. */
    public function keepOnly(int $userId, int $currentDeviceId, int $newVersion): void
    {
        $this->devices->deleteOthers($userId, $currentDeviceId);
        if ($currentDeviceId > 0) $this->devices->setAuthVersion($currentDeviceId, $newVersion);
    }

    /** @return list<array<string,mixed>> */
    public function devicesFor(User $user, int $currentDeviceId): array
    {
        return array_map(fn(array $r) => [
            'id'        => (int) $r['id'],
            'label'     => $r['browser'] . ' on ' . $r['os'],
            'ip'        => $r['ip_last'],
            'ip_first'  => $r['ip_created'],
            'signed_in' => $r['created_at'],
            'last_used' => $r['last_used_at'],
            'current'   => (int) $r['id'] === $currentDeviceId,
        ], $this->devices->listForUser((int) $user->id));
    }

    // ── internals ─────────────────────────────────────────────
    private function rotateRow(int $id, string $selector, string $oldHash, string $ip): void
    {
        $validator = bin2hex(random_bytes(32));
        $won = $this->devices->rotate($id, $oldHash, hash('sha256', $validator), $ip, self::days() * 86400, self::graceSeconds());
        if ($won) self::setCookie($selector . '.' . $validator);   // lost the race: another request already rotated; keep its cookie
    }

    private function reject(int $deviceId, string $why): null
    {
        error_log("[auth] remembered device #$deviceId rejected: $why (ip " . Request::ip() . ')');
        $this->devices->delete($deviceId);
        self::clearCookie();
        return null;
    }

    private static function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    /** @return array{0:string,1:string}|null */
    private static function readCookie(): ?array
    {
        $raw = $_COOKIE[self::COOKIE] ?? null;
        if (!is_string($raw) || !preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/', $raw, $m)) return null;
        return [$m[1], $m[2]];
    }

    /** Same /24 (IPv4) or /48 (IPv6) network? */
    private static function sameNetwork(string $a, string $b): bool
    {
        $pa = inet_pton($a); $pb = inet_pton($b);
        if ($pa === false || $pb === false || strlen($pa) !== strlen($pb)) return false;
        $bytes = strlen($pa) === 4 ? 3 : 6;
        return substr($pa, 0, $bytes) === substr($pb, 0, $bytes);
    }

    private static function cookieOptions(int $expires): array
    {
        return [
            'expires'  => $expires,
            'path'     => BASE_URL !== '' ? BASE_URL : '/',
            'secure'   => Request::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    private static function setCookie(string $value): void
    {
        $_COOKIE[self::COOKIE] = $value;
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie(self::COOKIE, $value, self::cookieOptions(time() + self::days() * 86400));
        }
    }

    private static function clearCookie(): void
    {
        unset($_COOKIE[self::COOKIE]);
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie(self::COOKIE, '', self::cookieOptions(time() - 42000));
        }
    }
}
