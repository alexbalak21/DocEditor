<?php

use App\Controllers\DocumentController;
use App\Controllers\CustomerController;
use App\Controllers\ItemController;
use App\Controllers\AuthController;
use App\Controllers\LogController;
use App\Controllers\ProfileController;
use App\Controllers\Api\DocumentApiController;
use App\Controllers\Api\CustomerApiController;
use App\Controllers\Api\ItemApiController;

/**
 * Authentication — everything else requires a logged-in user (see Router::guard)
 */
$router->get('/login',   [AuthController::class, 'showLogin'], ['public' => true]);
$router->post('/login',  [AuthController::class, 'login'],     ['public' => true]);
$router->post('/logout', [AuthController::class, 'logout'],    ['public' => true, 'csrf' => false]);

$router->get('/profile',           [ProfileController::class, 'show']);
$router->post('/profile',          [ProfileController::class, 'update']);
$router->post('/profile/password', [ProfileController::class, 'password']);
$router->post('/profile/devices/revoke-others', [ProfileController::class, 'revokeOthers']);
$router->post('/profile/devices/:id/revoke',    [ProfileController::class, 'revokeDevice']);

$router->get('/api/csrf', [AuthController::class, 'csrf']);
$router->get('/api/session/ping', [AuthController::class, 'ping']);

/**
 * Web routes — HTML pages
 */
$router->get('/',               [DocumentController::class, 'index']);
$router->get('/documents',      [DocumentController::class, 'index']);
$router->get('/editor',         [DocumentController::class, 'editor']);
$router->get('/preview',        [DocumentController::class, 'preview']);

$router->get('/customers',      [CustomerController::class, 'index']);
$router->get('/items',          [ItemController::class,     'index']);
$router->get('/documentation',  [DocumentController::class, 'documentation']);
$router->get('/logs',           [LogController::class, 'index']);
$router->get('/logs/download',  [LogController::class, 'download']);

/**
 * API routes — JSON responses
 */

// Documents — static routes MUST come before :id routes
$router->get('/api/documents',                [DocumentApiController::class, 'index']);
$router->get('/api/documents/default',        [DocumentApiController::class, 'default']);
$router->get('/api/numbers/next',             [DocumentApiController::class, 'nextNumber']);
$router->get('/api/numbers/peek',             [DocumentApiController::class, 'peekNumber']);
$router->get('/api/documents/export',         [DocumentApiController::class, 'export']);
$router->post('/api/documents/import',        [DocumentApiController::class, 'import']);
$router->get('/api/documents/:id',            [DocumentApiController::class, 'show']);
$router->post('/api/documents',               [DocumentApiController::class, 'store']);
$router->post('/api/documents/:id',           [DocumentApiController::class, 'update']);
$router->post('/api/documents/:id/delete',    [DocumentApiController::class, 'destroy']);
$router->post('/api/documents/:id/duplicate', [DocumentApiController::class, 'duplicate']);
$router->post('/api/documents/:id/convert',   [DocumentApiController::class, 'convert']);

// Customers
$router->get('/api/customers',              [CustomerApiController::class, 'index']);
$router->post('/api/customers/import',      [CustomerApiController::class, 'import']);
$router->get('/api/customers/:id',          [CustomerApiController::class, 'show']);
$router->post('/api/customers',             [CustomerApiController::class, 'store']);
$router->post('/api/customers/:id',         [CustomerApiController::class, 'update']);
$router->post('/api/customers/:id/delete',  [CustomerApiController::class, 'destroy']);

// Items
$router->get('/api/items',                  [ItemApiController::class, 'index']);
$router->post('/api/items/import',          [ItemApiController::class, 'import']);
$router->get('/api/items/:id',              [ItemApiController::class, 'show']);
$router->post('/api/items',                 [ItemApiController::class, 'store']);
$router->post('/api/items/:id',             [ItemApiController::class, 'update']);
$router->post('/api/items/:id/delete',      [ItemApiController::class, 'destroy']);
