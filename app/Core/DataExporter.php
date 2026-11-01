<?php

namespace App\Core;

use App\Services\DocumentService;
use App\Services\CustomerService;
use App\Services\ItemService;

/**
 * DataExporter — builds a full-app snapshot (documents + customers + items).
 *
 * Plain export:
 *   { format, version, exported_at, documents:[], customers:[], items:[] }
 *
 * Encrypted export (AES-256 via libsodium secretbox):
 *   { format:"doceditor-encrypted", version:1, exported_at, nonce:<b64>, data:<b64-ciphertext> }
 *   The plaintext that is encrypted is the same JSON as the plain export.
 *   Key: 32 raw bytes derived from the user-supplied passphrase via BLAKE2b-256.
 */
class DataExporter
{
    public const FORMAT         = 'doceditor-export';
    public const FORMAT_ENC     = 'doceditor-encrypted';
    public const VERSION        = 2;

    public function __construct(
        private DocumentService $docs,
        private CustomerService $customers,
        private ItemService     $items,
    ) {}

    /** Build the full plain-text export array (ready to json_encode). */
    public function buildPayload(): array
    {
        // Documents (decrypted by the repository layer)
        $documents = array_map(fn($d) => [
            'id'         => $d->id,
            'status'     => $d->status,
            'created_at' => $d->createdAt,
            'updated_at' => $d->updatedAt,
            'data'       => $d->data,
        ], $this->docs->allFull());

        // Customers (decrypted by repository layer)
        $customers = array_map(
            fn($c) => array_diff_key($c->toArray(), ['id' => 0, 'created_at' => 0, 'updated_at' => 0]),
            $this->customers->all()
        );

        // Items
        $items = array_map(
            fn($i) => array_diff_key($i->toArray(), ['id' => 0, 'created_at' => 0, 'updated_at' => 0]),
            $this->items->all()
        );

        return [
            'format'      => self::FORMAT,
            'version'     => self::VERSION,
            'exported_at' => date('c'),
            'counts'      => [
                'documents' => count($documents),
                'customers' => count($customers),
                'items'     => count($items),
            ],
            'documents' => $documents,
            'customers' => $customers,
            'items'     => $items,
        ];
    }

    /**
     * Build an encrypted export payload.
     * The passphrase is stretched with BLAKE2b-256 to a 32-byte sodium key.
     * The same key derivation is used on import — no KDF state needs to be stored.
     *
     * @throws \RuntimeException if sodium is unavailable
     */
    public function buildEncrypted(string $passphrase): array
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new \RuntimeException('PHP sodium extension is required for encrypted export.');
        }
        if (strlen($passphrase) < 4) {
            throw new \InvalidArgumentException('Encryption passphrase must be at least 4 characters.');
        }

        $key       = self::deriveKey($passphrase);
        $nonce     = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = json_encode($this->buildPayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $cipher    = sodium_crypto_secretbox($plaintext, $nonce, $key);
        sodium_memzero($key);

        return [
            'format'      => self::FORMAT_ENC,
            'version'     => self::VERSION,
            'exported_at' => date('c'),
            'nonce'       => base64_encode($nonce),
            'data'        => base64_encode($cipher),
        ];
    }

    /**
     * Derive a 32-byte sodium key from a passphrase using BLAKE2b-256.
     * Deterministic: same passphrase → same key, no stored salt needed.
     * (Acceptable here because the nonce is random per export.)
     */
    public static function deriveKey(string $passphrase): string
    {
        // BLAKE2b with 32-byte output, keyed with a fixed domain string
        $domain = 'doceditor-export-v1';
        return sodium_crypto_generichash($passphrase, $domain, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    /** All items as model objects. */
    private function itemsAll(): array
    {
        return $this->items->all();
    }
}
