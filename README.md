# Sistem Surat Masuk Bapperida

> **Repo private.** Dokumentasi internal untuk developer. Bukan untuk publikasi.

Aplikasi web internal untuk mencatat surat masuk, alur disposisi (3 tahap), dan arsip metadata surat di lingkungan Bapperida. Akses hanya untuk pengguna yang sudah login. Semua aksi penting tercatat di audit log.

## Stack

| Komponen | Detail |
|---|---|
| Bahasa | PHP 8.x, `strict_types`, tanpa framework dan tanpa Composer |
| Database | MySQL lewat PDO (prepared statement asli, `EMULATE_PREPARES=false`) |
| Arsip eksternal | Supabase (REST, tabel `archive_surat_metadata`) |
| Dokumen surat | Tautan Google Drive (hanya host `drive.google.com` dan `docs.google.com`) |
| Hosting | InfinityFree (Apache, `.htaccess`) |
| Deploy | GitHub Actions → FTP (`SamKirkland/FTP-Deploy-Action`) |

## Struktur folder

```
app/
  bootstrap.php        Loader .env, error handler, autoload namespace App\
  Controllers/         Auth, Dashboard, Surat, User, Security, Settings, ArchiveSync, Health
  Services/            Logika bisnis (AuthService, SuratService, UserAdminService,
                       ArchiveSyncService, SupabaseArchiveClient)
  Models/              Akses database (User, Surat, AuthSession, Activity,
                       ArchiveSync, SystemMetrics)
  Middleware/          AuthMiddleware (login, role, sesi perangkat)
  Security/            AuditLogger, LoginRateLimiter
  Routing/Router.php   Router sederhana (GET/POST, parameter {id})
  Database/Connection.php   Singleton PDO
  Helpers/functions.php     e(), url(), redirect(), render(), csrfToken(), dll
  Views/               Template PHP per modul (auth, dashboard, surat, users,
                       security, settings, archive-sync)
bin/                   Script CLI (tidak bisa dijalankan lewat web)
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
| `/surat` | GET | login | Daftar dan pencarian surat |
| `/surat/{id}` | GET | login | Detail surat dan disposisi |
| `/surat/tambah` | GET, POST | admin | Tambah surat |
| `/surat/{id}/edit` | GET, POST | admin, pelayanan | Ubah surat |
| `/surat/{id}/hapus` | POST | admin | Hapus surat |
| `/pengguna` ... | GET, POST | admin | Kelola user (tambah, ubah, hapus) |
| `/aktivitas` | GET | login | Aktivitas dan sesi perangkat |
| `/sesi/{session_id}/cabut` | POST | login | Cabut sesi perangkat |
| `/pengaturan` | GET | login | Pengaturan dan developer tools |
| `/pengaturan/developer-tools/metrics` | GET | admin | Metrik sistem live |
| `/pengaturan/developer-tools/logs/clear` | POST | admin | Kosongkan app.log |
| `/sinkronisasi` | GET | admin | Status antrean sync Supabase |
| `/sinkronisasi/push`, `/proses`, `/coba-lagi` | POST | admin | Kirim, proses, ulangi antrean |

Route lama (`/login.php`, `/surat-masuk.php`, dll) masih terdaftar sebagai kompatibilitas. Hapus kalau sudah tidak dipakai.

## Peran pengguna

Hanya ada dua role: **admin** dan **pelayanan**. Admin mengelola semuanya. Pelayanan bisa melihat dan mengubah surat, tapi tidak bisa menambah atau menghapus. Admin aktif terakhir tidak bisa dinonaktifkan atau diturunkan rolenya.

## Autentikasi dan keamanan

- Login memakai sesi PHP ditambah **cookie perangkat** `bapperida_device` (token 64 hex, di database disimpan sebagai SHA-256, berlaku 30 hari). Tabel: `auth_sessions`.
- Sesi perangkat bisa dilihat dan dicabut dari menu aktivitas.
- **Rate limit login**: 5 percobaan gagal per 15 menit, disimpan di `storage/private/login-attempts.json`.
- **CSRF** pada semua form POST (`csrfToken()`, `validateCsrf()`).
- **Audit log** ke tabel `audit_logs` (login, otorisasi gagal, perubahan user, sync gagal, dll).
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
| `surat_sequences` | Penomoran |
| `archive_sync_queue` | Antrean sinkron ke Supabase |
| `audit_logs` | Jejak audit |
| `system_metric_samples` | Sampel metrik sistem |

Field surat: `nomor_surat`, `tanggal_surat`, `tanggal_masuk`, `sifat` (Sangat Segera / Segera / Rahasia), `surat_dari`, `pengusul`, `hal`, `catatan`, `link_drive`.

> Skema SQL belum ada di repo. Simpan di `database/schema.sql` agar developer lain bisa membangun ulang database.

## Sinkronisasi arsip (Supabase)

Perubahan surat masuk ke `archive_sync_queue`, lalu dikirim ke Supabase oleh `ArchiveSyncService`.

Status antrean: `PENDING` → `PROCESSING` → `SENT` → `CONFIRMED`, atau `FAILED` (dicoba ulang dengan `available_at`).

Dijalankan dari menu `/sinkronisasi` (admin) atau CLI:

```bash
php bin/sync-archive.php --limit=10                    # proses antrean (1-100)
php bin/queue-archive-backfill.php --after-id=0 --limit=100   # masukkan data lama ke antrean
```

Backfill dijalankan berulang dengan `--after-id` dari hasil sebelumnya sampai `queued` bernilai 0. InfinityFree tidak menyediakan SSH, jadi di hosting gunakan tombol di menu sinkronisasi.

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

```bash
git clone https://github.com/RielAkuonza99/<NAMA_REPO>.git
cd <NAMA_REPO>
# buat .env dengan variabel di atas (APP_ENV=local)
php -S localhost:8000 -t public public/router.php
```

Cek `http://localhost:8000/health`, harus menampilkan `"status": "ok"`.

## Deploy

Push ke `main` → `.github/workflows/deploy.yml` → FTP ke `/htdocs/` di InfinityFree.

Secrets GitHub: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`.

Tidak ikut di-deploy: `.git*`, `.env`, `.gemini`, `.claude`, `.agents`, `.opencode`, `anti-slop`, `database`, `storage/logs`, `storage/private`, semua `*.md`.

Catatan: file yang dihapus dari repo tidak otomatis terhapus di server. `storage/logs` dan `storage/private` dibuat otomatis oleh aplikasi saat dibutuhkan.

## Konvensi kode

- Namespace `App\` mengikuti struktur folder `app/` (PSR-4 manual di `bootstrap.php`).
- Controller tipis, logika di Service, query SQL hanya di Model.
- Selalu prepared statement, jangan menggabung input ke SQL.
- Setiap aksi tulis memanggil `AuditLogger::record()`.
- Teks UI dan pesan error dalam bahasa Indonesia.

## Catatan

- Tautan dokumen memakai link Google Drive yang divalidasi host-nya. Tidak ada integrasi Google Drive API di kode saat ini.
- Endpoint `/health` publik dan menampilkan pesan error saat gagal. Pertimbangkan membatasinya atau menyembunyikan `message` di production.
- Folder `anti-slop/` berisi hasil audit UI (audit-001, audit-002) beserta follow-up.
