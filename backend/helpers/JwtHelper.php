<?php

declare(strict_types=1);

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final class JwtHelper
{
    public static function encode(array $payload): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if (!is_string($secret) || trim($secret) === '') {
            throw new \RuntimeException('JWT_SECRET não configurado');
        }

        $ttl = (int) ($_ENV['JWT_EXPIRATION'] ?? 86400);
        if ($ttl <= 0) {
            $ttl = 86400;
        }
        $payload['iat'] = time();
        $payload['exp'] = time() + $ttl;

        return JWT::encode($payload, $secret, 'HS256');
    }

    public static function decode(string $jwt): ?object
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if (!is_string($secret) || trim($secret) === '') {
            return null;
        }

        try {
            return JWT::decode($jwt, new Key($secret, 'HS256'));
        } catch (Throwable) {
            return null;
        }
    }
}
