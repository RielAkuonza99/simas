<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

final class FaqController
{
    public function index(): void
    {
        AuthMiddleware::requireLogin();
        $role = (string) (currentUser()['role'] ?? '');
        $roleTopics = $role === 'admin' ? [
            [
                'title' => 'Surat dan persetujuan',
                'questions' => [
                    ['Bagaimana menangani permintaan edit?', 'Buka Notifikasi, periksa surat dan alasan dari Pelayanan, lalu pilih Setujui atau Tolak. Permintaan yang masih menunggu tidak dapat dihapus.'],
                    ['Berapa lama akses edit berlaku?', 'Setelah disetujui, Pelayanan dapat mengedit surat itu selama 24 jam. Setelah masa berlaku habis, pengguna perlu mengajukan izin lagi.'],
                    ['Bagaimana mengekspor surat?', 'Di halaman Surat Masuk, pilih Ekspor. Pilih Per minggu (pilih tanggal mana pun di minggu itu), Per bulan (pilih bulan dan tahun), atau Seluruhnya. Lalu klik Unduh Excel atau Unduh PDF. Surat tertentu juga dapat dipilih lewat ID dan akan diekspor tanpa mengikuti rentang waktu.'],
                    ['Apa bedanya ekspor Excel dan PDF?', 'Isinya sama: satu tabel berisi nomor agenda, tanggal, asal, nomor, sifat, hal, rincian disposisi, status, dan bukti dukung, lengkap dengan ringkasan di bagian atas. Excel cocok untuk diolah atau difilter, PDF cocok untuk dicetak atau dibagikan. PDF dibatasi 500 surat per berkas; untuk data lebih banyak gunakan Excel.'],
                    ['Apakah surat yang dihapus dapat dipulihkan?', 'Tidak. Penghapusan menghapus surat beserta disposisinya. Periksa data yang dipilih sebelum mengonfirmasi.'],
                ],
            ],
            [
                'title' => 'Akun dan aktivitas',
                'questions' => [
                    ['Bagaimana mengelola akun pengguna?', 'Buka Pengguna untuk menambah, mengubah role, menonaktifkan, atau menghapus akun. Administrator aktif terakhir tidak dapat dinonaktifkan atau diturunkan rolenya.'],
                    ['Bagaimana memeriksa sesi perangkat?', 'Buka Pengaturan untuk melihat aktivitas dan sesi. Sesi yang tidak dikenal dapat dicabut dari halaman tersebut.'],
                ],
            ],
        ] : [
            [
                'title' => 'Mencatat surat',
                'questions' => [
                    ['Bagaimana menambahkan surat masuk?', 'Pilih Tambah Surat, isi informasi surat dan disposisi, lalu simpan. Nomor agenda dibuat oleh sistem.'],
                    ['Bagaimana mengisi Tujuan disposisi?', 'Ketik tujuan langsung pada kolom Tujuan; saran seperti Kasubbag umum, Sekertaris, dan Kepala Badan muncul saat mengetik. Gunakan Tambah tujuan untuk menambah kolom, maksimal 5 tujuan per tahap. Satu kolom berisi satu tujuan, tanpa koma.'],
                    ['Di mana melihat surat yang sudah dicatat?', 'Buka Surat Masuk. Gunakan pencarian atau filter tanggal untuk menemukan surat, lalu pilih barisnya untuk melihat rincian.'],
                ],
            ],
            [
                'title' => 'Mengubah surat dan menghubungi admin',
                'questions' => [
                    ['Bagaimana meminta izin edit?', 'Buka detail surat, pilih Edit, tulis alasan, lalu kirim permintaan. Surat belum dapat diubah sebelum admin menyetujuinya.'],
                    ['Berapa lama izin edit berlaku?', 'Izin yang disetujui berlaku 24 jam untuk surat tersebut. Ajukan izin baru jika waktunya sudah habis.'],
                    ['Bagaimana mengetahui keputusan admin?', 'Periksa Notifikasi di bagian atas. Keputusan admin akan muncul sebagai notifikasi.'],
                    ['Apa bedanya permintaan edit dan pengajuan?', 'Permintaan edit meminta hak untuk mengubah surat. Pengajuan mengirim pesan atau keterangan kepada admin tanpa meminta akses edit.'],
                    ['Apa yang dapat dilakukan role Pelayanan?', 'Pelayanan dapat melihat, mencari, dan menambahkan surat. Ekspor dan penghapusan surat hanya tersedia untuk Administrator.'],
                ],
            ],
        ];
        $commonTopics = [
            [
                'title' => 'Pencarian dan disposisi',
                'questions' => [
                    ['Apa yang dapat dicari?', 'Pencarian menerima ID surat, nomor agenda, nomor surat, asal surat, atau hal surat. Filter tanggal dapat digunakan bersama kata kunci.'],
                    ['Di mana melihat riwayat disposisi?', 'Buka detail surat untuk melihat tahap disposisinya. Riwayat seluruh surat tersedia dari menu Riwayat Disposisi.'],
                ],
            ],
            [
                'title' => 'Notifikasi dan bantuan',
                'questions' => [
                    ['Apa arti angka pada ikon Notifikasi?', 'Angka menunjukkan jumlah pesan yang belum dibaca. Pilih ikon untuk melihat ringkasan atau buka riwayat notifikasi.'],
                    ['Bagaimana mengirim laporan atau usulan?', 'Gunakan formulir Feedback untuk Developer di bawah panduan. Sertakan halaman yang bermasalah dan langkah untuk mengulanginya. Anda dapat melampirkan sampai 5 foto atau screenshot (JPEG, PNG, atau WebP, maksimal 5 MB per foto). Jangan lampirkan data surat yang bersifat rahasia.'],
                ],
            ],
        ];
        $notice = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        render('faq/index', [
            'pageTitle' => 'Panduan dan Feedback',
            'role' => $role,
            'roleLabel' => $role === 'admin' ? 'Administrator' : 'Pelayanan',
            'roleTopics' => $roleTopics,
            'commonTopics' => $commonTopics,
            'notice' => $notice,
        ]);
    }
}
