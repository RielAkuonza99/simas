<?php
/*
 * Shared tables for "Perangkat dan sesi" and "Change log".
 * Expects: $sessions, $logs, $isAdmin, $activityRoute ('pengaturan' | 'aktivitas'), $activityAnchor ('#activity' | '').
 */
?>
<section class="card" aria-labelledby="sessions-heading">
    <div class="card__head">
        <h3 class="card__title" id="sessions-heading">Perangkat dan sesi</h3>
        <span class="muted text-sm"><?= number_format($sessions['total'], 0, ',', '.') ?> riwayat</span>
    </div>
    <?php if (!$sessions['items']): ?>
        <?= ui_empty_state('shield', 'Belum ada sesi perangkat', 'Login berhasil akan tercatat di sini.') ?>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table data-table--stack">
            <thead>
            <tr>
                <?php if ($isAdmin): ?><th>Pengguna</th><?php endif; ?>
                <th>Sesi / perangkat</th><th>IP</th><th>Login (UTC)</th><th>Terakhir aktif (UTC)</th><th>Berlaku sampai (UTC)</th><th>Status</th><th class="col-act">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($sessions['items'] as $session): ?>
                <tr>
                    <?php if ($isAdmin): ?><td data-label="Pengguna"><?= e($session['username']) ?></td><?php endif; ?>
                    <td data-label="Sesi / perangkat">
                        <div>
                            <span class="code">#<?= (int) $session['id'] ?></span>
                            <span class="cell-sub"><?= e(describeUserAgent($session['user_agent'])) ?></span>
                            <?php if ($session['user_agent']): ?>
                                <details class="inline-details">
                                    <summary>Detail teknis</summary>
                                    <code><?= e($session['user_agent']) ?></code>
                                </details>
                            <?php endif; ?>
                            <?php if ($session['status'] === 'Aktif' && (int) ($session['similar_active_sessions'] ?? 0) > 1): ?>
                                <span class="cell-sub"><?= (int) $session['similar_active_sessions'] ?> sesi aktif dengan browser dan IP yang sama diringkas di sini.</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="tnum" data-label="IP"><?= e($session['ip_address'] ?: '-') ?></td>
                    <td data-label="Login (UTC)"><?= e($session['created_at']) ?></td>
                    <td data-label="Terakhir aktif (UTC)"><?= e($session['last_seen_at']) ?></td>
                    <td data-label="Berlaku sampai (UTC)"><?= e($session['expires_at']) ?></td>
                    <td data-label="Status">
                        <span><?= ui_badge((string) $session['status'], ui_status_tone((string) $session['status']), true) ?><?php if ($session['revoked_by_username']): ?><span class="cell-sub">oleh <?= e($session['revoked_by_username']) ?></span><?php endif; ?></span>
                    </td>
                    <td class="col-act" data-label="Aksi">
                        <?php if ($session['status'] === 'Aktif'): ?>
                            <form method="post" action="<?= e(url('sesi/' . (int) $session['id'] . '/cabut')) ?>"
                                  data-confirm
                                  data-confirm-title="Cabut akses perangkat ini?"
                                  data-confirm-message="<?= e('Sesi #' . (int) $session['id'] . ' (' . describeUserAgent($session['user_agent']) . ') akan berakhir dan perangkat harus login ulang. Riwayat tetap disimpan untuk audit.') ?>"
                                  data-confirm-label="Cabut akses"
                                  data-confirm-tone="danger">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>">
                                <button class="btn btn-quiet-danger btn-sm" type="submit">Cabut akses</button>
                            </form>
                        <?php else: ?><span class="muted">-</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php if ($sessions['pages'] > 1): ?>
        <nav class="pagination" aria-label="Halaman riwayat sesi">
            <span class="pagination__summary">Halaman <?= (int) $sessions['page'] ?> dari <?= (int) $sessions['pages'] ?></span>
            <div class="pagination__links">
                <?php if ($sessions['page'] > 1): ?><a class="page-link page-link--nav" href="<?= e(url($activityRoute, ['sessions_page' => (int) $sessions['page'] - 1, 'logs_page' => (int) $logs['page']]) . $activityAnchor) ?>"><?= ui_icon('chevron-left') ?>Sebelumnya</a><?php endif; ?>
                <?php if ($sessions['page'] < $sessions['pages']): ?><a class="page-link page-link--nav" href="<?= e(url($activityRoute, ['sessions_page' => (int) $sessions['page'] + 1, 'logs_page' => (int) $logs['page']]) . $activityAnchor) ?>">Berikutnya<?= ui_icon('chevron-right') ?></a><?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</section>

<details class="card change-log-details">
    <summary class="card__head change-log-summary" aria-controls="change-log-content">
        <h3 class="card__title" id="audit-heading">Change log</h3>
        <span class="muted text-sm"><?= number_format($logs['total'], 0, ',', '.') ?> aktivitas</span>
        <?= ui_icon('chevron-down') ?>
    </summary>
    <div id="change-log-content">
    <?php if (!$logs['items']): ?>
        <?= ui_empty_state('activity', 'Belum ada aktivitas', 'Aktivitas pengguna akan tercatat di sini.') ?>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table data-table--stack">
            <thead><tr><th>Waktu (UTC)</th><th>Pengguna</th><th>Aktivitas</th><th>Modul</th><th>Data</th><th>Sesi</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs['items'] as $log): ?>
                <?php $details = $log['details'] ? json_decode($log['details'], true) : null; ?>
                <tr>
                    <td data-label="Waktu (UTC)"><?= e($log['created_at']) ?></td>
                    <td data-label="Pengguna"><?= e($log['actor_username'] ?: 'Pengguna dihapus') ?></td>
                    <td data-label="Aktivitas"><?= ui_badge((string) $log['action'], ui_status_tone((string) $log['action'])) ?></td>
                    <td data-label="Modul"><?= e($log['module']) ?></td>
                    <td data-label="Data">
                        <div>
                            <?= $log['record_id'] === null ? '-' : e($log['record_id']) ?>
                            <?php if (is_array($details) && $details !== []): ?><span class="cell-sub"><?= e(json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></span><?php endif; ?>
                        </div>
                    </td>
                    <td data-label="Sesi"><?= $log['auth_session_id'] === null ? '-' : '#' . (int) $log['auth_session_id'] ?></td>
                    <td class="tnum" data-label="IP"><?= e($log['ip_address'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php if ($logs['pages'] > 1): ?>
        <nav class="pagination" aria-label="Halaman change log">
            <span class="pagination__summary">Halaman <?= (int) $logs['page'] ?> dari <?= (int) $logs['pages'] ?></span>
            <div class="pagination__links">
                <?php if ($logs['page'] > 1): ?><a class="page-link page-link--nav" href="<?= e(url($activityRoute, ['sessions_page' => (int) $sessions['page'], 'logs_page' => (int) $logs['page'] - 1]) . $activityAnchor) ?>"><?= ui_icon('chevron-left') ?>Sebelumnya</a><?php endif; ?>
                <?php if ($logs['page'] < $logs['pages']): ?><a class="page-link page-link--nav" href="<?= e(url($activityRoute, ['sessions_page' => (int) $sessions['page'], 'logs_page' => (int) $logs['page'] + 1]) . $activityAnchor) ?>">Berikutnya<?= ui_icon('chevron-right') ?></a><?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
    </div>
</details>
