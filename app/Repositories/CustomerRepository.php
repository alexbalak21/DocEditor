<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\Customer;
use PDO;

class CustomerRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return Customer[] */
    public function findAll(string $search = ''): array
    {
        $sql    = 'SELECT * FROM customers WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (name LIKE :q1 OR city LIKE :q2 OR contact LIKE :q3)';
            $params[':q1'] = "%$search%";
            $params[':q2'] = "%$search%";
            $params[':q3'] = "%$search%";
        }
        $sql .= ' ORDER BY name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(fn($r) => Customer::fromRow($r), $stmt->fetchAll());
    }

    public function findById(int $id): ?Customer
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? Customer::fromRow($row) : null;
    }

    public function findByName(string $name): ?Customer
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE name = ? COLLATE utf8mb4_general_ci LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        return $row ? Customer::fromRow($row) : null;
    }

    public function create(Customer $customer): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO customers (name, address, city, contact, phone, vat)
            VALUES (:name, :address, :city, :contact, :phone, :vat)
        ');
        $stmt->execute([
            ':name'    => $customer->name,
            ':address' => $customer->address,
            ':city'    => $customer->city,
            ':contact' => $customer->contact,
            ':phone'   => $customer->phone,
            ':vat'     => $customer->vat,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, Customer $customer): void
    {
        $stmt = $this->db->prepare('
            UPDATE customers
            SET name=:name, address=:address, city=:city,
                contact=:contact, phone=:phone, vat=:vat, updated_at=NOW()
            WHERE id=:id
        ');
        $stmt->execute([
            ':name'    => $customer->name,
            ':address' => $customer->address,
            ':city'    => $customer->city,
            ':contact' => $customer->contact,
            ':phone'   => $customer->phone,
            ':vat'     => $customer->vat,
            ':id'      => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
    }
}
