<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';

$statusGloss = [
    'PENDING' => 'Menunggu',
    'PROCESSING' => 'Diproses',
    'SENT' => 'Terkirim',
    'CONFIRMED' => 'Terkonfirmasi',
    'FAILED' => 'Gagal',
    'SUPERSEDED' => 'Digantikan',
];
$connected = $provider['status'] === 'Terhubung';
?>
<div class="page-head">
    <div class="page-head__main">
        <h2 class="page-title">Sinkronisasi metadata arsip</h2>
        <p class="page-sub">Kirim snapshot surat internal ke Supabase. Metadata yang sudah tersimpan tetap dipertahankan saat surat di internal dihapus.</p>
    </div>
</div>

<?= ui_alert($notice) ?>

<div class="stack">
<section class="card" aria-labelledby="provider-heading">
    <div class="card__head">
        <h3 class="card__title" id="provider-heading">Status koneksi Supabase</h3>
        <span class="sync-connection-status" role="status"><?= ui_badge((string) $provider['status'], $connected ? 'ok' : 'bad', true) ?></span>
    </div>
    <div class="card__body"><p><?= e($provider['message']) ?></p></div>
</section>

<section class="card" aria-labelledby="queue-heading">
    <div class="card__head">
        <div>
            <h3 class="card__title" id="queue-heading">Status antrean</h3>
            <p class="card__desc">Status dihitung dari event terbaru untuk setiap ID arsip.</p>
        </div>
    </div>
    <dl class="queue-grid">
        <?php foreach ($summary as $status => $total): ?>
            <div class="queue-tile<?= $status === 'FAILED' && (int) $total > 0 ? ' queue-tile--bad' : '' ?><?= $status === 'CONFIRMED' && (int) $total > 0 ? ' queue-tile--ok' : '' ?>">
                <dt><?= e($status) ?><?php if (isset($statusGloss[$status])): ?><small><?= e($statusGloss[$status]) ?></small><?php endif; ?></dt>
                <dd><?= number_format((int) $total, 0, ',', '.') ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
</section>

<section class="card" aria-labelledby="manual-sync-heading">
    <div class="card__head">
        <div>
            <h3 class="card__title" id="manual-sync-heading">Kontrol sinkronisasi</h3>
            <p class="card__desc">Push menyiapkan surat baru atau berubah. Sinkronkan sekarang mengantrekan surat yang belum terkonfirmasi, lalu mengirim antrean ke Supabase.</p>
        </div>
    </div>
    <div class="sync-controls">
        <form method="post" action="<?= e(url('sinkronisasi/push')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <button class="btn btn-secondary" type="submit" data-loading-text="Menyiapkan…"><?= ui_icon('inbox') ?>Push data internal</button>
        </form>
        <form method="post" action="<?= e(url('sinkronisasi/proses')) ?>" data-manual-sync-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="force_snapshot" value="1" data-force-snapshot>
            <button class="btn btn-primary" type="button" data-manual-sync><?= ui_icon('sync') ?>Sinkronkan sekarang</button>
        </form>
        <span class="sync-run-status" data-sync-run-status role="status" aria-live="polite"></span>
    </div>
</section>

