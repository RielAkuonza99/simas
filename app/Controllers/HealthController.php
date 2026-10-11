<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;

final class HealthController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');

        try {
            $pdo = Connection::get();
            $pdo->query('SELECT 1');

            echo json_encode([
                'status' => 'ok',
                'environment' => APP_ENVIRONMENT,
                'database' => 'connected',
                'base_url' => APP_BASE_URL,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            return;
        } catch (\Throwable $error) {
            http_response_code(503);
            echo json_encode([
                'status' => 'error',
                'environment' => APP_ENVIRONMENT,
                'database' => 'unavailable',
                'message' => $error->getMessage(),
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        }
    }
}
