<?php

namespace App\Core;

/**
 * Minimal .env loader — used as fallback when composer/vendor is absent.
 * If vlucas/phpdotenv is installed via composer, bootstrap.php uses that instead.
 */
class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) return;
        if (!is_file($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!str_contains($line, '=')) continue;

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Strip surrounding quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        // Note: a value of "0" is a real value (the old `?:` version treated it as "unset")
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
        $v = getenv($key);
        return ($v !== false && $v !== '') ? $v : $default;
    }
}
