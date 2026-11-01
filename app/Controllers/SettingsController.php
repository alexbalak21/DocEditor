<?php

namespace App\Controllers;

use App\Core\DataExporter;
use App\Core\DataImporter;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Services\CustomerService;
use App\Services\DocumentService;
use App\Services\ItemService;

class SettingsController
{
    private function makeExporter(): DataExporter
    {
        return new DataExporter(
            new DocumentService(),
            new CustomerService(),
            new ItemService(),
        );
    }

    private function makeImporter(): DataImporter
    {
        return new DataImporter(
            new DocumentService(),
            new CustomerService(),
            new ItemService(),
        );
    }

    // ── GET /settings ──────────────────────────────────────────
    public function index(): void
    {
        // Stats for the overview card
        $db    = \App\Core\Database::getInstance();
        $stats = [
            'documents' => (int) $db->query('SELECT COUNT(*) FROM documents')->fetchColumn(),
            'customers' => (int) $db->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
            'items'     => (int) $db->query('SELECT COUNT(*) FROM items')->fetchColumn(),
            'notes'     => (int) $db->query('SELECT COUNT(*) FROM notes')->fetchColumn(),
        ];

        // Log files for the logs tab
        $logFiles = Logger::logFiles();

        // Notes for the notes tab
        $notes = $db->query('
            SELECT n.*, u.name AS user_name, u.email AS user_email
            FROM notes n LEFT JOIN users u ON u.id = n.user_id
            ORDER BY n.created_at DESC LIMIT 100
        ')->fetchAll();
        foreach ($notes as &$note) {
            $note['images'] = json_decode($note['images'] ?? '[]', true) ?: [];
        }
        unset($note);

        Response::view('settings.index', [
            'title'    => 'Settings',
            'stats'    => $stats,
            'logFiles' => $logFiles,
            'notes'    => $notes,
        ]);
    }

    // ── POST /api/settings/export ──────────────────────────────
    /** Plain JSON export — returns the payload directly to the browser. */
    public function exportPlain(): void
    {
        try {
            $payload = $this->makeExporter()->buildPayload();
            Logger::info('settings.export.plain', ['counts' => $payload['counts']]);
            Response::ok($payload);
        } catch (\Throwable $e) {
            Logger::error('settings.export.plain.failed', ['error' => $e->getMessage()]);
            Response::error($e->getMessage());
        }
    }

    // ── POST /api/settings/export/encrypted ───────────────────
    /** Encrypted JSON export — key supplied by the user. */
    public function exportEncrypted(): void
    {
        $req        = new Request();
        $passphrase = trim((string) $req->input('passphrase', ''));

        if ($passphrase === '') {
            Response::error('A passphrase is required for encrypted export.');
            return;
        }

        try {
            $payload = $this->makeExporter()->buildEncrypted($passphrase);
            Logger::info('settings.export.encrypted');
            Response::ok($payload);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage());
        } catch (\Throwable $e) {
            Logger::error('settings.export.encrypted.failed', ['error' => $e->getMessage()]);
            Response::error('Export failed: ' . $e->getMessage());
        }
    }

    // ── POST /api/settings/import ──────────────────────────────
    /**
     * Import a plain or encrypted export.
     * Body: { payload: <parsed JSON object>, passphrase?: string }
     * The caller parses the JSON file in JS and sends the object.
     */
    public function import(): void
    {
        $req        = new Request();
        $payload    = $req->input('payload');
        $passphrase = trim((string) $req->input('passphrase', ''));

        if (!is_array($payload)) {
            Response::error('No payload provided — send the parsed JSON object.');
            return;
        }

        try {
            $results = $this->makeImporter()->import($payload, $passphrase);

            $counts = array_map(fn($r) => $r['imported'], $results);
            Logger::info('settings.import', $counts);

            Response::ok([
                'results' => $results,
                'summary' => $this->summarise($results),
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage());
        } catch (\RuntimeException $e) {
            Logger::warning('settings.import.failed', ['error' => $e->getMessage()]);
            Response::error($e->getMessage());
        } catch (\Throwable $e) {
            Logger::error('settings.import.exception', ['error' => $e->getMessage()]);
            Response::error('Import failed: ' . $e->getMessage());
        }
    }

    // ── POST /api/settings/notes/:id/delete ───────────────────
    public function deleteNote(array $params): void
    {
        $db = \App\Core\Database::getInstance();
        $db->prepare('DELETE FROM notes WHERE id = ?')->execute([(int)$params['id']]);
        Response::ok();
    }

    // ── Helpers ────────────────────────────────────────────────
    private function summarise(array $results): string
    {
        $parts = [];
        foreach ($results as $entity => $r) {
            $parts[] = ucfirst($entity) . ': '
                . $r['imported'] . ' imported, '
                . $r['skipped']  . ' skipped'
                . ($r['failed'] > 0 ? ', ' . $r['failed'] . ' failed' : '');
        }
        return implode(' · ', $parts);
    }
}
