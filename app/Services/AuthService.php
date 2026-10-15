<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Models\AuthSessionModel;
use App\Models\UserModel;
use App\Security\AuditLogger;
use PDO;

final class AuthService
{
    private AuthSessionModel $sessions;
    private PDO $pdo;

    public function __construct(
        private UserModel $users,
        private AuditLogger $audit,
        ?AuthSessionModel $sessions = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Connection::get();
        $this->sessions = $sessions ?? new AuthSessionModel($this->pdo);
    }

    public function authenticate(string $username, string $password): bool
    {
        $user = $this->users->findByUsername($username);
        if (!$user || (int) $user['is_active'] !== 1 || !password_verify($password, $user['password'])) {
            $this->audit->record('LOGIN_FAILURE', 'auth', null, null, $username);
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            session_regenerate_id(true);
            $token = bin2hex(random_bytes(32));
            $expiresAt = time() + 30 * 86400;
            $sessionId = $this->sessions->create(
                (int) $user['id'],
                $user['username'],
                hash('sha256', $token),
                AuthMiddleware::clientIp(),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                $expiresAt
            );
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
            ];
            $_SESSION['auth_session_id'] = $sessionId;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $this->audit->record('LOGIN', 'auth', $sessionId, (int) $user['id']);
            $this->pdo->commit();
            self::setDeviceCookie($token, $expiresAt);
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            unset($_SESSION['user_id'], $_SESSION['user'], $_SESSION['auth_session_id']);
            throw $error;
        }

        return true;
    }

    public function logout(): void
    {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $sessionId = isset($_SESSION['auth_session_id']) ? (int) $_SESSION['auth_session_id'] : null;
        if ($userId !== null && $sessionId !== null) {
            $this->pdo->beginTransaction();
            try {
                $this->audit->record('LOGOUT', 'auth', $sessionId, $userId);
                $this->sessions->revoke($sessionId, $userId, 'logout', $userId);
                $this->pdo->commit();
            } catch (\Throwable $error) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $error;
            }
        }

        self::clearDeviceCookie();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function setDeviceCookie(string $token, int $expiresAt): void
    {
        setcookie('bapperida_device', $token, [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => self::requestIsHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearDeviceCookie(): void
    {
        setcookie('bapperida_device', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => self::requestIsHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function requestIsHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }
}