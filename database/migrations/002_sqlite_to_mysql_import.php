#!/usr/bin/env php
<?php
/**
 * Import DocEditor JSON exports into MySQL.
 *
 * Usage:
 *   php database/migrations/002_sqlite_to_mysql_import.php documents.json customers.json items.json
 *
 * The filename is retained for compatibility with the previous SQLite importer.
 * JSON files may be the application's normal exports: documents are wrapped in
 * {documents: [...]}, while customers and items are usually bare arrays.
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Services\CustomerService;
use App\Services\DocumentService;
use App\Services\ItemService;

$paths = array_slice($argv, 1);
if ($paths === []) {
    echo "Usage: php database/migrations/002_sqlite_to_mysql_import.php <file.json> [file.json ...]\n";
    exit(1);
}

$documents = new DocumentService();
$customers = new CustomerService();
$items = new ItemService();

foreach ($paths as $path) {
    if (!is_file($path) || !is_readable($path)) {
        fwrite(STDERR, "JSON file not found or unreadable: $path\n");
        exit(1);
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        fwrite(STDERR, "$path: invalid JSON (" . json_last_error_msg() . ")\n");
        exit(1);
    }

    $kind = detectKind($decoded, $path);
    $rows = extractRows($decoded, $kind);
    if ($rows === []) {
        echo basename($path) . ": no $kind found, skipping.\n";
        continue;
    }

    $result = match ($kind) {
        'documents' => $documents->importMany($rows),
        'customers' => $customers->importMany($rows),
        'items' => $items->importMany($rows),
    };

    echo basename($path) . " ($kind): "
        . $result['imported'] . " imported, "
        . $result['skipped'] . " skipped, "
        . $result['failed'] . " failed.\n";

    if ($result['errors'] !== []) {
        foreach ($result['errors'] as $error) {
            echo "  - $error\n";
        }
    }
}

echo "Done.\n";

/**
 * @param mixed $decoded
 */
function detectKind(mixed $decoded, string $path): string
{
    if (is_array($decoded)) {
        foreach (['documents', 'customers', 'items'] as $kind) {
            if (isset($decoded[$kind]) && is_array($decoded[$kind])) return $kind;
        }

        if (array_is_list($decoded) && $decoded !== []) {
            $first = is_array($decoded[0]) ? $decoded[0] : [];
            if (isset($first['data']) || isset($first['type']) || isset($first['items'])) return 'documents';
            if (isset($first['reference'])) return 'items';
            if (isset($first['name'])) return 'customers';
        }
    }

    $name = strtolower(pathinfo($path, PATHINFO_FILENAME));
    foreach (['documents', 'customers', 'items'] as $kind) {
        if (str_contains($name, $kind)) return $kind;
    }

    throw new InvalidArgumentException(
        "$path: unable to determine whether this is documents, customers, or items JSON"
    );
}

/**
 * @param mixed $decoded
 * @return array<int, mixed>
 */
function extractRows(mixed $decoded, string $kind): array
{
    if (is_array($decoded) && isset($decoded[$kind]) && is_array($decoded[$kind])) {
        return array_values($decoded[$kind]);
    }
    return is_array($decoded) && array_is_list($decoded) ? array_values($decoded) : [];
}
