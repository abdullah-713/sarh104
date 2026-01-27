-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- مضيف: 127.0.0.1:3306
-- وقت الجيل: 27 يناير 2026 الساعة 21:35
-- إصدار الخادم: 11.8.3-MariaDB-log
-- نسخة PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- قاعدة بيانات: `u850419603_101`
--

DELIMITER $$
--
-- الإجراءات
--
CREATE DEFINER=`u850419603_101`@`127.0.0.1` PROCEDURE `sp_update_psychological_profile` (IN `p_user_id` BIGINT UNSIGNED)   BEGIN
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
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- بنية الجدول `activity_log`
--

CREATE TABLE `activity_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `model_type` varchar(100) DEFAULT NULL,
  `model_id` bigint(20) UNSIGNED DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `attendance`
--

CREATE TABLE `attendance` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `recorded_branch_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'الفرع المسجل فيه الحضور (للتاريخ)',
  `date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `check_in_lat` decimal(10,7) DEFAULT NULL,
  `check_in_lng` decimal(10,7) DEFAULT NULL,
  `check_out_lat` decimal(10,7) DEFAULT NULL,
  `check_out_lng` decimal(10,7) DEFAULT NULL,
  `check_in_address` varchar(255) DEFAULT NULL,
  `check_out_address` varchar(255) DEFAULT NULL,
  `check_in_method` enum('manual','auto_gps') DEFAULT 'manual' COMMENT 'طريقة تسجيل الحضور',
  `check_in_distance` decimal(10,2) DEFAULT NULL,
  `check_out_distance` decimal(10,2) DEFAULT NULL,
  `work_minutes` int(10) UNSIGNED DEFAULT NULL,
  `late_minutes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `early_leave_minutes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `overtime_minutes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `penalty_points` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bonus_points` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('present','absent','late','half_day','leave','holiday') NOT NULL DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'قفل السجل بعد الترحيل',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `branches`
--

CREATE TABLE `branches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `geofence_radius` int(10) UNSIGNED NOT NULL DEFAULT 100,
  `timezone` varchar(50) NOT NULL DEFAULT 'Asia/Riyadh',
  `is_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `is_ghost_branch` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `ghost_visible_to` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ghost_visible_to`)),
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- إرجاع أو استيراد بيانات الجدول `branches`
--

