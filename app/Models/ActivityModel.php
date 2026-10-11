<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class ActivityModel
{
    public const PAGE_SIZE = 30;

    public function __construct(private PDO $pdo)
    {
    }

    public function sessions(int $userId, bool $allUsers, int $page): array
    {
        $conditions = [];
        $parameters = $allUsers ? [] : ['user_id' => $userId];
        if (!$allUsers) {
            $conditions[] = 's.user_id = :user_id';
        }
        $conditions[] = '(s.revoked_at IS NOT NULL OR s.expires_at <= UTC_TIMESTAMP() OR NOT EXISTS (
            SELECT 1 FROM auth_sessions newer
            WHERE newer.user_id = s.user_id
                AND newer.ip_address <=> s.ip_address
                AND newer.user_agent = s.user_agent
                AND newer.revoked_at IS NULL
                AND newer.expires_at > UTC_TIMESTAMP()
                AND (newer.created_at > s.created_at OR (newer.created_at = s.created_at AND newer.id > s.id))
        ))';
        $where = ' WHERE ' . implode(' AND ', $conditions);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM auth_sessions s' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, $page), $pages);

        $statement = $this->pdo->prepare(
                'SELECT s.id, s.user_id, s.username, s.ip_address, s.user_agent, s.created_at, s.last_seen_at,
                    s.expires_at, s.revoked_at, s.revoked_by, s.revoke_reason,
                    (SELECT COUNT(*) FROM auth_sessions matching
                     WHERE matching.user_id = s.user_id
                    AND matching.ip_address <=> s.ip_address
                    AND matching.user_agent = s.user_agent
                    AND matching.revoked_at IS NULL
                    AND matching.expires_at > UTC_TIMESTAMP()) AS similar_active_sessions,
                    CASE
                        WHEN s.revoked_at IS NOT NULL THEN \'Dicabut\'
                        WHEN s.expires_at <= UTC_TIMESTAMP() THEN \'Kedaluwarsa\'
                        ELSE \'Aktif\'
                    END AS status,
                    revoker.username AS revoked_by_username
             FROM auth_sessions s
             LEFT JOIN users revoker ON revoker.id = s.revoked_by' . $where .
            ' ORDER BY s.created_at DESC, s.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, PDO::PARAM_INT);
        }
        $statement->bindValue(':limit', self::PAGE_SIZE, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * self::PAGE_SIZE, PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];
    }

    public function auditLogs(int $userId, bool $allUsers, int $page): array
    {
        $where = $allUsers ? '' : ' WHERE l.user_id = :user_id';
        $parameters = $allUsers ? [] : ['user_id' => $userId];
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM audit_logs l' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, $page), $pages);

        $statement = $this->pdo->prepare(
            'SELECT l.id, l.actor_username, l.action, l.module, l.record_id, l.details,
                    l.ip_address, l.created_at, l.auth_session_id
             FROM audit_logs l' . $where .
            ' ORDER BY l.created_at DESC, l.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, PDO::PARAM_INT);
        }
        $statement->bindValue(':limit', self::PAGE_SIZE, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * self::PAGE_SIZE, PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];
    }
}
