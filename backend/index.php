<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

// Avoid hard failure in environments that inject vars without a .env file.
Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();

$allowedOriginsFromEnv = $_ENV['ALLOWED_ORIGINS'] ?? '';
$allowed_origins = array_values(array_filter(array_map(
    static fn(string $origin): string => trim($origin),
    explode(',', $allowedOriginsFromEnv)
)));

if ($allowed_origins === []) {
    $allowed_origins = [
        'http://localhost:5173',
        'https://system-loan-gold.vercel.app',
    ];
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/routes/api.php';
