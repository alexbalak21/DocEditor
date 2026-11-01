<?php

namespace App\Repositories;

use App\Core\Database;
use App\Core\Encryption;
use App\Models\Customer;
use PDO;

class CustomerRepository
{
    private PDO $db;

    /** Every customer field is sensitive PII — encrypt them all. */
    private const ENCRYPTED_COLS = ['name', 'address', 'city', 'contact', 'phone', 'vat'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Encryption helpers ──────────────────────────────────────

    private function encryptRow(Customer $c): array
    {
        return Encryption::encryptFields([
            'name'    => $c->name,
            'address' => $c->address,
            'city'    => $c->city,
            'contact' => $c->contact,
            'phone'   => $c->phone,
            'vat'     => $c->vat,
        ], self::ENCRYPTED_COLS);
    }

    private function decryptRow(array $row): array
    {
        return Encryption::decryptFields($row, self::ENCRYPTED_COLS);
    }

    // ── Queries ─────────────────────────────────────────────────

    /** @return Customer[] */
    public function findAll(string $search = ''): array
    {
        // All searchable fields are encrypted — fetch all and filter in PHP
        $stmt = $this->db->query('SELECT * FROM customers ORDER BY id ASC');
        $customers = array_map(
            fn($row) => Customer::fromRow($this->decryptRow($row)),
            $stmt->fetchAll()
        );

        if ($search !== '') {
            $q = strtolower($search);
            $customers = array_values(array_filter($customers, function (Customer $c) use ($q) {
                return str_contains(strtolower($c->name),    $q)
                    || str_contains(strtolower($c->city),    $q)
                    || str_contains(strtolower($c->contact), $q);
            }));
        }

        // Re-sort by name after decryption
        usort($customers, fn($a, $b) => strcasecmp($a->name, $b->name));

        return $customers;
    }

    public function findById(int $id): ?Customer
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? Customer::fromRow($this->decryptRow($row)) : null;
    }

    public function findByName(string $name): ?Customer
    {
        // Can't use SQL LIKE on ciphertext — fetch all and match in PHP
        $stmt = $this->db->query('SELECT * FROM customers');
        foreach ($stmt->fetchAll() as $row) {
            $decrypted = $this->decryptRow($row);
            if (strtolower($decrypted['name'] ?? '') === strtolower($name)) {
                return Customer::fromRow($decrypted);
            }
        }
        return null;
    }

    // ── Writes ──────────────────────────────────────────────────

    public function create(Customer $customer): int
    {
        $row = $this->encryptRow($customer);
        $stmt = $this->db->prepare('
            INSERT INTO customers (name, address, city, contact, phone, vat)
            VALUES (:name, :address, :city, :contact, :phone, :vat)
        ');
        $stmt->execute([
            ':name'    => $row['name'],
            ':address' => $row['address'],
            ':city'    => $row['city'],
            ':contact' => $row['contact'],
            ':phone'   => $row['phone'],
            ':vat'     => $row['vat'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, Customer $customer): void
    {
        $row = $this->encryptRow($customer);
        $stmt = $this->db->prepare('
            UPDATE customers
            SET name=:name, address=:address, city=:city,
                contact=:contact, phone=:phone, vat=:vat, updated_at=NOW()
            WHERE id=:id
        ');
        $stmt->execute([
            ':name'    => $row['name'],
            ':address' => $row['address'],
            ':city'    => $row['city'],
            ':contact' => $row['contact'],
            ':phone'   => $row['phone'],
            ':vat'     => $row['vat'],
            ':id'      => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
    }
}
