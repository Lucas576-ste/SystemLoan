<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDOException;

final class Notification
{
    public static function createForLoan(
        int $userId,
        int $loanId,
        string $borrowerName,
        string $returnDate
    ): void {
        try {
            $pdo = Database::getConnection();

            $toolStmt = $pdo->prepare(
                'SELECT t.name FROM loans l JOIN tools t ON t.id = l.tool_id WHERE l.id = :loan_id LIMIT 1'
            );
            $toolStmt->execute(['loan_id' => $loanId]);
            $toolRow = $toolStmt->fetch();
            $toolName = is_array($toolRow) ? (string) $toolRow['name'] : 'ferramenta';

            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $returnDate)
                ?: \DateTimeImmutable::createFromFormat('Y-m-d', substr($returnDate, 0, 10));
            $returnFormatted = $dt instanceof \DateTimeImmutable
                ? $dt->format('d/m/Y')
                : $returnDate;

            $message = "Empréstimo de \"{$toolName}\" registrado para {$borrowerName}. Devolução prevista: {$returnFormatted}.";

            $stmt = $pdo->prepare(
                "INSERT INTO notifications (user_id, loan_id, type, message)
                 VALUES (:user_id, :loan_id, 'loan_created', :message)"
            );
            $stmt->execute([
                'user_id' => $userId,
                'loan_id' => $loanId,
                'message' => $message,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    public static function findByUser(int $userId): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'SELECT id, user_id, loan_id, type, message, is_read, created_at
                 FROM notifications
                 WHERE user_id = :user_id
                 ORDER BY created_at DESC
                 LIMIT 100'
            );
            $stmt->execute(['user_id' => $userId]);
            $rows = $stmt->fetchAll();

            if (!is_array($rows)) {
                return [];
            }

            return array_map([self::class, 'normalizeRow'], $rows);
        } catch (PDOException $e) {
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    public static function markRead(int $id, int $userId): bool
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'UPDATE notifications SET is_read = true WHERE id = :id AND user_id = :user_id'
            );
            $stmt->execute(['id' => $id, 'user_id' => $userId]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    private static function normalizeRow(array $row): array
    {
        // PDO/pgsql retorna booleanos como 't'/'f'; normaliza para bool PHP
        $raw = $row['is_read'] ?? false;
        $row['is_read'] = $raw === true || $raw === 't' || $raw === '1' || $raw === 1;
        return $row;
    }
}
