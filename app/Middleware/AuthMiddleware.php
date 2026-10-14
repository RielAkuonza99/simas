<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Database\Connection;
use App\Models\AuthSessionModel;
use App\Security\AuditLogger;

final class AuthMiddleware
{
    public static function requireLogin(): void
    {
        if (!self::resumeDeviceSession()) {
            redirect('', ['status' => 'login_required']);
        }
    }

    public static function resumeDeviceSession(): bool
    {
        $pdo = Connection::get();
        $sessions = new AuthSessionModel($pdo);
        $token = (string) ($_COOKIE['bapperida_device'] ?? '');

        if (!empty($_SESSION['user_id']) && !empty($_SESSION['auth_session_id']) && preg_match('/^[a-f0-9]{64}$/', $token)) {
            $current = $sessions->validateCurrent(
                (int) $_SESSION['auth_session_id'],
                (int) $_SESSION['user_id'],
                hash('sha256', $token)
            );
            if ($current !== null) {
                $_SESSION['user'] = [
                    'id' => (int) $current['user_id'],
                    'username' => $current['current_username'],
                    'role' => $current['role'],
                ];
                $sessions->touch((int) $current['auth_session_id']);

                return true;
            }
        }

        if (preg_match('/^[a-f0-9]{64}$/', $token)) {
            $restored = $sessions->findActiveByToken(hash('sha256', $token));
            if ($restored !== null) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $restored['user_id'];
                $_SESSION['user'] = [
                    'id' => (int) $restored['user_id'],
                    'username' => $restored['username'],
                    'role' => $restored['role'],
                ];
                $_SESSION['auth_session_id'] = (int) $restored['auth_session_id'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $expiresAt = strtotime($restored['expires_at'] . ' UTC');
                if ($expiresAt === false) {
                    throw new \RuntimeException('Masa berlaku sesi perangkat tidak valid.');
                }
                \App\Services\AuthService::setDeviceCookie($token, $expiresAt);
                $sessions->touch((int) $restored['auth_session_id']);
                (new AuditLogger($pdo))->record(
                    'DEVICE_SESSION_RESTORED',
                    'auth',
                    (int) $restored['auth_session_id'],
                    (int) $restored['user_id']
                );

                return true;
            }
        }

        if (!empty($_SESSION['user_id']) || $token !== '') {
            self::clearLocalAuthentication();
        }

        return false;
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if ((currentUser()['role'] ?? null) !== 'admin') {
            (new AuditLogger(Connection::get()))->record('AUTHORIZATION_FAILURE', 'authorization', null, (int) currentUser()['id'], null, [
                'required_role' => 'admin',
            ]);
            http_response_code(403);
            exit('Anda tidak memiliki hak untuk melakukan tindakan ini.');
        }
    }

    public static function requireSuratEditor(): void
    {
        self::requireLogin();
        if (!in_array(currentUser()['role'] ?? null, ['admin', 'pelayanan'], true)) {
            (new AuditLogger(Connection::get()))->record('AUTHORIZATION_FAILURE', 'authorization', null, (int) currentUser()['id'], null, [
                'required_roles' => ['admin', 'pelayanan'],
            ]);
            http_response_code(403);
            exit('Anda tidak memiliki hak untuk mengubah surat.');
        }
    }

    public static function requireSuratCreator(): void
    {
        self::requireLogin();
        if (!in_array(currentUser()['role'] ?? null, ['admin', 'pelayanan'], true)) {
            (new AuditLogger(Connection::get()))->record('AUTHORIZATION_FAILURE', 'authorization', null, (int) currentUser()['id'], null, [
                'required_roles' => ['admin', 'pelayanan'],
            ]);
            http_response_code(403);
            exit('Anda tidak memiliki hak untuk menambahkan surat.');
        }
    }

    public static function clientIp(): ?string
    {
        $address = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return filter_var($address, FILTER_VALIDATE_IP) ? $address : null;
    }

    private static function clearLocalAuthentication(): void
    {
        \App\Services\AuthService::clearDeviceCookie();
        $_SESSION = [];
        session_regenerate_id(true);
    }
}