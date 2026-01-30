-- ╔══════════════════════════════════════════════════════════════════════════════╗
-- ║                     صرح الإتقان - SARH AL-ITQAN                              ║
-- ║                     MASTER DATABASE SCHEMA v1.8.0                            ║
-- ╠══════════════════════════════════════════════════════════════════════════════╣
-- ║  This file combines all database schemas into a single installation file.   ║
-- ║  Components: Core + Live Operations + Integrity + Psychological Traps       ║
-- ╚══════════════════════════════════════════════════════════════════════════════╝

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET collation_connection = 'utf8mb4_unicode_520_ci';
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ============================================================================
-- SECTION 1: CORE SYSTEM TABLES
-- ============================================================================

-- 1.1 System Settings
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` JSON NULL DEFAULT NULL,
    `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
    `setting_type` ENUM('string', 'number', 'boolean', 'json', 'text') NOT NULL DEFAULT 'string',
    `description` VARCHAR(255) NULL DEFAULT NULL,
    `is_public` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_setting_key` (`setting_key`),
    INDEX `idx_setting_group` (`setting_group`),
    INDEX `idx_setting_public` (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- 1.2 Roles
CREATE TABLE IF NOT EXISTS `roles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(50) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `role_level` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `permissions` JSON NULL DEFAULT NULL,
    `color` VARCHAR(20) NULL DEFAULT '#6c757d',
    `icon` VARCHAR(50) NULL DEFAULT 'bi-person',
    `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_role_slug` (`slug`),
    INDEX `idx_role_level` (`role_level`),
    INDEX `idx_role_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- 1.3 Branches
CREATE TABLE IF NOT EXISTS `branches` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `address` TEXT NULL DEFAULT NULL,
    `city` VARCHAR(100) NULL DEFAULT NULL,
    `phone` VARCHAR(20) NULL DEFAULT NULL,
    `email` VARCHAR(100) NULL DEFAULT NULL,
    `latitude` DECIMAL(10, 7) NULL DEFAULT NULL,
    `longitude` DECIMAL(10, 7) NULL DEFAULT NULL,
    `geofence_radius` INT UNSIGNED NOT NULL DEFAULT 100,
    `timezone` VARCHAR(50) NOT NULL DEFAULT 'Asia/Riyadh',
    `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `is_ghost_branch` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `ghost_visible_to` JSON NULL DEFAULT NULL,
    `settings` JSON NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_branch_code` (`code`),
    INDEX `idx_branch_active` (`is_active`),
    INDEX `idx_branch_ghost` (`is_ghost_branch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- 1.4 Users
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `emp_code` VARCHAR(50) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NULL DEFAULT NULL,
    `avatar` VARCHAR(255) NULL DEFAULT NULL,
    `role_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `department` VARCHAR(100) NULL DEFAULT NULL,
    `job_title` VARCHAR(100) NULL DEFAULT NULL,
    `hire_date` DATE NULL DEFAULT NULL,
    `national_id` VARCHAR(20) NULL DEFAULT NULL,
    `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `is_online` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `last_login_at` TIMESTAMP NULL DEFAULT NULL,
    `last_activity_at` TIMESTAMP NULL DEFAULT NULL,
    `last_latitude` DECIMAL(10, 7) NULL DEFAULT NULL,
    `last_longitude` DECIMAL(10, 7) NULL DEFAULT NULL,
    `login_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` TIMESTAMP NULL DEFAULT NULL,
    `remember_token` VARCHAR(100) NULL DEFAULT NULL,
    `current_points` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `total_points_earned` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `total_points_deducted` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `streak_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'عداد الأيام المثالية المتتالية للحلبة',
    `preferences` JSON NULL DEFAULT NULL,
    `custom_schedule` JSON NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_emp_code` (`emp_code`),
    UNIQUE KEY `uk_username` (`username`),
    UNIQUE KEY `uk_email` (`email`),
    INDEX `idx_user_role` (`role_id`),
    INDEX `idx_user_branch` (`branch_id`),
    INDEX `idx_user_active` (`is_active`),
    INDEX `idx_user_online` (`is_online`),
    INDEX `idx_user_activity` (`last_activity_at`),
    CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_user_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- 1.5 User Sessions
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `session_token` VARCHAR(255) NOT NULL,
    `device_type` VARCHAR(50) NULL DEFAULT NULL,
    `device_name` VARCHAR(100) NULL DEFAULT NULL,
    `browser` VARCHAR(100) NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` TEXT NULL DEFAULT NULL,
    `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `last_activity_at` TIMESTAMP NULL DEFAULT NULL,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_session_token` (`session_token`),
    INDEX `idx_session_user` (`user_id`),
    INDEX `idx_session_active` (`is_active`),
    INDEX `idx_session_expires` (`expires_at`),
    CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- 1.6 Attendance
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `branch_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `recorded_branch_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'الفرع المسجل فيه الحضور (للتاريخ)',
    `date` DATE NOT NULL,
    `check_in_time` TIME NULL DEFAULT NULL,
    `check_out_time` TIME NULL DEFAULT NULL,
    `check_in_lat` DECIMAL(10, 7) NULL DEFAULT NULL,
    `check_in_lng` DECIMAL(10, 7) NULL DEFAULT NULL,
    `check_out_lat` DECIMAL(10, 7) NULL DEFAULT NULL,
    `check_out_lng` DECIMAL(10, 7) NULL DEFAULT NULL,
    `check_in_address` VARCHAR(255) NULL DEFAULT NULL,
    `check_out_address` VARCHAR(255) NULL DEFAULT NULL,
    `check_in_method` ENUM('manual', 'auto_gps') NULL DEFAULT 'manual' COMMENT 'طريقة تسجيل الحضور',
    `check_in_distance` DECIMAL(10, 2) NULL DEFAULT NULL,
    `check_out_distance` DECIMAL(10, 2) NULL DEFAULT NULL,
    `work_minutes` INT UNSIGNED NULL DEFAULT NULL,
    `late_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
    `early_leave_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
    `overtime_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
    `penalty_points` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `bonus_points` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('present', 'absent', 'late', 'half_day', 'leave', 'holiday') NOT NULL DEFAULT 'present',
    `notes` TEXT NULL DEFAULT NULL,
    `is_locked` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'قفل السجل بعد الترحيل',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_attendance_user_date` (`user_id`, `date`),
    INDEX `idx_attendance_branch` (`branch_id`),
    INDEX `idx_attendance_recorded_branch` (`recorded_branch_id`),
    INDEX `idx_attendance_date` (`date`),
    INDEX `idx_attendance_status` (`status`),
    CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_attendance_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- 1.7 Activity Log
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `model_type` VARCHAR(100) NULL DEFAULT NULL,
    `model_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `old_values` JSON NULL DEFAULT NULL,
    `new_values` JSON NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_activity_user` (`user_id`),
    INDEX `idx_activity_action` (`action`),
    INDEX `idx_activity_model` (`model_type`, `model_id`),
    INDEX `idx_activity_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ============================================================================
