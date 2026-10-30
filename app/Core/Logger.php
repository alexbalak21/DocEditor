<?php

namespace App\Core;

use App\Core\Database;
use PDO;

/**
 * Logger — writes structured log entries to:
 *   1. storage/logs/app-YYYY-MM-DD.log  (always, file per day)
 *   2. app_logs DB table                (when DB is available, for the web viewer)
 *
 * Usage:
 *   Logger::info('login.success', ['email' => 'x@y.com', 'ip' => '1.2.3.4']);
 *   Logger::warning('login.throttled', ['ip' => '1.2.3.4', 'attempts' => 12]);
 *   Logger::error('exception', ['message' => $e->getMessage()]);
 */
class Logger
{
    public const DEBUG   = 'DEBUG';
    public const INFO    = 'INFO';
    public const WARNING = 'WARNING';
    public const ERROR   = 'ERROR';

    private static string $logDir = ROOT . '/storage/logs';

    // ── Public API ─────────────────────────────────────────────

    public static function debug(string $event, array $context = []): void
    {
        self::write(self::DEBUG, $event, $context);
    }

    public static function info(string $event, array $context = []): void
    {
        self::write(self::INFO, $event, $context);
    }

    public static function warning(string $event, array $context = []): void
    {
        self::write(self::WARNING, $event, $context);
    }

    public static function error(string $event, array $context = []): void
    {
        self::write(self::ERROR, $event, $context);
    }

    // ── Core write ─────────────────────────────────────────────

    private static function write(string $level, string $event, array $context): void
    {
        $now     = new \DateTimeImmutable('now');
        $ts      = $now->format('Y-m-d H:i:s');
        $dateKey = $now->format('Y-m-d');
        $ip      = Request::ip();
        $user    = self::currentUserId();

        // Sanitise context values
        $ctx = self::sanitise($context);

        // ── 1. File log ──────────────────────────────────────
        $logFile = self::$logDir . '/app-' . $dateKey . '.log';
        $ctxStr  = empty($ctx) ? '' : ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $line    = "[{$ts}] [{$level}] [{$ip}] [uid:{$user}] {$event}{$ctxStr}" . PHP_EOL;

        // Ensure directory exists (silently, never throw from a logger)
        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0775, true);
        }
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

        // ── 2. DB log ────────────────────────────────────────
        try {
            $db = Database::getInstance();
            $db->prepare(
                'INSERT INTO app_logs (level, event, ip, user_id, context, logged_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $level,
                substr($event, 0, 100),
                substr($ip, 0, 45),
                $user ?: null,
                empty($ctx) ? null : json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $ts,
            ]);
        } catch (\Throwable) {
            // DB unavailable — file log is enough, never throw from Logger
        }
    }

    // ── Helpers ────────────────────────────────────────────────

    private static function currentUserId(): ?int
    {
        try {
            // Avoid triggering Auth::user() during early bootstrap
            $id = $_SESSION['uid'] ?? null;
            return $id !== null ? (int) $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Strip anything that should not be logged (passwords, tokens). */
    private static function sanitise(array $ctx): array
    {
        $blocked = ['password', 'token', 'secret', 'hash', 'cookie'];
        $out = [];
        foreach ($ctx as $k => $v) {
            $key = strtolower((string) $k);
            foreach ($blocked as $b) {
                if (str_contains($key, $b)) {
                    $out[$k] = '***';
                    continue 2;
                }
            }
            $out[$k] = is_scalar($v) ? $v : json_encode($v);
        }
        return $out;
    }

    // ── Log file utilities ─────────────────────────────────────

    /**
     * Return the list of daily log files, newest first.
     * @return array<string>  absolute paths
     */
    public static function logFiles(): array
    {
        if (!is_dir(self::$logDir)) return [];
        $files = glob(self::$logDir . '/app-*.log') ?: [];
        rsort($files);
        return $files;
    }

    /**
     * Tail the last $lines lines from a log file.
     * @return array<string>
     */
    public static function tail(string $file, int $lines = 200): array
    {
        if (!is_file($file)) return [];
        // Read the whole file; for typical log sizes this is fine.
        $all = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        return array_slice($all, -$lines);
    }
}
