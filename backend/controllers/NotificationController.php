<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Notification;
use Throwable;

final class NotificationController
{
    private string $authMiddlewareClass;

    public function __construct(string $authMiddlewareClass = AuthMiddleware::class)
    {
        $this->authMiddlewareClass = $authMiddlewareClass;
    }

    public function index(): void
    {
        $payload = $this->requirePayload();
        if ($payload === null) {
            return;
        }

        try {
            self::respond(200, Notification::findByUser((int) $payload->sub));
        } catch (Throwable) {
            self::respond(500, ['error' => 'Erro interno']);
        }
    }

    public function markRead(int $id): void
    {
        $payload = $this->requirePayload();
        if ($payload === null) {
            return;
        }

        try {
            $updated = Notification::markRead($id, (int) $payload->sub);
            if (!$updated) {
                self::respond(404, ['error' => 'Notificação não encontrada']);
                return;
            }

            self::respond(200, ['message' => 'Notificação marcada como lida']);
        } catch (Throwable) {
            self::respond(500, ['error' => 'Erro interno']);
        }
    }

    private function requirePayload(): ?object
    {
        $payload = ($this->authMiddlewareClass)::requireAuth();
        if ($payload === null || !isset($payload->sub)) {
            if ($payload !== null) {
                self::respond(401, ['error' => 'Token inválido ou expirado']);
            }
            return null;
        }

        return $payload;
    }

    private static function respond(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        echo json_encode($payload);
    }
}
