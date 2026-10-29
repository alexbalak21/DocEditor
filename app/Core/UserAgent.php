<?php

namespace App\Core;

/**
 * UserAgent — reduces a User-Agent string to a stable "browser family + operating system" pair.
 * Versions are deliberately ignored (browsers update themselves all the time); only a change of
 * browser or OS, which a stolen cookie replayed from another machine would show, counts as a mismatch.
 */
class UserAgent
{
    /** @return array{browser:string, os:string} */
    public static function parse(string $ua): array
    {
        return ['browser' => self::browser($ua), 'os' => self::os($ua)];
    }

    private static function browser(string $ua): string
    {
        return match (true) {
            (bool) preg_match('~\b(Edg|EdgA|EdgiOS|Edge)/~', $ua)      => 'Edge',
            (bool) preg_match('~\b(OPR|OPiOS|Opera)\b~', $ua)          => 'Opera',
            (bool) preg_match('~SamsungBrowser/~', $ua)                 => 'Samsung Internet',
            (bool) preg_match('~\b(Firefox|FxiOS)/~', $ua)              => 'Firefox',
            (bool) preg_match('~\b(Chrome|CriOS|Chromium)/~', $ua)      => 'Chrome',
            (bool) preg_match('~Safari/~', $ua)                         => 'Safari',
            default                                                     => 'Other',
        };
    }

    private static function os(string $ua): string
    {
        return match (true) {
            (bool) preg_match('~iPhone|iPad|iPod~', $ua)                => 'iOS',
            (bool) preg_match('~Android~', $ua)                         => 'Android',
            (bool) preg_match('~Windows~', $ua)                         => 'Windows',
            (bool) preg_match('~CrOS~', $ua)                            => 'ChromeOS',
            (bool) preg_match('~Macintosh|Mac OS X~', $ua)              => 'macOS',
            (bool) preg_match('~Linux|X11~', $ua)                       => 'Linux',
            default                                                     => 'Other',
        };
    }
}