-- SECTION 2: LIVE OPERATIONS TABLES
-- ============================================================================

-- 2.1 Notifications
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type` VARCHAR(50) NOT NULL DEFAULT 'info',
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NULL DEFAULT NULL,
    `icon` VARCHAR(50) NULL DEFAULT 'bi-bell',
    `scope_type` ENUM('global', 'branch', 'user') NOT NULL DEFAULT 'user',
    `scope_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `action_url` VARCHAR(255) NULL DEFAULT NULL,
    `is_persistent` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_notification_type` (`type`),
    INDEX `idx_notification_scope` (`scope_type`, `scope_id`),
    INDEX `idx_notification_expires` (`expires_at`),
    INDEX `idx_notification_created` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- 2.2 User Notification Reads
CREATE TABLE IF NOT EXISTS `user_notification_reads` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `notification_id` BIGINT UNSIGNED NOT NULL,
    `read_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_notification` (`user_id`, `notification_id`),
    CONSTRAINT `fk_unr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_unr_notification` FOREIGN KEY (`notification_id`) REFERENCES `notifications`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- 2.3 Push Subscriptions (Web Push)
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `endpoint` TEXT NOT NULL,
    `endpoint_hash` CHAR(64) NOT NULL,
    `p256dh` VARCHAR(255) NOT NULL,
    `auth` VARCHAR(255) NOT NULL,
    `subscription_json` JSON NOT NULL,
    `user_agent` TEXT NULL DEFAULT NULL,
    `device_type` VARCHAR(50) NULL DEFAULT NULL,
    `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `last_seen_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_push_endpoint_hash` (`endpoint_hash`),
    INDEX `idx_push_user` (`user_id`),
    INDEX `idx_push_active` (`is_active`),
    CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- 2.4 User Location History (Optional)
