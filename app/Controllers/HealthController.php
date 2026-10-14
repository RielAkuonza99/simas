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
                'database' => 'connected',
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            return;
        } catch (\Throwable $error) {
            error_log('Health check failed: ' . $error->getMessage());
            http_response_code(503);
            echo json_encode([
                'status' => 'error',
                'database' => 'unavailable',
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        }
    }
}
