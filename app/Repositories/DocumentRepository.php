<?php

namespace App\Repositories;

use App\Core\Database;
use App\Core\Encryption;
use App\Models\Document;
use PDO;

class DocumentRepository
{
    private PDO $db;

    /** Columns encrypted at rest. `data` is the full JSON blob. `customer` is the display name. */
    private const ENCRYPTED_COLS = ['customer', 'data'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Encryption helpers ──────────────────────────────────────

    private function encrypt(array $row): array
    {
        foreach (self::ENCRYPTED_COLS as $col) {
            if (isset($row[$col]) && $row[$col] !== '') {
                $row[$col] = Encryption::encrypt((string) $row[$col]);
            }
        }
        return $row;
    }

    private function decrypt(array $row): array
    {
        foreach (self::ENCRYPTED_COLS as $col) {
            if (isset($row[$col]) && $row[$col] !== '') {
                $row[$col] = Encryption::decrypt((string) $row[$col]);
            }
        }
        return $row;
    }

    private function decryptAndParse(array $row): Document
    {
        $row = $this->decrypt($row);
        return Document::fromRow($row);
    }

    // ── Queries ─────────────────────────────────────────────────

    /** @return Document[] */
    public function findAll(string $search = '', string $type = '', string $status = '', string $from = '', string $to = ''): array
    {
        // customer and data are encrypted — only filter by plaintext cols in SQL
        $sql    = 'SELECT id, type, number, date, status, customer, created_at, updated_at FROM documents WHERE 1=1';
        $params = [];

        if ($type !== '') {
            $sql .= ' AND type = :type';
            $params[':type'] = $type;
        }
        if (in_array($status, ['draft', 'final', 'paid'], true)) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $sql .= ' AND date >= :from';
            $params[':from'] = $from;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $sql .= ' AND date <= :to';
            $params[':to'] = $to;
        }
        $sql .= ' ORDER BY date DESC, updated_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Decrypt customer name, then apply search/number filter in PHP
        $docs = array_map(fn($row) => $this->decryptAndParse($row), $rows);

        if ($search !== '') {
            $q = strtolower($search);
            $docs = array_values(array_filter($docs, function (Document $d) use ($q) {
                return str_contains(strtolower($d->number), $q)
                    || str_contains(strtolower($d->customer), $q);
            }));
        }

        return $docs;
    }

    public function findById(int $id): ?Document
    {
        $stmt = $this->db->prepare('SELECT * FROM documents WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->decryptAndParse($row) : null;
    }

    /** @return Document[] every document including its data blob, oldest first */
    public function findAllFull(): array
    {
        $rows = $this->db->query('SELECT * FROM documents ORDER BY created_at ASC, id ASC')->fetchAll();
        return array_map(fn($row) => $this->decryptAndParse($row), $rows);
    }

    /** id of a document with this type + number, or null. */
    public function findIdByTypeAndNumber(string $type, string $number): ?int
    {
        // number is plaintext — safe to query directly
        $stmt = $this->db->prepare('SELECT id FROM documents WHERE type = ? AND number = ? LIMIT 1');
        $stmt->execute([$type, $number]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** Numbers of the invoices whose "quote_ref" points at this quote number. */
    public function findInvoiceNumbersByQuoteRef(string $quoteNumber): array
    {
        // data is encrypted — we can't use JSON_EXTRACT on ciphertext.
        // Fetch all invoices and filter in PHP.
        $stmt = $this->db->prepare("SELECT * FROM documents WHERE type = 'INVOICE'");
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $numbers = [];
        foreach ($rows as $row) {
            $doc = $this->decryptAndParse($row);
            if (($doc->data['quote_ref'] ?? '') === $quoteNumber) {
                $numbers[] = $doc->number;
            }
        }
        return $numbers;
    }

    // ── Writes ──────────────────────────────────────────────────

    public function create(Document $doc): int
    {
        $row = $this->encrypt([
            'customer' => $doc->customer,
            'data'     => json_encode($doc->data, JSON_UNESCAPED_UNICODE),
        ]);

        $stmt = $this->db->prepare('
            INSERT INTO documents (type, number, date, status, customer, data)
            VALUES (:type, :number, :date, :status, :customer, :data)
        ');
        $stmt->execute([
            ':type'     => $doc->type,
            ':number'   => $doc->number,
            ':date'     => $doc->date,
            ':status'   => $doc->status,
            ':customer' => $row['customer'],
            ':data'     => $row['data'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Insert keeping the original created_at / updated_at (used by the bulk import). */
    public function createWithDates(Document $doc): int
    {
        $row = $this->encrypt([
            'customer' => $doc->customer,
            'data'     => json_encode($doc->data, JSON_UNESCAPED_UNICODE),
        ]);

        $stmt = $this->db->prepare('
            INSERT INTO documents (type, number, date, status, customer, data, created_at, updated_at)
            VALUES (:type, :number, :date, :status, :customer, :data, :created, :updated)
        ');
        $stmt->execute([
            ':type'     => $doc->type,
            ':number'   => $doc->number,
            ':date'     => $doc->date,
            ':status'   => $doc->status,
            ':customer' => $row['customer'],
            ':data'     => $row['data'],
            ':created'  => $doc->createdAt,
            ':updated'  => $doc->updatedAt,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, Document $doc): void
    {
        $row = $this->encrypt([
            'customer' => $doc->customer,
            'data'     => json_encode($doc->data, JSON_UNESCAPED_UNICODE),
        ]);

        $stmt = $this->db->prepare('
            UPDATE documents
            SET type=:type, number=:number, date=:date, status=:status,
                customer=:customer, data=:data, updated_at=NOW()
            WHERE id=:id
        ');
        $stmt->execute([
            ':type'     => $doc->type,
            ':number'   => $doc->number,
            ':date'     => $doc->date,
            ':status'   => $doc->status,
            ':customer' => $row['customer'],
            ':data'     => $row['data'],
            ':id'       => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);
    }

    public function duplicate(int $id): int
    {
        $doc = $this->findById($id); // already decrypted
        if (!$doc) throw new \RuntimeException('Document not found');

        $data           = $doc->data;
        $data['number'] = ($data['number'] ?? '') . '-COPY';

        $copy = new Document(
            id:       null,
            type:     $doc->type,
            number:   $data['number'],
            date:     $doc->date,
            status:   'draft',
            customer: $doc->customer,
            data:     $data,
        );
        return $this->create($copy); // re-encrypts
    }
}
