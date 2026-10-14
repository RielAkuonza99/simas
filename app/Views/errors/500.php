<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Gangguan sistem | BAPPERIDA</title>
    <style>
        :root {
            color-scheme: light;
            font-family: "Public Sans", "Segoe UI", Arial, sans-serif;
            color: #161e29;
            background: #f4f6f9;
            font-synthesis: none;
            text-rendering: optimizeLegibility;
        }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; background: #f4f6f9; line-height: 1.55; }
        header { padding: 18px max(20px, calc((100% - 900px) / 2)); border-bottom: 1px solid #e1e5eb; background: #fff; }
        .brand { color: #161e29; font-size: .9rem; font-weight: 700; }
        main { width: min(100% - 32px, 760px); margin: 0 auto; padding: clamp(32px, 7vw, 72px) 0; }
        .panel { padding: clamp(24px, 5vw, 48px); border: 1px solid #e1e5eb; border-radius: 10px; background: #fff; }
        .status { margin: 0 0 12px; color: #b3261e; font-size: .875rem; font-weight: 700; }
        h1 { max-width: 14ch; margin: 0; font-size: clamp(2rem, 6vw, 3.25rem); line-height: 1.12; }
        .intro { max-width: 62ch; margin: 18px 0 0; color: #3a4553; }
        .technical { margin-top: 32px; padding-top: 24px; border-top: 1px solid #e1e5eb; }
        h2 { margin: 0 0 14px; font-size: 1rem; }
        dl { display: grid; grid-template-columns: minmax(110px, 150px) minmax(0, 1fr); gap: 10px 16px; margin: 0; }
        dt { color: #3a4553; font-weight: 600; }
        dd { min-width: 0; margin: 0; overflow-wrap: anywhere; }
        code { color: #06476f; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: .875rem; }
        .action { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; margin-top: 28px; padding: 0 18px; border-radius: 6px; background: #0b5e91; color: #fff; font-weight: 650; text-decoration: none; }
        .action:hover { background: #084a74; }
        .action:focus-visible { outline: 3px solid #161e29; outline-offset: 3px; }
        @media (max-width: 480px) {
            header { padding: 16px; }
            main { width: min(100% - 24px, 760px); padding: 24px 0; }
            .panel { padding: 22px 18px; }
            dl { grid-template-columns: 1fr; gap: 3px; }
            dd + dt { margin-top: 10px; }
        }
    </style>
</head>
<body>
    <header><div class="brand">Sistem Surat Masuk BAPPERIDA</div></header>
    <main>
        <section class="panel" aria-labelledby="error-title">
            <p class="status">Kesalahan server 500</p>
            <h1 id="error-title">Terjadi kesalahan sistem.</h1>
            <p class="intro">Permintaan belum dapat diproses. Rincian teknis tidak ditampilkan di halaman ini demi keamanan.</p>
            <section class="technical" aria-labelledby="technical-title">
                <h2 id="technical-title">Informasi untuk teknisi</h2>
                <dl>
                    <dt>ID insiden</dt><dd><code><?= $escape($referenceId) ?></code></dd>
                    <dt>Permintaan</dt><dd><code><?= $escape($requestMethod . ' ' . $requestPath) ?></code></dd>
                    <dt>Waktu</dt><dd><time datetime="<?= $escape($occurredAt) ?>"><?= $escape($occurredAt) ?></time></dd>
                    <dt>Log server</dt><dd><code><?= $escape($logPath) ?></code></dd>
                </dl>
                <p class="intro">Cari ID insiden di log aplikasi untuk melihat exception dan stack trace.</p>
            </section>
            <a class="action" href="<?= $escape($homeUrl) ?>">Kembali ke beranda</a>
        </section>
    </main>
</body>
</html>