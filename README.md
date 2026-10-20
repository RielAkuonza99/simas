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
bin/                   Script CLI dan generator metadata versi untuk deploy
database/              SQL database bersih, demo, upgrade hosting, dan metadata Supabase
config/                app.php, security.php, auth.php, database.php, updates.json
includes/              header, sidebar, footer, ui (partial layout)
public/                index.php, router.php (dev server), assets/ (css, js, font)
routes/web.php         Semua definisi route
storage/logs/          app.log, audit.log (diabaikan git)
storage/private/       login-attempts.json (diabaikan git)
.github/workflows/     deploy.yml (demo), deploy-production.yml (production manual)
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
| `/surat/ekspor/xlsx`, `/surat/ekspor/pdf` | POST | admin | Ekspor Excel atau PDF per minggu, per bulan, seluruhnya, atau ID terpilih |
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
| `/pengaturan` | GET | login | Profil, dashboard versi, dan developer tools (dashboard versi khusus admin) |
| `/pengaturan/developer-tools/metrics` | GET | admin | Metrik sistem live |
| `/pengaturan/developer-tools/logs/clear` | POST | admin | Kosongkan app.log |
| `/sinkronisasi` | GET | admin | Status antrean sync Supabase |
| `/sinkronisasi/ekspor` | GET | admin | Ekspor metadata Supabase ke CSV (maksimal 100.000 baris) |
| `/sinkronisasi/push`, `/proses`, `/coba-lagi` | POST | admin | Kirim, proses, ulangi antrean |

Route lama (`/login.php`, `/surat-masuk.php`, dll) masih terdaftar sebagai kompatibilitas. Hapus kalau sudah tidak dipakai.

## Peran pengguna

Hanya ada dua role: **admin** dan **pelayanan**. Keduanya dapat melihat dan menambahkan surat. Admin dapat mengubah dan menghapus surat; Pelayanan harus memperoleh persetujuan admin per surat. Persetujuan berlaku 24 jam dan dapat dipakai untuk beberapa perubahan dalam jangka tersebut. Setelah 24 jam, Pelayanan perlu mengajukan persetujuan lagi. Permintaan edit yang menunggu hanya memiliki aksi Setujui dan Tolak; keputusan menandai notifikasi admin sebagai dibaca. Pelayanan dapat mengirim pengajuan dari detail surat dengan dialog konfirmasi yang menampilkan teks persis sebelum dikirim. Popup notifikasi hanya menampilkan ringkasan; pesan penuh dan penghapusan tersedia di riwayat. Admin aktif terakhir tidak bisa dinonaktifkan atau diturunkan rolenya. Admin dapat mengelola maksimal lima akun lain; akun admin yang sedang digunakan tidak dihitung. Tabel data (pengguna, riwayat disposisi, daftar surat, log aktivitas, pratinjau sinkronisasi) dan kartu ringkasan dashboard tidak lagi menumpuk ke bawah di layar sempit; semuanya digeser ke samping.

Halaman **Panduan** menampilkan FAQ yang berbeda untuk Administrator dan Pelayanan, ditambah pertanyaan umum.

Ekspor surat (admin) menyediakan tiga rentang: **per minggu** (parameter `week_date`, tanggal mana pun dalam minggu itu; Senin sampai Minggu), **per bulan** (`month_m` 1-12 dan `month_y`), atau **seluruhnya**. Admin juga dapat mencari dan mencentang ID tertentu; daftar ID terpilih menjadi sumber ekspor dan mengabaikan rentang. Format: **Excel (.xlsx)** dan **PDF**. Keduanya memakai satu definisi tabel (`SuratExportFormatter::columns()` dan `::row()`) sehingga kolom, urutan, dan isinya identik: blok judul (periode, waktu dibuat, ringkasan) lalu tabel 12 kolom dengan rincian disposisi per tahap. Excel memakai header beku, filter, garis tabel, dan judul kolom berulang saat dicetak; PDF A4 landscape dengan header tabel berulang dan nomor halaman. PDF dibatasi 500 surat per berkas (`SuratController::PDF_MAX_ROWS`); gunakan Excel untuk data lebih besar. Ekspor CSV surat sudah dihapus (CSV Supabase di `/sinkronisasi/ekspor` tetap ada).

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
- Ekspor Excel menyimpan semua nilai sebagai teks (inline string) sehingga isi dari database tidak pernah dijalankan sebagai formula.
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

