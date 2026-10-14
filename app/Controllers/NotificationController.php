<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Models\AdminInboxModel;
use App\Security\AuditLogger;

final class NotificationController
{
    public function index(): void
    {
        AuthMiddleware::requireLogin();
        $inbox = new AdminInboxModel(Connection::get());
        $userId = (int) currentUser()['id'];
        $this->json([
            'items' => $inbox->notificationsForUser($userId),
            'popup' => $this->popupBatch($inbox, $userId),
        ]);
    }

    /**
     * Notifikasi yang boleh muncul sebagai popup. Penanda "terakhir ditampilkan" disimpan di sesi
     * server per akun, bukan di browser, sehingga refresh, cache, atau storage browser tidak
     * memengaruhi hasilnya. Poll pertama setelah login hanya menetapkan penanda tanpa popup.
     */
    private function popupBatch(AdminInboxModel $inbox, int $userId): array
    {
        $latest = $inbox->latestNotificationId($userId);
        $pointer = $_SESSION['toast_last_id'][$userId] ?? null;
        if (!is_int($pointer) || $pointer > $latest) {
            // Poll pertama, atau ID di database direset (misalnya impor ulang SQL): mulai dari posisi sekarang.
            $_SESSION['toast_last_id'][$userId] = $latest;

            return [];
        }
        if ($latest === $pointer) {
            return [];
        }
        $popup = $inbox->unreadAfter($userId, $pointer, 5);
        $_SESSION['toast_last_id'][$userId] = $latest;

        return $popup;
    }

    public function history(): void
    {
        AuthMiddleware::requireLogin();
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $inbox = new AdminInboxModel(Connection::get());
        render('notifications/history', [
            'pageTitle' => 'Riwayat Notifikasi',
            'history' => $inbox->notificationHistory((int) currentUser()['id'], $page !== false && $page > 0 ? $page : 1),
            'notice' => $_SESSION['flash'] ?? null,
        ]);
        unset($_SESSION['flash']);
    }

    public function markRead(): void
    {
        AuthMiddleware::requireLogin();
        requirePost();
        validateCsrf();
        $id = $this->requestId();
        $changed = $id > 0 && (new AdminInboxModel(Connection::get()))->markRead($id, (int) currentUser()['id']);
        $this->json(['ok' => $changed], $changed ? 200 : 404);
    }

    public function markReadFromHistory(): void
    {
        AuthMiddleware::requireLogin();
        requirePost();
        validateCsrf();
        $id = $this->requestId();
        $changed = $id > 0 && (new AdminInboxModel(Connection::get()))->markRead($id, (int) currentUser()['id']);
        if (!$changed) {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Pesan tidak dapat ditandai dibaca. Permintaan edit yang masih menunggu harus diputuskan terlebih dahulu.'];
        }
        redirect('riwayat-notifikasi');
    }

    public function deleteFromHistory(): void
    {
        AuthMiddleware::requireLogin();
        requirePost();
        validateCsrf();
        $id = $this->requestId();
        $changed = $id > 0 && (new AdminInboxModel(Connection::get()))->deleteNotification($id, (int) currentUser()['id']);
        if ($changed) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Pesan dihapus permanen dari riwayat notifikasi.'];
        } else {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Pesan tidak dapat dihapus. Permintaan edit yang masih menunggu harus diputuskan terlebih dahulu.'];
        }
        redirect('riwayat-notifikasi');
    }

    public function clearHistory(): void
    {
        AuthMiddleware::requireLogin();
        requirePost();
        validateCsrf();
        $removed = (new AdminInboxModel(Connection::get()))->deleteAllNotifications((int) currentUser()['id']);
        $_SESSION['flash'] = ['type' => 'success', 'message' => $removed . ' pesan dihapus permanen. Permintaan edit yang masih menunggu keputusan tetap ada.'];
        redirect('riwayat-notifikasi');
    }

    public function reviewEditRequest(): void
    {
        AuthMiddleware::requireAdmin();
        requirePost();
        validateCsrf();
        $id = $this->requestId();
        $decision = $_POST['decision'] ?? '';
        if ($id < 1 || !in_array($decision, ['approve', 'reject'], true)) {
            $this->json(['ok' => false, 'error' => 'invalid_request'], 400);
            return;
        }

        $pdo = Connection::get();
        $updated = (new AdminInboxModel($pdo))->reviewEditRequest(
            $id,
            (int) currentUser()['id'],
            $decision === 'approve'
        );
        if ($updated) {
            (new AuditLogger($pdo))->record(
                $decision === 'approve' ? 'SURAT_EDIT_APPROVED' : 'SURAT_EDIT_REJECTED',
                'surat_masuk',
                null,
                (int) currentUser()['id'],
                null,
                ['request_id' => $id]
            );
        }
        $this->json(['ok' => $updated], $updated ? 200 : 409);
    }

    private function requestId(): int
    {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

        return $id !== false && $id !== null && $id > 0 ? $id : 0;
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}