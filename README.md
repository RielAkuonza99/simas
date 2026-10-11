# Sistem Surat Masuk Bapperida

Aplikasi ini adalah sistem internal untuk pengelolaan surat masuk di lingkungan Bapperida. Fokus utama sistem adalah pengelolaan dokumen, disposisi, dan riwayat aktivitas dalam lingkungan yang bersifat privat dan terbatas pada pengguna yang berwenang.

## Tujuan aplikasi

Sistem ini dirancang untuk membantu admin dan petugas mengelola surat masuk secara tertib, cepat, dan teraudit. Fungsional utama meliputi:

- pencatatan surat masuk dan metadata dasar
- pengelolaan disposisi dan status tindak lanjut
- manajemen dokumen dan tautan arsip dari Google Drive
- pencarian arsip dan penelusuran surat lama
- pencatatan aktivitas pengguna dan autentikasi
- pengelolaan akses pengguna dan perangkat yang masuk ke sistem

Aplikasi ini bukan website publik. Semua akses dilakukan melalui mekanisme autentikasi dan otorisasi yang dibatasi untuk pengguna internal yang berwenang.

## Karakter sistem

- private by design
- berbasis admin dan petugas internal
- penggunaan data bersifat operasional, bukan promosi atau publikasi
- fokus pada kejelasan informasi, audit, dan kelancaran penanganan surat

## Arsitektur utama

Struktur aplikasi berorientasi pada arsitektur web sederhana namun aman:

- frontend: layout berbasis PHP view, template header/sidebar, serta styling responsif
- application layer: controller, service, dan model untuk pengelolaan surat, user, serta autentikasi
- data layer: basis data relasional untuk surat, user, log aktivitas, dan metadata terkait
- integration layer: konfigurasi Google Drive API, sinkronisasi metadata, serta antrean proses arsip
- security layer: session, autentikasi, audit log, pembatasan akses, serta monitoring aktivitas

## Roadmap

### Fase 1 - Basis operasional
- pengelolaan surat masuk dan disposisi
- struktur user dan peran akses
- sesi login serta log aktivitas

### Fase 2 - Integrasi dan kontrol
- konfigurasi Google Drive API
- sinkronisasi metadata dan arsip
- pemeliharaan antrean tugas serta status proses

### Fase 3 - Penyesuaian pengalaman pengguna
- revisi layout responsif dan konsistensi UI
- penyederhanaan informasi agar lebih fungsional bagi admin
- perbaikan visual pada halaman dashboard dan settings

### Fase 4 - Stabilitas operasional
- monitoring performa sistem
- penyempurnaan keamanan dan audit trail
- pemeliharaan data dan kelengkapan arsip

## Catatan penting

Dokumen ini fokus pada konteks bisnis dan kebutuhan operasional aplikasi. Ini bukan panduan teknis lengkap untuk menjalankan sistem, melainkan ringkasan fungsi, arah pengembangan, dan karakter aplikasi secara umum.