INSERT INTO `branches` (`id`, `name`, `code`, `address`, `city`, `phone`, `email`, `latitude`, `longitude`, `geofence_radius`, `timezone`, `is_active`, `is_ghost_branch`, `ghost_visible_to`, `settings`, `created_at`, `updated_at`) VALUES
(1, 'صرح الاتقان الرئيسي', 'SARH01', 'المقر الرئيسي', 'الرياض', '+966500000000', 'sarh1@sarh.io', 24.5723680, 46.6028290, 17, 'Asia/Riyadh', 1, 0, NULL, '{\"attendance_mode\":\"flexible\"}', '2026-01-26 18:42:54', NULL),
(2, 'صرح الاتقان كورنر', 'SARH02', 'فرع كورنر', 'الرياض', '+966500000001', 'sarh2@sarh.io', 24.5724390, 46.6030080, 17, 'Asia/Riyadh', 1, 0, NULL, '{\"attendance_mode\":\"flexible\"}', '2026-01-26 18:42:54', NULL),
(3, 'صرح الاتقان 2', 'SARH03', 'الفرع الثاني', 'الرياض', '+966500000002', 'sarh3@sarh.io', 24.5722620, 46.6025800, 17, 'Asia/Riyadh', 1, 0, NULL, '{\"attendance_mode\":\"flexible\"}', '2026-01-26 18:42:54', NULL),
(4, 'فضاء المحركات 1', 'FADA01', 'فضاء المحركات الأول', 'الرياض', '+966500000003', 'fada1@sarh.io', 24.5696813, 46.6140591, 17, 'Asia/Riyadh', 1, 0, NULL, '{\"attendance_mode\":\"flexible\"}', '2026-01-26 18:42:54', NULL),
(5, 'فضاء المحركات 2', 'FADA02', 'فضاء المحركات الثاني', 'الرياض', '+966500000004', 'fada2@sarh.io', 24.5660880, 46.6217590, 17, 'Asia/Riyadh', 1, 0, NULL, '{\"attendance_mode\":\"flexible\"}', '2026-01-26 18:42:54', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `employee_schedules`
--

CREATE TABLE `employee_schedules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `work_start_time` time NOT NULL DEFAULT '08:00:00',
  `work_end_time` time NOT NULL DEFAULT '17:00:00',
  `grace_period_minutes` int(10) UNSIGNED NOT NULL DEFAULT 15,
  `attendance_mode` enum('unrestricted','time_only','location_only','time_and_location') NOT NULL DEFAULT 'time_and_location',
  `working_days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'أيام العمل [0=الأحد, 6=السبت]' CHECK (json_valid(`working_days`)),
  `allowed_branches` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'الفروع المسموح التسجيل منها' CHECK (json_valid(`allowed_branches`)),
  `geofence_radius` int(10) UNSIGNED NOT NULL DEFAULT 100 COMMENT 'نصف قطر السماح بالمتر',
  `is_flexible_hours` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `min_working_hours` decimal(4,2) NOT NULL DEFAULT 8.00,
  `max_working_hours` decimal(4,2) NOT NULL DEFAULT 12.00,
  `early_checkin_minutes` int(10) UNSIGNED NOT NULL DEFAULT 30,
  `late_checkout_allowed` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `overtime_allowed` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `remote_checkin_allowed` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `late_penalty_per_minute` decimal(5,2) NOT NULL DEFAULT 0.50,
  `early_bonus_points` decimal(5,2) NOT NULL DEFAULT 5.00,
  `overtime_bonus_per_hour` decimal(5,2) NOT NULL DEFAULT 10.00,
  `is_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `effective_from` date DEFAULT NULL,
  `effective_until` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- إرجاع أو استيراد بيانات الجدول `employee_schedules`
--

INSERT INTO `employee_schedules` (`id`, `user_id`, `work_start_time`, `work_end_time`, `grace_period_minutes`, `attendance_mode`, `working_days`, `allowed_branches`, `geofence_radius`, `is_flexible_hours`, `min_working_hours`, `max_working_hours`, `early_checkin_minutes`, `late_checkout_allowed`, `overtime_allowed`, `remote_checkin_allowed`, `late_penalty_per_minute`, `early_bonus_points`, `overtime_bonus_per_hour`, `is_active`, `effective_from`, `effective_until`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, '08:00:00', '21:00:00', 999, 'unrestricted', '[0,1,2,3,4,5,6]', NULL, 500, 1, 8.00, 12.00, 30, 1, 1, 1, 0.50, 5.00, 10.00, 1, NULL, NULL, NULL, NULL, '2026-01-26 18:42:54', NULL),
(2, 2, '00:00:00', '23:59:59', 999, 'unrestricted', '[0,1,2,3,4,5,6]', NULL, 99999, 1, 8.00, 12.00, 30, 1, 1, 1, 0.50, 5.00, 10.00, 1, NULL, NULL, NULL, NULL, '2026-01-26 18:42:54', NULL),
(3, 3, '08:00:00', '21:00:00', 999, 'unrestricted', '[0,1,2,3,4,5,6]', NULL, 150, 1, 8.00, 12.00, 30, 1, 1, 1, 0.50, 5.00, 10.00, 1, NULL, NULL, NULL, NULL, '2026-01-26 18:42:54', NULL),
(4, 4, '08:00:00', '21:00:00', 999, 'unrestricted', '[0,1,2,3,4,5,6]', NULL, 150, 1, 8.00, 12.00, 30, 1, 1, 1, 0.50, 5.00, 10.00, 1, NULL, NULL, NULL, NULL, '2026-01-26 18:42:54', NULL),
(5, 5, '08:00:00', '21:00:00', 999, 'unrestricted', '[0,1,2,3,4,5,6]', NULL, 200, 1, 8.00, 12.00, 30, 1, 1, 1, 0.50, 5.00, 10.00, 1, NULL, NULL, NULL, NULL, '2026-01-26 18:42:54', NULL),
(6, 6, '08:00:00', '21:00:00', 999, 'unrestricted', '[0,1,2,3,4,5,6]', NULL, 150, 1, 8.00, 12.00, 30, 1, 1, 1, 0.50, 5.00, 10.00, 1, NULL, NULL, NULL, NULL, '2026-01-26 18:42:54', NULL),
(7, 7, '08:00:00', '21:00:00', 999, 'unrestricted', '[0,1,2,3,4,5,6]', NULL, 200, 1, 8.00, 12.00, 30, 1, 1, 1, 0.50, 5.00, 10.00, 1, NULL, NULL, NULL, NULL, '2026-01-26 18:42:54', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `integrity_logs`
--

CREATE TABLE `integrity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action_type` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` bigint(20) UNSIGNED DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `location_lat` decimal(10,7) DEFAULT NULL,
  `location_lng` decimal(10,7) DEFAULT NULL,
  `is_reviewed` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `integrity_reports`
--

CREATE TABLE `integrity_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sender_id` bigint(20) UNSIGNED NOT NULL,
  `reported_id` bigint(20) UNSIGNED DEFAULT NULL,
  `report_type` enum('violation','harassment','theft','fraud','other') NOT NULL DEFAULT 'violation',
  `content` text NOT NULL,
  `evidence_files` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`evidence_files`)),
  `is_anonymous_claim` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `admin_notes` text DEFAULT NULL,
  `status` enum('pending','investigating','resolved','dismissed','fake') NOT NULL DEFAULT 'pending',
  `resolved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `sender_revealed_to` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sender_revealed_to`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `leaves`
