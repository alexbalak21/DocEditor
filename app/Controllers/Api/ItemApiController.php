<?php

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\ItemService;

class ItemApiController
{
    private ItemService $service;
    private Request $request;

    public function __construct()
    {
        $this->service = new ItemService();
        $this->request = new Request();
    }

    public function index(): void
    {
        Response::ok(['items' => $this->service->list($this->request->query('q', ''))]);
    }

    public function show(array $params): void
    {
        $item = $this->service->get((int)$params['id']);
        if (!$item) Response::error('Item not found', 404);
        Response::ok(['item' => $item->toArray()]);
    }

    public function store(): void
    {
        try {
            $id = $this->service->create($this->request->all());
            Response::ok(['id' => $id]);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage());
        }
    }

    public function update(array $params): void
    {
        try {
            $this->service->update((int)$params['id'], $this->request->all());
            Response::ok();
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage());
        }
    }

    /** POST /api/items/import  body: {items: [...]} (or a bare array) */
    public function import(): void
    {
        $body = $this->request->all();
        $rows = $body['items'] ?? (array_is_list($body) ? $body : null);
        if (!is_array($rows) || $rows === []) Response::error('Nothing to import');
        if (count($rows) > 500) Response::error('Too many rows in one request (max 500)');
        Response::ok($this->service->importMany(array_values($rows)));
    }

    public function destroy(array $params): void
    {
        $this->service->delete((int)$params['id']);
        Response::ok();
    }
}
