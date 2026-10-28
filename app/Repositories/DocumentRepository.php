<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\Document;
use PDO;

class DocumentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return Document[] */
    public function findAll(string $search = '', string $type = ''): array
    {
        $sql    = 'SELECT id, type, number, date, status, customer, created_at, updated_at FROM documents WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (number LIKE :q OR customer LIKE :q)';
            $params[':q'] = "%$search%";
        }
        if ($type !== '') {
            $sql .= ' AND type = :type';
            $params[':type'] = $type;
        }
        $sql .= ' ORDER BY updated_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(
            fn(array $row) => Document::fromRow($row),
            $stmt->fetchAll()
        );
    }

    public function findById(int $id): ?Document
    {
        $stmt = $this->db->prepare('SELECT * FROM documents WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? Document::fromRow($row) : null;
    }

    public function create(Document $doc): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO documents (type, number, date, status, customer, data)
            VALUES (:type, :number, :date, :status, :customer, :data)
        ');
        $stmt->execute([
            ':type'     => $doc->type,
            ':number'   => $doc->number,
            ':date'     => $doc->date,
            ':status'   => $doc->status,
            ':customer' => $doc->customer,
            ':data'     => json_encode($doc->data, JSON_UNESCAPED_UNICODE),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @return Document[] every document including its data blob, oldest first */
    public function findAllFull(): array
    {
        $rows = $this->db->query('SELECT * FROM documents ORDER BY created_at ASC, id ASC')->fetchAll();
        return array_map(fn(array $r) => Document::fromRow($r), $rows);
    }

    /** id of a document with this type + number, or null. */
    public function findIdByTypeAndNumber(string $type, string $number): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM documents WHERE type = ? AND number = ? LIMIT 1');
        $stmt->execute([$type, $number]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** Insert keeping the original created_at / updated_at (used by the bulk import). */
    public function createWithDates(Document $doc): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO documents (type, number, date, status, customer, data, created_at, updated_at)
            VALUES (:type, :number, :date, :status, :customer, :data, :created, :updated)
        ');
        $stmt->execute([
            ':type'     => $doc->type,
            ':number'   => $doc->number,
            ':date'     => $doc->date,
            ':status'   => $doc->status,
            ':customer' => $doc->customer,
            ':data'     => json_encode($doc->data, JSON_UNESCAPED_UNICODE),
            ':created'  => $doc->createdAt,
            ':updated'  => $doc->updatedAt,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, Document $doc): void
    {
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
            ':customer' => $doc->customer,
            ':data'     => json_encode($doc->data, JSON_UNESCAPED_UNICODE),
            ':id'       => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);
    }

    public function duplicate(int $id): int
    {
        $doc = $this->findById($id);
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
        return $this->create($copy);
    }
}
