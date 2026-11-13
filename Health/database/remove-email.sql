-- Run ONLY on an existing MySQL/MariaDB installation that imported the old email schema.
-- Fresh installations use health.sql directly and do not need this migration.
-- Export a backup in phpMyAdmin first. Existing IDs, password hashes, and bookings remain.
USE health_appointments;
-- Legacy email-only accounts receive a username such as legacy-12.
UPDATE users SET username=CONCAT('legacy-',id) WHERE username IS NULL OR username='';
ALTER TABLE users MODIFY username VARCHAR(50) NOT NULL;
ALTER TABLE users DROP INDEX unique_email, DROP COLUMN email;
