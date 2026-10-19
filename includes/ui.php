<?php
declare(strict_types=1);

/*
 * Presentation helpers for the shared UI. Markup only: nothing here reads or
 * writes application state, and nothing changes what a view is allowed to see.
 */

if (!function_exists('ui_icon')) {
    function ui_icon(string $name, string $class = ''): string
    {
        return '<svg class="' . e(trim('icon ' . $class)) . '" aria-hidden="true" focusable="false"><use href="#i-' . e($name) . '"></use></svg>';
    }
}

if (!function_exists('ui_sprite')) {
    /** Inline SVG sprite, printed once per page by includes/header.php. */
    function ui_sprite(): void
    {
        $symbols = [
            'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
            'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
            'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h6"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'sync' => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1a1.7 1.7 0 0 1-2.4 2.4l-.1-.1a1.7 1.7 0 0 0-2.9 1.2v.2a1.7 1.7 0 0 1-3.4 0v-.2a1.7 1.7 0 0 0-2.9-1.2l-.1.1a1.7 1.7 0 0 1-2.4-2.4l.1-.1a1.7 1.7 0 0 0-1.2-2.9H4a1.7 1.7 0 0 1 0-3.4h.2a1.7 1.7 0 0 0 1.2-2.9l-.1-.1a1.7 1.7 0 0 1 2.4-2.4l.1.1a1.7 1.7 0 0 0 2.9-1.2V2a1.7 1.7 0 0 1 3.4 0v.2a1.7 1.7 0 0 0 2.9 1.2l.1-.1a1.7 1.7 0 0 1 2.4 2.4l-.1.1a1.7 1.7 0 0 0 1.2 2.9h.2a1.7 1.7 0 0 1 0 3.4h-.2a1.7 1.7 0 0 0-1.2 2.9z"/>',
            'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
            'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
            'eye-off' => '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.53 13.53 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><path d="m2 2 20 20"/>',
            'pencil' => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z"/><path d="m15 5 4 4"/>',
            'trash' => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6M14 11v6"/>',
            'external' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
            'arrow-left' => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
            'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
            'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
            'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
            'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
            'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
            'check-circle' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
            'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
            'alert-circle' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
            'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
            'paperclip' => '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
            'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
            'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
            'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
            'reset' => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
            'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
            'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
            'mail-plus' => '<path d="M22 13V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12c0 1.1.9 2 2 2h8"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/><path d="M19 16v6M16 19h6"/>',
            'help-circle' => '<circle cx="12" cy="12" r="10"/><path d="M9.6 9a2.5 2.5 0 0 1 4.8 1c0 1.7-2.4 2-2.4 3.8"/><path d="M12 17h.01"/>',
        ];

        echo '<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>';
        foreach ($symbols as $name => $paths) {
            echo '<symbol id="i-' . $name . '" viewBox="0 0 24 24">' . $paths . '</symbol>';
        }
        echo '</defs></svg>';
    }
}

if (!function_exists('ui_badge')) {
    /** Tone: neutral | ok | warn | bad | info | dark */
    function ui_badge(string $label, string $tone = 'neutral', bool $dot = false): string
    {
        $tone = in_array($tone, ['neutral', 'ok', 'warn', 'bad', 'info', 'dark'], true) ? $tone : 'neutral';

        return '<span class="badge badge--' . $tone . ($dot ? ' badge--dot' : '') . '">' . e($label) . '</span>';
    }
}

