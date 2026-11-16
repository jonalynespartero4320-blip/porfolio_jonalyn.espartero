-- ============================================================
-- IMS-CICTD RBAC Permission-Based Login Migration
-- Source DB reviewed: C:\Users\JEN MC\Downloads\ims-cictd (31).sql
--
-- Purpose:
--   1. Keep your existing users table and password_hash login intact.
--   2. Replace JSON-only permissions with normalized RBAC tables.
--   3. Allow Super Admin to assign roles and permission overrides per user.
--   4. Provide views usable by login code for effective permissions.
--
-- Safe import order:
--   Import your original SQL dump first, then import this file.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- ------------------------------------------------------------
-- 1. Harden users table for permission-based login
-- ------------------------------------------------------------
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `role_id` INT(11) DEFAULT NULL AFTER `role`,
  ADD COLUMN IF NOT EXISTS `status` ENUM('pending','active','disabled','locked') NOT NULL DEFAULT 'pending' AFTER `password_changed_at`,
  ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `failed_login_attempts` INT(11) NOT NULL DEFAULT 0 AFTER `last_login_at`,
  ADD COLUMN IF NOT EXISTS `locked_until` DATETIME DEFAULT NULL AFTER `failed_login_attempts`;

UPDATE `users`
SET `status` = CASE
    WHEN `is_active` = 1 THEN 'active'
    ELSE 'pending'
END
WHERE `status` = 'pending';

-- ------------------------------------------------------------
-- 2. Core RBAC tables
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(80) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `module` VARCHAR(100) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `code` VARCHAR(160) NOT NULL,
  `label` VARCHAR(180) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_permissions_code` (`code`),
  KEY `idx_permissions_module` (`module`),
  KEY `idx_permissions_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` INT(11) NOT NULL,
  `permission_id` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `idx_role_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` INT(11) NOT NULL,
  `role_id` INT(11) NOT NULL,
  `assigned_by` INT(11) DEFAULT NULL,
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `role_id`),
  KEY `idx_user_roles_role` (`role_id`),
  KEY `idx_user_roles_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_roles_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Per-user exception layer. Use this when Super Admin wants to allow/deny