Struktur surat: **Agenda Surat** (`id`, nomor agenda, tanggal diterima), **Informasi Surat** (asal, nomor/tanggal, sifat, hal, bukti dukung), dan **Disposisi** (3 tahap awal, maksimal 5; pejabat, tujuan, tanggal, catatan). **Tujuan** diketik manual, maksimal 5 per tahap (`SuratService::TUJUAN_MAKS`), masing-masing sampai 80 karakter dan tanpa koma. Saran isian (`TUJUAN_SARAN`): Kasubbag umum, Sekertaris, Kepala Badan. Nilai disimpan di `surat_disposisi.tujuan` (varchar 500) sebagai daftar dipisah `, `. Tahap akhir disimpan pada `selesai_tahap`, beserta akun/tanggal pencatatan dan `link_selesai` opsional. Kolom lama `pengusul`, `catatan` isi surat, dan lampiran tahap dipertahankan pada database hosting agar migrasi tidak menghapus data historis, tetapi tidak dipakai form baru.

`database/migrations/mysql.sql` adalah skrip MySQL gabungan untuk membuat skema baru atau memperbarui skema lama. Skrip ini tidak membuat database dan tidak menghapus data; tetap lakukan backup sebelum menjalankannya.

`database/seeders/demo.sql` menambahkan 100 surat sintetis ke database yang sudah disiapkan oleh migration. Gunakan hanya di database lokal/demo, bukan production. `database/backups/` disiapkan untuk dump cadangan lokal; isinya diabaikan Git.

Pada database lokal yang sudah berjalan, gunakan `php bin/seed-demo-letters.php` untuk membuat 100 surat contoh (50 selesai dan 50 belum); perintah ini hanya berjalan saat `APP_ENV=local` dan menolak seed duplikat.

Skrip MySQL gabungan aman dijalankan ulang: tabel dibuat jika belum ada, kolom/index/constraint lama diperiksa sebelum ditambahkan, dan migrasi tujuan dapat diulang. Backup dahulu sebelum menjalankannya pada database hosting. Folder `database/` tidak ikut deploy otomatis, jadi upload/import manual melalui panel hosting. Jalankan `database/migrations/supabase-archive.sql` secara terpisah satu kali di SQL Editor Supabase karena menggunakan PostgreSQL.

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

File `.env` ada di root, diabaikan git, dan **tidak di-deploy**. Buat manual di server lewat File Manager; `env.example` adalah templatnya (tanpa rahasia). File bernama `env` (tanpa titik) juga diabaikan git dan deploy; jangan pernah di-commit.

| Variabel | Fungsi |
|---|---|
| `APP_ENV` | `local` atau `production` |
| `APP_DEBUG` | `true` hanya untuk lokal |
| `APP_BASE_URL` | Kosongkan jika otomatis |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | Koneksi MySQL (wajib di production) |
| `SUPABASE_URL` | URL project Supabase |
| `SUPABASE_SERVICE_ROLE_KEY` | Kunci service role (rahasia, jangan dibagikan) |
| `SUPABASE_ARCHIVE_TABLE` | Default `archive_surat_metadata` |
| `BREVO_API_KEY` | API key Brevo untuk mengirim feedback (rahasia, hanya di `.env`) |
| `BREVO_SENDER_EMAIL` | Pengirim terverifikasi di Brevo (wajib; tanpa nilai ini feedback hanya tersimpan dengan status `PENDING`) |
| `DEVELOPER_FEEDBACK_EMAIL` | Tujuan email feedback (wajib, sama seperti di atas) |
| `DEVELOPER_API_TOKEN` | Token Bearer acak untuk API developer (rahasia, minimal 40 karakter) |
| `DEVELOPER_API_ORIGINS` | Origin dashboard yang diizinkan browser, pisahkan dengan koma; tanpa path dan tanpa wildcard |

Alamat pengirim harus diverifikasi pada akun Brevo. Feedback (halaman Panduan) dapat melampirkan sampai **5 foto** (JPEG/PNG/WebP, maksimal 5 MB per foto, total 12 MB); semuanya dikirim sebagai lampiran email Brevo (batas Brevo 20 MB per email) dan disimpan privat di `storage/private/developer-feedback/`. Browser mengecilkan foto besar otomatis. Jika total unggahan melebihi `post_max_size` server, pengguna mendapat pesan jelas. Buat token API secara lokal, lalu simpan hanya di `.env` hosting dan server dashboard:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

## API Developer

API memakai header `Authorization: Bearer <DEVELOPER_API_TOKEN>`. Endpoint v1 saat ini hanya menyediakan pengelolaan feedback:

| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/api/v1` | Daftar endpoint yang tersedia |
| `GET` | `/api/v1/feedback` | Daftar feedback; mendukung `limit`, `offset`, dan `status` |
| `GET` | `/api/v1/feedback/{id}` | Detail feedback |
| `GET` | `/api/v1/feedback/{id}/photo` | Lampiran privat |
| `PATCH` | `/api/v1/feedback/{id}` | Ubah `review_status` menjadi `NEW`, `REVIEWED`, atau `DONE` |
| `DELETE` | `/api/v1/feedback/{id}` | Hapus feedback beserta lampirannya |

Contoh ubah status:

```http
PATCH /api/v1/feedback/12
Authorization: Bearer <DEVELOPER_API_TOKEN>
Content-Type: application/json

