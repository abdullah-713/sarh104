-- ═══════════════════════════════════════════════════════════════════════════════
-- صرح الإتقان - SARH AL-ITQAN
-- Migration: نظام الصلاحيات الفردية المتقدم
-- Date: 2026-01-30
-- ═══════════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 1. إضافة عمود الصلاحيات الفردية للمستخدمين
-- ═══════════════════════════════════════════════════════════════════════════════

ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `permissions` JSON NULL DEFAULT NULL 
COMMENT 'صلاحيات فردية للمستخدم (تُدمج مع صلاحيات الدور)' 
AFTER `custom_schedule`;

ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `visible_modules` JSON NULL DEFAULT NULL 
COMMENT 'الوحدات المرئية للمستخدم (يتحكم بها السوبر يوزر)' 
AFTER `permissions`;

ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `is_super_admin` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 
COMMENT 'صلاحيات مطلقة - لا قيود' 
AFTER `is_active`;

ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `managed_by` BIGINT UNSIGNED NULL DEFAULT NULL 
COMMENT 'المدير المباشر' 
AFTER `branch_id`;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 2. تحديث جدول الأدوار ليكون اسم فقط
-- ═══════════════════════════════════════════════════════════════════════════════

-- الأدوار تبقى كتصنيف فقط، الصلاحيات الفعلية في جدول المستخدمين

-- ═══════════════════════════════════════════════════════════════════════════════
-- 3. إنشاء جدول قائمة الصلاحيات المتاحة
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `available_permissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `permission_key` VARCHAR(100) NOT NULL,
    `permission_name_ar` VARCHAR(150) NOT NULL,
    `permission_name_en` VARCHAR(150) NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'general',
    `description` TEXT NULL DEFAULT NULL,
    `is_dangerous` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'صلاحية خطرة تتطلب تأكيد',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_permission_key` (`permission_key`),
    INDEX `idx_permission_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 4. إدراج الصلاحيات المتاحة
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO `available_permissions` (`permission_key`, `permission_name_ar`, `permission_name_en`, `category`, `is_dangerous`, `sort_order`) VALUES
-- صلاحيات الحضور
('view_attendance', 'عرض سجل الحضور', 'View Attendance', 'attendance', 0, 10),
('checkin_checkout', 'تسجيل الحضور والانصراف', 'Check In/Out', 'attendance', 0, 11),
('edit_own_attendance', 'تعديل حضوره الشخصي', 'Edit Own Attendance', 'attendance', 0, 12),
('edit_all_attendance', 'تعديل حضور الجميع', 'Edit All Attendance', 'attendance', 1, 13),
('view_team_attendance', 'عرض حضور الفريق', 'View Team Attendance', 'attendance', 0, 14),
('approve_corrections', 'الموافقة على طلبات التصحيح', 'Approve Corrections', 'attendance', 0, 15),

-- صلاحيات الموظفين
('view_employees', 'عرض قائمة الموظفين', 'View Employees', 'employees', 0, 20),
('create_employee', 'إضافة موظف جديد', 'Create Employee', 'employees', 0, 21),
('edit_employee', 'تعديل بيانات الموظفين', 'Edit Employee', 'employees', 0, 22),
('delete_employee', 'حذف موظف', 'Delete Employee', 'employees', 1, 23),
('manage_employees', 'إدارة الموظفين كاملة', 'Manage Employees', 'employees', 0, 24),
('reset_password', 'إعادة تعيين كلمات المرور', 'Reset Passwords', 'employees', 1, 25),

-- صلاحيات الفروع
('view_branches', 'عرض الفروع', 'View Branches', 'branches', 0, 30),
('create_branch', 'إضافة فرع جديد', 'Create Branch', 'branches', 0, 31),
('edit_branch', 'تعديل الفروع', 'Edit Branch', 'branches', 0, 32),
('delete_branch', 'حذف فرع', 'Delete Branch', 'branches', 1, 33),
('manage_branches', 'إدارة الفروع كاملة', 'Manage Branches', 'branches', 0, 34),

-- صلاحيات التقارير
('view_reports', 'عرض التقارير', 'View Reports', 'reports', 0, 40),
('export_reports', 'تصدير التقارير', 'Export Reports', 'reports', 0, 41),
('view_analytics', 'عرض التحليلات', 'View Analytics', 'reports', 0, 42),
('view_secret_reports', 'عرض التقارير السرية', 'View Secret Reports', 'reports', 1, 43),

-- صلاحيات الإجازات
('request_leave', 'طلب إجازة', 'Request Leave', 'leaves', 0, 50),
('approve_leave', 'الموافقة على الإجازات', 'Approve Leave', 'leaves', 0, 51),
('manage_leaves', 'إدارة الإجازات كاملة', 'Manage Leaves', 'leaves', 0, 52),

-- صلاحيات الإشعارات
('send_notifications', 'إرسال إشعارات', 'Send Notifications', 'notifications', 0, 60),
('broadcast_notifications', 'إرسال إشعارات للجميع', 'Broadcast Notifications', 'notifications', 0, 61),

-- صلاحيات النظام
('view_settings', 'عرض الإعدادات', 'View Settings', 'system', 0, 70),
('edit_settings', 'تعديل الإعدادات', 'Edit Settings', 'system', 1, 71),
('view_logs', 'عرض سجلات النظام', 'View Logs', 'system', 0, 72),
('manage_roles', 'إدارة الأدوار', 'Manage Roles', 'system', 1, 73),
('manage_permissions', 'إدارة الصلاحيات', 'Manage Permissions', 'system', 1, 74),
('access_developer', 'الوصول لأدوات المطور', 'Access Developer Tools', 'system', 1, 75),

-- صلاحيات النزاهة
('view_integrity', 'عرض سجلات النزاهة', 'View Integrity Logs', 'integrity', 0, 80),
('manage_integrity', 'إدارة النزاهة', 'Manage Integrity', 'integrity', 1, 81),
('view_traps', 'عرض الفخاخ', 'View Traps', 'integrity', 1, 82),
('manage_traps', 'إدارة الفخاخ', 'Manage Traps', 'integrity', 1, 83),

-- صلاحيات الدردشة
('use_chat', 'استخدام الدردشة', 'Use Chat', 'chat', 0, 90),
('moderate_chat', 'إدارة الدردشة', 'Moderate Chat', 'chat', 0, 91),

-- صلاحيات خاصة
('bypass_geofence', 'تجاوز السياج الجغرافي', 'Bypass Geofence', 'special', 1, 100),
('work_remotely', 'العمل عن بعد', 'Work Remotely', 'special', 0, 101),
('flexible_hours', 'ساعات عمل مرنة', 'Flexible Hours', 'special', 0, 102),
('immunity', 'حصانة من العقوبات', 'Immunity', 'special', 1, 103)

ON DUPLICATE KEY UPDATE 
    `permission_name_ar` = VALUES(`permission_name_ar`),
    `permission_name_en` = VALUES(`permission_name_en`);

-- ═══════════════════════════════════════════════════════════════════════════════
-- 5. إنشاء جدول الوحدات/الصفحات المتاحة
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `available_modules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `module_key` VARCHAR(100) NOT NULL,
    `module_name_ar` VARCHAR(150) NOT NULL,
    `module_name_en` VARCHAR(150) NOT NULL,
    `icon` VARCHAR(50) NULL DEFAULT 'bi-app',
    `url` VARCHAR(255) NULL DEFAULT NULL,
    `parent_module` VARCHAR(100) NULL DEFAULT NULL,
    `required_permission` VARCHAR(100) NULL DEFAULT NULL,
    `is_menu_item` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_module_key` (`module_key`),
    INDEX `idx_module_parent` (`parent_module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 6. إدراج الوحدات المتاحة
