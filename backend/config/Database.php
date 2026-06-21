<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

final class Database
{
    public static function getConnection(): PDO
    {
        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? '5432';
        $dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'systemloan';
        $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?? 'postgres';
        $pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?? '';

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $host,
            $port,
            $dbname
        );

        try {
            return new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int) $e->getCode());
        }
    }
}
