<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';

$profileId = (int) ($profile['id'] ?? 0);
$profileUsername = (string) ($profile['username'] ?? '');
$profileRole = (string) ($profile['role'] ?? '');
$formatBytes = static function (?int $bytes): string {
    if ($bytes === null) {
        return 'Tidak tersedia';
    }
    $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    $value = (float) $bytes;
    $unit = 0;
    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        $unit++;
    }

    return number_format($value, $unit === 0 ? 0 : 1, ',', '.') . ' ' . $units[$unit];
};
$chart = static function (string $title, string $unit, array $history, string $key, bool $bytes = false) use ($formatBytes): void {
    $values = array_map(
        static fn (array $sample): float => (float) $sample[$key],
        $history
    );
    $label = $bytes ? 'memori proses PHP' : $title;
    ?>
    <figure class="metric-chart">
        <figcaption>
            <span><?= e($title) ?></span>
            <span class="metric-chart-unit"><?= e($unit) ?></span>
        </figcaption>
        <?php if (count($values) < 2): ?>
            <p class="metric-chart-empty">Grafik tren muncul setelah ada sampel pada waktu yang berbeda. Sampel dicatat maksimal setiap lima menit saat admin membuka halaman ini.</p>
        <?php else:
            $width = 600;
            $height = 176;
            $left = 54;
            $right = 14;
            $top = 16;
            $bottom = 28;
            $plotWidth = $width - $left - $right;
            $plotHeight = $height - $top - $bottom;
            $minimum = min($values);
            $maximum = max($values);
            $range = max(1.0, $maximum - $minimum);
            $padding = $range * 0.12;
            $minimum -= $padding;
            $maximum += $padding;
            $range = $maximum - $minimum;
            $points = [];
            foreach ($values as $index => $value) {
                $x = $left + (count($values) === 1 ? 0 : $index * $plotWidth / (count($values) - 1));
                $y = $top + ($maximum - $value) * $plotHeight / $range;
                $points[] = number_format($x, 1, '.', '') . ',' . number_format($y, 1, '.', '');
            }
            $latestValue = $values[array_key_last($values)];
            $displayValue = $bytes
                ? $formatBytes((int) $latestValue)
                : number_format($latestValue, 2, ',', '.') . ' ' . $unit;
            ?>
            <div class="metric-chart__scroll"><svg class="metric-chart-svg" viewBox="0 0 <?= $width ?> <?= $height ?>" role="img" aria-label="<?= e($title . ', sampel terbaru ' . $displayValue) ?>">
                <title><?= e($title) ?></title>
                <desc>Grafik garis dari <?= count($values) ?> sampel yang dicatat pada 24 jam terakhir.</desc>
                <?php for ($line = 0; $line <= 3; $line++):
                    $y = $top + $line * $plotHeight / 3;
                    $tick = $maximum - $line * $range / 3;
                    $tickLabel = $bytes
                        ? $formatBytes((int) $tick)
                        : number_format($tick, 0, ',', '.') . ' ' . $unit;
                    ?>
                    <line x1="<?= $left ?>" y1="<?= number_format($y, 1, '.', '') ?>" x2="<?= $width - $right ?>" y2="<?= number_format($y, 1, '.', '') ?>" class="metric-gridline" />
                    <text x="<?= $left - 8 ?>" y="<?= number_format($y + 4, 1, '.', '') ?>" text-anchor="end" class="metric-axis-label"><?= e($tickLabel) ?></text>
                <?php endfor; ?>
                <polyline points="<?= e(implode(' ', $points)) ?>" class="metric-line" />
                <?php foreach ($points as $point):
                    [$x, $y] = explode(',', $point);
                    ?>
                    <circle cx="<?= e($x) ?>" cy="<?= e($y) ?>" r="3.5" class="metric-point" />
                <?php endforeach; ?>
                <text x="<?= $left ?>" y="<?= $height - 5 ?>" class="metric-axis-label"><?= e(date('H:i', strtotime((string) $history[0]['sampled_at'] . ' UTC'))) ?></text>
                <text x="<?= $width - $right ?>" y="<?= $height - 5 ?>" text-anchor="end" class="metric-axis-label"><?= e(date('H:i', strtotime((string) $history[array_key_last($history)]['sampled_at'] . ' UTC'))) ?></text>
            </svg></div>
        <?php endif; ?>
    </figure>
    <?php
};
?>
<div class="page-head">
    <div class="page-head__main">
        <h2 class="page-title">Pengaturan</h2>
        <p class="page-sub">Profil akun dan informasi penggunaan aplikasi.</p>
    </div>
