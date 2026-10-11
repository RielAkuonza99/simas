<?php
$activityRoute = 'pengaturan';
$activityAnchor = '#activity';
?>
<div id="activity" class="settings-activity">
    <div class="page-head">
        <div class="page-head__main">
            <h3 class="page-title">Aktivitas &amp; sesi</h3>
            <p class="page-sub"><?= $isAdmin ? 'Sesi aktif diringkas berdasarkan akun, browser, dan IP yang sama. Riwayat tetap tersimpan.' : 'Riwayat aktivitas serta sesi perangkat akun Anda.' ?></p>
        </div>
    </div>
    <div class="stack">
        <?php require __DIR__ . '/_activity-tables.php'; ?>
    </div>
</div>