--

CREATE TABLE `leaves` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `leave_type` enum('annual','sick','emergency','unpaid','maternity','paternity','hajj','other') NOT NULL DEFAULT 'annual',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_count` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `reason` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `action_id` int(10) UNSIGNED DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'bi-bell',
  `scope_type` enum('global','branch','user') NOT NULL DEFAULT 'user',
  `scope_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action_url` varchar(255) DEFAULT NULL,
  `is_persistent` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- إرجاع أو استيراد بيانات الجدول `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `title`, `message`, `icon`, `scope_type`, `scope_id`, `action_url`, `is_persistent`, `expires_at`, `created_by`, `created_at`) VALUES
(1, 'success', 'مرحباً بك في نظام صرح الإتقان!', 'تم تثبيت النظام بنجاح. يمكنك البدء باستخدام جميع الميزات المتاحة.', 'bi-rocket-takeoff', 'global', NULL, NULL, 1, NULL, NULL, '2026-01-26 18:42:54');

-- --------------------------------------------------------

--
-- بنية الجدول `psychological_profiles`
--

CREATE TABLE `psychological_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `trust_score` int(11) NOT NULL DEFAULT 100,
  `curiosity_score` int(11) NOT NULL DEFAULT 0,
  `integrity_score` int(11) NOT NULL DEFAULT 100,
  `profile_type` enum('loyal_sentinel','curious_observer','opportunist','active_exploiter','potential_insider','undetermined') NOT NULL DEFAULT 'undetermined',
  `risk_level` enum('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `total_traps_seen` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_violations` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_trap_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `push_subscriptions`
--

