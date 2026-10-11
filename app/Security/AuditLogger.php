<?php
declare(strict_types=1);

namespace App\Security;

use PDO;

final class AuditLogger
{
    public function __construct(private ?PDO $pdo = null)
    {
    }

    public function record(
        string $action,
        string $module,
        ?int $recordId = null,
        ?int $userId = null,
        ?string $actorUsername = null,
        array $details = []
    ): void {
        $username = $actorUsername ?? ($_SESSION['user']['username'] ?? null);
        $sessionId = isset($_SESSION['auth_session_id']) ? (int) $_SESSION['auth_session_id'] : null;
        $ipAddress = \App\Middleware\AuthMiddleware::clientIp();
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

        if ($this->pdo !== null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO audit_logs
                    (user_id, actor_username, auth_session_id, action, module, record_id, details, ip_address, user_agent, created_at)
                 VALUES
                    (:user_id, :actor_username, :session_id, :action, :module, :record_id, :details, :ip_address, :user_agent, UTC_TIMESTAMP())'
            );
            $statement->execute([
                'user_id' => $userId ?? ($_SESSION['user_id'] ?? null),
                'actor_username' => $username,
                'session_id' => $sessionId,
                'action' => $action,
                'module' => $module,
                'record_id' => $recordId,
                'details' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        }

        $directory = APP_ROOT . '/storage/logs';
        if (!is_dir($directory)) {
            mkdir($directory, 0750, true);
        }

        $entry = [
            'user_id' => $userId ?? ($_SESSION['user_id'] ?? null),
            'actor_username' => $username,
            'auth_session_id' => $sessionId,
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'details' => $details,
            'ip' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => date(DATE_ATOM),
        ];

        file_put_contents($directory . '/audit.log', json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}