<?php

namespace App\Core;

use App\Services\DocumentService;
use App\Services\CustomerService;
use App\Services\ItemService;

/**
 * DataImporter — restores a full-app snapshot exported by DataExporter.
 * Handles both plain and encrypted exports.
 * Documents, customers and items that already exist are skipped (idempotent).
 */
class DataImporter
{
    public function __construct(
        private DocumentService $docs,
        private CustomerService $customers,
        private ItemService     $items,
    ) {}

    /**
     * Import a plain export payload (already decoded from JSON).
     *
     * @return array{documents:array, customers:array, items:array}
     */
    public function importPayload(array $payload): array
    {
        $results = [];

        if (!empty($payload['documents']) && is_array($payload['documents'])) {
            $results['documents'] = $this->docs->importMany($payload['documents']);
        } else {
            $results['documents'] = ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        }

        if (!empty($payload['customers']) && is_array($payload['customers'])) {
            $results['customers'] = $this->customers->importMany($payload['customers']);
        } else {
            $results['customers'] = ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        }

        if (!empty($payload['items']) && is_array($payload['items'])) {
            $results['items'] = $this->items->importMany($payload['items']);
        } else {
            $results['items'] = ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        }

        return $results;
    }

    /**
     * Decrypt an encrypted export and import it.
     *
     * @throws \RuntimeException if decryption fails (wrong key or tampered data)
     * @throws \InvalidArgumentException if the payload structure is wrong
     */
    public function importEncrypted(array $payload, string $passphrase): array
    {
        if (($payload['format'] ?? '') !== DataExporter::FORMAT_ENC) {
            throw new \InvalidArgumentException('Not an encrypted DocEditor export.');
        }
        if (empty($payload['nonce']) || empty($payload['data'])) {
            throw new \InvalidArgumentException('Encrypted export is missing nonce or data fields.');
        }

        $key    = DataExporter::deriveKey($passphrase);
        $nonce  = base64_decode($payload['nonce'], true);
        $cipher = base64_decode($payload['data'],  true);

        if ($nonce === false || $cipher === false) {
            throw new \InvalidArgumentException('Encrypted export contains invalid base64 data.');
        }

        $plaintext = sodium_crypto_secretbox_open($cipher, $nonce, $key);
        sodium_memzero($key);

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed — the passphrase is incorrect or the file was tampered with.');
        }

        $inner = json_decode($plaintext, true);
        if (!is_array($inner)) {
            throw new \RuntimeException('Decrypted data is not valid JSON.');
        }

        return $this->importPayload($inner);
    }

    /**
     * Auto-detect format and dispatch.
     * If format is 'doceditor-encrypted', $passphrase is required.
     */
    public function import(array $payload, string $passphrase = ''): array
    {
        $format = $payload['format'] ?? '';

        if ($format === DataExporter::FORMAT_ENC) {
            if ($passphrase === '') {
                throw new \InvalidArgumentException('This export is encrypted — a passphrase is required to import it.');
            }
            return $this->importEncrypted($payload, $passphrase);
        }

        if ($format === DataExporter::FORMAT || $format === 'doceditor-export') {
            return $this->importPayload($payload);
        }

        throw new \InvalidArgumentException("Unknown export format: \"{$format}\". Expected doceditor-export or doceditor-encrypted.");
    }
}
