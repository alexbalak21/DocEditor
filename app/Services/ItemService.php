<?php

namespace App\Services;

use App\Models\Item;
use App\Repositories\ItemRepository;

class ItemService
{
    public function __construct(
        private ItemRepository $repo = new ItemRepository()
    ) {}

    public function list(string $search = ''): array
    {
        return array_map(fn(Item $i) => $i->toArray(), $this->repo->findAll($search));
    }

    public function get(int $id): ?Item
    {
        return $this->repo->findById($id);
    }

    public function create(array $input): int
    {
        $this->validate($input);
        return $this->repo->create(new Item(
            id:          null,
            reference:   trim($input['reference']   ?? ''),
            title:       trim($input['title']       ?? ''),
            unit:        trim($input['unit']        ?? ''),
            price:       trim($input['price']       ?? ''),
            description: trim($input['description'] ?? ''),
        ));
    }

    public function update(int $id, array $input): void
    {
        $this->validate($input);
        $this->repo->update($id, new Item(
            id:          $id,
            reference:   trim($input['reference']   ?? ''),
            title:       trim($input['title']       ?? ''),
            unit:        trim($input['unit']        ?? ''),
            price:       trim($input['price']       ?? ''),
            description: trim($input['description'] ?? ''),
        ));
    }

    /**
     * Bulk import: items whose reference already exists are skipped.
     * @return array{imported:int, skipped:int, failed:int, errors:string[]}
     */
    public function importMany(array $rows): array
    {
        $res = ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        foreach ($rows as $i => $row) {
            $ref = is_array($row) ? trim((string)($row['reference'] ?? '')) : '';
            try {
                if (!is_array($row)) throw new \InvalidArgumentException('not an object');
                if ($this->repo->existsByReference($ref)) { $res['skipped']++; continue; }
                $row['price'] = is_numeric($row['price'] ?? null) ? (string)$row['price'] : '0';
                foreach (['title', 'unit', 'description'] as $k) $row[$k] = (string)($row[$k] ?? '');
                $this->create($row);
                $res['imported']++;
            } catch (\Throwable $e) {
                $res['failed']++;
                $res['errors'][] = ($ref !== '' ? $ref : '#' . ($i + 1)) . ': ' . $e->getMessage();
            }
        }
        return $res;
    }

    public function delete(int $id): void
    {
        $this->repo->delete($id);
    }

    private function validate(array $input): void
    {
        if (trim($input['reference'] ?? '') === '') throw new \InvalidArgumentException('Reference is required');
        if (trim($input['title']     ?? '') === '') throw new \InvalidArgumentException('Title is required');
    }
}
