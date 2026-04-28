<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\JwtHelper;

final class AuthMiddleware
{
    public static function getBearerPayload(): ?object
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!is_string($header) || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));
        if ($token === '') {
            return null;
        }

        return JwtHelper::decode($token);
    }

    public static function requireAuth(): ?object
    {
        $payload = self::getBearerPayload();
        if ($payload === null) {
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido ou expirado']);
            return null;
        }

        return $payload;
    }
}