-- ═══════════════════════════════════════════════════════════════════════════════

INSERT INTO `available_modules` (`module_key`, `module_name_ar`, `module_name_en`, `icon`, `url`, `required_permission`, `sort_order`) VALUES
('dashboard', 'الرئيسية', 'Dashboard', 'bi-house-fill', 'index.php', NULL, 1),
('attendance', 'الحضور', 'Attendance', 'bi-calendar-check', 'attendance.php', 'view_attendance', 2),
('quick_attendance', 'حضور سريع', 'Quick Attendance', 'bi-lightning-fill', 'quick-attendance.php', 'checkin_checkout', 3),
('team_attendance', 'حضور الفريق', 'Team Attendance', 'bi-people-fill', 'team-attendance.php', 'view_team_attendance', 4),
('employees', 'الموظفين', 'Employees', 'bi-person-badge', 'employees.php', 'view_employees', 5),
('branches', 'الفروع', 'Branches', 'bi-building', 'admin/management.php?tab=branches', 'view_branches', 6),
('reports', 'التقارير', 'Reports', 'bi-file-earmark-bar-graph', 'reports.php', 'view_reports', 7),
('analytics', 'التحليلات', 'Analytics', 'bi-graph-up-arrow', 'analytics.php', 'view_analytics', 8),
('leaves', 'الإجازات', 'Leaves', 'bi-calendar-x', 'leaves.php', 'request_leave', 9),
('notifications', 'الإشعارات', 'Notifications', 'bi-bell', 'notifications.php', NULL, 10),
('chat', 'الدردشة', 'Chat', 'bi-chat-dots', 'chat.php', 'use_chat', 11),
('settings', 'الإعدادات', 'Settings', 'bi-gear', 'settings.php', 'view_settings', 12),
('profile', 'الملف الشخصي', 'Profile', 'bi-person-circle', 'profile.php', NULL, 13),
('activity_log', 'سجل النشاط', 'Activity Log', 'bi-list-check', 'activity-log.php', 'view_logs', 14),
('management', 'مركز الإدارة', 'Management', 'bi-sliders', 'admin/management.php', 'manage_employees', 15),
('integrity', 'النزاهة', 'Integrity', 'bi-shield-check', 'admin/management.php?tab=integrity', 'view_integrity', 16),
('live_map', 'الخريطة الحية', 'Live Map', 'bi-map', 'admin/live-map.php', 'view_team_attendance', 17),
('traps', 'الفخاخ', 'Traps', 'bi-bug', 'admin/traps.php', 'manage_traps', 18),
('arena', 'الحلبة', 'Arena', 'bi-trophy', 'dashboard/arena.php', NULL, 19)

ON DUPLICATE KEY UPDATE 
    `module_name_ar` = VALUES(`module_name_ar`),
    `url` = VALUES(`url`);

-- ═══════════════════════════════════════════════════════════════════════════════
-- 7. تحديث المستخدم الأول ليكون سوبر أدمن
-- ═══════════════════════════════════════════════════════════════════════════════

UPDATE `users` SET `is_super_admin` = 1 WHERE `id` = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════════════════════════
-- تم بنجاح! 
-- الآن يمكن إدارة الصلاحيات لكل مستخدم بشكل فردي
-- ═══════════════════════════════════════════════════════════════════════════════
