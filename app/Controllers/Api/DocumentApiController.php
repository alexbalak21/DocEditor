<?php

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\DocumentService;

class DocumentApiController
{
    private DocumentService $service;
    private Request $request;

    public function __construct()
    {
        $this->service = new DocumentService();
        $this->request = new Request();
    }

    public function index(): void
    {
        $docs = $this->service->list(
            search: $this->request->query('q', ''),
            type:   $this->request->query('type', ''),
            status: (string) $this->request->query('status', ''),
            from:   (string) $this->request->query('from', ''),
            to:     (string) $this->request->query('to', ''),
        );
        Response::ok(['documents' => $docs]);
    }

    public function show(array $params): void
    {
        $doc = $this->service->get((int)$params['id']);
        if (!$doc) Response::error('Document not found', 404);
        Response::ok(['document' => $doc->toArray()]);
    }

    public function store(): void
    {
        $data = $this->request->input('data') ?? $this->service->defaultTemplate(
            $this->request->input('type', 'INVOICE')
        );
        $id = $this->service->create($data);
        Response::ok(['id' => $id]);
    }

    public function update(array $params): void
    {
        $data = $this->request->input('data');
        if (!$data) Response::error('No data provided');
        $this->service->update(
            id:     (int)$params['id'],
            data:   $data,
            status: $this->request->input('status', 'draft'),
        );
        Response::ok(['id' => (int)$params['id']]);
    }

    /** GET /api/documents/export — every document, full data, oldest first */
    public function export(): void
    {
        Response::ok($this->service->exportAll());
    }

    /** POST /api/documents/import  body: {documents: [...]} */
    public function import(): void
    {
        $docs = $this->request->input('documents');
        if (!is_array($docs) || $docs === []) Response::error('No documents provided');
        if (count($docs) > 200) Response::error('Too many documents in one request (max 200)');
        Response::ok($this->service->importMany(array_values($docs)));
    }

    public function destroy(array $params): void
    {
        $this->service->delete((int)$params['id']);
        Response::ok();
    }

    public function duplicate(array $params): void
    {
        $newId = $this->service->duplicate((int)$params['id']);
        Response::ok(['id' => $newId]);
    }

    /** POST /api/documents/:id/convert  body: {force?: bool} — quote → new draft invoice */
    public function convert(array $params): void
    {
        try {
            $res = $this->service->convertQuoteToInvoice((int)$params['id'], (bool)$this->request->input('force', false));
            Response::ok($res);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage());
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'already_converted:')) {
                Response::json([
                    'ok' => false, 'code' => 'already_converted',
                    'error' => 'This quote was already converted',
                    'invoices' => explode(',', substr($e->getMessage(), strlen('already_converted:'))),
                ], 409);
            }
            throw $e;
        }
    }

    public function default(): void
    {
        $type = strtoupper($this->request->query('type', 'INVOICE'));
        Response::ok(['data' => $this->service->defaultTemplate($type)]);
    }

    public function nextNumber(): void
    {
        $type = $this->request->query('type', 'INVOICE');
        Response::ok($this->service->nextNumber($type));
    }

    public function peekNumber(): void
    {
        $type = $this->request->query('type', 'INVOICE');
        Response::ok($this->service->peekNumber($type));
    }
}
