USE `rtc_db`;

CREATE TABLE IF NOT EXISTS `hospital_scan_access` (
  `id` VARCHAR(2) NOT NULL,
  `hospital` VARCHAR(150) NOT NULL,
  `pass` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `hospital_scan_access` (`id`, `hospital`, `pass`)
VALUES ('01', 'smsc', '0012MC')
ON DUPLICATE KEY UPDATE
  `hospital` = VALUES(`hospital`),
  `pass` = VALUES(`pass`);
