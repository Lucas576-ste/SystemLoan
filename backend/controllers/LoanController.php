<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Loan;
use App\Models\Notification;
use Throwable;

final class LoanController
{
    private string $authMiddlewareClass;

    public function __construct(string $authMiddlewareClass = AuthMiddleware::class)
    {
        $this->authMiddlewareClass = $authMiddlewareClass;
    }

    public function store(): void
    {
        $payload = $this->requirePayload();
        if ($payload === null) {
            return;
        }

        $input = self::getJsonInput();
        if ($input === null) {
            self::respond(400, ['error' => 'JSON inválido']);
            return;
        }

        $toolIdRaw = $input['tool_id'] ?? null;
        if (!is_numeric($toolIdRaw) || (int) $toolIdRaw <= 0) {
            self::respond(400, ['error' => 'tool_id é obrigatório e deve ser numérico']);
            return;
        }

        $borrowerName = trim((string) ($input['borrower_name'] ?? ''));
        if ($borrowerName === '') {
            self::respond(400, ['error' => 'borrower_name é obrigatório']);
            return;
        }
        if (mb_strlen($borrowerName) > 255) {
            self::respond(400, ['error' => 'borrower_name deve ter no máximo 255 caracteres']);
            return;
        }

        $borrowerPhone = trim((string) ($input['borrower_phone'] ?? ''));
        $borrowerPhone = $borrowerPhone === '' ? null : $borrowerPhone;
        if ($borrowerPhone !== null && mb_strlen($borrowerPhone) > 20) {
            self::respond(400, ['error' => 'borrower_phone deve ter no máximo 20 caracteres']);
            return;
        }

        $returnDateRaw = trim((string) ($input['return_date'] ?? ''));
        if ($returnDateRaw === '') {
            self::respond(400, ['error' => 'return_date é obrigatório']);
            return;
        }
        $returnDate = self::normalizeDateFilter($returnDateRaw, false);
        if ($returnDate === null) {
            self::respond(400, ['error' => 'Formato de return_date inválido. Use YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS']);
            return;
        }

        try {
            $loan = Loan::create(
                (int) $toolIdRaw,
                (int) $payload->sub,
                $borrowerName,
                $borrowerPhone,
                $returnDate
            );

            if ($loan === null) {
                self::respond(500, ['error' => 'Erro interno']);
                return;
            }

            $ruleViolation = (string) ($loan['rule_violation'] ?? '');
            if ($ruleViolation === Loan::RULE_TOOL_UNAVAILABLE) {
                self::respond(422, ['error' => 'Ferramenta indisponível']);
                return;
            }

            if ($ruleViolation === Loan::RULE_NOT_OWNER) {
                self::respond(403, ['error' => 'Ferramenta não encontrada ou você não é o proprietário']);
                return;
            }

            // Notificacao best-effort: falha aqui nao reverte o emprestimo ja confirmado
            try {
                Notification::createForLoan(
                    (int) $payload->sub,
                    (int) $loan['id'],
                    $borrowerName,
                    $returnDate
                );
            } catch (Throwable) {
                // Falha silenciosa
            }

            self::respond(201, $loan);
        } catch (Throwable) {
            self::respond(500, ['error' => 'Erro interno']);
        }
    }

    public function myLoans(): void
    {
        $payload = $this->requirePayload();
        if ($payload === null) {
            return;
        }

        try {
            self::respond(200, Loan::findActiveByOwner((int) $payload->sub));
        } catch (Throwable) {
            self::respond(500, ['error' => 'Erro interno']);
        }
    }

    public function returnLoan(int $loanId): void
    {
        $payload = $this->requirePayload();
        if ($payload === null) {
            return;
        }

        try {
            $returned = Loan::returnLoan($loanId, (int) $payload->sub);
            if (!$returned) {
                self::respond(403, ['error' => 'Empréstimo não encontrado ou sem permissão']);
                return;
            }

            self::respond(200, ['message' => 'Ferramenta devolvida com sucesso']);
        } catch (Throwable) {
            self::respond(500, ['error' => 'Erro interno']);
        }
    }

    public function history(): void
    {
        $payload = $this->requirePayload();
        if ($payload === null) {
            return;
        }

        $status = trim((string) ($_GET['status'] ?? ''));
        $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo = trim((string) ($_GET['date_to'] ?? ''));
        $tool = trim((string) ($_GET['tool'] ?? ''));

        if ($status !== '' && !in_array($status, ['active', 'returned'], true)) {
            self::respond(400, ['error' => 'Status inválido. Use active ou returned']);
            return;
        }

        $normalizedDateFrom = self::normalizeDateFilter($dateFrom, false);
        if ($normalizedDateFrom === null && $dateFrom !== '') {
            self::respond(400, ['error' => 'Formato de data inválido']);
            return;
        }

        $normalizedDateTo = self::normalizeDateFilter($dateTo, true);
        if ($normalizedDateTo === null && $dateTo !== '') {
            self::respond(400, ['error' => 'Formato de data inválido']);
            return;
        }

        if ($normalizedDateFrom !== null && $normalizedDateTo !== null && $normalizedDateFrom > $normalizedDateTo) {
            self::respond(400, ['error' => 'Intervalo de datas inválido']);
            return;
        }

        $filters = [
            'status' => $status,
            'date_from' => $normalizedDateFrom ?? '',
            'date_to' => $normalizedDateTo ?? '',
            'tool' => $tool,
        ];

        try {
            self::respond(200, Loan::history((int) $payload->sub, $filters));
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

    private static function normalizeDateFilter(string $value, bool $isDateTo): ?string
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            $dateOnly = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
            $dateOnlyErrors = \DateTimeImmutable::getLastErrors();
            $hasDateOnlyErrors = is_array($dateOnlyErrors)
                && (($dateOnlyErrors['warning_count'] ?? 0) > 0 || ($dateOnlyErrors['error_count'] ?? 0) > 0);
            if ($dateOnly === false || $hasDateOnlyErrors || $dateOnly->format('Y-m-d') !== $value) {
                return null;
            }

            return $isDateTo ? $value . ' 23:59:59' : $value . ' 00:00:00';
        }

        $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
        $dateTimeErrors = \DateTimeImmutable::getLastErrors();
        $hasDateTimeErrors = is_array($dateTimeErrors)
            && (($dateTimeErrors['warning_count'] ?? 0) > 0 || ($dateTimeErrors['error_count'] ?? 0) > 0);
        if ($dateTime === false || $hasDateTimeErrors || $dateTime->format('Y-m-d H:i:s') !== $value) {
            return null;
        }

        return $dateTime->format('Y-m-d H:i:s');
    }

    private static function respond(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        echo json_encode($payload);
    }
}
