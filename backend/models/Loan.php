<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDOException;

final class Loan
{
    public const RULE_TOOL_UNAVAILABLE = 'tool_unavailable';
    public const RULE_BORROW_LIMIT_REACHED = 'borrow_limit_reached';

    public static function countActive(int $borrowerId): int
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                "SELECT COUNT(*)::int AS total
                 FROM loans
                 WHERE borrower_id = :borrower_id
                   AND status = 'active'"
            );
            $stmt->execute(['borrower_id' => $borrowerId]);
            $row = $stmt->fetch();

            return is_array($row) ? (int) ($row['total'] ?? 0) : 0;
        } catch (PDOException $e) {
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    public static function create(int $toolId, int $borrowerId): ?array
    {
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $borrowerLockStmt = $pdo->prepare(
                'SELECT id FROM users WHERE id = :borrower_id FOR UPDATE'
            );
            $borrowerLockStmt->execute(['borrower_id' => $borrowerId]);
            $borrower = $borrowerLockStmt->fetch();
            if (!is_array($borrower)) {
                $pdo->rollBack();
                return [ 'rule_violation' => self::RULE_BORROW_LIMIT_REACHED ];
            }

            $toolStmt = $pdo->prepare(
                'SELECT id, is_available
                 FROM tools
                 WHERE id = :tool_id
                 FOR UPDATE'
            );
            $toolStmt->execute(['tool_id' => $toolId]);
            $tool = $toolStmt->fetch();
            if (!is_array($tool) || !((bool) $tool['is_available'])) {
                $pdo->rollBack();
                return ['rule_violation' => self::RULE_TOOL_UNAVAILABLE];
            }

            $activeCountStmt = $pdo->prepare(
                "SELECT COUNT(*)::int AS total
                 FROM loans
                 WHERE borrower_id = :borrower_id
                   AND status = 'active'"
            );
            $activeCountStmt->execute(['borrower_id' => $borrowerId]);
            $activeCountRow = $activeCountStmt->fetch();
            $activeCount = is_array($activeCountRow) ? (int) ($activeCountRow['total'] ?? 0) : 0;
            if ($activeCount >= 3) {
                $pdo->rollBack();
                return ['rule_violation' => self::RULE_BORROW_LIMIT_REACHED];
            }

            $loanStmt = $pdo->prepare(
                "INSERT INTO loans (tool_id, borrower_id, status)
                 VALUES (:tool_id, :borrower_id, 'active')
                 RETURNING id, tool_id, borrower_id, status, loan_date, return_date, created_at, updated_at"
            );
            $loanStmt->execute([
                'tool_id' => $toolId,
                'borrower_id' => $borrowerId,
            ]);
            $loan = $loanStmt->fetch();
            if (!is_array($loan)) {
                $pdo->rollBack();
                return null;
            }

            $toolUpdateStmt = $pdo->prepare(
                'UPDATE tools
                 SET is_available = false,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :tool_id'
            );
            $toolUpdateStmt->execute(['tool_id' => $toolId]);
            if ($toolUpdateStmt->rowCount() !== 1) {
                $pdo->rollBack();
                return null;
            }

            $pdo->commit();
            return $loan;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    public static function findByBorrower(int $borrowerId): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                "SELECT l.id,
                        l.tool_id,
                        l.borrower_id,
                        l.status,
                        l.loan_date,
                        l.return_date,
                        t.name AS tool_name,
                        t.description AS tool_description,
                        t.user_id AS owner_id,
                        u.name AS owner_name
                 FROM loans l
                 JOIN tools t ON t.id = l.tool_id
                 JOIN users u ON u.id = t.user_id
                 WHERE l.borrower_id = :borrower_id
                   AND l.status = 'active'
                 ORDER BY l.loan_date DESC"
            );
            $stmt->execute(['borrower_id' => $borrowerId]);
            $loans = $stmt->fetchAll();

            return is_array($loans) ? $loans : [];
        } catch (PDOException $e) {
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    public static function returnLoan(int $loanId, int $borrowerId): bool
    {
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $loanStmt = $pdo->prepare(
                "SELECT id, tool_id
                 FROM loans
                 WHERE id = :loan_id
                   AND borrower_id = :borrower_id
                   AND status = 'active'
                 FOR UPDATE"
            );
            $loanStmt->execute([
                'loan_id' => $loanId,
                'borrower_id' => $borrowerId,
            ]);
            $loan = $loanStmt->fetch();
            if (!is_array($loan)) {
                $pdo->rollBack();
                return false;
            }

            $updateLoanStmt = $pdo->prepare(
                "UPDATE loans
                 SET status = 'returned',
                     return_date = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :loan_id
                   AND borrower_id = :borrower_id
                   AND status = 'active'"
            );
            $updateLoanStmt->execute([
                'loan_id' => $loanId,
                'borrower_id' => $borrowerId,
            ]);
            if ($updateLoanStmt->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }

            $toolUpdateStmt = $pdo->prepare(
                'UPDATE tools
                 SET is_available = true,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :tool_id'
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

    public static function history(int $borrowerId, array $filters): array
    {
        try {
            $pdo = Database::getConnection();
            $borrowerEmail = self::findBorrowerEmail($pdo, $borrowerId);
            if ($borrowerEmail === null) {
                return [];
            }

            $sql = 'SELECT loan_id, tool_name, tool_description, borrower_name, borrower_email, status, loan_date, return_date
                    FROM loan_history
                    WHERE borrower_email = :borrower_email';
            $params = ['borrower_email' => $borrowerEmail];

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

            return is_array($history) ? $history : [];
        } catch (PDOException $e) {
            throw new \RuntimeException('Erro interno', 0, $e);
        }
    }

    private static function findBorrowerEmail(\PDO $pdo, int $borrowerId): ?string
    {
        $stmt = $pdo->prepare(
            'SELECT email
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $borrowerId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        $email = (string) ($row['email'] ?? '');
        return $email === '' ? null : $email;
    }
}