-- one specific permission without creating a new role.
CREATE TABLE IF NOT EXISTS `user_permission_overrides` (
  `user_id` INT(11) NOT NULL,
  `permission_id` INT(11) NOT NULL,
  `effect` ENUM('allow','deny') NOT NULL DEFAULT 'allow',
  `reason` VARCHAR(255) DEFAULT NULL,
  `granted_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `permission_id`),
  KEY `idx_user_permission_overrides_permission` (`permission_id`),
  KEY `idx_user_permission_overrides_granted_by` (`granted_by`),
  CONSTRAINT `fk_user_permission_overrides_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_permission_overrides_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_permission_overrides_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Optional department scope, replacing the old permissions.departments JSON array.
CREATE TABLE IF NOT EXISTS `user_department_scope` (
  `user_id` INT(11) NOT NULL,
  `department_id` INT(11) NOT NULL,
  `granted_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `department_id`),
  KEY `idx_user_department_scope_department` (`department_id`),
  KEY `idx_user_department_scope_granted_by` (`granted_by`),
  CONSTRAINT `fk_user_department_scope_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_department_scope_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Login security tracking.
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `username` VARCHAR(100) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `was_successful` TINYINT(1) NOT NULL DEFAULT 0,
  `failure_reason` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_user` (`user_id`),
  KEY `idx_login_attempts_username` (`username`),
  KEY `idx_login_attempts_created_at` (`created_at`),
  CONSTRAINT `fk_login_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `session_token_hash` CHAR(64) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `revoked_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_sessions_token_hash` (`session_token_hash`),
  KEY `idx_user_sessions_user` (`user_id`),
  KEY `idx_user_sessions_expires` (`expires_at`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 3. Default roles
-- ------------------------------------------------------------
INSERT INTO `roles` (`slug`, `name`, `description`, `is_system`) VALUES
('super_admin', 'Super Admin', 'Full system access, including role and permission management.', 1),
('admin', 'Admin', 'Operational administrator. Permissions can be expanded or limited by Super Admin.', 1),
('user', 'User', 'Standard user with limited access.', 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `is_system` = VALUES(`is_system`);

-- ------------------------------------------------------------
-- 4. Generate permissions from your existing pages table
-- ------------------------------------------------------------
INSERT INTO `permissions` (`module`, `action`, `code`, `label`, `description`)
SELECT
  p.`slug` AS `module`,
  a.`action`,
  CONCAT(p.`slug`, '.', a.`action`) AS `code`,
  CONCAT(COALESCE(NULLIF(p.`title`, ''), p.`name`, p.`slug`), ' - ', a.`label`) AS `label`,
  CONCAT('Allows ', LOWER(a.`label`), ' on ', COALESCE(NULLIF(p.`title`, ''), p.`name`, p.`slug`), '.')
FROM `pages` p
JOIN (
  SELECT 'view' AS `action`, 'View' AS `label`
  UNION ALL SELECT 'add', 'Add'
  UNION ALL SELECT 'edit', 'Edit'
  UNION ALL SELECT 'delete', 'Delete'
  UNION ALL SELECT 'print', 'Print'
  UNION ALL SELECT 'import', 'Import'
  UNION ALL SELECT 'export', 'Export'
  UNION ALL SELECT 'archive', 'Archive'
  UNION ALL SELECT 'restore', 'Restore'
) a
ON DUPLICATE KEY UPDATE
  `module` = VALUES(`module`),
  `action` = VALUES(`action`),
  `label` = VALUES(`label`),
  `description` = VALUES(`description`);

-- System permissions not tied to a page.
INSERT INTO `permissions` (`module`, `action`, `code`, `label`, `description`) VALUES
('auth', 'login', 'auth.login', 'Login', 'Allows user to sign in.'),
('auth', 'logout', 'auth.logout', 'Logout', 'Allows user to sign out.'),
('auth', 'change_password', 'auth.change_password', 'Change Password', 'Allows user to change own password.'),
('rbac', 'manage_roles', 'rbac.manage_roles', 'Manage Roles', 'Allows role creation and role permission assignment.'),
('rbac', 'manage_user_permissions', 'rbac.manage_user_permissions', 'Manage User Permissions', 'Allows Super Admin to grant or deny user permissions.')
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `description` = VALUES(`description`);

-- ------------------------------------------------------------
-- 5. Default role permission grants
-- ------------------------------------------------------------
-- Super Admin: all permissions.
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p
WHERE r.`slug` = 'super_admin';

-- Admin: broad operational access, but no role/user permission governance by default.
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p
WHERE r.`slug` = 'admin'
  AND p.`code` NOT IN (
    'rbac.manage_roles',
    'rbac.manage_user_permissions',
    'admin_users.delete',
    'admin_users.archive',
    'admin_users.restore'
  );

-- User: basic login, dashboard, requests, and own password management.
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p
WHERE r.`slug` = 'user'
  AND p.`code` IN (
    'auth.login',
    'auth.logout',
    'auth.change_password',
    'dashboard.view',
    'request.view',
    'request.add',
    'request_equipment.view',
    'request_equipment.add',
    'request_service.view',
    'request_service.add',
    'request_equipment_status.view',
    'request_service_status.view'
  );

-- ------------------------------------------------------------
-- 6. Map existing users.role to RBAC user_roles
-- ------------------------------------------------------------
UPDATE `users` u
JOIN `roles` r ON r.`slug` = u.`role`
SET u.`role_id` = r.`id`;

INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`, `assigned_by`)
SELECT u.`id`, r.`id`, NULL
FROM `users` u
JOIN `roles` r ON r.`slug` = COALESCE(NULLIF(u.`role`, ''), 'user');

-- ------------------------------------------------------------
-- 7. Effective permission views for login and authorization
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW `v_user_effective_permissions` AS
SELECT DISTINCT
  ur.`user_id`,
  p.`id` AS `permission_id`,
  p.`module`,
  p.`action`,
  p.`code`,
  p.`label`
FROM `user_roles` ur
JOIN `role_permissions` rp ON rp.`role_id` = ur.`role_id`
JOIN `permissions` p ON p.`id` = rp.`permission_id`
WHERE NOT EXISTS (
  SELECT 1
  FROM `user_permission_overrides` deny_override
  WHERE deny_override.`user_id` = ur.`user_id`
    AND deny_override.`permission_id` = p.`id`
    AND deny_override.`effect` = 'deny'
)
UNION
SELECT DISTINCT
  allow_override.`user_id`,
  p.`id` AS `permission_id`,
  p.`module`,
  p.`action`,
  p.`code`,
  p.`label`
FROM `user_permission_overrides` allow_override
JOIN `permissions` p ON p.`id` = allow_override.`permission_id`
WHERE allow_override.`effect` = 'allow';

CREATE OR REPLACE VIEW `v_login_users` AS
SELECT
  u.`id`,
  u.`name`,
  u.`username`,
  u.`email`,
  u.`password_hash`,
  u.`role` AS `legacy_role`,
  COALESCE(primary_role.`slug`, u.`role`, 'user') AS `primary_role`,
  COALESCE(primary_role.`name`, u.`role`, 'User') AS `primary_role_name`,
  u.`status`,
  u.`is_active`,
  u.`force_password_change`,
  u.`password_changed_at`,
  u.`last_login_at`,
  u.`failed_login_attempts`,
  u.`locked_until`,
  GROUP_CONCAT(DISTINCT ep.`code` ORDER BY ep.`code` SEPARATOR ',') AS `permission_codes`
FROM `users` u
LEFT JOIN `roles` primary_role ON primary_role.`id` = u.`role_id`
LEFT JOIN `v_user_effective_permissions` ep ON ep.`user_id` = u.`id`
GROUP BY
  u.`id`,
  u.`name`,
  u.`username`,
  u.`email`,
  u.`password_hash`,
  u.`role`,
  primary_role.`slug`,
  primary_role.`name`,
  u.`status`,
  u.`is_active`,
  u.`force_password_change`,
  u.`password_changed_at`,
  u.`last_login_at`,
  u.`failed_login_attempts`,
  u.`locked_until`;

-- ------------------------------------------------------------
-- 8. Suggested login query
-- ------------------------------------------------------------
-- Your PHP login can authenticate with:
--
-- SELECT *
-- FROM v_login_users
-- WHERE username = ?
--   AND status = 'active'
--   AND (locked_until IS NULL OR locked_until <= NOW())
-- LIMIT 1;
--
-- Then verify password_hash with password_verify().
-- Use permission_codes as the user's effective permission list.

COMMIT;
