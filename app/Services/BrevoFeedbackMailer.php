<?php
declare(strict_types=1);

namespace App\Services;

final class BrevoFeedbackMailer
{
    /** @return array{status: string, http_status: ?int, reason: string, detail?: string} */
    public function send(array $feedback, array|string|null $attachmentPaths = null): array
    {
        $apiKey = trim((string) (app_env('BREVO_API_KEY') ?: ''));
        $senderEmail = trim((string) (app_env('BREVO_SENDER_EMAIL') ?: ''));
        $recipientEmail = trim((string) (app_env('DEVELOPER_FEEDBACK_EMAIL') ?: ''));
        if ($apiKey === '' || $senderEmail === '' || $recipientEmail === '') {
            return ['status' => 'PENDING', 'http_status' => null, 'reason' => 'not_configured'];
        }

        if (!function_exists('curl_init')) {
            return ['status' => 'FAILED', 'http_status' => null, 'reason' => 'curl_unavailable'];
        }

        $replyEmail = (string) ($feedback['sender_email'] ?? '');
        if (filter_var($senderEmail, FILTER_VALIDATE_EMAIL) === false
            || filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false
            || filter_var($replyEmail, FILTER_VALIDATE_EMAIL) === false) {
            return ['status' => 'FAILED', 'http_status' => null, 'reason' => 'invalid_email_configuration'];
        }

        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $name = (string) ($feedback['sender_name'] ?? '');
        $message = (string) ($feedback['message'] ?? '');
        $whatsapp = (string) ($feedback['whatsapp'] ?? '');
        $feedbackId = (int) ($feedback['id'] ?? 0);
        $photoCount = (int) ($feedback['photo_count'] ?? 0);
        $subjectName = preg_replace('/[\r\n]+/', ' ', $name) ?? 'Pengguna';
        $baseUrl = defined('APP_BASE_URL') ? rtrim((string) APP_BASE_URL, '/') : '';
        $baseUrlScheme = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));
        $logoUrl = $baseUrlScheme === 'https' && filter_var($baseUrl, FILTER_VALIDATE_URL)
            ? $baseUrl . '/assets/img/icon.svg'
            : '';
        $logo = $logoUrl !== ''
            ? '<img src="' . $escape($logoUrl) . '" alt="Logo BAPPERIDA" width="56" height="56" style="display:block;width:56px;height:56px;border:0;border-radius:50%;">'
            : '';
        $messageHtml = nl2br($escape($message), false);
        $payload = [
            'sender' => ['name' => 'BAPPERIDA | Feedback SIMAS', 'email' => $senderEmail],
            'to' => [['email' => $recipientEmail]],
            'replyTo' => ['email' => $replyEmail, 'name' => $name],
            'subject' => '[Feedback SIMAS] ' . mb_substr($subjectName, 0, 80),
            'htmlContent' => '<!doctype html><html><body style="margin:0;padding:0;background-color:#f1f5f8;font-family:Arial,Helvetica,sans-serif;color:#182b3a;">'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f1f5f8"><tr><td align="center" style="padding:28px 12px;">'
                . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border:1px solid #d5e0e8;">'
                . '<tr><td bgcolor="#153d7b" style="padding:22px 24px;color:#ffffff;">'
                . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
                . ($logo !== '' ? '<td valign="middle" style="padding-right:16px;">' . $logo . '</td>' : '')
                . '<td valign="middle"><div style="font-size:12px;line-height:18px;font-weight:bold;color:#e5f3fa;">BAPPERIDA</div>'
                . '<div style="font-size:22px;line-height:28px;font-weight:bold;color:#ffffff;">Feedback SIMAS</div></td>'
                . '</tr></table></td></tr>'
                . '<tr><td style="padding:24px;">'
                . '<div style="font-size:16px;line-height:24px;font-weight:bold;color:#153d7b;">Feedback baru dari ' . $escape($name) . '</div>'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:18px;border-collapse:collapse;">'
                . '<tr><td style="padding:8px 0;border-bottom:1px solid #e3eaf0;font-size:13px;color:#486173;">Email</td><td style="padding:8px 0;border-bottom:1px solid #e3eaf0;font-size:13px;color:#182b3a;">' . $escape($replyEmail) . '</td></tr>'
                . '<tr><td style="padding:8px 0;border-bottom:1px solid #e3eaf0;font-size:13px;color:#486173;">WhatsApp</td><td style="padding:8px 0;border-bottom:1px solid #e3eaf0;font-size:13px;color:#182b3a;">' . $escape($whatsapp) . '</td></tr>'
                . '<tr><td style="padding:8px 0;border-bottom:1px solid #e3eaf0;font-size:13px;color:#486173;">ID feedback</td><td style="padding:8px 0;border-bottom:1px solid #e3eaf0;font-size:13px;color:#182b3a;">' . $feedbackId . '</td></tr>'
                . '<tr><td style="padding:8px 0;font-size:13px;color:#486173;">Lampiran foto</td><td style="padding:8px 0;font-size:13px;color:#182b3a;">' . $photoCount . '</td></tr>'
                . '</table><div style="margin-top:22px;padding:16px;background-color:#f4f8fa;border-left:4px solid #0781c3;font-size:14px;line-height:22px;color:#182b3a;">' . $messageHtml . '</div>'
                . '</td></tr><tr><td style="padding:14px 24px;border-top:1px solid #e3eaf0;font-size:12px;line-height:18px;color:#486173;">Pesan otomatis dari formulir feedback SIMAS BAPPERIDA.</td></tr>'
                . '</table></td></tr></table></body></html>',
            'textContent' => "Feedback baru dari {$name}\n\n"
                . "Email: {$replyEmail}\nWhatsApp: {$whatsapp}\nID feedback: {$feedbackId}\nLampiran foto: {$photoCount}\n\n"
                . "Pesan:\n{$message}\n\nPesan otomatis dari formulir feedback SIMAS BAPPERIDA.",
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

        // Batas lampiran API transaksional Brevo sekitar 4 MB. Di atas batas aman, kirim tanpa lampiran
        // (foto tetap tersimpan di SIMAS) daripada membuat seluruh email gagal.
        $withAttachments = $attachments !== [] && $totalBytes <= self::SAFE_ATTACHMENT_BYTES;
        if ($withAttachments) {
            $payload['attachment'] = $attachments;
        } elseif ($attachments !== []) {
            $payload = $this->withoutAttachmentNote($payload, count($attachments));
        }

        $result = $this->post($apiKey, $payload, $feedbackId);

        // Brevo menolak karena lampiran (400/413)? Coba sekali lagi tanpa lampiran supaya pesan tetap sampai.
        if ($result['status'] === 'FAILED' && $withAttachments && in_array($result['http_status'], [400, 413], true)) {
            unset($payload['attachment']);
            $payload = $this->withoutAttachmentNote($payload, count($attachments));
            $retry = $this->post($apiKey, $payload, $feedbackId);
            if ($retry['status'] === 'SENT') {
                $retry['reason'] = 'sent_without_attachments';
                return $retry;
            }
            return $retry;
        }

        return $result;
    }

    private const SAFE_ATTACHMENT_BYTES = 2621440;

    /** @param array<string, mixed> $payload */
    private function withoutAttachmentNote(array $payload, int $count): array
    {
        $note = "Catatan: {$count} foto tidak ikut terlampir pada email ini. Foto tersimpan di SIMAS dan dapat dibuka lewat API developer.";
        $payload['textContent'] = ($payload['textContent'] ?? '') . "\n\n" . $note;
        $payload['htmlContent'] = str_replace(
            '</body>',
            '<p style="margin:12px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#486173;">'
                . htmlspecialchars($note, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p></body>',
            (string) ($payload['htmlContent'] ?? '')
        );

        return $payload;
    }

    /**
     * Kirim ke Brevo dan catat penyebab kegagalan ke storage/logs/app.log
     * (error_log() PHP tidak bisa dibaca di hosting bersama).
     *
     * @param array<string, mixed> $payload
     * @return array{status: string, http_status: ?int, reason: string, detail?: string}
     */
    private function post(string $apiKey, array $payload, int $feedbackId): array
    {
        try {
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['status' => 'FAILED', 'http_status' => null, 'reason' => 'payload_invalid'];
        }

        $response = false;
        $httpStatus = 0;
        $curlErrorCode = 0;
        $curlError = '';
        for ($attempt = 1; $attempt <= 2; $attempt++) {
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
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $response = curl_exec($handle);
            $httpStatus = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $curlErrorCode = curl_errno($handle);
            $curlError = curl_error($handle);
            curl_close($handle);
            if ($response !== false) {
                break;
            }
        }

        if ($response === false) {
            $detail = 'curl#' . $curlErrorCode . ' ' . mb_substr($curlError, 0, 120);
            $this->log($feedbackId, 'transport_error ' . $detail);
            return ['status' => 'FAILED', 'http_status' => null, 'reason' => 'transport_error', 'detail' => $detail];
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            $providerResponse = json_decode((string) $response, true);
            $providerCode = is_array($providerResponse) && is_string($providerResponse['code'] ?? null)
                ? (string) preg_replace('/[^A-Za-z0-9_.-]/', '', $providerResponse['code'])
                : '';
            $providerMessage = is_array($providerResponse) && is_string($providerResponse['message'] ?? null)
                ? mb_substr(trim((string) preg_replace('/\s+/', ' ', $providerResponse['message'])), 0, 220)
                : mb_substr(trim(strip_tags((string) $response)), 0, 220);
            $detail = $this->describe($httpStatus, $providerCode, $providerMessage);
            $this->log($feedbackId, 'HTTP ' . $httpStatus . ' code=' . ($providerCode ?: 'unknown') . ' message=' . $providerMessage);

            return ['status' => 'FAILED', 'http_status' => $httpStatus, 'reason' => 'provider_rejected', 'detail' => $detail];
        }

        return ['status' => 'SENT', 'http_status' => $httpStatus, 'reason' => 'sent'];
    }

    /** Terjemahan singkat penyebab yang paling sering terjadi, supaya langsung terbaca di notifikasi. */
    private function describe(int $httpStatus, string $code, string $message): string
    {
        $lower = strtolower($message);
        if ($httpStatus === 401 && (str_contains($lower, 'ip') || str_contains($lower, 'unrecognised'))) {
            return 'HTTP 401: IP server hosting belum diizinkan di Brevo (Security > Authorised IPs)';
        }
        if ($httpStatus === 401) {
            return 'HTTP 401: API key Brevo ditolak (salah, dicabut, atau bukan API v3)';
        }
        if ($httpStatus === 400 && (str_contains($lower, 'sender') || str_contains($lower, 'not valid') || str_contains($lower, 'not active'))) {
            return 'HTTP 400: email pengirim belum diverifikasi di Brevo (Senders, Domains & Dedicated IPs)';
        }
        if ($httpStatus === 402 || $httpStatus === 403) {
            return 'HTTP ' . $httpStatus . ': akun Brevo dibatasi atau SMTP transaksional belum aktif';
        }
        if ($httpStatus === 429) {
            return 'HTTP 429: kuota kirim Brevo terlampaui';
        }

        return 'HTTP ' . $httpStatus . ($code !== '' ? ' ' . $code : '');
    }

    private function log(int $feedbackId, string $message): void
    {
        $line = 'Brevo feedback #' . $feedbackId . ' gagal: ' . $message;
        if (function_exists('app_early_log')) {
            app_early_log($line);
        }
        error_log($line);
    }
}
