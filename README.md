# SIMAS (Sistem Informasi Surat Masuk)

> **Repo private.** Dokumentasi internal untuk developer. Bukan untuk publikasi.

Aplikasi web internal untuk mencatat agenda dan informasi surat masuk, alur disposisi (3-5 tahap), serta arsip metadata surat di lingkungan Bapperida. Akses hanya untuk pengguna yang sudah login. Semua aksi penting tercatat di audit log.

## Stack

| Komponen | Detail |
|---|---|
| Bahasa | PHP 8.x, `strict_types`, tanpa framework |
| Dependency PHP | Dompdf (Composer) untuk ekspor PDF |
| Database | MySQL lewat PDO (prepared statement asli, `EMULATE_PREPARES=false`) |
| Arsip eksternal | Supabase (REST, tabel `archive_surat_metadata`) |
| Dokumen surat | Tautan Google Drive (hanya host `drive.google.com` dan `docs.google.com`) |
| Hosting | InfinityFree (Apache, `.htaccess`) |
| Deploy | GitHub Actions → FTP (`SamKirkland/FTP-Deploy-Action`) |

## Struktur folder

```
app/
  bootstrap.php        Loader .env, error handler, autoload namespace App\
      Controllers/         Auth, Dashboard, Surat, User, Security, Settings, ArchiveSync, Notification, Health
  Services/            Logika bisnis (AuthService, SuratService, UserAdminService,
                       ArchiveSyncService, SupabaseArchiveClient)
      Models/              Akses database (User, Surat, AuthSession, Activity,
                                                                   AdminInbox, ArchiveSync, SystemMetrics)
  Middleware/          AuthMiddleware (login, role, sesi perangkat)
  Security/            AuditLogger, LoginRateLimiter
  Routing/Router.php   Router sederhana (GET/POST, parameter {id})
  Database/Connection.php   Singleton PDO
  Helpers/functions.php     e(), url(), redirect(), render(), csrfToken(), dll
      Views/               Template PHP per modul (auth, dashboard, surat, users,
                                                                   security, settings, archive-sync, disposition-history)
bin/                   Script CLI (tidak bisa dijalankan lewat web)
database/              SQL database bersih, demo, upgrade hosting, dan metadata Supabase
config/                app.php, security.php, auth.php, database.php
includes/              header, sidebar, footer, ui (partial layout)
public/                index.php, router.php (dev server), assets/ (css, js, font)
routes/web.php         Semua definisi route
storage/logs/          app.log, audit.log (diabaikan git)
storage/private/       login-attempts.json (diabaikan git)
.github/workflows/     deploy.yml
anti-slop/             Catatan audit UI dan follow-up (internal, tidak di-deploy)
.agents .claude .gemini .opencode/   Skill AI tools (tidak di-deploy)
```

## Alur request

```
Browser → .htaccess (root) → public/index.php
        → app/bootstrap.php (.env, error handler, security header, session)
        → routes/web.php → Router::dispatch()
        → Controller → AuthMiddleware → Service → Model (PDO) → Views/ + includes/
```

`.htaccess` di root memblokir akses langsung ke `/public/...`, mengarahkan `/assets/*` ke `public/assets/*`, dan melempar semua URL lain ke `public/index.php`. Folder `app/`, `config/`, dan `storage/` masing-masing punya `.htaccess` penolak akses.

## Route

