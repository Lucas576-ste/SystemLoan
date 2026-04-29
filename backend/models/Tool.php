<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDOException;

final class Tool
{
    public const MAX_IMAGES = 4;

    public static function findAll(): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                "SELECT
                    t.id,
                    t.user_id,
                    u.name AS owner_name,
                    t.name,
                    t.description,
                    t.is_available,
                    t.created_at,
                    COALESCE((
                        SELECT json_agg(ti.image_path ORDER BY ti.sort_order, ti.id)
                        FROM tool_images ti
                        WHERE ti.tool_id = t.id
                    ), '[]'::json) AS image_urls
                 FROM tools t
                 JOIN users u ON u.id = t.user_id
                 ORDER BY t.created_at DESC"
            );
            $stmt->execute();
            $tools = $stmt->fetchAll();

            if (!is_array($tools)) {
                return [];
            }

            return array_map([self::class, 'normalizeToolRow'], $tools);
        } catch (PDOException $e) {
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    public static function findByUser(int $userId): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                "SELECT
                    t.id,
                    t.user_id,
                    u.name AS owner_name,
                    t.name,
                    t.description,
                    t.is_available,
                    t.created_at,
                    COALESCE((
                        SELECT json_agg(ti.image_path ORDER BY ti.sort_order, ti.id)
                        FROM tool_images ti
                        WHERE ti.tool_id = t.id
                    ), '[]'::json) AS image_urls
                 FROM tools t
                 JOIN users u ON u.id = t.user_id
                 WHERE t.user_id = :user_id
                 ORDER BY t.created_at DESC"
            );
            $stmt->execute(['user_id' => $userId]);
            $tools = $stmt->fetchAll();

            if (!is_array($tools)) {
                return [];
            }

            return array_map([self::class, 'normalizeToolRow'], $tools);
        } catch (PDOException $e) {
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    public static function findById(int $id): ?array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                "SELECT
                    t.id,
                    t.user_id,
                    u.name AS owner_name,
                    t.name,
                    t.description,
                    t.is_available,
                    t.created_at,
                    COALESCE((
                        SELECT json_agg(ti.image_path ORDER BY ti.sort_order, ti.id)
                        FROM tool_images ti
                        WHERE ti.tool_id = t.id
                    ), '[]'::json) AS image_urls
                 FROM tools t
                 JOIN users u ON u.id = t.user_id
                 WHERE t.id = :id
                 LIMIT 1"
            );
            $stmt->execute(['id' => $id]);
            $tool = $stmt->fetch();

            if (!is_array($tool)) {
                return null;
            }

            return self::normalizeToolRow($tool);
        } catch (PDOException $e) {
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    public static function create(int $userId, string $name, string $description): ?array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                "WITH inserted AS (
                    INSERT INTO tools (user_id, name, description)
                    VALUES (:user_id, :name, :description)
                    RETURNING id, user_id, name, description, is_available, created_at
                )
                SELECT
                    i.id,
                    i.user_id,
                    u.name AS owner_name,
                    i.name,
                    i.description,
                    i.is_available,
                    i.created_at,
                    '[]'::json AS image_urls
                FROM inserted i
                JOIN users u ON u.id = i.user_id
                LIMIT 1"
            );
            $stmt->execute([
                'user_id' => $userId,
                'name' => $name,
                'description' => $description,
            ]);
            $tool = $stmt->fetch();

            if (!is_array($tool)) {
                return null;
            }

            return self::normalizeToolRow($tool);
        } catch (PDOException $e) {
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    public static function update(int $id, int $userId, string $name, string $description): ?array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                "WITH updated AS (
                    UPDATE tools
                    SET name = :name,
                        description = :description,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id AND user_id = :user_id
                    RETURNING id, user_id, name, description, is_available, created_at
                )
                SELECT
                    up.id,
                    up.user_id,
                    u.name AS owner_name,
                    up.name,
                    up.description,
                    up.is_available,
                    up.created_at,
                    COALESCE((
                        SELECT json_agg(ti.image_path ORDER BY ti.sort_order, ti.id)
                        FROM tool_images ti
                        WHERE ti.tool_id = up.id
                    ), '[]'::json) AS image_urls
                FROM updated up
                JOIN users u ON u.id = up.user_id
                LIMIT 1"
            );
            $stmt->execute([
                'id' => $id,
                'user_id' => $userId,
                'name' => $name,
                'description' => $description,
            ]);
            $tool = $stmt->fetch();

            if (!is_array($tool)) {
                return null;
            }

            return self::normalizeToolRow($tool);
        } catch (PDOException $e) {
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    public static function replaceImages(int $toolId, int $userId, array $imagePaths): bool
    {
        if (count($imagePaths) > self::MAX_IMAGES) {
            return false;
        }

        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $ownershipStmt = $pdo->prepare(
                'SELECT id FROM tools WHERE id = :tool_id AND user_id = :user_id LIMIT 1'
            );
            $ownershipStmt->execute([
                'tool_id' => $toolId,
                'user_id' => $userId,
            ]);

            if (!is_array($ownershipStmt->fetch())) {
                $pdo->rollBack();
                return false;
            }

            $deleteStmt = $pdo->prepare('DELETE FROM tool_images WHERE tool_id = :tool_id');
            $deleteStmt->execute(['tool_id' => $toolId]);

            if ($imagePaths !== []) {
                $insertStmt = $pdo->prepare(
                    'INSERT INTO tool_images (tool_id, image_path, sort_order)
                     VALUES (:tool_id, :image_path, :sort_order)'
                );

                foreach ($imagePaths as $index => $imagePath) {
                    $insertStmt->execute([
                        'tool_id' => $toolId,
                        'image_path' => $imagePath,
                        'sort_order' => $index,
                    ]);
                }
            }

            $pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    public static function findImagePathsByToolId(int $toolId): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'SELECT image_path
                 FROM tool_images
                 WHERE tool_id = :tool_id
                 ORDER BY sort_order ASC, id ASC'
            );
            $stmt->execute(['tool_id' => $toolId]);
            $rows = $stmt->fetchAll();
            if (!is_array($rows)) {
                return [];
            }

            $paths = [];
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['image_path']) && is_string($row['image_path'])) {
                    $paths[] = $row['image_path'];
                }
            }
            return $paths;
        } catch (PDOException $e) {
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    public static function delete(int $id, int $userId): bool
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'DELETE FROM tools WHERE id = :id AND user_id = :user_id'
            );
            $stmt->execute([
                'id' => $id,
                'user_id' => $userId,
            ]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    private static function normalizeToolRow(array $row): array
    {
        $imageUrls = $row['image_urls'] ?? [];
        if (is_string($imageUrls)) {
            $decoded = json_decode($imageUrls, true);
            $imageUrls = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($imageUrls)) {
            $imageUrls = [];
        }

        $row['image_urls'] = array_values(array_filter(
            $imageUrls,
            static fn ($item): bool => is_string($item) && $item !== ''
        ));

        return $row;
    }
}
