<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class AuthSessionModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(
        int $userId,
        string $username,
        string $tokenHash,
        ?string $ipAddress,
        string $userAgent,
        int $expiresAt
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO auth_sessions
                (user_id, username, token_hash, ip_address, user_agent, created_at, last_seen_at, expires_at)
             VALUES
                (:user_id, :username, :token_hash, :ip_address, :user_agent, UTC_TIMESTAMP(), UTC_TIMESTAMP(), :expires_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'username' => $username,
            'token_hash' => $tokenHash,
            'ip_address' => $ipAddress,
            'user_agent' => substr($userAgent, 0, 500),
            'expires_at' => gmdate('Y-m-d H:i:s', $expiresAt),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findActiveByToken(string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.id AS auth_session_id, s.user_id, s.username, s.expires_at, u.role
             FROM auth_sessions s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.token_hash = :token_hash
                AND s.revoked_at IS NULL
                AND s.expires_at > UTC_TIMESTAMP()
                AND u.is_active = 1
             LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $session = $statement->fetch();

        return $session ?: null;
    }

    public function validateCurrent(int $sessionId, int $userId, string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.id AS auth_session_id, s.user_id, s.username, s.expires_at, u.username AS current_username,
                    u.role, u.is_active
             FROM auth_sessions s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.id = :session_id
                AND s.user_id = :user_id
                AND s.token_hash = :token_hash
                AND s.revoked_at IS NULL
                AND s.expires_at > UTC_TIMESTAMP()
                AND u.is_active = 1
             LIMIT 1'
        );
        $statement->execute([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'token_hash' => $tokenHash,
        ]);
        $session = $statement->fetch();

        return $session ?: null;
    }

    public function touch(int $sessionId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE auth_sessions
             SET last_seen_at = UTC_TIMESTAMP()
             WHERE id = :id AND last_seen_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)'
        );
        $statement->execute(['id' => $sessionId]);
    }

    public function revoke(int $sessionId, int $actorId, string $reason, ?int $ownerId = null): bool
    {
        $sql = 'UPDATE auth_sessions
                SET revoked_at = UTC_TIMESTAMP(), revoked_by = :actor_id, revoke_reason = :reason
                WHERE id = :id AND revoked_at IS NULL';
        $parameters = ['actor_id' => $actorId, 'reason' => $reason, 'id' => $sessionId];
        if ($ownerId !== null) {
            $sql .= ' AND user_id = :owner_id';
            $parameters['owner_id'] = $ownerId;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->rowCount() === 1;
    }

}
