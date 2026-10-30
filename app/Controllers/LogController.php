<?php

namespace App\Controllers;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;

class LogController
{
    private const LINES_PER_PAGE = 300;

    public function index(): void
    {
        $request  = new Request();
        $files    = Logger::logFiles();
        $selected = (string) $request->query('file', '');
        $level    = strtoupper((string) $request->query('level', ''));
        $search   = (string) $request->query('q', '');

        // Determine which file to read
        $activeFile = null;
        if ($selected !== '') {
            // Validate: must be one of our known log files (path traversal guard)
            foreach ($files as $f) {
                if (basename($f) === basename($selected)) {
                    $activeFile = $f;
                    break;
                }
            }
        }
        if ($activeFile === null && !empty($files)) {
            $activeFile = $files[0]; // default: today's log
        }

        $lines = $activeFile ? Logger::tail($activeFile, 2000) : [];

        // Filter by level
        if ($level !== '') {
            $lines = array_filter($lines, fn($l) => str_contains($l, "[{$level}]"));
        }
        // Filter by search string
        if ($search !== '') {
            $lines = array_filter($lines, fn($l) => stripos($l, $search) !== false);
        }

        $lines = array_values(array_reverse($lines)); // newest first
        $total = count($lines);
        $lines = array_slice($lines, 0, self::LINES_PER_PAGE);

        Response::view('logs.index', [
            'title'      => 'Server Logs',
            'files'      => $files,
            'activeFile' => $activeFile,
            'lines'      => $lines,
            'total'      => $total,
            'level'      => $level,
            'search'     => $search,
            'perPage'    => self::LINES_PER_PAGE,
        ]);
    }

    /** Stream the raw log file as a download. */
    public function download(): void
    {
        $request = new Request();
        $files   = Logger::logFiles();
        $wanted  = basename((string) $request->query('file', ''));

        $found = null;
        foreach ($files as $f) {
            if (basename($f) === $wanted) { $found = $f; break; }
        }

        if ($found === null || !is_file($found)) {
            http_response_code(404);
            echo 'Log file not found.';
            exit;
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($found) . '"');
        header('Content-Length: ' . filesize($found));
        readfile($found);
        exit;
    }
}
