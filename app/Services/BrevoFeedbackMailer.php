<?php
declare(strict_types=1);

namespace App\Services;

final class BrevoFeedbackMailer
{
    /** @return array{status: string, http_status: ?int, reason: string} */
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