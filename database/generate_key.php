<?php
/**
 * database/generate_key.php
 *
 * Generates a secure random APP_KEY for use in .env.
 * Run once during initial setup:
 *
 *   php database/generate_key.php
 *
 * Then add the printed key to your .env:
 *   APP_KEY=<output>
 */

if (!function_exists('sodium_crypto_secretbox')) {
    echo "ERROR: PHP sodium extension is not available.\n";
    echo "Install with: sudo apt install php-sodium\n";
    exit(1);
}

$key = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));

echo "\n";
echo "=== DocEditor — APP_KEY Generator ===\n\n";
echo "Add this line to your .env file:\n\n";
echo "APP_KEY={$key}\n\n";
echo "WARNING: Store this key securely.\n";
echo "If you lose it, ALL encrypted data becomes permanently unreadable.\n";
echo "Back it up separately from the database.\n\n";
