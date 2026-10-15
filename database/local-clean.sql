-- Clean demo database for the Bapperida letter application.
-- Import this file into a local MySQL or MariaDB server.

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','pelayanan') NOT NULL DEFAULT 'pelayanan',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_users_role_active` (`role`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `surat_sequences` (
  `id` tinyint(3) unsigned NOT NULL,
  `next_no` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `surat_masuk` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kode_pencarian` varchar(20) DEFAULT NULL,
  `no` int(10) unsigned NOT NULL,
  `nomor_surat` varchar(100) DEFAULT NULL,
  `tanggal_surat` date DEFAULT NULL,
  `tanggal_masuk` date NOT NULL,
  `sifat` varchar(20) DEFAULT NULL,
  `surat_dari` varchar(255) DEFAULT NULL,
  `pengusul` varchar(255) DEFAULT NULL,
  `hal` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `link_drive` varchar(500) DEFAULT NULL,
  `selesai_tahap` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `selesai_oleh` int(10) unsigned DEFAULT NULL,
  `selesai_pada` datetime DEFAULT NULL,
  `link_selesai` varchar(500) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_surat_masuk_kode` (`kode_pencarian`),
  KEY `idx_tanggal_masuk` (`tanggal_masuk`),
  KEY `idx_surat_masuk_nomor_surat` (`nomor_surat`),
  KEY `idx_surat_masuk_surat_dari` (`surat_dari`),
  KEY `idx_surat_masuk_created_by` (`created_by`),
  KEY `idx_surat_selesai` (`selesai_tahap`,`selesai_pada`),
  FULLTEXT KEY `ft_surat_masuk_search` (`nomor_surat`,`surat_dari`,`pengusul`,`hal`,`catatan`),
  CONSTRAINT `fk_surat_masuk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_surat_selesai_oleh` FOREIGN KEY (`selesai_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `surat_disposisi` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `surat_id` int(10) unsigned NOT NULL,
  `tahap` tinyint(3) unsigned NOT NULL,
  `asal` varchar(150) DEFAULT NULL,
  `tujuan` varchar(150) DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `link_lampiran` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_disposisi_surat_tahap` (`surat_id`,`tahap`),
  KEY `idx_disposisi_tanggal` (`tanggal`),
  CONSTRAINT `fk_disposisi_surat` FOREIGN KEY (`surat_id`) REFERENCES `surat_masuk` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `surat_edit_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `surat_id` int(10) unsigned NOT NULL,
  `requester_id` int(10) unsigned DEFAULT NULL,
  `request_type` enum('EDIT_ACCESS','MESSAGE') NOT NULL DEFAULT 'EDIT_ACCESS',
  `status` enum('PENDING','APPROVED','REJECTED','USED') NOT NULL DEFAULT 'PENDING',
  `message` varchar(500) DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_edit_request_status` (`status`,`created_at`),
  KEY `idx_edit_request_user_surat` (`requester_id`,`surat_id`,`status`),
  CONSTRAINT `fk_edit_request_surat` FOREIGN KEY (`surat_id`) REFERENCES `surat_masuk` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_edit_request_user` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_edit_request_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recipient_id` int(10) unsigned NOT NULL,
  `sender_id` int(10) unsigned DEFAULT NULL,
  `surat_id` int(10) unsigned DEFAULT NULL,
  `edit_request_id` bigint(20) unsigned DEFAULT NULL,
  `kind` varchar(32) NOT NULL,
  `message` varchar(500) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_recipient` (`recipient_id`,`is_read`,`deleted_at`,`created_at`),
  CONSTRAINT `fk_notification_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notification_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notification_surat` FOREIGN KEY (`surat_id`) REFERENCES `surat_masuk` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notification_edit_request` FOREIGN KEY (`edit_request_id`) REFERENCES `surat_edit_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auth_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `last_seen_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_by` int(10) unsigned DEFAULT NULL,
  `revoke_reason` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_auth_sessions_token_hash` (`token_hash`),
  KEY `idx_auth_sessions_user_active` (`user_id`,`revoked_at`,`expires_at`),
  KEY `idx_auth_sessions_last_seen` (`last_seen_at`),
  KEY `fk_auth_sessions_revoked_by` (`revoked_by`),
  CONSTRAINT `fk_auth_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_auth_sessions_revoked_by_user` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `archive_sync_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `surat_id` int(10) unsigned DEFAULT NULL,
  `search_id` varchar(20) NOT NULL,
  `operation` enum('upsert','delete') NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `status` enum('PENDING','PROCESSING','SENT','CONFIRMED','FAILED','SUPERSEDED') NOT NULL DEFAULT 'PENDING',
  `attempt_count` int(10) unsigned NOT NULL DEFAULT 0,
  `available_at` datetime NOT NULL,
  `locked_at` datetime DEFAULT NULL,
  `last_attempt_at` datetime DEFAULT NULL,
  `last_sent_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_archive_queue_ready` (`status`,`available_at`,`id`),
  KEY `idx_archive_queue_search` (`search_id`,`id`),
  KEY `idx_archive_queue_surat` (`surat_id`,`search_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `actor_username` varchar(50) DEFAULT NULL,
  `auth_session_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `module` varchar(64) NOT NULL,
  `record_id` bigint(20) unsigned DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  KEY `idx_audit_logs_user` (`user_id`,`created_at`),
  KEY `idx_audit_logs_module_action` (`module`,`action`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_metric_samples` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sampled_at` datetime NOT NULL,
  `php_memory_bytes` bigint(20) unsigned NOT NULL,
  `database_latency_ms` decimal(9,2) NOT NULL,
  `disk_free_bytes` bigint(20) unsigned DEFAULT NULL,
  `disk_total_bytes` bigint(20) unsigned DEFAULT NULL,
  `queue_pending` bigint(20) unsigned NOT NULL DEFAULT 0,
  `queue_processing` bigint(20) unsigned NOT NULL DEFAULT 0,
  `queue_failed` bigint(20) unsigned NOT NULL DEFAULT 0,
  `queue_confirmed` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_metric_samples_sampled_at` (`sampled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `users` (`id`, `username`, `password`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'RielAkuonza', '$2y$10$OIMl5eoMi8q0JOYPG1EKde8gUmK2DgdNhuBDAQNshyVvz/UAhxIUK', 'admin', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

INSERT IGNORE INTO `surat_sequences` (`id`, `next_no`) VALUES (1, 1);

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
         WHEN 1 THEN 'Kepala, Seketariat'
         WHEN 2 THEN 'Kasubbag umum, Seketariat'
         ELSE 'Kepala'
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
