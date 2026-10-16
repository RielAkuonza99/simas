<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Models\DeveloperFeedbackModel;
use App\Security\AuditLogger;
use App\Services\BrevoFeedbackMailer;
use App\Services\FeedbackUploadService;
use DomainException;
use Throwable;

final class DeveloperFeedbackController
{
    public function submit(): void
    {
        AuthMiddleware::requireLogin();
        \requirePost();
        // Jika total unggahan melebihi post_max_size, PHP mengosongkan $_POST dan $_FILES sekaligus.
        if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Total ukuran foto melebihi batas unggahan server (' . ini_get('post_max_size') . '). Kurangi jumlah atau ukuran foto.'];
            header('Location: ' . \url('panduan') . '#feedback-dev', true, 303);
            exit;
        }
        \validateCsrf();

        $storedPhotos = [];
        $feedbackId = 0;
        try {
            $senderName = trim((string) ($_POST['sender_name'] ?? ''));
            $senderEmail = trim((string) ($_POST['sender_email'] ?? ''));
            $whatsapp = trim((string) ($_POST['whatsapp'] ?? ''));
            $message = trim(str_replace(["\r\n", "\r"], "\n", (string) ($_POST['message'] ?? '')));

            if ($senderName === '' || mb_strlen($senderName) > 120) {
                throw new DomainException('Nama harus diisi dan maksimal 120 karakter.');
            }
            if (filter_var($senderEmail, FILTER_VALIDATE_EMAIL) === false || strlen($senderEmail) > 254) {
                throw new DomainException('Masukkan alamat email yang valid.');
            }
            $digits = preg_replace('/\D/', '', $whatsapp) ?? '';
            if (preg_match('/^[0-9+().\-\s]{8,32}$/', $whatsapp) !== 1 || strlen($digits) < 8 || strlen($digits) > 16) {
                throw new DomainException('Masukkan nomor WhatsApp yang valid, termasuk kode negara bila diperlukan.');
            }
            if (mb_strlen($message) < 20 || mb_strlen($message) > 8000) {
                throw new DomainException('Keterangan harus berisi 20 sampai 8.000 karakter.');
            }

            $uploadService = new FeedbackUploadService();
            $storedPhotos = $uploadService->storeMany($_FILES['photos'] ?? []);
            $pdo = Connection::get();
            $feedback = new DeveloperFeedbackModel($pdo);
            $user = currentUser() ?? [];
            $feedbackId = $feedback->create(
                (int) ($user['id'] ?? 0),
                $senderName,
                $senderEmail,
                $whatsapp,
                $message,
                array_column($storedPhotos, 'filename')
            );

            $result = (new BrevoFeedbackMailer())->send([
                'id' => $feedbackId,
                'sender_name' => $senderName,
                'sender_email' => $senderEmail,
                'whatsapp' => $whatsapp,
                'message' => $message,
                'photo_count' => count($storedPhotos),
            ], array_column($storedPhotos, 'path'));
            $feedback->updateEmailStatus($feedbackId, $result['status'], $result['http_status']);

            try {
                (new AuditLogger($pdo))->record(
                    'FEEDBACK_SUBMITTED',
                    'developer_feedback',
                    $feedbackId,
                    (int) ($user['id'] ?? 0),
                    (string) ($user['username'] ?? ''),
                    ['email_status' => $result['status'], 'photo_count' => count($storedPhotos)]
                );
            } catch (Throwable) {
                error_log('Developer feedback audit record failed.');
            }

            $_SESSION['flash'] = match ($result['status']) {
                'SENT' => ['type' => 'success', 'message' => 'Feedback #' . $feedbackId . ' berhasil dikirim ke developer.'],
                'PENDING' => ['type' => 'warn', 'message' => 'Feedback #' . $feedbackId . ' tersimpan, tetapi email belum dikirim karena Brevo belum dikonfigurasi lengkap.'],
                default => ['type' => 'warn', 'message' => 'Feedback #' . $feedbackId . ' tersimpan, tetapi email notifikasi gagal dikirim.'],
            };
        } catch (DomainException $error) {
            if ($feedbackId === 0 && $storedPhotos !== []) {
                (new FeedbackUploadService())->remove(array_column($storedPhotos, 'filename'));
            }
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $error->getMessage()];
        } catch (Throwable) {
            if ($feedbackId === 0 && $storedPhotos !== []) {
                (new FeedbackUploadService())->remove(array_column($storedPhotos, 'filename'));
            }
            error_log('Developer feedback submission failed.');
            $_SESSION['flash'] = $feedbackId > 0
                ? ['type' => 'warn', 'message' => 'Feedback #' . $feedbackId . ' tersimpan, tetapi status email belum dapat diperbarui.']
                : ['type' => 'danger', 'message' => 'Feedback belum tersimpan. Periksa isian dan coba lagi.'];
        }

        header('Location: ' . \url('panduan') . '#feedback-dev', true, 303);
        exit;
    }
}