<?php
declare(strict_types=1);

namespace App\Services;

final class BrevoFeedbackMailer
{
    /** @return array{status: string, http_status: ?int, reason: string} */
    public function send(array $feedback, array|string|null $attachmentPaths = null): array
    {
        $apiKey = trim((string) (app_env('BREVO_API_KEY') ?: ''));
        if ($apiKey === '') {
            return ['status' => 'PENDING', 'http_status' => null, 'reason' => 'not_configured'];
        }

        if (!function_exists('curl_init')) {
            return ['status' => 'FAILED', 'http_status' => null, 'reason' => 'curl_unavailable'];
        }

        $senderEmail = trim((string) (app_env('BREVO_SENDER_EMAIL') ?: 'primabapperida@gmail.com'));
        $recipientEmail = trim((string) (app_env('DEVELOPER_FEEDBACK_EMAIL') ?: 'rielakuonza3@gmail.com'));
        $replyEmail = (string) ($feedback['sender_email'] ?? '');
        if (filter_var($senderEmail, FILTER_VALIDATE_EMAIL) === false
            || filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false
            || filter_var($replyEmail, FILTER_VALIDATE_EMAIL) === false) {
            return ['status' => 'FAILED', 'http_status' => null, 'reason' => 'invalid_email_configuration'];
        }

        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $name = (string) ($feedback['sender_name'] ?? '');
        $message = (string) ($feedback['message'] ?? '');
        $subjectName = preg_replace('/[\r\n]+/', ' ', $name) ?? 'Pengguna';
        $payload = [
            'sender' => ['name' => 'Website SIMAS BAPPERIDA', 'email' => $senderEmail],
            'to' => [['email' => $recipientEmail]],
            'replyTo' => ['email' => $replyEmail, 'name' => $name],
            'subject' => '[Feedback SIMAS] ' . mb_substr($subjectName, 0, 80),
            'htmlContent' => '<h2>Feedback untuk developer</h2>'
                . '<p><strong>Dari:</strong> ' . $escape($name) . '</p>'
                . '<p><strong>Email:</strong> ' . $escape($replyEmail) . '</p>'
                . '<p><strong>WhatsApp:</strong> ' . $escape((string) ($feedback['whatsapp'] ?? '')) . '</p>'
                . '<p><strong>ID feedback:</strong> ' . (int) ($feedback['id'] ?? 0) . '</p>'
                . '<hr><p>' . nl2br($escape($message), false) . '</p>',
        ];

        if (is_string($attachmentPaths)) {
            $attachmentPaths = [$attachmentPaths];
        }
        $attachmentPaths = $attachmentPaths ?? [];
        if (count($attachmentPaths) > FeedbackUploadService::MAX_FILES) {
            return ['status' => 'FAILED', 'http_status' => null, 'reason' => 'too_many_attachments'];
        }
        $attachments = [];
        $totalBytes = 0;
        foreach (array_values($attachmentPaths) as $index => $attachmentPath) {
            if (!is_string($attachmentPath) || !is_file($attachmentPath)) {
                return ['status' => 'FAILED', 'http_status' => null, 'reason' => 'attachment_unavailable'];
            }
            $extension = strtolower(pathinfo($attachmentPath, PATHINFO_EXTENSION));
            if (!in_array($extension, ['jpg', 'png', 'webp'], true)) {
                return ['status' => 'FAILED', 'http_status' => null, 'reason' => 'attachment_type_invalid'];
            }
            $size = filesize($attachmentPath);
            if ($size === false || $size < 1 || $size > FeedbackUploadService::MAX_BYTES) {
                return ['status' => 'FAILED', 'http_status' => null, 'reason' => 'attachment_size_invalid'];
            }
            $totalBytes += $size;
            if ($totalBytes > FeedbackUploadService::MAX_TOTAL_BYTES) {
                return ['status' => 'FAILED', 'http_status' => null, 'reason' => 'attachments_too_large'];
            }
            $contents = @file_get_contents($attachmentPath);
            if ($contents === false) {
                return ['status' => 'FAILED', 'http_status' => null, 'reason' => 'attachment_unavailable'];
            }
            $attachments[] = [
                'name' => sprintf('feedback-%d-%02d.%s', (int) ($feedback['id'] ?? 0), $index + 1, $extension),
                'content' => base64_encode($contents),
            ];
        }
        if ($attachments !== []) {
            $payload['attachment'] = $attachments;
        }

        try {
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['status' => 'FAILED', 'http_status' => null, 'reason' => 'payload_invalid'];
        }

        $handle = curl_init('https://api.brevo.com/v3/smtp/email');
        if ($handle === false) {
            return ['status' => 'FAILED', 'http_status' => null, 'reason' => 'curl_init_failed'];
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'api-key: ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($handle);
        $httpStatus = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($response === false) {
            error_log('Brevo feedback send failed: transport_error');
            return ['status' => 'FAILED', 'http_status' => null, 'reason' => 'transport_error'];
        }
        if ($httpStatus < 200 || $httpStatus >= 300) {
            error_log('Brevo feedback send failed: HTTP ' . $httpStatus);
            return ['status' => 'FAILED', 'http_status' => $httpStatus, 'reason' => 'provider_rejected'];
        }

        return ['status' => 'SENT', 'http_status' => $httpStatus, 'reason' => 'sent'];
    }
}