CREATE TABLE `push_subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `endpoint` text NOT NULL,
  `endpoint_hash` char(64) NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `subscription_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`subscription_json`)),
  `user_agent` text DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `role_level` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `color` varchar(20) DEFAULT '#6c757d',
  `icon` varchar(50) DEFAULT 'bi-person',
  `is_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- إرجاع أو استيراد بيانات الجدول `roles`
--

INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `role_level`, `permissions`, `color`, `icon`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'موظف', 'employee', 'موظف عادي', 1, '[\"attendance.view\", \"attendance.checkin\"]', '#6c757d', 'bi-person', 1, '2026-01-26 18:42:54', NULL),
(2, 'مشرف', 'supervisor', 'مشرف على الفريق', 3, '[\"attendance.*\", \"reports.view\"]', '#17a2b8', 'bi-person-badge', 1, '2026-01-26 18:42:54', NULL),
(3, 'مدير فرع', 'branch_manager', 'مدير الفرع', 5, '[\"attendance.*\", \"reports.*\", \"employees.view\"]', '#28a745', 'bi-building', 1, '2026-01-26 18:42:54', NULL),
(4, 'مدير عام', 'general_manager', 'المدير العام', 8, '[\"*\"]', '#fd7e14', 'bi-briefcase', 1, '2026-01-26 18:42:54', NULL),
(5, 'مدير النظام', 'super_admin', 'مدير النظام الكامل', 10, '[\"*\"]', '#dc3545', 'bi-shield-lock', 1, '2026-01-26 18:42:54', NULL),
(6, 'المطور', 'developer', 'مطور النظام - صلاحيات كاملة', 99, '[\"*\", \"developer.*\", \"system.*\"]', '#9c27b0', 'bi-code-slash', 1, '2026-01-26 18:42:54', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `system_settings`
--

CREATE TABLE `system_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`setting_value`)),
  `setting_group` varchar(50) NOT NULL DEFAULT 'general',
  `setting_type` enum('string','number','boolean','json','text') NOT NULL DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `is_public` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- إرجاع أو استيراد بيانات الجدول `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `setting_type`, `description`, `is_public`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'app_name', '\"صرح الإتقان\"', 'general', 'string', 'اسم التطبيق', 1, 0, '2026-01-26 18:42:54', NULL),
(2, 'app_logo', '\"\"', 'general', 'string', 'رابط الشعار', 1, 0, '2026-01-26 18:42:54', NULL),
(3, 'timezone', '\"Asia/Riyadh\"', 'general', 'string', 'المنطقة الزمنية', 0, 0, '2026-01-26 18:42:54', NULL),
(4, 'work_start_time', '\"08:00\"', 'attendance', 'string', 'وقت بدء العمل', 1, 0, '2026-01-26 18:42:54', NULL),
(5, 'work_end_time', '\"21:00\"', 'attendance', 'string', 'وقت انتهاء العمل (9 مساءً)', 1, 0, '2026-01-26 18:42:54', NULL),
(6, 'grace_period_minutes', '999', 'attendance', 'number', 'فترة السماح (999 = دائماً)', 0, 0, '2026-01-26 18:42:54', NULL),
(7, 'checkin_cutoff_hour', '18', 'attendance', 'number', 'ساعة إغلاق الحضور (6 مساءً)', 0, 0, '2026-01-26 18:42:54', NULL),
(8, 'late_penalty_per_minute', '0.5', 'attendance', 'number', 'خصم التأخير لكل دقيقة', 0, 0, '2026-01-26 18:42:54', NULL),
(9, 'overtime_bonus_per_minute', '0.25', 'attendance', 'number', 'مكافأة الإضافي لكل دقيقة', 0, 0, '2026-01-26 18:42:54', NULL),
(10, 'default_attendance_mode', '\"unrestricted\"', 'attendance', 'string', 'نوع الحضور الافتراضي', 0, 0, '2026-01-26 18:42:54', NULL),
(11, 'map_visibility_mode', '\"branch\"', 'live_ops', 'string', 'وضع رؤية الخريطة', 0, 0, '2026-01-26 18:42:54', NULL),
(12, 'heartbeat_interval', '10000', 'live_ops', 'number', 'فاصل النبضات بالمللي ثانية', 0, 0, '2026-01-26 18:42:54', NULL),
(13, 'live_mode_enabled', 'true', 'live_ops', 'boolean', 'تفعيل الوضع الحي', 0, 0, '2026-01-26 18:42:54', NULL),
(14, 'ghost_branch_enabled', 'true', 'integrity', 'boolean', 'تفعيل الفروع الوهمية', 0, 0, '2026-01-26 18:42:54', NULL),
(15, 'main_branch_lat', '24.5723738', 'location', 'string', 'خط عرض المقر الرئيسي', 0, 0, '2026-01-26 18:42:54', NULL),
(16, 'main_branch_lng', '46.6028185', 'location', 'string', 'خط طول المقر الرئيسي', 0, 0, '2026-01-26 18:42:54', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `trap_configurations`
--

CREATE TABLE `trap_configurations` (
  `id` int(10) UNSIGNED NOT NULL,
  `trap_type` varchar(50) NOT NULL,
  `trap_name` varchar(100) NOT NULL,
  `trap_name_ar` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `trigger_chance` decimal(4,2) NOT NULL DEFAULT 0.10,
  `cooldown_minutes` int(10) UNSIGNED NOT NULL DEFAULT 10080,
  `min_role_level` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `max_role_level` int(10) UNSIGNED NOT NULL DEFAULT 7,
  `is_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- إرجاع أو استيراد بيانات الجدول `trap_configurations`
