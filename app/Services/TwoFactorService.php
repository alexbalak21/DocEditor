<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Mailer;
use App\Models\User;
use PDO;

/**
 * TwoFactorService — email-based OTP second factor.
 *
 * Flow:
 *  1. Credentials OK → issue($user, $ip) → stores code hash in DB, emails code to user.
 *  2. User submits code → verify($pendingId, $code, $ip) → returns User or error.
 *  3. Max 5 wrong attempts per code; code expires after OTP_LIFETIME_MINUTES (default 10).
 *
 * The pending_2fa_id stored in the session is just the row id (not a secret).
 * The actual secret is the hashed code in the DB — the session id is the gate.
 */
class TwoFactorService
{
    private const MAX_ATTEMPTS = 5;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function lifetimeMinutes(): int
    {
        return max(1, (int) Env::get('OTP_LIFETIME_MINUTES', '10'));
    }

    public static function isEnabled(): bool
    {
        return strtolower(Env::get('TWO_FACTOR_ENABLED', 'true')) !== 'false';
    }

    // ── Issue ────────────────────────────────────────────────────

    /**
     * Generate a 6-digit code, store its hash, and email it.
     * Returns the row id (stored in session as pending_2fa_id).
     *
     * @throws \RuntimeException if sending the email fails
     */
    public function issue(User $user, string $ip): int
    {
        // Clean up any expired codes for this user
        $this->db->prepare(
            "DELETE FROM two_factor_codes WHERE user_id = ? AND expires_at < NOW()"
        )->execute([$user->id]);

        $code    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash    = password_hash($code, PASSWORD_DEFAULT);
        $lifetime = self::lifetimeMinutes();

        $stmt = $this->db->prepare(
            'INSERT INTO two_factor_codes (user_id, code_hash, ip, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . $lifetime . ' MINUTE))'
        );
        $stmt->execute([$user->id, $hash, $ip]);
        $id = (int) $this->db->lastInsertId();

        $this->sendCode($user, $code);

        Logger::info('2fa.code_issued', ['uid' => $user->id, 'ip' => $ip]);

        return $id;
    }

    // ── Verify ───────────────────────────────────────────────────

    /**
     * Validate the submitted code.
     *
     * @return array{ok:bool, user?:User, error?:string}
     */
    public function verify(int $pendingId, string $code, string $ip): array
    {
        $code = trim($code);

        $stmt = $this->db->prepare(
            'SELECT * FROM two_factor_codes
             WHERE id = ? AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$pendingId]);
        $row = $stmt->fetch();

        if (!$row) {
            Logger::warning('2fa.invalid_or_expired', ['pending_id' => $pendingId, 'ip' => $ip]);
            return ['ok' => false, 'error' => 'This code has expired. Please sign in again to receive a new one.'];
        }

        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            Logger::warning('2fa.max_attempts', ['uid' => $row['user_id'], 'ip' => $ip]);
            return ['ok' => false, 'error' => 'Too many incorrect attempts. Please sign in again.'];
        }

        if (!password_verify($code, $row['code_hash'])) {
            $this->db->prepare(
                'UPDATE two_factor_codes SET attempts = attempts + 1 WHERE id = ?'
            )->execute([$pendingId]);

            $remaining = self::MAX_ATTEMPTS - (int) $row['attempts'] - 1;
            Logger::warning('2fa.wrong_code', ['uid' => $row['user_id'], 'ip' => $ip, 'remaining' => $remaining]);

            return ['ok' => false, 'error' => "Incorrect code. {$remaining} attempt(s) remaining."];
        }

        // Mark code as used
        $this->db->prepare(
            'UPDATE two_factor_codes SET used_at = NOW() WHERE id = ?'
        )->execute([$pendingId]);

        // Load the user
        $user = (new \App\Repositories\UserRepository())->findById((int) $row['user_id']);
        if (!$user) {
            return ['ok' => false, 'error' => 'User not found.'];
        }

        Logger::info('2fa.verified', ['uid' => $user->id, 'ip' => $ip]);

        return ['ok' => true, 'user' => $user];
    }

    // ── Resend ───────────────────────────────────────────────────

    /**
     * Issue a fresh code for the same pending session (resend link).
     * Invalidates the old code for this user.
     *
     * @return array{ok:bool, id?:int, error?:string}
     */
    public function resend(int $pendingId, string $ip): array
    {
        $stmt = $this->db->prepare(
            'SELECT user_id FROM two_factor_codes WHERE id = ? AND used_at IS NULL LIMIT 1'
        );
        $stmt->execute([$pendingId]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['ok' => false, 'error' => 'Session expired. Please sign in again.'];
        }

        $user = (new \App\Repositories\UserRepository())->findById((int) $row['user_id']);
        if (!$user) {
            return ['ok' => false, 'error' => 'User not found.'];
        }

        // Invalidate all pending codes for this user
        $this->db->prepare(
            'UPDATE two_factor_codes SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL'
        )->execute([$user->id]);

        try {
            $newId = $this->issue($user, $ip);
            return ['ok' => true, 'id' => $newId];
        } catch (\Throwable $e) {
            Logger::error('2fa.resend_failed', ['uid' => $user->id, 'error' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Failed to send new code: ' . $e->getMessage()];
        }
    }

    // ── Email ─────────────────────────────────────────────────────

    private function sendCode(User $user, string $code): void
    {
        $appName = Env::get('APP_NAME', 'DocEditor');
        $html = '<p>Your sign-in code:</p><p>' . e($code) . '</p>';
        $plain = "Your sign-in code:\n{$code}";

        Mailer::send(
            toAddress: $user->email,
            toName:    $user->name,
            subject:   "{$appName} code :",
            html:      $html,
            plain:     $plain,
        );
    }
}
