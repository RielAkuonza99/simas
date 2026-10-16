-- Upgrade feedback tables created before multiple-photo support.
-- Back up the database and select the application database before running.

SET @photos_json_exists = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'developer_feedback'
    AND column_name = 'photos_json'
);

SET @migration_sql = IF(
  @photos_json_exists = 0,
  'ALTER TABLE `developer_feedback` ADD COLUMN `photos_json` json DEFAULT NULL AFTER `photo_filename`',
  'SELECT ''photos_json already exists; no change made'' AS migration_status'
);

PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;