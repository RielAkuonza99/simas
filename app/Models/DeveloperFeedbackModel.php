<?php
declare(strict_types=1);

namespace App\Models;

use DomainException;
use PDO;
use Throwable;

final class DeveloperFeedbackModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(
        int $userId,
        string $senderName,
        string $senderEmail,
        string $whatsapp,
        string $message,
        array $photoFilenames
    ): int {
        if (count($photoFilenames) > 5) {
            throw new DomainException('Maksimal 5 foto untuk satu feedback.');
        }
        foreach ($photoFilenames as $filename) {
            if (!is_string($filename) || preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/D', $filename) !== 1) {
                throw new DomainException('Nama file foto feedback tidak valid.');
            }
        }

        $this->pdo->beginTransaction();
        try {
            $recent = $this->pdo->prepare(
                'SELECT COUNT(*) FROM developer_feedback
                 WHERE user_id = :user_id AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)'
            );
            $recent->execute(['user_id' => $userId]);
            if ((int) $recent->fetchColumn() >= 5) {
                throw new DomainException('Batas feedback harian tercapai. Coba kirim lagi besok.');
            }

            $statement = $this->pdo->prepare(
                'INSERT INTO developer_feedback
                    (user_id, sender_name, sender_email, whatsapp, message, photo_filename, photos_json, created_at)
                 VALUES
                    (:user_id, :sender_name, :sender_email, :whatsapp, :message, :photo_filename, :photos_json, UTC_TIMESTAMP())'
            );
            $statement->execute([
                'user_id' => $userId,
                'sender_name' => $senderName,
                'sender_email' => $senderEmail,
                'whatsapp' => $whatsapp,
                'message' => $message,
                'photo_filename' => $photoFilenames[0] ?? null,
                'photos_json' => $photoFilenames === []
                    ? null
                    : json_encode($photoFilenames, JSON_THROW_ON_ERROR),
            ]);
            $feedbackId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();

            return $feedbackId;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function updateEmailStatus(int $feedbackId, string $status, ?int $httpStatus): void
    {
        if (!in_array($status, ['PENDING', 'SENT', 'FAILED'], true)) {
            throw new \InvalidArgumentException('Status email feedback tidak valid.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE developer_feedback SET email_status = :status, email_http_status = :http_status WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'http_status' => $httpStatus,
            'id' => $feedbackId,
        ]);
    }

    public function recent(int $limit = 50, int $offset = 0, ?string $status = null): array
    {
        $limit = min(max(1, $limit), 100);
        $offset = max(0, $offset);
        $where = '';
        $parameters = [];
        if ($status !== null) {
            if (!in_array($status, ['NEW', 'REVIEWED', 'DONE'], true)) {
                throw new \InvalidArgumentException('Filter status feedback tidak valid.');
            }
            $where = ' WHERE f.review_status = :status';
            $parameters['status'] = $status;
        }

        $statement = $this->pdo->prepare(
            'SELECT f.id, f.user_id, u.username, f.sender_name, f.sender_email, f.whatsapp,
                    f.message, f.photo_filename, f.photos_json, f.email_status, f.review_status,
                    f.email_http_status, f.created_at, f.updated_at
             FROM developer_feedback f
             LEFT JOIN users u ON u.id = f.user_id' . $where . '
             ORDER BY f.created_at DESC, f.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->normalizePhotos(...), $statement->fetchAll());
    }

    public function find(int $feedbackId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT f.id, f.user_id, u.username, f.sender_name, f.sender_email, f.whatsapp,
                    f.message, f.photo_filename, f.photos_json, f.email_status, f.review_status,
                    f.email_http_status, f.created_at, f.updated_at
             FROM developer_feedback f
             LEFT JOIN users u ON u.id = f.user_id
             WHERE f.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $feedbackId]);
        $feedback = $statement->fetch();

        return $feedback ? $this->normalizePhotos($feedback) : null;
    }

    public function updateReviewStatus(int $feedbackId, string $status): bool
    {
        if (!in_array($status, ['NEW', 'REVIEWED', 'DONE'], true)) {
            throw new \InvalidArgumentException('Status tindak lanjut feedback tidak valid.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE developer_feedback
             SET review_status = :status, updated_at = UTC_TIMESTAMP()
             WHERE id = :id'
        );
        $statement->execute(['status' => $status, 'id' => $feedbackId]);

        return $statement->rowCount() === 1 || $this->find($feedbackId) !== null;
    }

    public function delete(int $feedbackId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM developer_feedback WHERE id = :id');
        $statement->execute(['id' => $feedbackId]);

        return $statement->rowCount() === 1;
    }

    private function normalizePhotos(array $feedback): array
    {
        $photos = [];
        $encodedPhotos = $feedback['photos_json'] ?? null;
        if (is_string($encodedPhotos) && $encodedPhotos !== '') {
            try {
                $decoded = json_decode($encodedPhotos, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded) && array_is_list($decoded)) {
                    $photos = array_values(array_filter(
                        $decoded,
                        static fn (mixed $filename): bool => is_string($filename)
                            && preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/D', $filename) === 1
                    ));
                }
            } catch (\JsonException) {
                $photos = [];
            }
        }
        if ($photos === [] && is_string($feedback['photo_filename'] ?? null)
            && preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/D', $feedback['photo_filename']) === 1) {
            $photos = [$feedback['photo_filename']];
        }
        unset($feedback['photos_json']);
        $feedback['photos'] = $photos;

        return $feedback;
    }
}