--

INSERT INTO `trap_configurations` (`id`, `trap_type`, `trap_name`, `trap_name_ar`, `description`, `trigger_chance`, `cooldown_minutes`, `min_role_level`, `max_role_level`, `is_active`, `settings`, `created_at`) VALUES
(1, 'data_leak', 'Salary Data Leak', 'تسريب بيانات الراتب', NULL, 0.10, 10080, 1, 7, 1, '{\"severity_weight\": 10}', '2026-01-26 18:42:54'),
(2, 'gps_debug', 'GPS Debug Mode', 'وضع تصحيح GPS', NULL, 0.08, 14400, 1, 5, 1, '{\"requires_gps_error\": true}', '2026-01-26 18:42:54'),
(3, 'admin_override', 'Ghost Admin Button', 'زر المدير الشبح', NULL, 0.05, 20160, 1, 7, 1, '{\"appear_duration_ms\": 8000}', '2026-01-26 18:42:54'),
(4, 'confidential_bait', 'Confidential Notification', 'طُعم الإشعار السري', NULL, 0.12, 7200, 1, 7, 1, '{\"auto_dismiss_ms\": 12000}', '2026-01-26 18:42:54'),
(5, 'recruitment', 'Recruitment Test', 'اختبار التجنيد', NULL, 0.03, 43200, 1, 4, 1, '{\"reward_amount\": 500}', '2026-01-26 18:42:54');

-- --------------------------------------------------------

--
-- بنية الجدول `trap_logs`
--

