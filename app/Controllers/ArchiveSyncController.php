<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Models\ArchiveSyncModel;
use App\Security\AuditLogger;
use App\Services\ArchiveSyncService;
use App\Services\SupabaseArchiveClient;

final class ArchiveSyncController
{
    public function index(): void
    {
        AuthMiddleware::requireAdmin();
        $pdo = Connection::get();
        $queue = new ArchiveSyncModel($pdo);
        $provider = (new SupabaseArchiveClient())->previewMetadata();

        render('archive-sync/index', [
            'pageTitle' => 'Sinkronisasi Metadata',
            'provider' => $provider,
            'summary' => $queue->summary(),
            'events' => $queue->latest(),
            'previewUrl' => url('sinkronisasi/preview'),
            'notice' => $_SESSION['flash'] ?? null,
        ]);
        unset($_SESSION['flash']);
    }

    public function preview(): void
    {
        AuthMiddleware::requireAdmin();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');

        $provider = (new SupabaseArchiveClient())->previewMetadata();
        ob_start();
        render('archive-sync/metadata-preview', ['provider' => $provider]);
        $html = (string) ob_get_clean();

        echo json_encode([
            'status' => $provider['status'],
            'message' => $provider['message'],
            'count' => count($provider['items']),
            'html' => $html,
        ], JSON_THROW_ON_ERROR);
    }

    public function push(): void
    {
        AuthMiddleware::requireAdmin();
        requirePost();
        validateCsrf();

        $pdo = Connection::get();
        $queued = (new ArchiveSyncModel($pdo))->enqueueAllCurrent();
        (new AuditLogger($pdo))->record(
            'ARCHIVE_SYNC_PUSHED',
            'archive_sync',
            null,
            (int) currentUser()['id'],
            null,
            ['letters_queued' => $queued]
        );

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => $queued . ' surat baru atau berubah dimasukkan ke antrean. Tekan Sinkronkan sekarang untuk mengirimnya ke Supabase.',
        ];
        redirect('sinkronisasi');
    }

    public function processNext(): void
    {
        AuthMiddleware::requireAdmin();
        requirePost();
        validateCsrf();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');

        $pdo = Connection::get();
        $queue = new ArchiveSyncModel($pdo);
        $provider = new SupabaseArchiveClient();
        if (($_POST['force_snapshot'] ?? '') === '1') {
            $queued = $queue->enqueueAllCurrent();
            (new AuditLogger($pdo))->record(
                'ARCHIVE_SYNC_FORCED',
                'archive_sync',
                null,
                (int) currentUser()['id'],
                null,
                ['letters_queued' => $queued]
            );
        }
        if (!$provider->isConfigured()) {
            http_response_code(409);
            echo json_encode(['error' => 'provider_not_configured']);
            return;
        }

        try {
            $batch = (new ArchiveSyncService(
                $queue,
                $provider,
                new AuditLogger($pdo)
            ))->processBatch(1);
            $summary = $queue->summary();
            echo json_encode(['batch' => $batch, 'summary' => $summary], JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) {
            error_log('Manual archive sync failed: ' . $error->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'sync_batch_failed']);
        }
    }

    public function retry(): void
    {
        AuthMiddleware::requireAdmin();
        requirePost();
        validateCsrf();

        $id = filter_var($_POST['event_id'] ?? '', FILTER_VALIDATE_INT);
        if ($id === false || (int) $id < 1) {
            http_response_code(400);
            exit('Data antrean yang dipilih tidak valid.');
        }

        $pdo = Connection::get();
        $queue = new ArchiveSyncModel($pdo);
        if (!$queue->retry((int) $id)) {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Hanya data berstatus gagal yang dapat dijadwalkan ulang.'];
            redirect('sinkronisasi');
        }

        (new AuditLogger($pdo))->record(
            'ARCHIVE_SYNC_RETRY',
            'archive_sync',
            (int) $id,
            (int) currentUser()['id']
        );
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Data dijadwalkan ulang. Worker akan mengirimnya sesuai batas batch.'];
        redirect('sinkronisasi');
    }
}
