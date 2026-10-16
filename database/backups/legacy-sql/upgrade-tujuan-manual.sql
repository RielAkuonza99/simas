-- Migrasi satu kali: Tujuan disposisi menjadi isian manual (maksimal 5 per tahap).
-- Backup database dan pilih database aplikasi sebelum menjalankan file ini.
-- Aman dijalankan ulang: perubahan kolom bersifat idempoten dan penggantian nama
-- hanya mengenai nilai lama yang masih tersisa.

-- 1. Perlebar kolom supaya 5 tujuan manual (masing-masing sampai 80 karakter) muat.
ALTER TABLE `surat_disposisi`
  MODIFY COLUMN `tujuan` varchar(500) DEFAULT NULL;

-- 2. Ganti nama tujuan lama pada data yang sudah ada.
--    Seketariat -> Sekertaris, Kepala -> Kepala Badan.
--    Nilai disimpan sebagai daftar dipisah ", ", jadi dibungkus pemisah agar hanya
--    nama yang utuh yang diganti ("Kepala Badan" tidak berubah menjadi "Kepala Badan Badan").
UPDATE `surat_disposisi`
SET `tujuan` = SUBSTRING(
      REPLACE(
        REPLACE(
          CONCAT(', ', `tujuan`, ', '),
          ', Kepala, ', ', Kepala Badan, '
        ),
        ', Seketariat, ', ', Sekertaris, '
      ),
      3,
      CHAR_LENGTH(
        REPLACE(
          REPLACE(
            CONCAT(', ', `tujuan`, ', '),
            ', Kepala, ', ', Kepala Badan, '
          ),
          ', Seketariat, ', ', Sekertaris, '
        )
      ) - 4
    )
WHERE `tujuan` IS NOT NULL
  AND (CONCAT(', ', `tujuan`, ', ') LIKE '%, Kepala, %'
    OR CONCAT(', ', `tujuan`, ', ') LIKE '%, Seketariat, %');
