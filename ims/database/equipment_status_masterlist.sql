-- Equipment status masterlist and equipment linkage.
-- Run this once in phpMyAdmin or MySQL before using equipment status features.

CREATE TABLE IF NOT EXISTS `list_equipment_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_status` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_equipment_status` (`equipment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `list_equipment_status` (`equipment_status`)
SELECT 'Working'
WHERE NOT EXISTS (SELECT 1 FROM `list_equipment_status` WHERE `equipment_status` = 'Working');

INSERT INTO `list_equipment_status` (`equipment_status`)
SELECT 'For Repair'
WHERE NOT EXISTS (SELECT 1 FROM `list_equipment_status` WHERE `equipment_status` = 'For Repair');

INSERT INTO `list_equipment_status` (`equipment_status`)
SELECT 'Condemned'
WHERE NOT EXISTS (SELECT 1 FROM `list_equipment_status` WHERE `equipment_status` = 'Condemned');

INSERT INTO `list_equipment_status` (`equipment_status`)
SELECT 'Disposed'
WHERE NOT EXISTS (SELECT 1 FROM `list_equipment_status` WHERE `equipment_status` = 'Disposed');

ALTER TABLE `equipment`
  ADD COLUMN IF NOT EXISTS `equipment_status_id` int(11) NULL AFTER `department_id`;

ALTER TABLE `equipment`
  ADD INDEX IF NOT EXISTS `idx_equipment_status_id` (`equipment_status_id`);

ALTER TABLE `equipment`
  ADD CONSTRAINT `fk_equipment_status`
  FOREIGN KEY (`equipment_status_id`) REFERENCES `list_equipment_status` (`id`)
  ON UPDATE CASCADE
  ON DELETE SET NULL;
