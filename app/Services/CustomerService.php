<?php

namespace App\Services;

use App\Models\Customer;
use App\Repositories\CustomerRepository;

class CustomerService
{
    public function __construct(
        private CustomerRepository $repo = new CustomerRepository()
    ) {}

    public function list(string $search = ''): array
    {
        return array_map(fn(Customer $c) => $c->toArray(), $this->repo->findAll($search));
    }

    public function get(int $id): ?Customer
    {
        return $this->repo->findById($id);
    }

    public function create(array $input): int
    {
        $name = trim($input['name'] ?? '');
        if ($name === '') throw new \InvalidArgumentException('Name is required');

        return $this->repo->create(new Customer(
            id:      null,
            name:    $name,
            address: trim($input['address'] ?? ''),
            city:    trim($input['city']    ?? ''),
            contact: trim($input['contact'] ?? ''),
            phone:   trim($input['phone']   ?? ''),
            vat:     trim($input['vat']     ?? ''),
        ));
    }

    public function update(int $id, array $input): void
    {
        $name = trim($input['name'] ?? '');
        if ($name === '') throw new \InvalidArgumentException('Name is required');

        $this->repo->update($id, new Customer(
            id:      $id,
            name:    $name,
            address: trim($input['address'] ?? ''),
            city:    trim($input['city']    ?? ''),
            contact: trim($input['contact'] ?? ''),
            phone:   trim($input['phone']   ?? ''),
            vat:     trim($input['vat']     ?? ''),
        ));
    }

    /**
     * Bulk import: customers whose name already exists (case-insensitive) are skipped.
     * @return array{imported:int, skipped:int, failed:int, errors:string[]}
     */
    public function importMany(array $rows): array
    {
        $res = ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        foreach ($rows as $i => $row) {
            $name = is_array($row) ? trim((string)($row['name'] ?? '')) : '';
            try {
                if ($name === '') throw new \InvalidArgumentException('name is required');
                if ($this->repo->findByName($name)) { $res['skipped']++; continue; }
                $this->create($row);
                $res['imported']++;
            } catch (\Throwable $e) {
                $res['failed']++;
                $res['errors'][] = ($name !== '' ? $name : '#' . ($i + 1)) . ': ' . $e->getMessage();
            }
        }
        return $res;
    }

    public function delete(int $id): void
    {
        $this->repo->delete($id);
    }
}
