<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\JwtHelper;
use App\Models\User;
use Throwable;

final class AuthController
{
    public static function register(): void
    {
        $input = self::getJsonInput();
        if ($input === null) {
            self::respond(400, ['error' => 'JSON inválido']);
            return;
        }

        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            self::respond(400, ['error' => 'name, email e password são obrigatórios']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::respond(400, ['error' => 'Email inválido']);
            return;
        }

        if (strlen($password) < 6) {
            self::respond(400, ['error' => 'A senha deve ter no mínimo 6 caracteres']);
            return;
        }

        $existingUser = User::findByEmail($email);
        if ($existingUser !== null) {
            self::respond(409, ['error' => 'Email já cadastrado']);
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        if (!is_string($hashedPassword) || $hashedPassword === '') {
            self::respond(400, ['error' => 'Não foi possível processar a senha']);
            return;
        }

        $newUser = User::create($name, $email, $hashedPassword);

        if ($newUser === null) {
            self::respond(400, ['error' => 'Não foi possível criar usuário']);
            return;
        }

        self::respond(201, [
            'message' => 'Usuário criado com sucesso',
            'user' => [
                'id' => (int) $newUser['id'],
                'name' => (string) $newUser['name'],
                'email' => (string) $newUser['email'],
            ],
        ]);
    }

    public static function login(): void
    {
        $input = self::getJsonInput();
        if ($input === null) {
            self::respond(400, ['error' => 'JSON inválido']);
            return;
        }

        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($email === '' || $password === '') {
            self::respond(400, ['error' => 'email e password são obrigatórios']);
            return;
        }

        $user = User::findByEmail($email);
        if ($user === null) {
            self::respond(401, ['error' => 'Credenciais inválidas']);
            return;
        }

        if (!password_verify($password, (string) $user['password'])) {
            self::respond(401, ['error' => 'Credenciais inválidas']);
            return;
        }

        try {
            $token = JwtHelper::encode([
                'sub' => (int) $user['id'],
                'name' => (string) $user['name'],
                'email' => (string) $user['email'],
            ]);
        } catch (Throwable) {
            self::respond(400, ['error' => 'Não foi possível gerar o token']);
            return;
        }

        self::respond(200, [
            'token' => $token,
            'user' => [
                'id' => (int) $user['id'],
                'name' => (string) $user['name'],
                'email' => (string) $user['email'],
            ],
        ]);
    }

    private static function getJsonInput(): ?array
    {
        $rawBody = file_get_contents('php://input');
        if ($rawBody === false) {
            return null;
        }

        if (trim($rawBody) === '') {
            return [];
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    private static function respond(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        echo json_encode($payload);
    }
}