| URL | Method | Akses | Fungsi |
|---|---|---|---|
| `/`, `/login` | GET, POST | publik | Halaman login |
| `/logout` | POST | login | Keluar |
| `/health` | GET | publik | Cek status app dan koneksi DB (JSON) |
| `/dashboard` | GET | login | Ringkasan surat |
| `/panduan` | GET | login | FAQ sesuai role dan pertanyaan umum |
| `/surat` | GET | login | Daftar dan pencarian surat |
| `/surat/{id}` | GET | login | Agenda, informasi, disposisi, dan pengajuan pelayanan |
| `/surat/tambah` | GET, POST | admin, pelayanan | Tambah surat |
| `/surat/{id}/edit` | GET, POST | admin atau pelayanan dengan persetujuan admin yang berlaku 24 jam | Ubah surat |
| `/surat/{id}/edit-request` | POST | pelayanan | Minta akses edit untuk satu surat |
| `/surat/{id}/pengajuan` | POST | pelayanan | Kirim pesan pengajuan ke admin |
| `/surat/{id}/hapus` | POST | admin | Hapus surat |
| `/surat/hapus-terpilih` | POST | admin | Hapus sampai 100 surat terpilih |
| `/surat/ekspor/cari` | GET | admin | Cari surat berdasarkan ID untuk ekspor terpilih |
| `/surat/ekspor`, `/surat/ekspor/pdf` | POST | admin | Ekspor CSV atau PDF berdasarkan periode atau ID terpilih |
| `/riwayat-disposisi` | GET | login | Riwayat tahap disposisi akhir |
| `/notifikasi` | GET | login | Daftar notifikasi akun |
| `/riwayat-notifikasi` | GET | login | Baca pesan lengkap dan kelola notifikasi |
| `/notifikasi/{id}/baca-riwayat`, `/hapus-riwayat` | POST | penerima notifikasi | Tandai dibaca atau hapus permanen; permintaan edit pending harus diputuskan dahulu |
| `/notifikasi/{id}/baca` | POST | penerima notifikasi | Tandai notifikasi setelah keputusan admin |
| `/notifikasi/bersihkan` | POST | login | Hapus permanen notifikasi yang bukan permintaan edit pending |
| `/pengajuan/{id}/keputusan` | POST | admin | Setujui atau tolak permintaan edit |
| `/pengguna` ... | GET, POST | admin | Kelola user (tambah, ubah, hapus) |
| `/aktivitas` | GET | login | Aktivitas dan sesi perangkat |
| `/sesi/{session_id}/cabut` | POST | login | Cabut sesi perangkat |
| `/pengaturan` | GET | login | Pengaturan dan developer tools |
| `/pengaturan/developer-tools/metrics` | GET | admin | Metrik sistem live |
| `/pengaturan/developer-tools/logs/clear` | POST | admin | Kosongkan app.log |
| `/sinkronisasi` | GET | admin | Status antrean sync Supabase |
| `/sinkronisasi/ekspor` | GET | admin | Ekspor metadata Supabase ke CSV (maksimal 100.000 baris) |
| `/sinkronisasi/push`, `/proses`, `/coba-lagi` | POST | admin | Kirim, proses, ulangi antrean |

Route lama (`/login.php`, `/surat-masuk.php`, dll) masih terdaftar sebagai kompatibilitas. Hapus kalau sudah tidak dipakai.

## Peran pengguna

Hanya ada dua role: **admin** dan **pelayanan**. Keduanya dapat melihat dan menambahkan surat. Admin dapat mengubah dan menghapus surat; Pelayanan harus memperoleh persetujuan admin per surat. Persetujuan berlaku 24 jam dan dapat dipakai untuk beberapa perubahan dalam jangka tersebut. Setelah 24 jam, Pelayanan perlu mengajukan persetujuan lagi. Permintaan edit yang menunggu hanya memiliki aksi Setujui dan Tolak; keputusan menandai notifikasi admin sebagai dibaca. Pelayanan dapat mengirim pengajuan dari detail surat dengan dialog konfirmasi yang menampilkan teks persis sebelum dikirim. Popup notifikasi hanya menampilkan ringkasan; pesan penuh dan penghapusan tersedia di riwayat. Admin aktif terakhir tidak bisa dinonaktifkan atau diturunkan rolenya. Admin dapat mengelola maksimal lima akun lain; akun admin yang sedang digunakan tidak dihitung. Tabel data (pengguna, riwayat disposisi, daftar surat, log aktivitas, pratinjau sinkronisasi) dan kartu ringkasan dashboard tidak lagi menumpuk ke bawah di layar sempit; semuanya digeser ke samping.

Halaman **Panduan** menampilkan FAQ yang berbeda untuk Administrator dan Pelayanan, ditambah pertanyaan umum.

Ekspor surat menyediakan minggu ini, bulan ini, tahun ini, atau seluruh surat. Admin juga dapat mencari dan mencentang ID tertentu; daftar ID terpilih menjadi sumber ekspor. CSV dan PDF memuat detail surat serta disposisi.

## Autentikasi dan keamanan

