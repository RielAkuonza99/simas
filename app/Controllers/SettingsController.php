<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Models\ActivityModel;
use App\Models\SystemMetricsModel;
use App\Security\AuditLogger;
use RuntimeException;

final class SettingsController
{
    public function index(): void
    {
        AuthMiddleware::requireLogin();
        $user = currentUser();
        $isAdmin = ($user['role'] ?? '') === 'admin';
        $pdo = Connection::get();
        $metrics = null;
        $metricsUnavailable = false;
        if ($isAdmin) {
            try {
                $metrics = (new SystemMetricsModel($pdo))->dashboard();
            } catch (\Throwable $error) {
                error_log('Settings dashboard metrics unavailable: ' . $error->getMessage());
                $metricsUnavailable = true;
            }
        }
        $activity = new ActivityModel($pdo);
        $sessions = $activity->sessions(
            (int) $user['id'],
            $isAdmin,
            (int) ($_GET['sessions_page'] ?? 1)
        );
        $logs = $activity->auditLogs(
            (int) $user['id'],
            $isAdmin,
            (int) ($_GET['logs_page'] ?? 1)
        );
        $notice = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        $appLogPath = APP_ROOT . '/storage/logs/app.log';
        $appLogBytes = is_file($appLogPath) ? (int) filesize($appLogPath) : 0;
        $updates = json_decode(
            (string) file_get_contents(APP_ROOT . '/config/updates.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        render('settings/index', [
            'pageTitle' => 'Pengaturan',
            'profile' => $user,
            'isAdmin' => $isAdmin,
            'metrics' => $metrics,
            'metricsUnavailable' => $metricsUnavailable,
            'sessions' => $sessions,
            'logs' => $logs,
            'notice' => $notice,
            'appLogBytes' => $appLogBytes,
            'updates' => $updates,
        ]);
    }

    public function liveMetrics(): void
    {
        AuthMiddleware::requireAdmin();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');

        try {
            $metrics = (new SystemMetricsModel(Connection::get()))->dashboard();
            echo json_encode([
                'current' => $metrics['current'],
                'queue' => $metrics['queue'],
            ], JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) {
            error_log('Live developer metrics unavailable: ' . $error->getMessage());
            http_response_code(503);
            echo json_encode(['error' => 'metrics_unavailable']);
        }
    }

    public function clearAppLog(): void
    {
        AuthMiddleware::requireAdmin();
        requirePost();
        validateCsrf();

        $path = APP_ROOT . '/storage/logs/app.log';
        $handle = @fopen($path, 'c+');
        $bytesCleared = 0;
        $cleared = false;
        if ($handle !== false) {
            if (flock($handle, LOCK_EX)) {
                $metadata = fstat($handle);
                $bytesCleared = (int) ($metadata['size'] ?? 0);
                $cleared = ftruncate($handle, 0) && fflush($handle);
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }

        if ($cleared) {
            (new AuditLogger(Connection::get()))->record(
                'APP_LOG_CLEARED',
                'system',
                null,
                (int) currentUser()['id'],
                null,
                ['bytes_cleared' => $bytesCleared]
            );
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Log aplikasi berhasil dibersihkan. Change log tetap tersimpan.'];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Log aplikasi tidak dapat dibersihkan. Periksa hak akses penyimpanan.'];
        }

        header('Location: ' . url('pengaturan') . '#developer-tools', true, 303);
        exit;
    }
}