CREATE TABLE `trap_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `trap_type` varchar(50) NOT NULL,
  `trap_config_id` int(10) UNSIGNED DEFAULT NULL,
  `action_taken` varchar(50) NOT NULL,
  `action_category` enum('positive','neutral','negative','critical') NOT NULL DEFAULT 'neutral',
  `score_change` int(11) NOT NULL DEFAULT 0,
  `trust_delta` int(11) NOT NULL DEFAULT 0,
  `curiosity_delta` int(11) NOT NULL DEFAULT 0,
  `integrity_delta` int(11) NOT NULL DEFAULT 0,
  `response_time_ms` int(10) UNSIGNED DEFAULT NULL,
  `context_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context_data`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `emp_code` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `branch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `national_id` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `is_online` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `last_latitude` decimal(10,7) DEFAULT NULL,
  `last_longitude` decimal(10,7) DEFAULT NULL,
  `login_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `current_points` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_points_earned` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_points_deducted` decimal(10,2) NOT NULL DEFAULT 0.00,
  `streak_count` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'عداد الأيام المثالية المتتالية للحلبة',
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `custom_schedule` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_schedule`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`id`, `emp_code`, `username`, `email`, `password_hash`, `full_name`, `phone`, `avatar`, `role_id`, `branch_id`, `department`, `job_title`, `hire_date`, `national_id`, `is_active`, `is_online`, `email_verified_at`, `last_login_at`, `last_activity_at`, `last_latitude`, `last_longitude`, `login_attempts`, `locked_until`, `remember_token`, `current_points`, `total_points_earned`, `total_points_deducted`, `streak_count`, `preferences`, `custom_schedule`, `created_at`, `updated_at`) VALUES
(10, 'ADMIN001', 'admin', 'admin@sarh.online', '$2y$10$FzA2RNtExS.lQ2mDHxOImOoX/8m2a8xP3f.AQIstw2m8qTXpp6S0C', 'مدير النظام', NULL, NULL, 5, 1, NULL, NULL, NULL, NULL, 1, 1, NULL, '2026-01-27 13:13:31', '2026-01-27 13:13:31', NULL, NULL, 0, NULL, NULL, 0.00, 0.00, 0.00, 0, NULL, NULL, '2026-01-27 13:09:42', '2026-01-27 13:13:31'),
(11, 'EMP001', 'employee1', 'emp1@sarh.online', '$2y$10$ok5D8lDH9PjG2qZvAUKq4uTF0lGXzRk9Y8Fbbb/oWA2QWDk2DYSYe', 'أحمد محمد', NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0.00, 0.00, 0.00, 0, NULL, NULL, '2026-01-27 13:09:42', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `user_location_history`
--

CREATE TABLE `user_location_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `accuracy` decimal(10,2) DEFAULT NULL,
  `source` enum('gps','network','manual') NOT NULL DEFAULT 'gps',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `user_notification_reads`
--

CREATE TABLE `user_notification_reads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `notification_id` bigint(20) UNSIGNED NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `device_name` varchar(100) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `is_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- إرجاع أو استيراد بيانات الجدول `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `device_type`, `device_name`, `browser`, `ip_address`, `user_agent`, `is_active`, `last_activity_at`, `expires_at`, `created_at`) VALUES
(1, 10, '8b1bf9c028fed5b80836d52e1f73b56f3e5b39dccb1a6330b9b150d34d0f7fe8', 'desktop', NULL, NULL, '176.17.144.250', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, NULL, '2026-01-27 15:13:31', '2026-01-27 13:13:31');

-- --------------------------------------------------------

--
-- بنية الجدول `user_trap_cooldowns`
--

