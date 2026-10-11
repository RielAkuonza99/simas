<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Models\AuthSessionModel;
use App\Security\AuditLogger;
use App\Services\AuthService;
use App\Models\UserModel;

final class SecurityController
{
    public function activity(): void
    {
        AuthMiddleware::requireLogin();
        header('Location: ' . url('pengaturan') . '#activity', true, 303);
        exit;
    }

    public function revokeSession(): void
    {
        AuthMiddleware::requireLogin();
        requirePost();
        validateCsrf();

        $user = currentUser();
        $isAdmin = ($user['role'] ?? '') === 'admin';
        $sessionId = filter_var($_GET['session_id'] ?? $_POST['session_id'] ?? '', FILTER_VALIDATE_INT);
        if ($sessionId === false || (int) $sessionId < 1) {
            http_response_code(400);
            exit('Sesi yang dipilih tidak valid.');
        }

        $pdo = Connection::get();
        $ownerId = $isAdmin ? null : (int) $user['id'];
        $sessions = new AuthSessionModel($pdo);
        $pdo->beginTransaction();
        try {
            if (!$sessions->revoke((int) $sessionId, (int) $user['id'], 'user_revoked', $ownerId)) {
                $pdo->rollBack();
                $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Sesi tidak ditemukan atau sudah tidak aktif.'];
                header('Location: ' . url('pengaturan') . '#activity', true, 303);
                exit;
            }

            (new AuditLogger($pdo))->record(
                'SESSION_REVOKED',
                'auth',
                (int) $sessionId,
                (int) $user['id'],
                null,
                ['revoked_by_admin' => $isAdmin]
            );
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }

        if ((int) ($_SESSION['auth_session_id'] ?? 0) === (int) $sessionId) {
            (new AuthService(
                new UserModel($pdo),
                new AuditLogger($pdo),
                $sessions
            ))->logout();
            redirect('', ['status' => 'login_required']);
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Akses perangkat berhasil dicabut. Riwayat tetap disimpan untuk audit.'];
        header('Location: ' . url('pengaturan') . '#activity', true, 303);
        exit;
    }
}
