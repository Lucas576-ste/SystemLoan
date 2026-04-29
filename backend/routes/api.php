<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\LoanController;
use App\Controllers\ToolController;
use App\Middleware\AuthMiddleware;
use App\Models\User;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . trim($path, '/');

if (str_starts_with($path, '/api')) {
    $rest = trim(substr($path, 4), '/');
    $path = $rest === '' ? '/' : '/' . $rest;
}

if ($method === 'GET' && ($path === '/' || $path === '')) {
    echo json_encode(['message' => 'SystemLoan API', 'status' => 'ok']);
    exit;
}

if ($method === 'GET' && $path === '/health') {
    echo json_encode(['message' => 'SystemLoan API', 'status' => 'ok']);
    exit;
}

if ($method === 'POST' && $path === '/register') {
    AuthController::register();
    exit;
}

if ($method === 'POST' && $path === '/login') {
    AuthController::login();
    exit;
}

if ($method === 'GET' && $path === '/me') {
    $payload = AuthMiddleware::requireAuth();
    if ($payload === null || !isset($payload->sub)) {
        if ($payload !== null) {
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido ou expirado']);
        }
        exit;
    }

    $user = User::findById((int) $payload->sub);
    if ($user === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Usuário não encontrado']);
        exit;
    }

    http_response_code(200);
    echo json_encode(['user' => $user]);
    exit;
}

$toolController = new ToolController();
$loanController = new LoanController();

if ($method === 'GET' && $path === '/tools') {
    $toolController->index();
    exit;
}

if ($method === 'GET' && $path === '/tools/mine') {
    $toolController->myTools();
    exit;
}

if ($method === 'POST' && $path === '/tools') {
    $toolController->store();
    exit;
}

if ($method === 'PUT' && preg_match('#^/tools/([0-9]+)$#', $path, $matches) === 1) {
    $toolController->update((int) $matches[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/tools/([0-9]+)$#', $path, $matches) === 1) {
    $toolController->update((int) $matches[1]);
    exit;
}

if ($method === 'DELETE' && preg_match('#^/tools/([0-9]+)$#', $path, $matches) === 1) {
    $toolController->destroy((int) $matches[1]);
    exit;
}

if ($method === 'GET' && $path === '/loans/history') {
    $loanController->history();
    exit;
}

if ($method === 'GET' && $path === '/loans/mine') {
    $loanController->myLoans();
    exit;
}

if ($method === 'POST' && $path === '/loans') {
    $loanController->store();
    exit;
}

if ($method === 'PATCH' && preg_match('#^/loans/([0-9]+)/return$#', $path, $matches) === 1) {
    $loanController->returnLoan((int) $matches[1]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not found']);
