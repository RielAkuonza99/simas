-- One-time upgrade for an existing MySQL/MariaDB hosting database.
-- Back up the database and select the application database before running.

ALTER TABLE `surat_masuk`
  ADD COLUMN `selesai_tahap` tinyint(3) unsigned NOT NULL DEFAULT 0 AFTER `link_drive`,
  ADD COLUMN `selesai_oleh` int(10) unsigned DEFAULT NULL AFTER `selesai_tahap`,
  ADD COLUMN `selesai_pada` datetime DEFAULT NULL AFTER `selesai_oleh`,
  ADD COLUMN `link_selesai` varchar(500) DEFAULT NULL AFTER `selesai_pada`,
  ADD KEY `idx_surat_selesai` (`selesai_tahap`,`selesai_pada`),
  ADD CONSTRAINT `fk_surat_selesai_oleh` FOREIGN KEY (`selesai_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL;

CREATE TABLE `surat_edit_requests` (
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

CREATE TABLE `admin_notifications` (
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