if (!function_exists('ui_alert')) {
    /** Renders a flash notice of shape ['type' => ..., 'message' => ...]. */
    function ui_alert(?array $notice, bool $asToast = false, string $actionLabel = '', string $actionUrl = ''): string
    {
        if (!$notice || trim((string) ($notice['message'] ?? '')) === '') {
            return '';
        }

        $tone = match ((string) ($notice['type'] ?? 'info')) {
            'success' => 'ok',
            'warning' => 'warn',
            'danger', 'error' => 'bad',
            default => 'info',
        };
        $icon = ['ok' => 'check-circle', 'warn' => 'alert-triangle', 'bad' => 'alert-circle', 'info' => 'info'][$tone];
        $role = in_array($tone, ['warn', 'bad'], true) ? 'alert' : 'status';
        $toastAttributes = $asToast ? ' data-toast-source data-toast-tone="' . e($tone) . '"' : '';
        if ($asToast && $actionLabel !== '' && $actionUrl !== '') {
            $toastAttributes .= ' data-toast-action-label="' . e($actionLabel) . '" data-toast-action-url="' . e($actionUrl) . '"';
        }

        return '<div class="alert alert--' . $tone . '" role="' . $role . '" data-dismissible' . $toastAttributes . '>'
            . ui_icon($icon, 'alert__icon')
            . '<div class="alert__body">' . e($notice['message']) . '</div>'
            . '</div>';
    }
}

if (!function_exists('ui_error_summary')) {
    /** Server-side validation errors, shown above a form. */
    function ui_error_summary(array $errors, string $title = 'Data belum dapat disimpan'): string
    {
        if (!$errors) {
            return '';
        }

        $items = '';
        foreach ($errors as $error) {
            $items .= '<li>' . e($error) . '</li>';
        }

        return '<div class="alert alert--bad" role="alert" tabindex="-1" data-error-summary>'
            . ui_icon('alert-circle', 'alert__icon')
            . '<div class="alert__body"><strong>' . e($title) . '</strong><ul class="alert__list">' . $items . '</ul></div>'
            . '</div>';
    }
}

if (!function_exists('ui_disposisi_meter')) {
    /** Five-segment progress meter for the number of disposition stages (0-5). */
    function ui_disposisi_meter(int $count): string
    {
        $count = min(max($count, 0), 5);
        $segments = '';
        for ($i = 1; $i <= 5; $i++) {
            $segments .= '<i' . ($i <= $count ? ' class="on"' : '') . '></i>';
        }

        return '<span class="meter' . ($count === 0 ? ' is-empty' : '') . '" role="img" aria-label="Disposisi ' . $count . ' dari 5 tahap">'
            . '<span class="meter__segs" aria-hidden="true">' . $segments . '</span>'
            . '<span class="meter__text" aria-hidden="true">' . $count . '/5</span></span>';
    }
}

if (!function_exists('ui_avatar')) {
    function ui_avatar(string $name, string $class = ''): string
    {
        $initial = mb_strtoupper(mb_substr(trim($name) !== '' ? trim($name) : '?', 0, 1));

        return '<span class="avatar ' . e($class) . '" aria-hidden="true">' . e($initial) . '</span>';
    }
}

if (!function_exists('ui_role_label')) {
    function ui_role_label(?string $role): string
    {
        return $role === 'admin' ? 'Administrator' : 'Pelayanan';
    }
}

if (!function_exists('ui_empty_state')) {
    /** $actionHtml must already be escaped markup (a button or link). */
    function ui_empty_state(string $icon, string $title, string $text = '', string $actionHtml = ''): string
    {
        return '<div class="empty-state">'
            . '<span class="empty-state__icon">' . ui_icon($icon) . '</span>'
            . '<h3 class="empty-state__title">' . e($title) . '</h3>'
            . ($text !== '' ? '<p class="empty-state__text">' . e($text) . '</p>' : '')
            . ($actionHtml !== '' ? '<div class="empty-state__action">' . $actionHtml . '</div>' : '')
            . '</div>';
    }
}

if (!function_exists('ui_status_tone')) {
    /** Maps known status / action words to a badge tone. Unknown words stay neutral. */
    function ui_status_tone(string $value): string
    {
        return match (strtoupper(trim($value))) {
            'AKTIF', 'CONFIRMED', 'TERHUBUNG', 'CREATE', 'LOGIN', 'USER_CREATED' => 'ok',
            'PENDING', 'PROCESSING', 'SENT', 'UPDATE', 'USER_UPDATED' => 'info',
            'FAILED', 'DELETE', 'USER_DELETED', 'LOGIN_FAILURE', 'AUTHORIZATION_FAILURE', 'DICABUT' => 'bad',
            'KEDALUWARSA', 'DIARSIPKAN' => 'warn',
            default => 'neutral',
        };
    }
}