CREATE TABLE `user_trap_cooldowns` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `trap_type` varchar(50) NOT NULL,
  `last_shown_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cooldown_until` timestamp NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_psychological_profiles`
-- (See below for the actual view)
--
CREATE TABLE `v_psychological_profiles` (
`id` bigint(20) unsigned
,`user_id` bigint(20) unsigned
,`trust_score` int(11)
,`curiosity_score` int(11)
,`integrity_score` int(11)
,`profile_type` enum('loyal_sentinel','curious_observer','opportunist','active_exploiter','potential_insider','undetermined')
,`risk_level` enum('low','medium','high','critical')
,`total_traps_seen` int(10) unsigned
,`total_violations` int(10) unsigned
,`last_trap_at` timestamp
,`created_at` timestamp
,`updated_at` timestamp
,`full_name` varchar(100)
,`emp_code` varchar(50)
,`email` varchar(100)
,`role_name` varchar(100)
,`branch_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_trap_statistics`
-- (See below for the actual view)
--
CREATE TABLE `v_trap_statistics` (
`trap_type` varchar(50)
,`total_shown` bigint(21)
,`positive_responses` decimal(22,0)
,`negative_responses` decimal(22,0)
,`critical_responses` decimal(22,0)
,`avg_response_time_ms` decimal(14,4)
);

--
-- Indexes for dumped tables
--

--
-- فهارس للجدول `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_user` (`user_id`),
  ADD KEY `idx_activity_action` (`action`),
  ADD KEY `idx_activity_model` (`model_type`,`model_id`),
  ADD KEY `idx_activity_created` (`created_at`);

--
-- فهارس للجدول `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_attendance_user_date` (`user_id`,`date`),
  ADD KEY `idx_attendance_branch` (`branch_id`),
  ADD KEY `idx_attendance_recorded_branch` (`recorded_branch_id`),
  ADD KEY `idx_attendance_date` (`date`),
  ADD KEY `idx_attendance_status` (`status`);

--
-- فهارس للجدول `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_branch_code` (`code`),
  ADD KEY `idx_branch_active` (`is_active`),
  ADD KEY `idx_branch_ghost` (`is_ghost_branch`);

--
-- فهارس للجدول `employee_schedules`
--
ALTER TABLE `employee_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_schedule_user` (`user_id`),
  ADD KEY `idx_schedule_mode` (`attendance_mode`),
  ADD KEY `idx_schedule_active` (`is_active`);

--
-- فهارس للجدول `integrity_logs`
--
ALTER TABLE `integrity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_integrity_user` (`user_id`),
  ADD KEY `idx_integrity_type` (`action_type`),
  ADD KEY `idx_integrity_severity` (`severity`),
  ADD KEY `idx_integrity_created` (`created_at` DESC),
  ADD KEY `idx_integrity_reviewed` (`is_reviewed`);

--
-- فهارس للجدول `integrity_reports`
--
ALTER TABLE `integrity_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reports_sender` (`sender_id`),
  ADD KEY `idx_reports_reported` (`reported_id`),
  ADD KEY `idx_reports_status` (`status`),
  ADD KEY `idx_reports_created` (`created_at` DESC);

--
-- فهارس للجدول `leaves`
--
ALTER TABLE `leaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_leave_user` (`user_id`),
  ADD KEY `idx_leave_dates` (`start_date`,`end_date`),
  ADD KEY `idx_leave_status` (`status`),
  ADD KEY `idx_action_id` (`action_id`);

--
-- فهارس للجدول `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification_type` (`type`),
  ADD KEY `idx_notification_scope` (`scope_type`,`scope_id`),
  ADD KEY `idx_notification_expires` (`expires_at`),
  ADD KEY `idx_notification_created` (`created_at` DESC);

--
-- فهارس للجدول `psychological_profiles`
--
ALTER TABLE `psychological_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_profile_user` (`user_id`),
  ADD KEY `idx_profile_type` (`profile_type`),
  ADD KEY `idx_profile_risk` (`risk_level`),
  ADD KEY `idx_profile_trust` (`trust_score`);

--
-- فهارس للجدول `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_push_endpoint_hash` (`endpoint_hash`),
  ADD KEY `idx_push_user` (`user_id`),
  ADD KEY `idx_push_active` (`is_active`);

--
-- فهارس للجدول `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_role_slug` (`slug`),
  ADD KEY `idx_role_level` (`role_level`),
  ADD KEY `idx_role_active` (`is_active`);

--
-- فهارس للجدول `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_setting_key` (`setting_key`),
  ADD KEY `idx_setting_group` (`setting_group`),
  ADD KEY `idx_setting_public` (`is_public`);

--
-- فهارس للجدول `trap_configurations`
--
ALTER TABLE `trap_configurations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_trap_type` (`trap_type`),
  ADD KEY `idx_trap_active` (`is_active`);

--
-- فهارس للجدول `trap_logs`
--
ALTER TABLE `trap_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_traplog_user` (`user_id`),
  ADD KEY `idx_traplog_type` (`trap_type`),
  ADD KEY `idx_traplog_category` (`action_category`),
  ADD KEY `idx_traplog_created` (`created_at` DESC);

--
-- فهارس للجدول `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_emp_code` (`emp_code`),
  ADD UNIQUE KEY `uk_username` (`username`),
  ADD UNIQUE KEY `uk_email` (`email`),
  ADD KEY `idx_user_role` (`role_id`),
  ADD KEY `idx_user_branch` (`branch_id`),
  ADD KEY `idx_user_active` (`is_active`),
  ADD KEY `idx_user_online` (`is_online`),
  ADD KEY `idx_user_activity` (`last_activity_at`);

--
-- فهارس للجدول `user_location_history`
--
ALTER TABLE `user_location_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_location_user` (`user_id`),
  ADD KEY `idx_location_created` (`created_at`);

--
-- فهارس للجدول `user_notification_reads`
--
ALTER TABLE `user_notification_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_notification` (`user_id`,`notification_id`),
  ADD KEY `fk_unr_notification` (`notification_id`);

--
-- فهارس للجدول `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_session_token` (`session_token`),
  ADD KEY `idx_session_user` (`user_id`),
  ADD KEY `idx_session_active` (`is_active`),
  ADD KEY `idx_session_expires` (`expires_at`);

--
-- فهارس للجدول `user_trap_cooldowns`
--
ALTER TABLE `user_trap_cooldowns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_trap` (`user_id`,`trap_type`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `employee_schedules`
--
ALTER TABLE `employee_schedules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `integrity_logs`
--
ALTER TABLE `integrity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `integrity_reports`
--
ALTER TABLE `integrity_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leaves`
--
ALTER TABLE `leaves`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `psychological_profiles`
--
ALTER TABLE `psychological_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `trap_configurations`
--
ALTER TABLE `trap_configurations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `trap_logs`
--
ALTER TABLE `trap_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_location_history`
--
ALTER TABLE `user_location_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_notification_reads`
--
ALTER TABLE `user_notification_reads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_trap_cooldowns`
--
ALTER TABLE `user_trap_cooldowns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `v_psychological_profiles`
--
DROP TABLE IF EXISTS `v_psychological_profiles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u850419603_101`@`127.0.0.1` SQL SECURITY DEFINER VIEW `v_psychological_profiles`  AS SELECT `pp`.`id` AS `id`, `pp`.`user_id` AS `user_id`, `pp`.`trust_score` AS `trust_score`, `pp`.`curiosity_score` AS `curiosity_score`, `pp`.`integrity_score` AS `integrity_score`, `pp`.`profile_type` AS `profile_type`, `pp`.`risk_level` AS `risk_level`, `pp`.`total_traps_seen` AS `total_traps_seen`, `pp`.`total_violations` AS `total_violations`, `pp`.`last_trap_at` AS `last_trap_at`, `pp`.`created_at` AS `created_at`, `pp`.`updated_at` AS `updated_at`, `u`.`full_name` AS `full_name`, `u`.`emp_code` AS `emp_code`, `u`.`email` AS `email`, `r`.`name` AS `role_name`, `b`.`name` AS `branch_name` FROM (((`psychological_profiles` `pp` join `users` `u` on(`pp`.`user_id` = `u`.`id`)) left join `roles` `r` on(`u`.`role_id` = `r`.`id`)) left join `branches` `b` on(`u`.`branch_id` = `b`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_trap_statistics`
--
DROP TABLE IF EXISTS `v_trap_statistics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u850419603_101`@`127.0.0.1` SQL SECURITY DEFINER VIEW `v_trap_statistics`  AS SELECT `trap_logs`.`trap_type` AS `trap_type`, count(0) AS `total_shown`, sum(case when `trap_logs`.`action_category` = 'positive' then 1 else 0 end) AS `positive_responses`, sum(case when `trap_logs`.`action_category` = 'negative' then 1 else 0 end) AS `negative_responses`, sum(case when `trap_logs`.`action_category` = 'critical' then 1 else 0 end) AS `critical_responses`, avg(`trap_logs`.`response_time_ms`) AS `avg_response_time_ms` FROM `trap_logs` GROUP BY `trap_logs`.`trap_type` ;

--
-- القيود المفروضة على الجداول الملقاة
--

--
-- قيود الجداول `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `employee_schedules`
--
ALTER TABLE `employee_schedules`
  ADD CONSTRAINT `fk_schedule_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `leaves`
--
ALTER TABLE `leaves`
  ADD CONSTRAINT `fk_leave_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `psychological_profiles`
--
ALTER TABLE `psychological_profiles`
  ADD CONSTRAINT `fk_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `trap_logs`
--
ALTER TABLE `trap_logs`
  ADD CONSTRAINT `fk_traplog_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;

--
-- قيود الجداول `user_location_history`
--
ALTER TABLE `user_location_history`
  ADD CONSTRAINT `fk_location_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `user_notification_reads`
--
ALTER TABLE `user_notification_reads`
  ADD CONSTRAINT `fk_unr_notification` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_unr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `user_trap_cooldowns`
--
ALTER TABLE `user_trap_cooldowns`
  ADD CONSTRAINT `fk_cooldown_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
