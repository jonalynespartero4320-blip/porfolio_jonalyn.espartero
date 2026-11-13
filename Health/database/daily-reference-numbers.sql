-- New references: 001-MMDDYY-A or 001-MMDDYY-P.
-- Each appointment date and AM/PM time has an independent sequence.
-- Old references and counters are retained; cancellations never reuse a number.
USE health_appointments;
CREATE TABLE IF NOT EXISTS daily_reference_sequences (
    date DATE NOT NULL,
    period ENUM('AM','PM') NOT NULL,
    last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (date,period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
