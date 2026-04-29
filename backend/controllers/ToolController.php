<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Tool;
use Throwable;

final class ToolController
{
    private const MAX_IMAGES = 4;
    private const MAX_FILE_SIZE_BYTES = 5_242_880; // 5MB
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

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
            http_response_code(200);
            echo json_encode(Tool::findAll());
        } catch (Throwable) {
            self::respond(500, ['error' => 'Erro interno']);
        }
    }

    public function myTools(): void
    {
        $payload = $this->requirePayload();
        if ($payload === null) {
            return;
        }

        try {
            $userId = (int) $payload->sub;
            http_response_code(200);
            echo json_encode(Tool::findByUser($userId));
        } catch (Throwable) {
            self::respond(500, ['error' => 'Erro interno']);
        }
    }

    public function store(): void
    {
        $payload = $this->requirePayload();
        if ($payload === null) {
            return;
        }

        $input = self::getRequestInput();
        if ($input === null) {
            self::respond(400, ['error' => 'Dados inválidos']);
            return;
        }

        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));

        if ($name === '') {
            self::respond(400, ['error' => 'Nome da ferramenta é obrigatório']);
            return;
        }

        try {
            $uploadedImages = $this->extractUploadedImages();
            if (is_string($uploadedImages)) {
                self::respond(400, ['error' => $uploadedImages]);
                return;
            }

            $tool = Tool::create((int) $payload->sub, $name, $description);
            if ($tool === null) {
                self::respond(500, ['error' => 'Erro interno']);
                return;
            }

            if ($uploadedImages !== []) {
                $savedPaths = $this->storeUploadedImages((int) $tool['id'], $uploadedImages);
                if (is_string($savedPaths)) {
                    Tool::delete((int) $tool['id'], (int) $payload->sub);
                    self::respond(400, ['error' => $savedPaths]);
                    return;
                }

                $saved = Tool::replaceImages((int) $tool['id'], (int) $payload->sub, $savedPaths);
                if (!$saved) {
                    $this->deleteLocalFiles($savedPaths);
                    Tool::delete((int) $tool['id'], (int) $payload->sub);
                    self::respond(500, ['error' => 'Não foi possível salvar as imagens']);
                    return;
                }
            }

            $createdTool = Tool::findById((int) $tool['id']);
            self::respond(201, $createdTool ?? $tool);
        } catch (Throwable) {
            self::respond(500, ['error' => 'Erro interno']);
        }
    }

    public function update(int $id): void
    {
        $payload = $this->requirePayload();
        if ($payload === null) {
            return;
        }

        $input = self::getRequestInput();
        if ($input === null) {
            self::respond(400, ['error' => 'Dados inválidos']);
            return;
        }

        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));

        if ($name === '') {
            self::respond(400, ['error' => 'Nome da ferramenta é obrigatório']);
            return;
        }

        try {
            $uploadedImages = $this->extractUploadedImages();
            if (is_string($uploadedImages)) {
                self::respond(400, ['error' => $uploadedImages]);
                return;
            }

            $oldImagePaths = Tool::findImagePathsByToolId($id);
            $tool = Tool::update($id, (int) $payload->sub, $name, $description);
            if ($tool === null) {
                self::respond(403, ['error' => 'Ferramenta não encontrada ou sem permissão']);
                return;
            }

            if ($uploadedImages !== []) {
                $savedPaths = $this->storeUploadedImages($id, $uploadedImages);
                if (is_string($savedPaths)) {
                    self::respond(400, ['error' => $savedPaths]);
                    return;
                }

                $saved = Tool::replaceImages($id, (int) $payload->sub, $savedPaths);
                if (!$saved) {
                    $this->deleteLocalFiles($savedPaths);
                    self::respond(500, ['error' => 'Não foi possível atualizar as imagens']);
                    return;
                }

                $this->deleteLocalFiles($oldImagePaths);
                $tool = Tool::findById($id) ?? $tool;
            }

            self::respond(200, $tool);
        } catch (Throwable) {
            self::respond(500, ['error' => 'Erro interno']);
        }
    }

    public function destroy(int $id): void
    {
        $payload = $this->requirePayload();
        if ($payload === null) {
            return;
        }

        try {
            $imagePaths = Tool::findImagePathsByToolId($id);
            $deleted = Tool::delete($id, (int) $payload->sub);
            if (!$deleted) {
                self::respond(403, ['error' => 'Ferramenta não encontrada ou sem permissão']);
                return;
            }

            $this->deleteLocalFiles($imagePaths);
            self::respond(200, ['message' => 'Ferramenta excluída com sucesso']);
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

    private static function getRequestInput(): ?array
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_starts_with($contentType, 'multipart/form-data')) {
            return is_array($_POST) ? $_POST : [];
        }

        return self::getJsonInput();
    }

    /**
     * @return array<int, array{name:string,tmp_name:string,size:int,error:int}>|string
     */
    private function extractUploadedImages(): array|string
    {
        if (!isset($_FILES['images'])) {
            return [];
        }

        $raw = $_FILES['images'];
        if (!is_array($raw)) {
            return 'Formato de upload inválido.';
        }

        $names = $raw['name'] ?? null;
        $tmpNames = $raw['tmp_name'] ?? null;
        $sizes = $raw['size'] ?? null;
        $errors = $raw['error'] ?? null;

        if (!is_array($names) || !is_array($tmpNames) || !is_array($sizes) || !is_array($errors)) {
            return 'Formato de upload inválido.';
        }

        $files = [];
        foreach ($names as $index => $name) {
            $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name' => (string) $name,
                'tmp_name' => (string) ($tmpNames[$index] ?? ''),
                'size' => (int) ($sizes[$index] ?? 0),
                'error' => $error,
            ];
        }

        if (count($files) > self::MAX_IMAGES) {
            return 'Máximo de 4 imagens por ferramenta.';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return 'Falha no upload de uma das imagens.';
            }
            if ($file['size'] <= 0 || $file['size'] > self::MAX_FILE_SIZE_BYTES) {
                return 'Cada imagem deve ter até 5MB.';
            }
            if (!is_uploaded_file($file['tmp_name'])) {
                return 'Arquivo de imagem inválido.';
            }
            $mimeType = (string) ($finfo->file($file['tmp_name']) ?: '');
            if (!array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
                return 'Formato de imagem inválido. Use JPEG, PNG ou WEBP.';
            }
        }

        return $files;
    }

    /**
     * @param array<int, array{name:string,tmp_name:string,size:int,error:int}> $files
     * @return array<int, string>|string
     */
    private function storeUploadedImages(int $toolId, array $files): array|string
    {
        $uploadDir = dirname(__DIR__) . '/uploads/tools';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return 'Não foi possível preparar a pasta de upload.';
        }

        $savedPaths = [];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        foreach ($files as $index => $file) {
            $mimeType = (string) ($finfo->file($file['tmp_name']) ?: '');
            $extension = self::ALLOWED_MIME_TYPES[$mimeType] ?? null;
            if ($extension === null) {
                $this->deleteLocalFiles($savedPaths);
                return 'Formato de imagem inválido. Use JPEG, PNG ou WEBP.';
            }

            $fileName = sprintf(
                '%d_%d_%s.%s',
                $toolId,
                $index,
                bin2hex(random_bytes(8)),
                $extension
            );
            $destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $this->deleteLocalFiles($savedPaths);
                return 'Não foi possível salvar uma das imagens.';
            }

            $savedPaths[] = '/uploads/tools/' . $fileName;
        }

        return $savedPaths;
    }

    /**
     * @param array<int, string> $paths
     */
    private function deleteLocalFiles(array $paths): void
    {
        $baseDir = dirname(__DIR__);
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '' || !str_starts_with($path, '/uploads/tools/')) {
                continue;
            }

            $fullPath = $baseDir . $path;
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    private static function respond(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        echo json_encode($payload);
    }
}
