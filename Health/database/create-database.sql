-- Mandaluyong City Health Department: database creation script.
-- Fresh installations only; CREATE TABLE IF NOT EXISTS does not upgrade older tables.
-- Contains no customer records or staff passwords.
-- After import, create the first super admin with scripts/create-super-admin.php.
-- Wellnest Health: fresh MySQL 8.0+ / MariaDB 10.5+ database for WAMP.
-- Import this file in phpMyAdmin. Existing databases/tables are never dropped.
-- Re-importing seeds does not reset counters, passwords, or booking records.
-- This is a schema + seed import, not an export of existing SQLite appointments.
CREATE DATABASE IF NOT EXISTS health_appointments
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE health_appointments;
SET NAMES utf8mb4;
SET time_zone = '+08:00';

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('patient','admin','super_admin') NOT NULL DEFAULT 'patient',
    PRIMARY KEY (id), UNIQUE KEY unique_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS doctors (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    specialty VARCHAR(100) NOT NULL,
    initials VARCHAR(10) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    doctor_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    time CHAR(5) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    arrived_at DATETIME NULL,
    arrived_by BIGINT UNSIGNED NULL,
    -- MySQL/MariaDB equivalent of SQLite's partial unique reserved-slot index.
    active_slot TINYINT GENERATED ALWAYS AS
        (CASE WHEN status IN ('pending','confirmed') THEN 1 ELSE NULL END) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY reserved_slot (doctor_id,date,time,active_slot),
    KEY patient_schedule (user_id,date,time),
    CONSTRAINT appointments_patient_fk FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT appointments_doctor_fk FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    CONSTRAINT appointments_arrival_staff_fk FOREIGN KEY (arrived_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_bookings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    gender ENUM('Male','Female','Other','Prefer not to say') NOT NULL,
    company VARCHAR(150) NOT NULL,
    job VARCHAR(150) NOT NULL,
    date DATE NOT NULL,
    period ENUM('AM','PM') NOT NULL,
    reference VARCHAR(128) NOT NULL,
    request_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status ENUM('confirmed','cancelled','completed') NOT NULL DEFAULT 'confirmed',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    arrived_at DATETIME NULL,
    arrived_by BIGINT UNSIGNED NULL,
    cancellation_reason VARCHAR(500) NULL,
    cancelled_at DATETIME NULL,
    cancellation_source ENUM('customer','admin') NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_reference (reference),
    UNIQUE KEY unique_request_token (request_token),
    KEY customer_capacity (date,period,status),
    CONSTRAINT customers_arrival_staff_fk FOREIGN KEY (arrived_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reference_sequences (
    period ENUM('AM','PM') NOT NULL,
    last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    identity CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempts INT UNSIGNED NOT NULL,
    expires BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (identity), KEY expired_attempts (expires)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS daily_reference_sequences (
    date DATE NOT NULL,
    period ENUM('AM','PM') NOT NULL,
    last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (date,period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO doctors (id,name,specialty,initials)
SELECT 1,'Dr. Olivia Chen','General Medicine','OC'
WHERE NOT EXISTS (SELECT 1 FROM doctors WHERE id=1);
INSERT INTO doctors (id,name,specialty,initials)
SELECT 2,'Dr. James Wilson','Cardiology','JW'
WHERE NOT EXISTS (SELECT 1 FROM doctors WHERE id=2);
INSERT INTO doctors (id,name,specialty,initials)
SELECT 3,'Dr. Amara Santos','Family Medicine','AS'
WHERE NOT EXISTS (SELECT 1 FROM doctors WHERE id=3);

INSERT INTO reference_sequences (period,last_number)
SELECT 'AM',0 WHERE NOT EXISTS (SELECT 1 FROM reference_sequences WHERE period='AM');
INSERT INTO reference_sequences (period,last_number)
SELECT 'PM',0 WHERE NOT EXISTS (SELECT 1 FROM reference_sequences WHERE period='PM');

