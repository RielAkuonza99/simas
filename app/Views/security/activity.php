<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';
$activityRoute = 'aktivitas';
$activityAnchor = '';
?>
<div class="page-head">
    <div class="page-head__main">
        <h2 class="page-title">Aktivitas &amp; Sesi</h2>
        <p class="page-sub"><?= $isAdmin ? 'Riwayat aktivitas dan sesi seluruh pengguna.' : 'Riwayat aktivitas serta sesi perangkat akun Anda.' ?></p>
    </div>
</div>

<?= ui_alert($notice) ?>

<div class="stack">
    <?php require __DIR__ . '/_activity-tables.php'; ?>
</div>
<?php require APP_ROOT . '/includes/footer.php'; ?>
