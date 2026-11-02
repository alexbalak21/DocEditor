<?php

namespace App\Core;

/**
 * Mailer — email sender with PHP mail() and direct SMTP transports.
 *
 * Configuration via .env:
 *   MAIL_DRIVER=mail       (mail | smtp | log)
 *   MAIL_HOST=smtp.example.com
 *   MAIL_PORT=587
 *   MAIL_ENCRYPTION=tls   (tls | ssl | none)
 *   MAIL_USERNAME=user@example.com
 *   MAIL_PASSWORD=secret
 *   MAIL_FROM_ADDRESS=noreply@example.com
 *   MAIL_FROM_NAME="DocEditor"
 *
 * For local dev / testing, set MAIL_DRIVER=log to write emails to the log.
 */
class Mailer
{
    // ── Send ────────────────────────────────────────────────────

    /**
     * @throws \RuntimeException on a mail transport error
     */
    public static function send(
        string $toAddress,
        string $toName,
        string $subject,
        string $html,
        string $plain = '',
    ): void {
        $driver = strtolower((string) Env::get('MAIL_DRIVER', 'mail'));
        if ($driver === 'log') {
            self::logMail($toAddress, $subject, $plain ?: strip_tags($html));
            return;
        }

        $fromAddr   = Env::get('MAIL_FROM_ADDRESS', '');
        $fromName   = Env::get('MAIL_FROM_NAME',     Env::get('APP_NAME', 'DocEditor'));
        if ($fromAddr === '') {
            throw new \RuntimeException('MAIL_FROM_ADDRESS must be configured.');
        }

        if ($driver === 'smtp') {
            self::sendSmtp($toAddress, $toName, $subject, $html, $plain, $fromAddr, $fromName);
            return;
        }

        if ($driver !== 'mail') {
            throw new \RuntimeException("Unsupported mail driver: {$driver}");
        }

        $boundary = '==Multipart_' . bin2hex(random_bytes(8));
        $headers = implode("\r\n", [
            "From: " . self::encodeHeader($fromName) . " <{$fromAddr}>",
            "To: "   . self::encodeHeader($toName)   . " <{$toAddress}>",
            "Subject: " . self::encodeHeader($subject),
            "Date: " . date('r'),
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "X-Mailer: DocEditor/1.0",
        ]);
        $message = self::buildBody($boundary, $html, $plain);
        if (!mail($toAddress, self::encodeHeader($subject), $message, $headers)) {
            throw new \RuntimeException('PHP mail() failed to queue the message.');
        }

        Logger::info('mail.sent', ['to' => $toAddress, 'subject' => $subject]);
    }

    private static function sendSmtp(
        string $toAddress,
        string $toName,
        string $subject,
        string $html,
        string $plain,
        string $fromAddr,
        string $fromName,
    ): void {
        $host = (string) Env::get('MAIL_HOST', 'localhost');
        $port = (int) Env::get('MAIL_PORT', 587);
        $enc  = strtolower((string) Env::get('MAIL_ENCRYPTION', 'tls'));
        $user = (string) Env::get('MAIL_USERNAME', '');
        $pass = (string) Env::get('MAIL_PASSWORD', '');

        $transport = $enc === 'ssl' ? "ssl://{$host}" : $host;
        $socket = @fsockopen($transport, $port, $errno, $errstr, 10);
        if (!$socket) {
            throw new \RuntimeException("SMTP connect failed [{$errno}]: {$errstr}");
        }
        stream_set_timeout($socket, 10);

        try {
            self::expect($socket, 220);
            self::cmd($socket, 'EHLO ' . (gethostname() ?: 'localhost'), 250);
            if ($enc === 'tls') {
                self::cmd($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('STARTTLS negotiation failed.');
                }
                self::cmd($socket, 'EHLO ' . (gethostname() ?: 'localhost'), 250);
            }
            if ($user !== '' && $pass !== '') {
                self::cmd($socket, 'AUTH LOGIN', 334);
                self::cmd($socket, base64_encode($user), 334);
                self::cmd($socket, base64_encode($pass), 235);
            }
            self::cmd($socket, "MAIL FROM:<{$fromAddr}>", 250);
            self::cmd($socket, "RCPT TO:<{$toAddress}>", 250);
            self::cmd($socket, 'DATA', 354);
            fwrite($socket, self::buildMessage(
                $fromAddr, $fromName, $toAddress, $toName, $subject, $html, $plain
            ) . "\r\n.\r\n");
            self::expect($socket, 250);
            self::cmd($socket, 'QUIT', 221);
        } finally {
            fclose($socket);
        }

        Logger::info('mail.sent', ['to' => $toAddress, 'subject' => $subject]);
    }

    private static function cmd($socket, string $command, int $expectedCode): string
    {
        fwrite($socket, $command . "\r\n");
        return self::expect($socket, $expectedCode);
    }

    private static function expect($socket, int $expectedCode): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        $actualCode = (int) substr($response, 0, 3);
        if ($actualCode !== $expectedCode) {
            throw new \RuntimeException(
                "SMTP expected {$expectedCode}, got {$actualCode}: " . trim($response)
            );
        }
        return $response;
    }

    // ── Message builder ─────────────────────────────────────────

    private static function buildBody(
        string $boundary, string $html, string $plain,
    ): string {
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

        return $body;
    }

    private static function buildMessage(
        string $fromAddr,
        string $fromName,
        string $toAddr,
        string $toName,
        string $subject,
        string $html,
        string $plain,
    ): string {
        $boundary = '==Multipart_' . bin2hex(random_bytes(8));
        $headers = implode("\r\n", [
            "From: " . self::encodeHeader($fromName) . " <{$fromAddr}>",
            "To: " . self::encodeHeader($toName) . " <{$toAddr}>",
            "Subject: " . self::encodeHeader($subject),
            "Message-ID: <" . bin2hex(random_bytes(8)) . "@doceditor>",
            "Date: " . date('r'),
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "X-Mailer: DocEditor/1.0",
        ]);
        return $headers . "\r\n\r\n" . self::buildBody($boundary, $html, $plain);
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