</div>

<?= ui_alert($notice) ?>

<div class="settings-stack">
<section class="card" aria-labelledby="profile-heading">
    <div class="card__head">
        <div>
            <h3 class="card__title" id="profile-heading">Profil pengguna</h3>
            <p class="card__desc">Identitas akun yang sedang digunakan.</p>
        </div>
    </div>
    <div class="profile-card">
        <?= ui_avatar($profileUsername, 'avatar--xl') ?>
        <dl class="profile-card__info kv">
            <div><dt>Username</dt><dd><?= e($profileUsername) ?></dd></div>
            <div><dt>Role</dt><dd><?= ui_badge(ui_role_label($profileRole), $profileRole === 'admin' ? 'info' : 'neutral') ?></dd></div>
            <div><dt>ID pengguna</dt><dd class="tnum">#<?= $profileId ?></dd></div>
        </dl>
    </div>
</section>

<?php if ($isAdmin && is_array($metrics)): ?>
    <?php
    $current = $metrics['current'];
    $diskTotal = $current['disk_total_bytes'];
    $diskFree = $current['disk_free_bytes'];
    $diskUsedPercent = $diskTotal && $diskFree !== null
        ? max(0, min(100, (int) round((1 - $diskFree / $diskTotal) * 100)))
        : null;
    ?>
    <section class="card" id="developer-tools" data-devtools-live data-metrics-url="<?= e(url('pengaturan/developer-tools/metrics')) ?>" aria-labelledby="system-heading">
        <div class="card__head">
            <div>
                <h3 class="card__title" id="system-heading">Developer tools</h3>
                <p class="card__desc">Status teknis untuk admin. Fokus pada performa aplikasi dan integrasi internal.</p>
            </div>
            <span class="badge badge--info" data-live-updated role="status">Memuat data terkini...</span>
        </div>

        <div class="readings">
            <div class="reading">
                <span>Memori PHP</span>
                <strong data-live-metric="php_memory_bytes" data-live-format="bytes"><?= e($formatBytes((int) $current['php_memory_bytes'])) ?></strong>
            </div>
            <div class="reading">
                <span>Latency database</span>
                <strong data-live-metric="database_latency_ms" data-live-format="milliseconds"><?= e(number_format((float) $current['database_latency_ms'], 2, ',', '.')) ?> ms</strong>
            </div>
            <div class="reading">
                <span>Ruang aplikasi</span>
                <strong data-live-metric="disk_free_bytes" data-live-format="bytes"><?= e($formatBytes($current['disk_free_bytes'])) ?></strong>
                <small data-live-disk-used><?= $diskUsedPercent === null ? 'Kapasitas volume tidak tersedia.' : e($diskUsedPercent . '% terpakai.') ?></small>
                <?php if ($diskUsedPercent !== null): ?><div class="progress" data-live-disk-bar style="--value: <?= (int) $diskUsedPercent ?>%"><div class="progress__bar"></div></div><?php endif; ?>
            </div>
            <div class="reading">
                <span>Runtime</span>
                <strong>PHP <?= e(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) ?></strong>
                <small><?= e(APP_ENVIRONMENT) ?> mode</small>
            </div>
        </div>

        <div class="chart-grid">
            <?php $chart('Memori proses PHP', 'bytes', $metrics['history'], 'php_memory_bytes', true); ?>
            <?php $chart('Respons database', 'ms', $metrics['history'], 'database_latency_ms'); ?>
        </div>
    </section>

    <section class="card" aria-labelledby="network-heading">
        <div class="card__head">
            <div>
                <h3 class="card__title" id="network-heading">Koneksi dan jaringan</h3>
                <p class="card__desc">Statistik koneksi browser saat ini. Nilainya berubah mengikuti kondisi perangkat.</p>
            </div>
        </div>
        <dl class="readings">
            <div class="reading"><dt>Koneksi perangkat</dt><dd data-network-state role="status">Memeriksa koneksi…</dd></div>
            <div class="reading"><dt>Jenis koneksi browser</dt><dd data-network-type>Tidak tersedia di browser ini</dd></div>
            <div class="reading"><dt>Perkiraan RTT browser</dt><dd data-network-rtt>Tidak tersedia</dd></div>
        </dl>
        <p class="note">Jenis koneksi dan RTT merupakan estimasi browser dan tidak dikirim ke server.</p>
    </section>

    <section class="card" aria-labelledby="api-heading">
        <div class="card__head">
            <div>
                <h3 class="card__title" id="api-heading">Integrasi dan sinkronisasi</h3>
                <p class="card__desc">Status sinkronisasi metadata dibandingkan dengan jumlah surat di database.</p>
            </div>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('sinkronisasi')) ?>"><?= ui_icon('sync') ?>Buka antrean</a>
        </div>
        <dl class="queue-grid">
            <div class="queue-tile"><dt>Surat internal</dt><dd data-live-metric="archive_total"><?= (int) $metrics['current']['archive_total'] ?></dd></div>
            <div class="queue-tile"><dt>Menunggu</dt><dd data-live-metric="queue_PENDING"><?= (int) $metrics['queue']['PENDING'] ?></dd></div>
            <div class="queue-tile"><dt>Diproses</dt><dd data-live-metric="queue_PROCESSING"><?= (int) $metrics['queue']['PROCESSING'] ?></dd></div>
            <div class="queue-tile"><dt>Terkonfirmasi</dt><dd data-live-metric="queue_CONFIRMED"><?= (int) $metrics['queue']['CONFIRMED'] ?></dd></div>
            <div class="queue-tile"><dt>Gagal</dt><dd data-live-metric="queue_FAILED"><?= (int) $metrics['queue']['FAILED'] ?></dd></div>
        </dl>
    </section>
