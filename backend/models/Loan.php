<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDOException;

final class Loan
{
    public const RULE_TOOL_UNAVAILABLE = 'tool_unavailable';
    public const RULE_NOT_OWNER = 'not_owner';

    public static function create(
        int $toolId,
        int $ownerId,
        string $borrowerName,
        ?string $borrowerPhone,
        string $returnDate,
        ?int $borrowerId = null
    ): ?array {
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            // Verifica propriedade e disponibilidade da ferramenta em uma unica query com lock
            $toolStmt = $pdo->prepare(
                'SELECT id, is_available FROM tools WHERE id = :tool_id AND user_id = :owner_id FOR UPDATE'
            );
            $toolStmt->execute(['tool_id' => $toolId, 'owner_id' => $ownerId]);
            $tool = $toolStmt->fetch();

            if (!is_array($tool)) {
                $pdo->rollBack();
                return ['rule_violation' => self::RULE_NOT_OWNER];
            }

            if (!((bool) $tool['is_available'])) {
                $pdo->rollBack();
                return ['rule_violation' => self::RULE_TOOL_UNAVAILABLE];
            }

            $loanStmt = $pdo->prepare(
                "INSERT INTO loans (tool_id, borrower_id, borrower_name, borrower_phone, status, return_date)
                 VALUES (:tool_id, :borrower_id, :borrower_name, :borrower_phone, 'active', :return_date)
                 RETURNING id, tool_id, borrower_id, borrower_name, borrower_phone, status, loan_date, return_date, created_at, updated_at"
            );
            $loanStmt->execute([
                'tool_id'        => $toolId,
                'borrower_id'    => $borrowerId,
                'borrower_name'  => $borrowerName,
                'borrower_phone' => $borrowerPhone,
                'return_date'    => $returnDate,
            ]);
            $loan = $loanStmt->fetch();

            if (!is_array($loan)) {
                $pdo->rollBack();
                return null;
            }

            $toolUpdateStmt = $pdo->prepare(
                'UPDATE tools SET is_available = false, updated_at = CURRENT_TIMESTAMP WHERE id = :tool_id'
            );
            $toolUpdateStmt->execute(['tool_id' => $toolId]);

            if ($toolUpdateStmt->rowCount() !== 1) {
                $pdo->rollBack();
                return null;
            }

            $pdo->commit();
            return self::normalizeRow($loan);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    public static function findActiveByOwner(int $ownerId): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                "SELECT l.id,
                        l.tool_id,
                        l.borrower_id,
                        l.borrower_name,
                        l.borrower_phone,
                        l.status,
                        l.loan_date,
                        l.return_date,
                        t.name AS tool_name,
                        t.description AS tool_description,
                        t.user_id AS owner_id
                 FROM loans l
                 JOIN tools t ON t.id = l.tool_id
                 WHERE t.user_id = :owner_id
                   AND l.status = 'active'
                 ORDER BY l.loan_date DESC"
            );
            $stmt->execute(['owner_id' => $ownerId]);
            $loans = $stmt->fetchAll();

            if (!is_array($loans)) {
                return [];
            }

            return array_map([self::class, 'normalizeRow'], $loans);
        } catch (PDOException $e) {
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    public static function returnLoan(int $loanId, int $ownerId): bool
    {
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            // Verifica que o emprestimo pertence a uma ferramenta do owner, com lock na linha do loan
            $loanStmt = $pdo->prepare(
                "SELECT l.id, l.tool_id
                 FROM loans l
                 JOIN tools t ON t.id = l.tool_id
                 WHERE l.id = :loan_id
                   AND t.user_id = :owner_id
                   AND l.status = 'active'
                 FOR UPDATE OF l"
            );
            $loanStmt->execute([
                'loan_id'  => $loanId,
                'owner_id' => $ownerId,
            ]);
            $loan = $loanStmt->fetch();

            if (!is_array($loan)) {
                $pdo->rollBack();
                return false;
            }

            // return_date nao e sobrescrito: mantém a data prevista de devolucao original
            $updateLoanStmt = $pdo->prepare(
                "UPDATE loans
                 SET status = 'returned',
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :loan_id
                   AND status = 'active'"
            );
            $updateLoanStmt->execute(['loan_id' => $loanId]);

            if ($updateLoanStmt->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }

            $toolUpdateStmt = $pdo->prepare(
                'UPDATE tools SET is_available = true, updated_at = CURRENT_TIMESTAMP WHERE id = :tool_id'
            );
            $toolUpdateStmt->execute(['tool_id' => (int) $loan['tool_id']]);

            if ($toolUpdateStmt->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
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

    public static function history(int $ownerId, array $filters): array
    {
        try {
            $pdo = Database::getConnection();

            $sql = 'SELECT loan_id, tool_name, tool_description, borrower_name, borrower_phone, status, loan_date, return_date
                    FROM loan_history
                    WHERE owner_id = :owner_id';
            $params = ['owner_id' => $ownerId];

            if (isset($filters['status']) && $filters['status'] !== '') {
                $sql .= ' AND status = :status';
                $params['status'] = (string) $filters['status'];
            }

            if (isset($filters['date_from']) && $filters['date_from'] !== '') {
                $sql .= ' AND loan_date >= :date_from';
                $params['date_from'] = (string) $filters['date_from'];
            }

            if (isset($filters['date_to']) && $filters['date_to'] !== '') {
                $sql .= ' AND loan_date <= :date_to';
                $params['date_to'] = (string) $filters['date_to'];
            }

            if (isset($filters['tool']) && $filters['tool'] !== '') {
                $sql .= ' AND tool_name ILIKE :tool';
                $params['tool'] = '%' . (string) $filters['tool'] . '%';
            }

            $sql .= ' ORDER BY loan_date DESC';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $history = $stmt->fetchAll();

            if (!is_array($history)) {
                return [];
            }

            return array_map([self::class, 'normalizeRow'], $history);
        } catch (PDOException $e) {
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    private static function normalizeRow(array $row): array
    {
        $isActive = ($row['status'] ?? '') === 'active';
        $returnDate = $row['return_date'] ?? '';
        $row['is_overdue'] = $isActive && $returnDate !== '' && strtotime((string) $returnDate) < time();
        return $row;
    }
}
