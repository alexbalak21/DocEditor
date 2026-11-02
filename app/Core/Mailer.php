<?php

namespace App\Core;

/**
 * Mailer — minimal SMTP client (no Composer dependencies).
 *
 * Supports:
 *   - Plain SMTP (port 25/587) with optional STARTTLS
 *   - SMTP over SSL/TLS (port 465, smtps://)
 *   - AUTH LOGIN and AUTH PLAIN
 *   - HTML + plain-text fallback
 *
 * Configuration via .env:
 *   MAIL_HOST=smtp.example.com
 *   MAIL_PORT=587              (25 | 465 | 587)
 *   MAIL_ENCRYPTION=tls        (tls = STARTTLS | ssl = SSL wrapper | none)
 *   MAIL_USERNAME=user@example.com
 *   MAIL_PASSWORD=secret
 *   MAIL_FROM_ADDRESS=noreply@example.com
 *   MAIL_FROM_NAME="DocEditor"
 *
 * For local dev / testing, set MAIL_DRIVER=log to write emails to the log
 * instead of sending them.
 */
class Mailer
{
    // ── Send ────────────────────────────────────────────────────

    /**
     * @throws \RuntimeException on connection or SMTP error
     */
    public static function send(
        string $toAddress,
        string $toName,
        string $subject,
        string $html,
        string $plain = '',
    ): void {
        if (Env::get('MAIL_DRIVER', 'smtp') === 'log') {
            self::logMail($toAddress, $subject, $plain ?: strip_tags($html));
            return;
        }

        $host       = Env::get('MAIL_HOST',         'localhost');
        $port       = (int) Env::get('MAIL_PORT',   '587');
        $enc        = strtolower(Env::get('MAIL_ENCRYPTION', 'tls'));  // tls | ssl | none
        $user       = Env::get('MAIL_USERNAME',      '');
        $pass       = Env::get('MAIL_PASSWORD',      '');
        $fromAddr   = Env::get('MAIL_FROM_ADDRESS',  $user);
        $fromName   = Env::get('MAIL_FROM_NAME',     Env::get('APP_NAME', 'DocEditor'));

        // Open socket
        $transport = ($enc === 'ssl') ? "ssl://{$host}" : $host;
        $socket = @fsockopen($transport, $port, $errno, $errstr, 10);
        if (!$socket) {
            throw new \RuntimeException("SMTP connect failed [{$errno}]: {$errstr}");
        }
        stream_set_timeout($socket, 10);

        try {
            self::expect($socket, 220);
            self::cmd($socket, "EHLO " . (gethostname() ?: 'localhost'), 250);

            if ($enc === 'tls') {
                self::cmd($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('STARTTLS negotiation failed.');
                }
                self::cmd($socket, "EHLO " . (gethostname() ?: 'localhost'), 250);
            }

            if ($user !== '' && $pass !== '') {
                self::cmd($socket, 'AUTH LOGIN', 334);
                self::cmd($socket, base64_encode($user), 334);
                self::cmd($socket, base64_encode($pass), 235);
            }

            self::cmd($socket, "MAIL FROM:<{$fromAddr}>", 250);
            self::cmd($socket, "RCPT TO:<{$toAddress}>",  250);
            self::cmd($socket, 'DATA', 354);

            $message = self::buildMessage($fromAddr, $fromName, $toAddress, $toName, $subject, $html, $plain);
            fwrite($socket, $message . "\r\n.\r\n");
            self::expect($socket, 250);

            self::cmd($socket, 'QUIT', 221);
        } finally {
            fclose($socket);
        }

        Logger::info('mail.sent', ['to' => $toAddress, 'subject' => $subject]);
    }

    // ── SMTP helpers ────────────────────────────────────────────

    private static function cmd($socket, string $cmd, int $expectedCode): string
    {
        fwrite($socket, $cmd . "\r\n");
        return self::expect($socket, $expectedCode);
    }

    private static function expect($socket, int $code): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            // Multi-line responses: "250-..." continues, "250 ..." ends
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        $actual = (int) substr($response, 0, 3);
        if ($actual !== $code) {
            throw new \RuntimeException("SMTP expected {$code}, got {$actual}: " . trim($response));
        }
        return $response;
    }

    // ── Message builder ─────────────────────────────────────────

    private static function buildMessage(
        string $fromAddr, string $fromName,
        string $toAddr,   string $toName,
        string $subject,  string $html, string $plain,
    ): string {
        $boundary = '==Multipart_' . bin2hex(random_bytes(8));
        $id       = '<' . bin2hex(random_bytes(8)) . '@doceditor>';

        $headers  = implode("\r\n", [
            "From: " . self::encodeHeader($fromName) . " <{$fromAddr}>",
            "To: "   . self::encodeHeader($toName)   . " <{$toAddr}>",
            "Subject: " . self::encodeHeader($subject),
            "Message-ID: {$id}",
            "Date: " . date('r'),
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "X-Mailer: DocEditor/1.0",
        ]);

        $body = implode("\r\n", [
            "--{$boundary}",
            "Content-Type: text/plain; charset=UTF-8",
            "Content-Transfer-Encoding: quoted-printable",
            "",
            quoted_printable_encode($plain ?: strip_tags($html)),
            "",
            "--{$boundary}",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: quoted-printable",
            "",
            quoted_printable_encode($html),
            "",
            "--{$boundary}--",
        ]);

        return $headers . "\r\n\r\n" . $body;
    }

    private static function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    // ── Log driver ──────────────────────────────────────────────

    private static function logMail(string $to, string $subject, string $body): void
    {
        Logger::info('mail.log_driver', [
            'to'      => $to,
            'subject' => $subject,
            'body'    => substr($body, 0, 500),
        ]);
    }
}