<?php elseif (!$isAdmin): ?>
    <?= ui_alert(['type' => 'info', 'message' => 'Informasi performa sistem dan status sinkronisasi hanya tersedia untuk administrator.']) ?>
<?php elseif ($metricsUnavailable): ?>
    <div class="alert alert--bad" role="alert">
        <?= ui_icon('alert-circle', 'alert__icon') ?>
        <div class="alert__body">
            <strong>Metrik sistem belum tersedia</strong>
            <div>Profil tetap dapat digunakan. Periksa koneksi MySQL dan pastikan migrasi 007 sudah diterapkan, lalu muat ulang halaman ini.</div>
        </div>
    </div>
<?php endif; ?>

<?php if ($isAdmin): ?>
    <section class="card" id="developer-tools-logs" aria-labelledby="logs-heading">
        <div class="card__head">
            <div>
                <h3 class="card__title" id="logs-heading">Log aplikasi</h3>
                <p class="card__desc">Bersihkan file error teknis. Change log dan riwayat sesi tidak ikut dihapus.</p>
            </div>
            <span class="badge"><?= e($formatBytes($appLogBytes)) ?></span>
        </div>
        <div class="log-tools">
            <span class="muted text-sm">Ukuran file app.log saat ini.</span>
            <form method="post" action="<?= e(url('pengaturan/developer-tools/logs/clear')) ?>"
                  data-confirm
                  data-confirm-title="Bersihkan app.log?"
                  data-confirm-message="Isi app.log akan dikosongkan. Change log dan riwayat sesi tetap tersimpan."
                  data-confirm-label="Bersihkan"
                  data-confirm-tone="danger">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <button class="btn btn-quiet-danger btn-sm" type="submit"><?= ui_icon('trash') ?>Bersihkan app.log</button>
            </form>
        </div>
    </section>
<?php endif; ?>
</div>

<?php require APP_ROOT . '/app/Views/security/activity-content.php'; ?>
<?php require APP_ROOT . '/includes/footer.php'; ?>
