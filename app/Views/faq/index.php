<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';
$isAdmin = $role === 'admin';
$roleLabel = $isAdmin ? 'Administrator' : 'Pelayanan';
$roleQuestions = $isAdmin ? [
    ['Bagaimana menangani permintaan edit surat?', 'Buka Notifikasi, lalu pilih Setujui atau Tolak. Keputusan sekaligus menandai permintaan sebagai dibaca. Permintaan yang masih menunggu tidak dapat dihapus.'],
    ['Apa yang terjadi saat permintaan disetujui?', 'Pelayanan dapat mengedit surat tersebut selama 24 jam. Setelah batas itu lewat, Pelayanan perlu mengirim permintaan baru.'],
    ['Bagaimana mengekspor surat?', 'Pilih Ekspor di halaman Surat Masuk. Tentukan minggu, bulan, tahun, atau seluruh data. Untuk memilih surat tertentu, cari ID lalu centang hasilnya. Pilihan ID memakai daftar surat terpilih, bukan rentang waktu.'],
    ['Apa isi berkas CSV dan PDF?', 'Kedua format memuat informasi surat, pencatatan, status, serta rincian disposisi dan lampirannya. CSV cocok untuk dibuka sebagai lembar kerja; PDF menyusun data surat dan disposisi dalam tabel.'],
    ['Bagaimana mengelola akun?', 'Buka menu Pengguna untuk menambah, mengubah role, menonaktifkan, atau menghapus akun. Akun administrator aktif terakhir tidak dapat dinonaktifkan atau diturunkan rolenya.'],
    ['Apakah surat yang dihapus dapat dipulihkan?', 'Tidak. Penghapusan surat menghapus data surat beserta disposisinya dari aplikasi. Periksa ID dan isi surat sebelum mengonfirmasi.'],
] : [
    ['Bagaimana menambahkan surat masuk?', 'Pilih Tambah Surat, isi informasi surat, lalu simpan. Surat yang sudah tercatat akan muncul di daftar Surat Masuk.'],
    ['Bagaimana meminta izin untuk mengedit surat?', 'Buka surat lalu pilih Edit. Tulis alasan permintaan dan kirim. Tunggu keputusan admin; selama status masih menunggu, surat belum dapat diubah.'],
    ['Berapa lama izin edit berlaku?', 'Izin yang disetujui berlaku selama 24 jam untuk surat tersebut. Setelah lewat, kirim permintaan edit baru.'],
    ['Bagaimana mengetahui keputusan admin?', 'Periksa Notifikasi di bagian atas. Permintaan yang disetujui atau ditolak akan ditandai sudah dibaca setelah admin mengambil keputusan.'],
    ['Untuk apa bagian Pengajuan pada detail surat?', 'Gunakan bagian Pengajuan untuk mengirim pesan atau keterangan kepada admin. Pengajuan ini berbeda dari permintaan izin edit.'],
    ['Apakah Pelayanan dapat menghapus surat atau mengekspor data?', 'Tidak. Penghapusan surat dan ekspor tersedia untuk Administrator. Pelayanan dapat melihat, mencari, menambah surat, serta meminta persetujuan edit.'],
];
$commonQuestions = [
    ['Bagaimana mencari surat?', 'Gunakan kolom pencarian untuk mencari berdasarkan ID, nomor agenda, nomor surat, asal, atau hal. Filter tanggal dapat dipakai bersamaan.'],
    ['Di mana melihat disposisi?', 'Buka surat dari daftar Surat Masuk untuk melihat rincian disposisi. Riwayat disposisi tersedia dari navigasi samping.'],
    ['Apa arti angka merah pada ikon notifikasi?', 'Angka itu menunjukkan jumlah notifikasi yang belum dibaca. Buka ikon untuk melihat ringkasan dan pilih Lihat selengkapnya untuk membaca riwayat.'],
];
?>
<div class="page-head">
    <div class="page-head__main">
        <h2 class="page-title">Panduan Penggunaan</h2>
        <p class="page-sub">Jawaban untuk <?= e($roleLabel) ?> dan pertanyaan umum.</p>
    </div>
</div>

<section class="faq-section" aria-labelledby="faq-role-heading">
    <div class="faq-section__head">
        <span class="faq-role-label"><?= e($roleLabel) ?></span>
        <h3 id="faq-role-heading">Panduan sesuai akses Anda</h3>
    </div>
    <div class="faq-list">
        <?php foreach ($roleQuestions as $index => [$question, $answer]): ?>
            <details class="faq-item" <?= $index === 0 ? 'open' : '' ?>>
                <summary><?= e($question) ?></summary>
                <div class="faq-item__answer"><p><?= e($answer) ?></p></div>
            </details>
        <?php endforeach; ?>
    </div>
</section>

<section class="faq-section" aria-labelledby="faq-common-heading">
    <div class="faq-section__head"><h3 id="faq-common-heading">Pertanyaan umum</h3></div>
    <div class="faq-list">
        <?php foreach ($commonQuestions as [$question, $answer]): ?>
            <details class="faq-item">
                <summary><?= e($question) ?></summary>
                <div class="faq-item__answer"><p><?= e($answer) ?></p></div>
            </details>
        <?php endforeach; ?>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
