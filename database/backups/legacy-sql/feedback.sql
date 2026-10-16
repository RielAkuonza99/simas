-- One-time additive migration for an existing application database.
-- Select the application database before importing this file.

CREATE TABLE IF NOT EXISTS `developer_feedback` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `sender_name` varchar(120) NOT NULL,
  `sender_email` varchar(254) NOT NULL,
  `whatsapp` varchar(32) NOT NULL,
  `message` text NOT NULL,
  `photo_filename` varchar(64) DEFAULT NULL,
  `photos_json` json DEFAULT NULL,
  `email_status` enum('PENDING','SENT','FAILED') NOT NULL DEFAULT 'PENDING',
  `review_status` enum('NEW','REVIEWED','DONE') NOT NULL DEFAULT 'NEW',
  `email_http_status` smallint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_developer_feedback_created` (`created_at`,`id`),
  KEY `idx_developer_feedback_review` (`review_status`,`created_at`),
  KEY `idx_developer_feedback_user` (`user_id`,`created_at`),
  CONSTRAINT `fk_developer_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;