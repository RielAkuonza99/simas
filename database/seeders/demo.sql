-- Demo data for a database created or upgraded with migrations/mysql.sql.
-- Do not run this against production data.

SET @demo_admin_id = (SELECT `id` FROM `users` WHERE `role` = 'admin' ORDER BY `id` LIMIT 1);
SET @demo_start_no = (SELECT COALESCE(MAX(`no`), 0) + 1 FROM `surat_masuk`);

INSERT INTO `surat_masuk`
  (`no`, `tanggal_masuk`, `nomor_surat`, `tanggal_surat`, `sifat`, `surat_dari`, `hal`,
   `selesai_tahap`, `selesai_oleh`, `selesai_pada`, `created_by`)
SELECT @demo_start_no + sample.`n`,
       DATE_SUB(CURDATE(), INTERVAL sample.`n` DAY),
       CONCAT('DEMO/', LPAD(sample.`n` + 1, 3, '0')),
       DATE_SUB(CURDATE(), INTERVAL sample.`n` + 2 DAY),
       CASE MOD(sample.`n`, 3) WHEN 0 THEN 'Rahasia' WHEN 1 THEN 'Segera' ELSE 'Sangat Segera' END,
       CONCAT('Instansi Demo ', LPAD(MOD(sample.`n`, 12) + 1, 2, '0')),
       CONCAT('DEMO-', LPAD(sample.`n` + 1, 3, '0'), ' - Surat uji struktur agenda dan disposisi.'),
       CASE WHEN MOD(sample.`n`, 2) = 0 THEN MOD(sample.`n`, 5) + 1 ELSE 0 END,
       CASE WHEN MOD(sample.`n`, 2) = 0 THEN @demo_admin_id ELSE NULL END,
       CASE WHEN MOD(sample.`n`, 2) = 0 THEN TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL sample.`n` DAY), '09:00:00') ELSE NULL END,
       @demo_admin_id
FROM (
  SELECT ones.`n` + tens.`n` * 10 AS `n`
  FROM (SELECT 0 AS `n` UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS ones
  CROSS JOIN (SELECT 0 AS `n` UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS tens
) AS sample
WHERE NOT EXISTS (SELECT 1 FROM `surat_masuk` WHERE `hal` LIKE 'DEMO-%');

UPDATE `surat_masuk`
SET `kode_pencarian` = CONCAT('BAPPERIDA-', LPAD(`id`, 4, '0'))
WHERE `kode_pencarian` IS NULL AND `hal` LIKE 'DEMO-%';

INSERT IGNORE INTO `surat_disposisi` (`surat_id`, `tahap`, `asal`, `tujuan`, `tanggal`, `catatan`)
SELECT s.`id`, stages.`tahap`,
       CONCAT('Pejabat Demo ', LPAD(MOD(s.`no`, 100) + 1, 3, '0')),
       CASE MOD(stages.`tahap`, 3)
         WHEN 1 THEN 'Kepala Badan, Sekertaris'
         WHEN 2 THEN 'Kasubbag umum, Sekertaris'
         ELSE 'Kepala Badan'
       END,
       DATE_ADD(s.`tanggal_masuk`, INTERVAL stages.`tahap` DAY),
       CONCAT('Catatan uji disposisi tahap ', stages.`tahap`)
FROM `surat_masuk` s
CROSS JOIN (
  SELECT 1 AS `tahap` UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
) AS stages
WHERE s.`hal` LIKE 'DEMO-%'
  AND stages.`tahap` <= CASE
    WHEN s.`selesai_tahap` > 0 THEN s.`selesai_tahap`
    ELSE MOD(s.`no`, 3) + 1
  END;

UPDATE `surat_sequences`
SET `next_no` = (SELECT COALESCE(MAX(`no`), 0) + 1 FROM `surat_masuk`)
WHERE `id` = 1;