{"review_status":"DONE"}
```

API belum mengekspos pengelolaan surat, pengguna, atau konfigurasi. Setiap resource harus mendapat endpoint dan validasi hak akses tersendiri; API tidak menyediakan akses SQL umum. Jangan menaruh token developer di JavaScript dashboard yang dikirim ke browser. Panggil API dari backend dashboard agar token tetap rahasia. CORS hanya pembatas origin browser, bukan pengganti autentikasi.

## Setup lokal

Buat database lokal kosong lalu impor skema beserta data demo dengan MySQL/MariaDB:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS bapperida_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p bapperida_local < database/migrations/mysql.sql
mysql -u root -p bapperida_local < database/seeders/demo.sql
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

## Versi dan rilis

Admin melihat versi website dan daftar versi fitur di **Pengaturan → Dashboard versi website**. Data dasarnya ada di `config/updates.json`; halaman web hanya menampilkan dan tidak mengubahnya.

Kolom `version` dan `release` berisi label versi yang dikelola di JSON, misalnya `V2.05.3` atau `S-3.5`. Sebelum upload, `bin/build-update-metadata.mjs` mengisi `git_updates` dari riwayat commit: Website Update menghitung seluruh commit, sedangkan setiap fitur menghitung commit pada file yang dipetakan di skrip. Label manual tetap dipertahankan. Ini hitungan commit, bukan jumlah event push.

Kedua workflow mengambil seluruh riwayat Git (`fetch-depth: 0`) dan membangun metadata tersebut hanya pada salinan kerja Actions untuk deployment. Hasil hitungan tidak ditulis balik ke Git; demo dan production pada commit yang sama mendapat metadata hitungan yang sama.

## Deploy

Push ke `main` menjalankan `.github/workflows/deploy.yml` dan deploy otomatis ke hosting demo.

Deploy production dijalankan manual dari tab **Actions** melalui workflow **Deploy to InfinityFree Production**, dengan branch `main` dipilih. Buat GitHub Environment bernama `production`, lalu isi environment secrets `FTP_SERVER_PRODUCTION`, `FTP_USERNAME_PRODUCTION`, dan `FTP_PASSWORD_PRODUCTION` menggunakan kredensial akun InfinityFree production. Workflow production tidak berjalan pada push biasa.

Workflow demo tetap menggunakan repository secrets `FTP_SERVER`, `FTP_USERNAME`, dan `FTP_PASSWORD`.

Tidak ikut di-deploy: `.git*`, `.env`, `.gemini`, `.claude`, `.agents`, `.opencode`, `anti-slop`, `database`, `storage/logs`, `storage/private`, semua `*.md`. Untuk database MySQL, backup lalu impor `database/migrations/mysql.sql`. Jangan impor `database/seeders/demo.sql` ke production. Jalankan `database/migrations/supabase-archive.sql` secara manual di Supabase sebelum versi aplikasi baru digunakan. Atur variabel Brevo dan API developer melalui File Manager sebelum memakai fitur terkait.

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

- Dashboard versi website tersedia untuk admin di Pengaturan; label versi berasal dari `config/updates.json` dan hitungan revisi Git dibuat saat deploy.
- Tujuan disposisi kini diketik manual (maksimal 5 per tahap) dan nama tujuan diperbarui; perubahan tercakup dalam `database/migrations/mysql.sql`.
- Ekspor surat: per minggu, per bulan, atau seluruhnya, dalam Excel dan PDF dengan tabel identik. CSV dan opsi tahun dihapus.
- Feedback: form kini benar-benar mengirim sampai 5 foto (sebelumnya field `photo` tidak cocok dengan `photos[]` di server) dan variabel Brevo didokumentasikan di `env.example`.

- Perbaikan spam popup notifikasi saat refresh dan bug notifikasi baru yang tertelan setelah ID database direset (lihat bagian keamanan).
- Status login pengguna kini menampilkan semua akun yang sedang aktif, bukan hanya akun sendiri.
- Tampilan kartu/tabel memanjang ke samping (geser horizontal) di semua ukuran layar.
- Cache-busting aset otomatis. Skema dan upgrade MySQL tersedia di `database/migrations/mysql.sql`; `database/seeders/demo.sql` menambahkan data demo.


## Catatan deploy (penting)

- Folder `vendor/` ada di `.gitignore` dan InfinityFree tidak punya Composer. Workflow `.github/workflows/deploy.yml`
  menjalankan `composer install --no-dev` lalu mengunggah `vendor/` lewat FTP. Tanpa itu ekspor PDF gagal
  (`Failed opening required .../vendor/autoload.php`).
- Penyebab kegagalan email feedback (Brevo) kini dicatat di `storage/logs/app.log` dan ditampilkan singkat pada notifikasi.
- CSP (`config/security.php`) harus mengizinkan `blob:` dan `data:` pada `img-src` agar pratinjau foto feedback tampil.
