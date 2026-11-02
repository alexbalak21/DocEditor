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
        $appName  = Env::get('APP_NAME', 'DocEditor');
        $lifetime = self::lifetimeMinutes();

        $formatted = substr($code, 0, 3) . ' ' . substr($code, 3); // "123 456"

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:'Segoe UI',system-ui,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 0">
    <tr><td align="center">
      <table width="440" cellpadding="0" cellspacing="0" style="background:white;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.1);overflow:hidden;max-width:440px">
        <tr><td style="background:#3771c8;padding:22px 32px">
          <p style="margin:0;color:white;font-size:18px;font-weight:700">{$appName}</p>
        </td></tr>
        <tr><td style="padding:32px">
          <p style="margin:0 0 8px;font-size:15px;font-weight:600;color:#1a1d23">Sign-in verification</p>
          <p style="margin:0 0 24px;font-size:13.5px;color:#5a6070;line-height:1.6">
            Hi {$user->name}, enter this code to complete your sign-in to {$appName}.
          </p>
          <div style="background:#f0f2f5;border-radius:8px;padding:18px;text-align:center;margin-bottom:24px">
            <p style="margin:0;font-size:36px;font-weight:800;letter-spacing:8px;color:#3771c8;font-family:monospace">{$formatted}</p>
          </div>
          <p style="margin:0;font-size:12.5px;color:#9098a8;line-height:1.6">
            This code expires in <strong>{$lifetime} minutes</strong>.
            If you did not attempt to sign in, you can safely ignore this email.
          </p>
        </td></tr>
        <tr><td style="padding:16px 32px;border-top:1px solid #e8eaf0">
          <p style="margin:0;font-size:11.5px;color:#c0c4cc">Sent by {$appName} · Do not share this code.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

        $plain = "Your {$appName} sign-in code is: {$code}\n\nExpires in {$lifetime} minutes. Do not share this code.";

        Mailer::send(
            toAddress: $user->email,
            toName:    $user->name,
            subject:   "[{$appName}] Your sign-in code: {$formatted}",
            html:      $html,
            plain:     $plain,
        );
    }
}