CREATE TABLE IF NOT EXISTS `user_location_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `latitude` DECIMAL(10, 7) NOT NULL,
    `longitude` DECIMAL(10, 7) NOT NULL,
    `accuracy` DECIMAL(10, 2) NULL DEFAULT NULL,
    `source` ENUM('gps', 'network', 'manual') NOT NULL DEFAULT 'gps',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_location_user` (`user_id`),
    INDEX `idx_location_created` (`created_at`),
    CONSTRAINT `fk_location_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ============================================================================
-- SECTION 3: INTEGRITY MODULE TABLES
-- ============================================================================

-- 3.1 Integrity Logs
CREATE TABLE IF NOT EXISTS `integrity_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `action_type` VARCHAR(100) NOT NULL,
    `target_type` VARCHAR(50) NULL DEFAULT NULL,
    `target_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `details` JSON NULL DEFAULT NULL,
    `severity` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'low',
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` TEXT NULL DEFAULT NULL,
    `location_lat` DECIMAL(10, 7) NULL DEFAULT NULL,
    `location_lng` DECIMAL(10, 7) NULL DEFAULT NULL,
    `is_reviewed` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `reviewed_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_integrity_user` (`user_id`),
    INDEX `idx_integrity_type` (`action_type`),
    INDEX `idx_integrity_severity` (`severity`),
    INDEX `idx_integrity_created` (`created_at` DESC),
    INDEX `idx_integrity_reviewed` (`is_reviewed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- 3.2 Integrity Reports (Anonymous Reports - The Mine)
CREATE TABLE IF NOT EXISTS `integrity_reports` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sender_id` BIGINT UNSIGNED NOT NULL,
    `reported_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `report_type` ENUM('violation', 'harassment', 'theft', 'fraud', 'other') NOT NULL DEFAULT 'violation',
    `content` TEXT NOT NULL,
    `evidence_files` JSON NULL DEFAULT NULL,
    `is_anonymous_claim` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `admin_notes` TEXT NULL DEFAULT NULL,
    `status` ENUM('pending', 'investigating', 'resolved', 'dismissed', 'fake') NOT NULL DEFAULT 'pending',
    `resolved_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `resolved_at` TIMESTAMP NULL DEFAULT NULL,
    `sender_revealed_to` JSON NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_reports_sender` (`sender_id`),
    INDEX `idx_reports_reported` (`reported_id`),
    INDEX `idx_reports_status` (`status`),
    INDEX `idx_reports_created` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ============================================================================
-- SECTION 4: PSYCHOLOGICAL TRAPS TABLES
-- ============================================================================

-- 4.1 Psychological Profiles
CREATE TABLE IF NOT EXISTS `psychological_profiles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `trust_score` INT NOT NULL DEFAULT 100,
    `curiosity_score` INT NOT NULL DEFAULT 0,
    `integrity_score` INT NOT NULL DEFAULT 100,
    `profile_type` ENUM('loyal_sentinel', 'curious_observer', 'opportunist', 'active_exploiter', 'potential_insider', 'undetermined') NOT NULL DEFAULT 'undetermined',
    `risk_level` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'low',
    `total_traps_seen` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_violations` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_trap_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_profile_user` (`user_id`),
    INDEX `idx_profile_type` (`profile_type`),
    INDEX `idx_profile_risk` (`risk_level`),
    INDEX `idx_profile_trust` (`trust_score`),
    CONSTRAINT `fk_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- 4.2 Trap Configurations
CREATE TABLE IF NOT EXISTS `trap_configurations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `trap_type` VARCHAR(50) NOT NULL,
    `trap_name` VARCHAR(100) NOT NULL,
    `trap_name_ar` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `trigger_chance` DECIMAL(4, 2) NOT NULL DEFAULT 0.10,
    `cooldown_minutes` INT UNSIGNED NOT NULL DEFAULT 10080,
    `min_role_level` INT UNSIGNED NOT NULL DEFAULT 1,
    `max_role_level` INT UNSIGNED NOT NULL DEFAULT 7,
    `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `settings` JSON NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_trap_type` (`trap_type`),
    INDEX `idx_trap_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- 4.3 Trap Logs
CREATE TABLE IF NOT EXISTS `trap_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `trap_type` VARCHAR(50) NOT NULL,
    `trap_config_id` INT UNSIGNED NULL,
    `action_taken` VARCHAR(50) NOT NULL,
    `action_category` ENUM('positive', 'neutral', 'negative', 'critical') NOT NULL DEFAULT 'neutral',
    `score_change` INT NOT NULL DEFAULT 0,
    `trust_delta` INT NOT NULL DEFAULT 0,
    `curiosity_delta` INT NOT NULL DEFAULT 0,
    `integrity_delta` INT NOT NULL DEFAULT 0,
    `response_time_ms` INT UNSIGNED NULL,
    `context_data` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_traplog_user` (`user_id`),
    INDEX `idx_traplog_type` (`trap_type`),
    INDEX `idx_traplog_category` (`action_category`),
    INDEX `idx_traplog_created` (`created_at` DESC),
    CONSTRAINT `fk_traplog_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- 4.4 User Trap Cooldowns
CREATE TABLE IF NOT EXISTS `user_trap_cooldowns` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `trap_type` VARCHAR(50) NOT NULL,
    `last_shown_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cooldown_until` TIMESTAMP NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_trap` (`user_id`, `trap_type`),
    CONSTRAINT `fk_cooldown_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ============================================================================
-- SECTION 5: STORED PROCEDURES
-- ============================================================================

DROP PROCEDURE IF EXISTS `sp_update_psychological_profile`;

DELIMITER //

CREATE PROCEDURE `sp_update_psychological_profile`(IN p_user_id BIGINT UNSIGNED)
BEGIN
    DECLARE v_trust INT DEFAULT 100;
    DECLARE v_curiosity INT DEFAULT 0;
    DECLARE v_integrity INT DEFAULT 100;
    DECLARE v_profile_type VARCHAR(30) DEFAULT 'undetermined';
    DECLARE v_risk_level VARCHAR(20) DEFAULT 'low';
    DECLARE v_total_traps INT DEFAULT 0;
    DECLARE v_total_violations INT DEFAULT 0;
    
    SELECT 
        GREATEST(0, LEAST(100, 100 + COALESCE(SUM(trust_delta), 0))),
        GREATEST(0, COALESCE(SUM(CASE WHEN curiosity_delta > 0 THEN curiosity_delta ELSE 0 END), 0)),
        GREATEST(0, LEAST(100, 100 + COALESCE(SUM(integrity_delta), 0))),
        COUNT(*),
        SUM(CASE WHEN action_category IN ('negative', 'critical') THEN 1 ELSE 0 END)
    INTO v_trust, v_curiosity, v_integrity, v_total_traps, v_total_violations
    FROM trap_logs WHERE user_id = p_user_id;
    
    IF v_trust >= 90 AND v_integrity >= 90 THEN
        SET v_profile_type = 'loyal_sentinel';
        SET v_risk_level = 'low';
    ELSEIF v_curiosity >= 30 AND v_trust >= 70 THEN
        SET v_profile_type = 'curious_observer';
        SET v_risk_level = 'low';
    ELSEIF v_trust < 50 AND v_integrity < 50 THEN
        SET v_profile_type = 'active_exploiter';
        SET v_risk_level = 'critical';
    ELSEIF v_trust < 70 AND v_curiosity >= 20 THEN
        SET v_profile_type = 'opportunist';
        SET v_risk_level = 'medium';
    ELSEIF v_trust < 40 THEN
        SET v_profile_type = 'potential_insider';
        SET v_risk_level = 'high';
    END IF;
    
    INSERT INTO psychological_profiles (user_id, trust_score, curiosity_score, integrity_score, profile_type, risk_level, total_traps_seen, total_violations, last_trap_at)
    VALUES (p_user_id, v_trust, v_curiosity, v_integrity, v_profile_type, v_risk_level, v_total_traps, v_total_violations, NOW())
    ON DUPLICATE KEY UPDATE
        trust_score = v_trust, curiosity_score = v_curiosity, integrity_score = v_integrity,
        profile_type = v_profile_type, risk_level = v_risk_level,
        total_traps_seen = v_total_traps, total_violations = v_total_violations,
        last_trap_at = NOW(), updated_at = NOW();
END //

DELIMITER ;

-- ============================================================================
-- SECTION 6: VIEWS
-- ============================================================================

CREATE OR REPLACE VIEW `v_psychological_profiles` AS
SELECT pp.*, u.full_name, u.emp_code, u.email, r.name AS role_name, b.name AS branch_name
FROM psychological_profiles pp
JOIN users u ON pp.user_id = u.id
LEFT JOIN roles r ON u.role_id = r.id
LEFT JOIN branches b ON u.branch_id = b.id;

CREATE OR REPLACE VIEW `v_trap_statistics` AS
SELECT trap_type, COUNT(*) AS total_shown,
    SUM(CASE WHEN action_category = 'positive' THEN 1 ELSE 0 END) AS positive_responses,
    SUM(CASE WHEN action_category = 'negative' THEN 1 ELSE 0 END) AS negative_responses,
    SUM(CASE WHEN action_category = 'critical' THEN 1 ELSE 0 END) AS critical_responses,
    AVG(response_time_ms) AS avg_response_time_ms
FROM trap_logs GROUP BY trap_type;

-- ============================================================================
-- SECTION 7: EMPLOYEE SCHEDULES TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `employee_schedules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `work_start_time` TIME NOT NULL DEFAULT '08:00:00',
    `work_end_time` TIME NOT NULL DEFAULT '17:00:00',
    `grace_period_minutes` INT UNSIGNED NOT NULL DEFAULT 15,
    `attendance_mode` ENUM('unrestricted', 'time_only', 'location_only', 'time_and_location') NOT NULL DEFAULT 'time_and_location',
    `working_days` JSON NULL DEFAULT NULL COMMENT 'أيام العمل [0=الأحد, 6=السبت]',
    `allowed_branches` JSON NULL DEFAULT NULL COMMENT 'الفروع المسموح التسجيل منها',
    `geofence_radius` INT UNSIGNED NOT NULL DEFAULT 100 COMMENT 'نصف قطر السماح بالمتر',
    `is_flexible_hours` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `min_working_hours` DECIMAL(4,2) NOT NULL DEFAULT 8.00,
    `max_working_hours` DECIMAL(4,2) NOT NULL DEFAULT 12.00,
    `early_checkin_minutes` INT UNSIGNED NOT NULL DEFAULT 30,
    `late_checkout_allowed` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `overtime_allowed` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `remote_checkin_allowed` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `late_penalty_per_minute` DECIMAL(5,2) NOT NULL DEFAULT 0.50,
    `early_bonus_points` DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    `overtime_bonus_per_hour` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `effective_from` DATE NULL DEFAULT NULL,
    `effective_until` DATE NULL DEFAULT NULL,
    `notes` TEXT NULL DEFAULT NULL,
    `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_schedule_user` (`user_id`),
    INDEX `idx_schedule_mode` (`attendance_mode`),
    INDEX `idx_schedule_active` (`is_active`),
    CONSTRAINT `fk_schedule_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ============================================================================
-- SECTION 8: LEAVES TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `leaves` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `leave_type` ENUM('annual', 'sick', 'emergency', 'unpaid', 'maternity', 'paternity', 'hajj', 'other') NOT NULL DEFAULT 'annual',
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `days_count` INT UNSIGNED NOT NULL DEFAULT 1,
    `reason` TEXT NULL DEFAULT NULL,
    `attachment` VARCHAR(255) NULL DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    `approved_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `approved_at` TIMESTAMP NULL DEFAULT NULL,
    `rejection_reason` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_leave_user` (`user_id`),
    INDEX `idx_leave_dates` (`start_date`, `end_date`),
    INDEX `idx_leave_status` (`status`),
    CONSTRAINT `fk_leave_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ============================================================================
-- SECTION 9: DEFAULT DATA
-- ============================================================================

-- 9.1 Default Roles
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `role_level`, `permissions`, `color`, `icon`) VALUES
(1, 'موظف', 'employee', 'موظف عادي', 1, '["attendance.view", "attendance.checkin"]', '#6c757d', 'bi-person'),
(2, 'مشرف', 'supervisor', 'مشرف على الفريق', 3, '["attendance.*", "reports.view"]', '#17a2b8', 'bi-person-badge'),
(3, 'مدير فرع', 'branch_manager', 'مدير الفرع', 5, '["attendance.*", "reports.*", "employees.view"]', '#28a745', 'bi-building'),
(4, 'مدير عام', 'general_manager', 'المدير العام', 8, '["*"]', '#fd7e14', 'bi-briefcase'),
(5, 'مدير النظام', 'super_admin', 'مدير النظام الكامل', 10, '["*"]', '#dc3545', 'bi-shield-lock'),
(6, 'المطور', 'developer', 'مطور النظام - صلاحيات كاملة', 99, '["*", "developer.*", "system.*"]', '#9c27b0', 'bi-code-slash')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 9.2 Branches (الفروع الخمسة)
INSERT INTO `branches` (`id`, `name`, `code`, `address`, `city`, `phone`, `email`, `latitude`, `longitude`, `geofence_radius`, `timezone`, `is_active`, `settings`) VALUES
(1, 'صرح الاتقان الرئيسي', 'SARH01', 'المقر الرئيسي', 'الرياض', '+966500000000', 'sarh1@sarh.io', 24.572368, 46.602829, 17, 'Asia/Riyadh', 1, '{"attendance_mode":"flexible"}'),
(2, 'صرح الاتقان كورنر', 'SARH02', 'فرع كورنر', 'الرياض', '+966500000001', 'sarh2@sarh.io', 24.572439, 46.603008, 17, 'Asia/Riyadh', 1, '{"attendance_mode":"flexible"}'),
(3, 'صرح الاتقان 2', 'SARH03', 'الفرع الثاني', 'الرياض', '+966500000002', 'sarh3@sarh.io', 24.572262, 46.602580, 17, 'Asia/Riyadh', 1, '{"attendance_mode":"flexible"}'),
(4, 'فضاء المحركات 1', 'FADA01', 'فضاء المحركات الأول', 'الرياض', '+966500000003', 'fada1@sarh.io', 24.56968126, 46.61405911, 17, 'Asia/Riyadh', 1, '{"attendance_mode":"flexible"}'),
(5, 'فضاء المحركات 2', 'FADA02', 'فضاء المحركات الثاني', 'الرياض', '+966500000004', 'fada2@sarh.io', 24.566088, 46.621759, 17, 'Asia/Riyadh', 1, '{"attendance_mode":"flexible"}')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `latitude` = VALUES(`latitude`), `longitude` = VALUES(`longitude`), `geofence_radius` = VALUES(`geofence_radius`);

-- 9.3 System Admin Account (مدير النظام)
-- Password: Admin@2026 (bcrypt hashed)
INSERT INTO `users` (`id`, `emp_code`, `username`, `email`, `password_hash`, `full_name`, `phone`, `role_id`, `branch_id`, `department`, `job_title`, `hire_date`, `is_active`, `current_points`) VALUES
(1, 'ADMIN001', 'admin', 'admin@sarh.io', '$2y$10$e96Olh1wZfTHeSLTWy7U/eYp0leFXc1zk.Sxeu/bp3v0YpUXzK2ou', 'مدير النظام', '+966500000001', 5, 1, 'الإدارة', 'مدير النظام', '2026-01-01', 1, 1000)
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- 9.4 Developer Account (المطور - The_Architect)
-- Password: MySecretPass2026 (bcrypt hashed)
INSERT INTO `users` (`id`, `emp_code`, `username`, `email`, `password_hash`, `full_name`, `phone`, `role_id`, `branch_id`, `department`, `job_title`, `hire_date`, `is_active`, `current_points`) VALUES
(2, 'DEV001', 'The_Architect', 'architect@sarh.io', '$2y$10$vYcI66G7HDYYvuQTr.h6/.R4bYtg5it/usz3TBuMeGLiyPFZtyiqm', 'المهندس المعماري', '+966500000002', 6, 1, 'التطوير', 'مهندس النظام', '2026-01-01', 1, 9999)
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- 9.5 Sample Employees (موظفين تجريبيين)
-- Password for all: Employee@2026
INSERT INTO `users` (`emp_code`, `username`, `email`, `password_hash`, `full_name`, `phone`, `role_id`, `branch_id`, `department`, `job_title`, `hire_date`, `is_active`, `current_points`) VALUES
('EMP001', 'ahmed', 'ahmed@sarh.io', '$2y$10$dlLatzxdanQS7grKwn29WOQIPjdpu5dQOV0vjSLENC5B3Q52970Ae', 'أحمد محمد', '+966501111111', 1, 1, 'المبيعات', 'مندوب مبيعات', '2026-01-01', 1, 500),
('EMP002', 'sara', 'sara@sarh.io', '$2y$10$dlLatzxdanQS7grKwn29WOQIPjdpu5dQOV0vjSLENC5B3Q52970Ae', 'سارة أحمد', '+966502222222', 1, 1, 'الموارد البشرية', 'أخصائية موارد بشرية', '2026-01-01', 1, 600),
('EMP003', 'khalid', 'khalid@sarh.io', '$2y$10$dlLatzxdanQS7grKwn29WOQIPjdpu5dQOV0vjSLENC5B3Q52970Ae', 'خالد العتيبي', '+966503333333', 2, 1, 'تقنية المعلومات', 'مشرف تقني', '2026-01-01', 1, 750),
('EMP004', 'fatima', 'fatima@sarh.io', '$2y$10$dlLatzxdanQS7grKwn29WOQIPjdpu5dQOV0vjSLENC5B3Q52970Ae', 'فاطمة السالم', '+966504444444', 1, 1, 'المحاسبة', 'محاسبة', '2026-01-01', 1, 550),
('EMP005', 'omar', 'omar@sarh.io', '$2y$10$dlLatzxdanQS7grKwn29WOQIPjdpu5dQOV0vjSLENC5B3Q52970Ae', 'عمر الشمري', '+966505555555', 3, 1, 'العمليات', 'مدير فرع', '2026-01-01', 1, 850)
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- 9.6 Employee Schedules (جداول دوام الموظفين)
-- الافتراضي: من 8 صباحاً إلى 9 مساءً، السماح دائماً
INSERT INTO `employee_schedules` (`user_id`, `work_start_time`, `work_end_time`, `grace_period_minutes`, `attendance_mode`, `working_days`, `geofence_radius`, `is_flexible_hours`, `remote_checkin_allowed`, `is_active`) VALUES
(1, '08:00:00', '21:00:00', 999, 'unrestricted', '[0,1,2,3,4,5,6]', 500, 1, 1, 1),
(2, '00:00:00', '23:59:59', 999, 'unrestricted', '[0,1,2,3,4,5,6]', 99999, 1, 1, 1),
(3, '08:00:00', '21:00:00', 999, 'unrestricted', '[0,1,2,3,4,5,6]', 150, 1, 1, 1),
(4, '08:00:00', '21:00:00', 999, 'unrestricted', '[0,1,2,3,4,5,6]', 150, 1, 1, 1),
(5, '08:00:00', '21:00:00', 999, 'unrestricted', '[0,1,2,3,4,5,6]', 200, 1, 1, 1),
(6, '08:00:00', '21:00:00', 999, 'unrestricted', '[0,1,2,3,4,5,6]', 150, 1, 1, 1),
(7, '08:00:00', '21:00:00', 999, 'unrestricted', '[0,1,2,3,4,5,6]', 200, 1, 1, 1)
ON DUPLICATE KEY UPDATE `attendance_mode` = VALUES(`attendance_mode`);

-- 9.7 Default System Settings
-- إعدادات الدوام الافتراضية: من 8 صباحاً إلى 9 مساءً، السماح بالتسجيل دائماً
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `setting_type`, `description`, `is_public`) VALUES
('app_name', '"صرح الإتقان"', 'general', 'string', 'اسم التطبيق', 1),
('app_logo', '""', 'general', 'string', 'رابط الشعار', 1),
('timezone', '"Asia/Riyadh"', 'general', 'string', 'المنطقة الزمنية', 0),
('work_start_time', '"08:00"', 'attendance', 'string', 'وقت بدء العمل', 1),
('work_end_time', '"21:00"', 'attendance', 'string', 'وقت انتهاء العمل (9 مساءً)', 1),
('grace_period_minutes', '999', 'attendance', 'number', 'فترة السماح (999 = دائماً)', 0),
('checkin_cutoff_hour', '18', 'attendance', 'number', 'ساعة إغلاق الحضور (6 مساءً)', 0),
('late_penalty_per_minute', '0.5', 'attendance', 'number', 'خصم التأخير لكل دقيقة', 0),
('overtime_bonus_per_minute', '0.25', 'attendance', 'number', 'مكافأة الإضافي لكل دقيقة', 0),
('default_attendance_mode', '"unrestricted"', 'attendance', 'string', 'نوع الحضور الافتراضي', 0),
('map_visibility_mode', '"branch"', 'live_ops', 'string', 'وضع رؤية الخريطة', 0),
('heartbeat_interval', '10000', 'live_ops', 'number', 'فاصل النبضات بالمللي ثانية', 0),
('live_mode_enabled', 'true', 'live_ops', 'boolean', 'تفعيل الوضع الحي', 0),
('ghost_branch_enabled', 'true', 'integrity', 'boolean', 'تفعيل الفروع الوهمية', 0),
('main_branch_lat', '24.5723738', 'location', 'string', 'خط عرض المقر الرئيسي', 0),
('main_branch_lng', '46.6028185', 'location', 'string', 'خط طول المقر الرئيسي', 0)
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- 9.8 Default Trap Configurations
INSERT INTO `trap_configurations` (`trap_type`, `trap_name`, `trap_name_ar`, `trigger_chance`, `cooldown_minutes`, `max_role_level`, `settings`) VALUES
('data_leak', 'Salary Data Leak', 'تسريب بيانات الراتب', 0.10, 10080, 7, '{"severity_weight": 10}'),
('gps_debug', 'GPS Debug Mode', 'وضع تصحيح GPS', 0.08, 14400, 5, '{"requires_gps_error": true}'),
('admin_override', 'Ghost Admin Button', 'زر المدير الشبح', 0.05, 20160, 7, '{"appear_duration_ms": 8000}'),
('confidential_bait', 'Confidential Notification', 'طُعم الإشعار السري', 0.12, 7200, 7, '{"auto_dismiss_ms": 12000}'),
('recruitment', 'Recruitment Test', 'اختبار التجنيد', 0.03, 43200, 4, '{"reward_amount": 500}')
ON DUPLICATE KEY UPDATE `trap_name` = VALUES(`trap_name`);

-- 9.9 Welcome Notification
INSERT INTO `notifications` (`type`, `title`, `message`, `icon`, `scope_type`, `is_persistent`, `created_at`) VALUES
('success', 'مرحباً بك في نظام صرح الإتقان!', 'تم تثبيت النظام بنجاح. يمكنك البدء باستخدام جميع الميزات المتاحة.', 'bi-rocket-takeoff', 'global', 1, NOW());

SET FOREIGN_KEY_CHECKS = 1;

SELECT '✅ Sarh Al-Itqan Database v2.0.0 installed successfully!' AS status;
SELECT '📍 Main Branch: الرياض (24.5723738, 46.6028185)' AS branch_info;
SELECT '👤 Admin: admin / Admin@2026' AS admin_info;
SELECT '🔧 Developer: The_Architect / MySecretPass2026' AS dev_info;
