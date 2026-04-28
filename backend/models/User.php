<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDOException;

final class User
{
    public static function findByEmail(string $email): ?array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'SELECT id, name, email, password FROM users WHERE email = :email LIMIT 1'
            );
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            return is_array($user) ? $user : null;
        } catch (PDOException) {
            return null;
        }
    }

    public static function create(string $name, string $email, string $password): ?array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password) VALUES (:name, :email, :password) RETURNING id, name, email'
            );
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
            $user = $stmt->fetch();

            return is_array($user) ? $user : null;
        } catch (PDOException) {
            return null;
        }
    }

    public static function findById(int $id): ?array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'SELECT id, name, email FROM users WHERE id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $id]);
            $user = $stmt->fetch();

            return is_array($user) ? $user : null;
        } catch (PDOException) {
            return null;
        }
    }
}
