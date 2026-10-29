<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\Item;
use PDO;

class ItemRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return Item[] */
    public function findAll(string $search = ''): array
    {
        $sql    = 'SELECT * FROM items WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (reference LIKE :q1 OR title LIKE :q2 OR description LIKE :q3)';
            $params[':q1'] = "%$search%";
            $params[':q2'] = "%$search%";
            $params[':q3'] = "%$search%";
        }
        $sql .= ' ORDER BY title ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(fn($r) => Item::fromRow($r), $stmt->fetchAll());
    }

    public function findById(int $id): ?Item
    {
        $stmt = $this->db->prepare('SELECT * FROM items WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? Item::fromRow($row) : null;
    }

    public function create(Item $item): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO items (reference, title, unit, price, description)
            VALUES (:reference, :title, :unit, :price, :description)
        ');
        $stmt->execute([
            ':reference'   => $item->reference,
            ':title'       => $item->title,
            ':unit'        => $item->unit,
            ':price'       => $item->price,
            ':description' => $item->description,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function existsByReference(string $reference): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM items WHERE reference = ? COLLATE utf8mb4_general_ci LIMIT 1');
        $stmt->execute([$reference]);
        return (bool) $stmt->fetchColumn();
    }

    public function update(int $id, Item $item): void
    {
        $stmt = $this->db->prepare('
            UPDATE items
            SET reference=:reference, title=:title, unit=:unit,
                price=:price, description=:description, updated_at=NOW()
            WHERE id=:id
        ');
        $stmt->execute([
            ':reference'   => $item->reference,
            ':title'       => $item->title,
            ':unit'        => $item->unit,
            ':price'       => $item->price,
            ':description' => $item->description,
            ':id'          => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM items WHERE id = ?')->execute([$id]);
    }
}