<section class="card" aria-labelledby="external-data-heading">
    <div class="card__head">
        <div>
            <h3 class="card__title" id="external-data-heading">Data surat di Supabase</h3>
            <p class="card__desc">Menampilkan <?= number_format(count($provider['items']), 0, ',', '.') ?> baris terbaru.</p>
        </div>
    </div>
    <?php if (!$connected): ?>
        <?= ui_empty_state('sync', 'Preview belum tersedia', 'Preview belum tersedia sampai koneksi Supabase berhasil.') ?>
    <?php elseif (!$provider['items']): ?>
        <?= ui_empty_state('inbox', 'Belum ada metadata surat', 'Belum ada metadata surat di Supabase.') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table sync-preview-table">
                <thead>
                <tr><th>ID Pencarian</th><th>No Agenda</th><th>No Surat</th><th>Tanggal surat</th><th>Tanggal diterima</th><th>Surat dari</th><th>Pengusul</th><th>Hal</th><th>Sifat</th><th>Catatan</th><th>Disposisi</th><th>Status arsip</th><th>Dihapus dari internal</th><th>Link Drive</th></tr>
                </thead>
                <tbody>
                <?php foreach ($provider['items'] as $item): ?>
                    <?php
                    $driveUrl = trim((string) ($item['drive_url'] ?? ''));
                    $driveParts = $driveUrl !== '' ? parse_url($driveUrl) : false;
                    $safeDriveUrl = is_array($driveParts)
                        && strtolower((string) ($driveParts['scheme'] ?? '')) === 'https'
                        && in_array(strtolower((string) ($driveParts['host'] ?? '')), ['drive.google.com', 'docs.google.com'], true)
                        ? $driveUrl
                        : null;
                    $dispositions = is_array($item['dispositions'] ?? null) ? $item['dispositions'] : [];
                    $isDeleted = !empty($item['is_deleted']);
                    ?>
                    <tr>
                        <td class="code"><?= e($item['search_id'] ?? '-') ?></td>
                        <td class="tnum"><?= (int) ($item['agenda_number'] ?? 0) ?></td>
                        <td><?= e($item['letter_number'] ?? '-') ?></td>
                        <td class="nowrap"><?= e(formatTanggal($item['letter_date'] ?? null)) ?></td>
                        <td class="nowrap"><?= e(formatTanggal($item['received_date'] ?? null)) ?></td>
                        <td><?= e($item['sender'] ?? '-') ?></td>
                        <td><?= e($item['proposer'] ?? '-') ?></td>
                        <td><?= e($item['hal'] ?? '-') ?></td>
                        <td><?= e($item['nature'] ?: 'Tidak diisi') ?></td>
                        <td><?= e($item['catatan'] ?? '-') ?></td>
                        <td>
                            <?php if ($dispositions): ?>
                                <details class="inline-details">
                                    <summary><?= count($dispositions) ?> tahap</summary>
                                    <ol>
                                        <?php foreach ($dispositions as $disposition): ?>
                                            <li>
                                                <?= e($disposition['from'] ?? '-') ?> ke <?= e($disposition['to'] ?? '-') ?>
                                                <?php if (!empty($disposition['date'])): ?>, <?= e(formatTanggal($disposition['date'])) ?><?php endif; ?>
                                                <?php if (!empty($disposition['catatan'])): ?><div><?= e($disposition['catatan']) ?></div><?php endif; ?>
                                                <?php if (!empty($disposition['attachment_url'])): ?><div class="muted">Lampiran: <?= e($disposition['attachment_url']) ?></div><?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ol>
                                </details>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td><?= $isDeleted ? ui_badge('Diarsipkan', 'warn') : ui_badge('Aktif', 'ok') ?></td>
                        <td class="nowrap"><?= e($item['deleted_at'] ?? '-') ?></td>
                        <td>
                            <?php if ($safeDriveUrl): ?>
                                <a href="<?= e($safeDriveUrl) ?>" target="_blank" rel="noopener noreferrer">Buka Drive</a>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
</div>

<details class="sync-history">
    <summary><span id="history-heading">Riwayat pengiriman</span><small>Maksimal 50 event terbaru</small></summary>
    <div class="card">
        <?php if (!$events): ?>
            <?= ui_empty_state('clock', 'Belum ada metadata di antrean', 'Data surat baru dan perubahan berikutnya akan tercatat otomatis.') ?>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table data-table--stack">
                <thead>
                <tr><th>Waktu antre</th><th>ID pencarian</th><th>Operasi</th><th>Status</th><th>Percobaan</th><th>Terakhir dikirim</th><th>Konfirmasi</th><th>Error terakhir</th><th class="col-act">Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td class="nowrap" data-label="Waktu antre"><?= e($event['created_at']) ?></td>
                        <td class="code" data-label="ID pencarian"><?= e($event['search_id']) ?></td>
                        <td data-label="Operasi"><?= e($event['operation']) ?></td>
                        <td data-label="Status"><?= ui_badge((string) $event['status'], ui_status_tone((string) $event['status']), true) ?></td>
                        <td class="tnum" data-label="Percobaan"><?= number_format((int) $event['attempt_count'], 0, ',', '.') ?></td>
                        <td class="nowrap" data-label="Terakhir dikirim"><?= e($event['last_sent_at'] ?: '-') ?></td>
                        <td class="nowrap" data-label="Konfirmasi"><?= e($event['confirmed_at'] ?: '-') ?></td>
                        <td data-label="Error terakhir"><?= e($event['last_error'] ?: '-') ?></td>
                        <td class="col-act" data-label="Aksi">
                            <?php if ($event['status'] === 'FAILED'): ?>
                                <form method="post" action="<?= e(url('sinkronisasi/coba-lagi')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                                    <button class="btn btn-secondary btn-sm" type="submit" data-loading-text="Menjadwalkan…"><?= ui_icon('reset') ?>Jadwalkan ulang</button>
                                </form>
                            <?php else: ?><span class="muted">-</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</details>
<?php require APP_ROOT . '/includes/footer.php'; ?>
