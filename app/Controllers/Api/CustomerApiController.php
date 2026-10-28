<?php

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\CustomerService;

class CustomerApiController
{
    private CustomerService $service;
    private Request $request;

    public function __construct()
    {
        $this->service = new CustomerService();
        $this->request = new Request();
    }

    public function index(): void
    {
        Response::ok(['customers' => $this->service->list($this->request->query('q', ''))]);
    }

    public function show(array $params): void
    {
        $customer = $this->service->get((int)$params['id']);
        if (!$customer) Response::error('Customer not found', 404);
        Response::ok(['customer' => $customer->toArray()]);
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

    /** POST /api/customers/import  body: {customers: [...]} (or a bare array) */
    public function import(): void
    {
        $body = $this->request->all();
        $rows = $body['customers'] ?? (array_is_list($body) ? $body : null);
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
