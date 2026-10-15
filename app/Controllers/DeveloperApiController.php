<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Models\DeveloperFeedbackModel;
use App\Services\FeedbackUploadService;
use JsonException;
use PDO;

final class DeveloperApiController
{
    public function index(): void
    {
        if (!$this->prepareRequest() || !$this->authorize()) {
            return;
        }

        $this->sendJson([
            'version' => 'v1',
            'scope' => 'developer_feedback',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/v1/feedback', 'description' => 'Daftar feedback'],
                ['method' => 'GET', 'path' => '/api/v1/feedback/{id}', 'description' => 'Detail feedback'],
                ['method' => 'GET', 'path' => '/api/v1/feedback/{id}/photo?index=0', 'description' => 'Lampiran privat berdasarkan indeks'],
                ['method' => 'PATCH', 'path' => '/api/v1/feedback/{id}', 'description' => 'Ubah status tindak lanjut'],
                ['method' => 'DELETE', 'path' => '/api/v1/feedback/{id}', 'description' => 'Hapus feedback'],
            ],
        ]);
    }

    public function options(): void
    {
        if (!$this->prepareRequest()) {
            return;
        }

        header('Access-Control-Allow-Methods: GET, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        header('Access-Control-Max-Age: 600');
        http_response_code(204);
    }

    public function listFeedback(): void
    {
        if (!$this->prepareRequest() || !$this->authorize()) {
            return;
        }

        $limit = filter_var($_GET['limit'] ?? 50, FILTER_VALIDATE_INT);
        $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);
        if ($limit === false || $offset === false || $offset < 0) {
            $this->sendJson(['error' => 'invalid_pagination'], 422);
        }
        $status = isset($_GET['status']) && is_string($_GET['status']) ? strtoupper($_GET['status']) : null;
        if ($status !== null && !in_array($status, ['NEW', 'REVIEWED', 'DONE'], true)) {
            $this->sendJson(['error' => 'invalid_status'], 422);
        }

        $items = array_map($this->apiItem(...), $this->feedbackModel()->recent($limit, $offset, $status));
        $this->sendJson([
            'data' => $items,
            'pagination' => ['limit' => min(max(1, $limit), 100), 'offset' => $offset],
        ]);
    }

    public function show(string $id): void
    {
        if (!$this->prepareRequest() || !$this->authorize()) {
            return;
        }

        $feedbackId = $this->parseId($id);
        if ($feedbackId === null) {
            $this->sendJson(['error' => 'invalid_id'], 400);
        }
        $feedback = $this->feedbackModel()->find($feedbackId);
        if ($feedback === null) {
            $this->sendJson(['error' => 'feedback_not_found'], 404);
        }

        $this->sendJson(['data' => $this->apiItem($feedback)]);
    }

    public function photo(string $id): void
    {
        if (!$this->prepareRequest() || !$this->authorize()) {
            return;
        }

        $feedbackId = $this->parseId($id);
        if ($feedbackId === null) {
            $this->sendJson(['error' => 'invalid_id'], 400);
        }
        $feedback = $this->feedbackModel()->find($feedbackId);
        $photoIndex = filter_var($_GET['index'] ?? 0, FILTER_VALIDATE_INT);
        if ($photoIndex === false || $photoIndex < 0 || $photoIndex >= FeedbackUploadService::MAX_FILES) {
            $this->sendJson(['error' => 'invalid_photo_index'], 400);
        }
        $photos = is_array($feedback['photos'] ?? null) ? $feedback['photos'] : [];
        $filename = is_string($photos[$photoIndex] ?? null) ? $photos[$photoIndex] : '';
        $uploads = new FeedbackUploadService();
        $path = $uploads->pathFor($filename);
        $mime = $uploads->mimeFor($filename);
        if ($feedback === null || $path === null || $mime === null || !is_file($path)) {
            $this->sendJson(['error' => 'photo_not_found'], 404);
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: inline; filename="feedback-' . $feedbackId . '.' . pathinfo($filename, PATHINFO_EXTENSION) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');
        readfile($path);
        exit;
    }

    public function update(string $id): void
    {
        if (!$this->prepareRequest() || !$this->authorize()) {
            return;
        }

        $feedbackId = $this->parseId($id);
        if ($feedbackId === null) {
            $this->sendJson(['error' => 'invalid_id'], 400);
        }
        try {
            $payload = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->sendJson(['error' => 'invalid_json'], 400);
        }
        $status = is_array($payload) && is_string($payload['review_status'] ?? null)
            ? strtoupper($payload['review_status'])
            : '';
        if (!in_array($status, ['NEW', 'REVIEWED', 'DONE'], true)) {
            $this->sendJson(['error' => 'invalid_review_status'], 422);
        }

        $model = $this->feedbackModel();
        if ($model->find($feedbackId) === null) {
            $this->sendJson(['error' => 'feedback_not_found'], 404);
        }
        $model->updateReviewStatus($feedbackId, $status);
        $this->recordApiAudit($feedbackId, 'DEVELOPER_API_FEEDBACK_UPDATED', ['review_status' => $status]);
        $this->sendJson(['data' => $this->apiItem($model->find($feedbackId) ?? [])]);
    }

    public function delete(string $id): void
    {
        if (!$this->prepareRequest() || !$this->authorize()) {
            return;
        }

        $feedbackId = $this->parseId($id);
        if ($feedbackId === null) {
            $this->sendJson(['error' => 'invalid_id'], 400);
        }
        $model = $this->feedbackModel();
        $feedback = $model->find($feedbackId);
        if ($feedback === null) {
            $this->sendJson(['error' => 'feedback_not_found'], 404);
        }
        if (!$model->delete($feedbackId)) {
            $this->sendJson(['error' => 'feedback_delete_failed'], 409);
        }

        (new FeedbackUploadService())->remove($feedback['photos'] ?? ($feedback['photo_filename'] ?? null));
        $this->recordApiAudit($feedbackId, 'DEVELOPER_API_FEEDBACK_DELETED');
        $this->sendJson(['data' => ['id' => $feedbackId, 'deleted' => true]]);
    }

    private function prepareRequest(): bool
    {
        header('Cache-Control: no-store, private');
        header('Vary: Origin');
        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin === '') {
            return true;
        }

        if (!in_array($origin, $this->allowedOrigins(), true)) {
            $this->sendJson(['error' => 'origin_not_allowed'], 403);
        }
        header('Access-Control-Allow-Origin: ' . $origin);
        return true;
    }

    private function allowedOrigins(): array
    {
        $configured = trim((string) (app_env('DEVELOPER_API_ORIGINS') ?: ''));
        if ($configured === '') {
            return [];
        }

        $origins = [];
        foreach (explode(',', $configured) as $candidate) {
            $candidate = trim($candidate);
            $parts = parse_url($candidate);
            if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
                || !in_array(strtolower($parts['scheme']), ['https', 'http'], true)
                || array_intersect(['user', 'pass', 'path', 'query', 'fragment'], array_keys($parts)) !== []
                || (strtolower($parts['scheme']) === 'http'
                    && !in_array(strtolower($parts['host']), ['localhost', '127.0.0.1'], true))) {
                continue;
            }
            $origins[] = $candidate;
        }

        return $origins;
    }

    private function authorize(): bool
    {
        $expected = trim((string) (app_env('DEVELOPER_API_TOKEN') ?: ''));
        if (strlen($expected) < 40) {
            $this->sendJson(['error' => 'developer_api_not_configured'], 503);
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authorization = (string) (
            $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? $headers['Authorization']
            ?? $headers['authorization']
            ?? ''
        );
        if (preg_match('/^Bearer\s+([^\s]+)$/i', trim($authorization), $matches) !== 1
            || !hash_equals($expected, $matches[1])) {
            $this->sendJson(['error' => 'unauthorized'], 401);
        }

        return true;
    }

    private function feedbackModel(): DeveloperFeedbackModel
    {
        return new DeveloperFeedbackModel(Connection::get());
    }

    private function apiItem(array $feedback): array
    {
        $id = (int) ($feedback['id'] ?? 0);
        $photos = is_array($feedback['photos'] ?? null) ? array_values($feedback['photos']) : [];
        unset($feedback['photo_filename'], $feedback['photos'], $feedback['photos_json']);
        $feedback['has_photo'] = $photos !== [];
        $feedback['photos'] = array_map(
            static fn (int $index): array => [
                'index' => $index,
                'url' => \url('api/v1/feedback/' . $id . '/photo', ['index' => $index]),
            ],
            array_keys($photos)
        );
        $feedback['photo_url'] = $feedback['photos'][0]['url'] ?? null;

        return $feedback;
    }

    private function recordApiAudit(int $feedbackId, string $action, array $details = []): void
    {
        $statement = Connection::get()->prepare(
            'INSERT INTO audit_logs
                (user_id, actor_username, auth_session_id, action, module, record_id, details, ip_address, user_agent, created_at)
             VALUES
                (NULL, :actor, NULL, :action, :module, :record_id, :details, :ip, :user_agent, UTC_TIMESTAMP())'
        );
        $statement->execute([
            'actor' => 'developer-api',
            'action' => $action,
            'module' => 'developer_feedback',
            'record_id' => $feedbackId,
            'details' => $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR),
            'ip' => \App\Middleware\AuthMiddleware::clientIp(),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ]);
    }

    private function parseId(string $id): ?int
    {
        if (preg_match('/^[1-9][0-9]{0,18}$/D', $id) !== 1) {
            return null;
        }
        $value = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $value === false ? null : $value;
    }

    private function sendJson(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        try {
            echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            echo '{"error":"response_encoding_failed"}';
        }
        exit;
    }
}