- Login memakai sesi PHP ditambah **cookie perangkat** `bapperida_device` (token 64 hex, di database disimpan sebagai SHA-256, berlaku 30 hari). Tabel: `auth_sessions`.
- Dependency PDF dipasang dari `composer.lock`; jalankan `composer install --no-dev` sebelum deploy manual. Workflow deploy memasang dependency dan mengirim folder `vendor/`.
- Sesi perangkat bisa dilihat dan dicabut dari menu aktivitas.
- **Rate limit login**: 5 percobaan gagal per 15 menit, disimpan di `storage/private/login-attempts.json`.
- **CSRF** pada semua form POST (`csrfToken()`, `validateCsrf()`).
- **Audit log** ke tabel `audit_logs` (login, otorisasi gagal, perubahan user, sync gagal, dll).
- Persetujuan edit berlaku 24 jam, keputusan terbaru menjadi sumber akses, dan pemeriksaan izin dikunci dalam transaksi penyimpanan. Inbox notifikasi dibatasi ke penerima; penghapusan hanya tersedia di riwayat.
- Popup notifikasi ditentukan server: penanda "terakhir ditampilkan" disimpan di sesi login per akun (`$_SESSION['toast_last_id']`), bukan di browser. Poll pertama setelah login hanya menetapkan penanda tanpa popup, sehingga refresh, cache, storage browser yang diblokir, maupun impor ulang SQL tidak memunculkan popup berulang atau menelan notifikasi baru. Status baca tetap terpisah.
- Aset statis dimuat lewat `asset()` dengan versi dari waktu ubah file (`?v=<filemtime>`), tidak perlu menaikkan nomor versi manual.
- Status login di Pengelolaan Pengguna dihitung dari `auth_sessions.last_seen_at` (diperbarui tiap 5 menit; jendela "Sedang login" 10 menit). Tidak ada perubahan skema.
- Semua form tulis memakai CSRF, output view di-escape, dan pesan notifikasi dirender sebagai teks.
- Ekspor CSV menetralkan awalan formula spreadsheet (`=`, `+`, `-`, `@`) pada nilai dari database.
- Header: CSP, `X-Frame-Options: DENY`, `nosniff`, HSTS (HTTPS), cookie `HttpOnly` dan `SameSite=Lax`.
- Output view wajib lewat `e()`.

## Data

Tabel yang dipakai kode:

| Tabel | Isi |
|---|---|
| `users` | Akun, role, status aktif |
| `auth_sessions` | Sesi perangkat |
| `surat_masuk` | Data surat |
| `surat_disposisi` | Disposisi per tahap (default tahap 1 sampai 3) |
| `surat_edit_requests` | Permintaan edit per surat dan pengajuan pelayanan |
| `admin_notifications` | Notifikasi per penerima, status baca, dan penghapusan |
| `surat_sequences` | Penomoran |
| `archive_sync_queue` | Antrean sinkron ke Supabase |
| `audit_logs` | Jejak audit |
| `system_metric_samples` | Sampel metrik sistem |

Struktur surat: **Agenda Surat** (`id`, nomor agenda, tanggal diterima), **Informasi Surat** (asal, nomor/tanggal, sifat, hal, bukti dukung), dan **Disposisi** (3 tahap awal, maksimal 5; pejabat, tujuan yang tersedia, tanggal, catatan). Tahap akhir disimpan pada `selesai_tahap`, beserta akun/tanggal pencatatan dan `link_selesai` opsional. Kolom lama `pengusul`, `catatan` isi surat, dan lampiran tahap dipertahankan pada database hosting agar migrasi tidak menghapus data historis, tetapi tidak dipakai form baru.

Untuk database lokal bersih, import `database/local-clean.sql`. File ini membuat database `bapperida_local`, mempertahankan akun admin `RielAkuonza`, dan mengosongkan data operasional.

`db-demo.sql` menyiapkan 100 surat contoh (50 selesai dan 50 belum). Pada database lokal yang sudah berjalan, gunakan `php bin/seed-demo-letters.php`; perintah ini hanya berjalan saat `APP_ENV=local` dan menolak seed duplikat.

Untuk database hosting yang sudah berisi data, backup database lalu pilih database aplikasi dan jalankan `database/db-host.sql` satu kali. Script bersifat aditif dan tidak menghapus kolom historis. Folder `database/` tidak ikut deploy otomatis, jadi upload/import manual melalui panel hosting. Jalankan `database/supabase-archive.sql` satu kali di SQL Editor Supabase untuk menambah kolom ID surat dan status disposisi akhir.

## Sinkronisasi arsip (Supabase)

