<?php

namespace App\Controllers\Api;

use App\Core\Response;
use App\Services\TemplateService;

class TemplateApiController
{
    /** GET /api/templates — all manifests */
    public function index(): void
    {
        Response::ok(['templates' => TemplateService::all()]);
    }

    /** GET /api/templates/:id — single manifest */
    public function show(array $params): void
    {
        $m = TemplateService::get($params['id']);
        if (!$m) Response::error('Template not found', 404);
        Response::ok(['template' => $m]);
    }
}
