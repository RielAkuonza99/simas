-- One-time upgrade for feedback tables created before multiple-photo support.
-- Back up the database and select the application database before running.
-- Do not import this file again after photos_json has been added.

ALTER TABLE `developer_feedback`
  ADD COLUMN `photos_json` json DEFAULT NULL AFTER `photo_filename`;