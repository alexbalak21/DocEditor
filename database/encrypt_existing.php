<?php
/**
 * database/encrypt_existing.php
 *
 * One-time script to encrypt all existing plaintext rows in documents and customers.
 * Run this ONCE after:
 *   1. Setting APP_KEY in .env
 *   2. Running migration 006_encryption.sql
 *
 * Usage (from project root):
 *   php database/encrypt_existing.php
 *
 * Safe to run multiple times — already-encrypted rows are detected and skipped.
 */

define('ROOT', dirname(__DIR__));
require_once ROOT . '/app/Core/Env.php';
App\Core\Env::load(ROOT . '/.env');

require_once ROOT . '/app/Core/Encryption.php';
require_once ROOT . '/app/Core/Database.php';

use App\Core\Encryption;
use App\Core\Database;

// ── Bootstrap minimal autoloader ──────────────────────────────
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = ROOT . '/' . str_replace(['App\\', '\\'], ['app/', '/'], $class) . '.php';
    if (is_file($file)) require_once $file;
});

echo "\n=== DocEditor — Database Encryption ===\n\n";

if (!Encryption::available()) {
    echo "ERROR: PHP sodium extension is not available.\n";
    echo "Install it with: sudo apt install php-sodium  (then restart PHP)\n";
    exit(1);
}

$db = Database::getInstance();

// ── Documents ─────────────────────────────────────────────────
echo "Processing documents...\n";

$rows = $db->query('SELECT id, customer, data FROM documents')->fetchAll(PDO::FETCH_ASSOC);
$total = count($rows);
$encrypted = 0;
$skipped   = 0;

foreach ($rows as $row) {
    $customerNeedsEnc = !looksEncrypted($row['customer']);
    $dataNeedsEnc     = !looksEncrypted($row['data']);

    if (!$customerNeedsEnc && !$dataNeedsEnc) {
        $skipped++;
        continue;
    }

    $newCustomer = $customerNeedsEnc ? Encryption::encrypt($row['customer']) : $row['customer'];
    $newData     = $dataNeedsEnc     ? Encryption::encrypt($row['data'])     : $row['data'];

    $stmt = $db->prepare('UPDATE documents SET customer=?, data=? WHERE id=?');
    $stmt->execute([$newCustomer, $newData, $row['id']]);
    $encrypted++;
}

echo "  Total rows : $total\n";
echo "  Encrypted  : $encrypted\n";
echo "  Skipped    : $skipped (already encrypted)\n\n";

// ── Customers ─────────────────────────────────────────────────
echo "Processing customers...\n";

$rows = $db->query('SELECT id, name, address, city, contact, phone, vat FROM customers')->fetchAll(PDO::FETCH_ASSOC);
$total = count($rows);
$encrypted = 0;
$skipped   = 0;

$fields = ['name', 'address', 'city', 'contact', 'phone', 'vat'];

foreach ($rows as $row) {
    $needsEnc = false;
    foreach ($fields as $f) {
        if ($row[$f] !== '' && !looksEncrypted($row[$f])) {
            $needsEnc = true;
            break;
        }
    }

    if (!$needsEnc) {
        $skipped++;
        continue;
    }

    $updates = [];
    foreach ($fields as $f) {
        $updates[$f] = ($row[$f] !== '' && !looksEncrypted($row[$f]))
            ? Encryption::encrypt($row[$f])
            : $row[$f];
    }

    $stmt = $db->prepare('
        UPDATE customers
        SET name=?, address=?, city=?, contact=?, phone=?, vat=?
        WHERE id=?
    ');
    $stmt->execute([
        $updates['name'], $updates['address'], $updates['city'],
        $updates['contact'], $updates['phone'], $updates['vat'],
        $row['id'],
    ]);
    $encrypted++;
}

echo "  Total rows : $total\n";
echo "  Encrypted  : $encrypted\n";
echo "  Skipped    : $skipped (already encrypted)\n\n";

echo "=== Done. All existing data is now encrypted. ===\n\n";
echo "Keep your APP_KEY safe — losing it means losing access to all data.\n\n";

// ── Helper ─────────────────────────────────────────────────────
function looksEncrypted(string $value): bool
{
    if (strlen($value) < 44) return false;
    $decoded = base64_decode($value, strict: true);
    return $decoded !== false && strlen($decoded) >= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 1;
}