Perubahan surat masuk ke `archive_sync_queue`, lalu dikirim ke Supabase oleh `ArchiveSyncService`. Metadata eksternal memuat ID surat internal, nomor agenda, status/tahap disposisi akhir, pejabat dan tanggal akhir, akun/waktu pencatatan, serta bukti dukung akhir.

Status antrean: `PENDING` → `PROCESSING` → `SENT` → `CONFIRMED`, atau `FAILED` (dicoba ulang dengan `available_at`).

Dijalankan dari menu `/sinkronisasi` (admin) atau CLI:

```bash
php bin/sync-archive.php --limit=10                    # proses antrean (1-100)
php bin/queue-archive-backfill.php --after-id=0 --limit=100   # masukkan data lama ke antrean
```

Backfill dijalankan berulang dengan `--after-id` dari hasil sebelumnya sampai `queued` bernilai 0. InfinityFree tidak menyediakan SSH, jadi di hosting gunakan tombol di menu sinkronisasi.

Ekspor internal tersedia di daftar surat untuk admin. Ekspor Supabase tersedia di `/sinkronisasi` dan membaca data per rentang hingga 100.000 baris. Jalankan migrasi Supabase sebelum memakai sinkronisasi dengan kontrak metadata baru.

## Konfigurasi (`.env`)

File `.env` ada di root, diabaikan git, dan **tidak di-deploy**. Buat manual di server lewat File Manager.

| Variabel | Fungsi |
|---|---|
| `APP_ENV` | `local` atau `production` |
| `APP_DEBUG` | `true` hanya untuk lokal |
| `APP_BASE_URL` | Kosongkan jika otomatis |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | Koneksi MySQL (wajib di production) |
| `SUPABASE_URL` | URL project Supabase |
| `SUPABASE_SERVICE_ROLE_KEY` | Kunci service role (rahasia, jangan dibagikan) |
| `SUPABASE_ARCHIVE_TABLE` | Default `archive_surat_metadata` |

## Setup lokal

Impor database dari folder proyek dengan MySQL/MariaDB:

```bash
mysql -u root -p < database/local-clean.sql
```

Atur `.env` agar `DB_NAME=bapperida_local`, lalu jalankan aplikasi:

```bash
git clone https://github.com/RielAkuonza99/<NAMA_REPO>.git
cd <NAMA_REPO>
composer install
# buat .env dengan variabel di atas (APP_ENV=local)
php -S localhost:8000 -t public public/router.php
```

Cek `http://localhost:8000/health`, harus menampilkan `"status": "ok"`.

## Deploy

Push ke `main` → `.github/workflows/deploy.yml` → FTP ke `/htdocs/` di InfinityFree.

Secrets GitHub: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`.

Tidak ikut di-deploy: `.git*`, `.env`, `.gemini`, `.claude`, `.agents`, `.opencode`, `anti-slop`, `database`, `storage/logs`, `storage/private`, semua `*.md`. Jalankan migrasi hosting dan Supabase secara manual sebelum versi aplikasi baru digunakan.

Catatan: file yang dihapus dari repo tidak otomatis terhapus di server. `storage/logs` dan `storage/private` dibuat otomatis oleh aplikasi saat dibutuhkan.

## Konvensi kode

- Namespace `App\` mengikuti struktur folder `app/` (PSR-4 manual di `bootstrap.php`).
- Controller tipis, logika di Service, query SQL hanya di Model.
- Selalu prepared statement, jangan menggabung input ke SQL.
- Setiap aksi tulis memanggil `AuditLogger::record()`.
- Teks UI dan pesan error dalam bahasa Indonesia.

## Catatan

- Tautan dokumen memakai link Google Drive yang divalidasi host-nya. Tidak ada integrasi Google Drive API di kode saat ini.
- Endpoint `/health` publik hanya menampilkan status aplikasi/database; detail error tidak dikirim ke klien.
- Folder `anti-slop/` berisi hasil audit UI (audit-001, audit-002) beserta follow-up.

## Pembaruan terbaru

- Perbaikan spam popup notifikasi saat refresh dan bug notifikasi baru yang tertelan setelah ID database direset (lihat bagian keamanan).
- Status login pengguna kini menampilkan semua akun yang sedang aktif, bukan hanya akun sendiri.
- Tampilan kartu/tabel memanjang ke samping (geser horizontal) di semua ukuran layar.
- Cache-busting aset otomatis. Skema database tidak berubah, jadi `db-demo.sql` dan `database/db-host.sql` tetap sama.
