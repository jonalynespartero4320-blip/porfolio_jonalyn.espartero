-- Existing MySQL/MariaDB installs: import once to add cancellation audit fields.
-- Fresh installs use health.sql instead. No records or reservations are removed.
USE health_appointments;
ALTER TABLE customer_bookings
    ADD COLUMN cancellation_reason VARCHAR(500) NULL,
    ADD COLUMN cancelled_at DATETIME NULL,
    ADD COLUMN cancellation_source ENUM('customer','admin') NULL;
