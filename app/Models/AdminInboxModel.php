<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class AdminInboxModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function notifyAdmins(string $kind, string $message, ?int $senderId, ?int $suratId = null, ?int $requestId = null): void
    {
        $admins = $this->pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
        $existingNotification = $requestId === null ? null : $this->pdo->prepare(
            'SELECT 1 FROM admin_notifications WHERE recipient_id = :recipient_id AND edit_request_id = :request_id AND kind = :kind LIMIT 1'
        );
        foreach ($admins as $adminId) {
            if ($senderId !== null && (int) $adminId === $senderId) {
                continue;
            }
            if ($existingNotification !== null) {
                $existingNotification->execute([
                    'recipient_id' => (int) $adminId,
                    'request_id' => $requestId,
                    'kind' => $kind,
                ]);
                if ($existingNotification->fetchColumn() !== false) {
                    continue;
                }
            }
            $this->createNotification((int) $adminId, $kind, $message, $senderId, $suratId, $requestId);
        }
    }

    public function createRequest(string $type, int $suratId, int $requesterId, string $message): int
    {
        if (!in_array($type, ['EDIT_ACCESS', 'MESSAGE'], true)) {
            throw new \InvalidArgumentException('Jenis pengajuan tidak valid.');
        }

        $this->pdo->beginTransaction();
        try {
            if ($type === 'EDIT_ACCESS') {
                $pending = $this->pdo->prepare(
                    "SELECT id, message FROM surat_edit_requests WHERE surat_id = :surat_id AND requester_id = :user_id
                        AND request_type = 'EDIT_ACCESS' AND status = 'PENDING' LIMIT 1 FOR UPDATE"
                );
                $pending->execute(['surat_id' => $suratId, 'user_id' => $requesterId]);
                $existingRequest = $pending->fetch(PDO::FETCH_ASSOC);
                if ($existingRequest !== false) {
                    $this->notifyAdmins(
                        'EDIT_REQUEST',
                        (string) $existingRequest['message'],
                        $requesterId,
                        $suratId,
                        (int) $existingRequest['id']
                    );
                    $this->pdo->commit();
                    return (int) $existingRequest['id'];
                }
            }

            $statement = $this->pdo->prepare(
                'INSERT INTO surat_edit_requests (surat_id, requester_id, request_type, status, message, created_at)
                 VALUES (:surat_id, :requester_id, :request_type, \'PENDING\', :message, UTC_TIMESTAMP())'
            );
            $statement->execute([
                'surat_id' => $suratId,
                'requester_id' => $requesterId,
                'request_type' => $type,
                'message' => $message,
            ]);
            $requestId = (int) $this->pdo->lastInsertId();
            $kind = $type === 'EDIT_ACCESS' ? 'EDIT_REQUEST' : 'PENGAJUAN';
            $this->notifyAdmins($kind, $message, $requesterId, $suratId, $requestId);
            $this->pdo->commit();

            return $requestId;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function hasApprovedEditAccess(int $suratId, int $userId): bool
    {
        $statement = $this->pdo->prepare(
                        "SELECT 1 FROM surat_edit_requests r
                         WHERE r.surat_id = :surat_id AND r.requester_id = :user_id
                             AND r.request_type = 'EDIT_ACCESS' AND r.status = 'APPROVED'
                             AND r.reviewed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)
                             AND NOT EXISTS (
                                     SELECT 1 FROM surat_edit_requests newer
                                     WHERE newer.surat_id = r.surat_id AND newer.requester_id = r.requester_id
                                         AND newer.request_type = 'EDIT_ACCESS' AND newer.id > r.id
                             )
                         LIMIT 1"
        );
        $statement->execute(['surat_id' => $suratId, 'user_id' => $userId]);

        return $statement->fetchColumn() !== false;
    }

    public function approvedEditSuratIds(array $suratIds, int $userId): array
    {
        $suratIds = array_values(array_unique(array_filter(
            array_map('intval', $suratIds),
            static fn (int $suratId): bool => $suratId > 0
        )));
        if ($suratIds === []) {
            return [];
        }

        $placeholders = [];
        $parameters = ['user_id' => $userId];
        foreach ($suratIds as $index => $suratId) {
            $placeholder = 'surat_id_' . $index;
            $placeholders[] = ':' . $placeholder;
            $parameters[$placeholder] = $suratId;
        }

        $statement = $this->pdo->prepare(
            "SELECT r.surat_id FROM surat_edit_requests r
             WHERE r.requester_id = :user_id AND r.request_type = 'EDIT_ACCESS' AND r.status = 'APPROVED'
                 AND r.reviewed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)
                 AND r.surat_id IN (" . implode(', ', $placeholders) . ")
                 AND NOT EXISTS (
                     SELECT 1 FROM surat_edit_requests newer
                     WHERE newer.surat_id = r.surat_id AND newer.requester_id = r.requester_id
                         AND newer.request_type = 'EDIT_ACCESS' AND newer.id > r.id
                 )"
        );
        $statement->execute($parameters);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function latestEditRequest(int $suratId, int $userId): ?array
    {
        $statement = $this->pdo->prepare(
              "SELECT id,
                    CASE WHEN status = 'APPROVED' AND reviewed_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)
                        THEN 'EXPIRED' ELSE status END AS status,
                    message, created_at, reviewed_at
               FROM surat_edit_requests
             WHERE surat_id = :surat_id AND requester_id = :user_id AND request_type = 'EDIT_ACCESS'
             ORDER BY id DESC LIMIT 1"
        );
        $statement->execute(['surat_id' => $suratId, 'user_id' => $userId]);
        $request = $statement->fetch();

        return $request ?: null;
    }

    public function lockApprovedEditRequest(int $suratId, int $userId): ?int
    {
        $statement = $this->pdo->prepare(
                        "SELECT r.id FROM surat_edit_requests r
                         WHERE r.surat_id = :surat_id AND r.requester_id = :user_id
                             AND r.request_type = 'EDIT_ACCESS' AND r.status = 'APPROVED'
                             AND r.reviewed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)
                             AND NOT EXISTS (
                                     SELECT 1 FROM surat_edit_requests newer
                                     WHERE newer.surat_id = r.surat_id AND newer.requester_id = r.requester_id
                                         AND newer.request_type = 'EDIT_ACCESS' AND newer.id > r.id
                             )
                         ORDER BY r.id DESC LIMIT 1 FOR UPDATE"
        );
        $statement->execute(['surat_id' => $suratId, 'user_id' => $userId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function reviewEditRequest(int $requestId, int $adminId, bool $approve): bool
    {
        $this->pdo->beginTransaction();
        try {
            $lookup = $this->pdo->prepare(
                "SELECT surat_id, requester_id, message FROM surat_edit_requests
                 WHERE id = :id AND request_type = 'EDIT_ACCESS' AND status = 'PENDING' FOR UPDATE"
            );
            $lookup->execute(['id' => $requestId]);
            $request = $lookup->fetch();
            if (!$request) {
                $this->pdo->commit();
                return false;
            }

            $status = $approve ? 'APPROVED' : 'REJECTED';
            $update = $this->pdo->prepare(
                'UPDATE surat_edit_requests SET status = :status, reviewed_by = :reviewed_by,
                    reviewed_at = UTC_TIMESTAMP() WHERE id = :id'
            );
            $update->execute(['status' => $status, 'reviewed_by' => $adminId, 'id' => $requestId]);
            $markRead = $this->pdo->prepare(
                "UPDATE admin_notifications SET is_read = 1, read_at = UTC_TIMESTAMP()
                 WHERE recipient_id = :admin_id AND edit_request_id = :request_id AND kind = 'EDIT_REQUEST'"
            );
            $markRead->execute(['admin_id' => $adminId, 'request_id' => $requestId]);
            if ($request['requester_id'] !== null) {
                $message = $approve
                    ? 'Permintaan edit surat #' . (int) $request['surat_id'] . ' disetujui. Akses edit berlaku selama 24 jam.'
                    : 'Permintaan edit surat #' . (int) $request['surat_id'] . ' ditolak.';
                $this->createNotification(
                    (int) $request['requester_id'],
                    $approve ? 'EDIT_APPROVED' : 'EDIT_REJECTED',
                    $message,
                    $adminId,
                    (int) $request['surat_id'],
                    $requestId
                );
            }
            $this->pdo->commit();

            return true;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function notificationsForUser(int $userId, int $limit = 25, int $offset = 0): array
    {
        $limit = min(max(1, $limit), 50);
        $offset = max(0, $offset);
        $statement = $this->pdo->prepare(
            'SELECT n.id, n.kind, n.message, n.surat_id, n.edit_request_id, n.is_read, n.created_at,
                    sender.username AS sender_username, r.request_type, r.status AS request_status
             FROM admin_notifications n
             LEFT JOIN users sender ON sender.id = n.sender_id
             LEFT JOIN surat_edit_requests r ON r.id = n.edit_request_id
             WHERE n.recipient_id = :user_id AND n.deleted_at IS NULL
             ORDER BY n.created_at DESC, n.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    /** ID notifikasi tertinggi milik penerima (termasuk yang sudah dihapus dari riwayat). */
    public function latestNotificationId(int $userId): int
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM admin_notifications WHERE recipient_id = :user_id');
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    /** Notifikasi belum dibaca yang lebih baru dari ID tertentu, dipakai untuk popup. */
    public function unreadAfter(int $userId, int $afterId, int $limit = 5): array
    {
        $limit = min(max(1, $limit), 5);
        $statement = $this->pdo->prepare(
            'SELECT n.id, n.kind, n.message, n.surat_id, n.edit_request_id, n.is_read, n.created_at,
                    sender.username AS sender_username, r.request_type, r.status AS request_status
             FROM admin_notifications n
             LEFT JOIN users sender ON sender.id = n.sender_id
             LEFT JOIN surat_edit_requests r ON r.id = n.edit_request_id
             WHERE n.recipient_id = :user_id AND n.deleted_at IS NULL AND n.is_read = 0 AND n.id > :after_id
             ORDER BY n.id DESC LIMIT ' . $limit
        );
        $statement->execute(['user_id' => $userId, 'after_id' => $afterId]);

        return array_reverse($statement->fetchAll());
    }

    public function notificationHistory(int $userId, int $page = 1): array
    {
        $perPage = 25;
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM admin_notifications WHERE recipient_id = :user_id AND deleted_at IS NULL');
        $count->execute(['user_id' => $userId]);
        $total = (int) $count->fetchColumn();
        $deletable = $this->pdo->prepare(
            "SELECT COUNT(*) FROM admin_notifications n
             LEFT JOIN surat_edit_requests r ON r.id = n.edit_request_id
             WHERE n.recipient_id = :user_id AND n.deleted_at IS NULL
               AND NOT (n.kind = 'EDIT_REQUEST' AND r.status = 'PENDING')"
        );
        $deletable->execute(['user_id' => $userId]);
        $deletableCount = (int) $deletable->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $items = $this->notificationsForUser($userId, $perPage, ($page - 1) * $perPage);

        return ['items' => $items, 'page' => $page, 'pages' => $pages, 'total' => $total, 'deletableCount' => $deletableCount];
    }

    public function markRead(int $notificationId, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE admin_notifications SET is_read = 1, read_at = UTC_TIMESTAMP()
                         WHERE id = :id AND recipient_id = :user_id AND deleted_at IS NULL
                             AND NOT EXISTS (
                                     SELECT 1 FROM surat_edit_requests r
                                     WHERE r.id = admin_notifications.edit_request_id
                                         AND r.request_type = \'EDIT_ACCESS\' AND r.status = \'PENDING\'
                             )'
        );
        $statement->execute(['id' => $notificationId, 'user_id' => $userId]);

        return $statement->rowCount() > 0;
    }

    public function deleteNotification(int $notificationId, int $userId): bool
    {
                $statement = $this->pdo->prepare(
                        "DELETE FROM admin_notifications
                         WHERE id = :id AND recipient_id = :user_id
                             AND NOT EXISTS (
                                     SELECT 1 FROM surat_edit_requests r
                                     WHERE r.id = admin_notifications.edit_request_id
                                         AND r.request_type = 'EDIT_ACCESS' AND r.status = 'PENDING'
                             )"
                );
        $statement->execute(['id' => $notificationId, 'user_id' => $userId]);

        return $statement->rowCount() > 0;
    }

    public function deleteAllNotifications(int $userId): int
    {
                $statement = $this->pdo->prepare(
                        "DELETE FROM admin_notifications
                         WHERE recipient_id = :user_id
                             AND NOT EXISTS (
                                     SELECT 1 FROM surat_edit_requests r
                                     WHERE r.id = admin_notifications.edit_request_id
                                         AND r.request_type = 'EDIT_ACCESS' AND r.status = 'PENDING'
                             )"
                );
        $statement->execute(['user_id' => $userId]);

        return $statement->rowCount();
    }

    public function createLetterNotification(int $suratId, int $creatorId, string $agenda): void
    {
        $this->notifyAdmins('SURAT_BARU', 'Surat baru B-' . $agenda . ' (#' . $suratId . ') telah ditambahkan.', $creatorId, $suratId);
    }

    private function createNotification(
        int $recipientId,
        string $kind,
        string $message,
        ?int $senderId,
        ?int $suratId,
        ?int $requestId
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO admin_notifications
                (recipient_id, sender_id, surat_id, edit_request_id, kind, message, created_at)
             VALUES (:recipient_id, :sender_id, :surat_id, :request_id, :kind, :message, UTC_TIMESTAMP())'
        );
        $statement->execute([
            'recipient_id' => $recipientId,
            'sender_id' => $senderId,
            'surat_id' => $suratId,
            'request_id' => $requestId,
            'kind' => $kind,
            'message' => mb_substr($message, 0, 500),
        ]);
    }
}