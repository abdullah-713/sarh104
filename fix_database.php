<?php
/**
 * إصلاح الأعمدة المفقودة في جدول users
 * Fix Missing Columns in Users Table
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$result = [
    'success' => false,
    'message' => '',
    'fixes_applied' => []
];

try {
    $pdo = Database::getInstance();
    
    // قائمة الأعمدة المطلوب إضافتها
    $columnsToAdd = [
        'is_super_admin' => "ALTER TABLE `users` ADD COLUMN `is_super_admin` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `is_active`",
        'permissions' => "ALTER TABLE `users` ADD COLUMN `permissions` JSON NULL DEFAULT NULL AFTER `is_super_admin`",
        'visible_modules' => "ALTER TABLE `users` ADD COLUMN `visible_modules` JSON NULL DEFAULT NULL AFTER `permissions`",
        'login_attempts' => "ALTER TABLE `users` ADD COLUMN `login_attempts` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `visible_modules`",
        'locked_until' => "ALTER TABLE `users` ADD COLUMN `locked_until` TIMESTAMP NULL DEFAULT NULL AFTER `login_attempts`",
        'preferences' => "ALTER TABLE `users` ADD COLUMN `preferences` JSON NULL DEFAULT NULL AFTER `locked_until`",
        'last_activity_at' => "ALTER TABLE `users` ADD COLUMN `last_activity_at` TIMESTAMP NULL DEFAULT NULL AFTER `last_login_at`",
    ];
    
    // الحصول على الأعمدة الموجودة
    $existingColumns = [];
    $columnsResult = $pdo->query("SHOW COLUMNS FROM users");
    foreach ($columnsResult as $col) {
        $existingColumns[] = $col['Field'];
    }
    
    $result['existing_columns'] = $existingColumns;
    
    // إضافة الأعمدة المفقودة
    foreach ($columnsToAdd as $columnName => $sql) {
        if (!in_array($columnName, $existingColumns)) {
            try {
                $pdo->exec($sql);
                $result['fixes_applied'][] = "✅ تمت إضافة عمود: $columnName";
            } catch (PDOException $e) {
                $result['fixes_applied'][] = "⚠️ خطأ في إضافة $columnName: " . $e->getMessage();
            }
        } else {
            $result['fixes_applied'][] = "✓ العمود موجود: $columnName";
        }
    }
    
    // تحديث المدير ليكون super_admin
    $pdo->exec("UPDATE users SET is_super_admin = 1 WHERE username IN ('admin', 'The_Architect')");
    $result['fixes_applied'][] = "✅ تم تحديث صلاحيات المدير والمطور";
    
    // إنشاء جدول user_sessions إذا لم يكن موجوداً
    $sessionTableCheck = $pdo->query("SHOW TABLES LIKE 'user_sessions'")->fetch();
    if (!$sessionTableCheck) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `user_sessions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `session_token` VARCHAR(255) NOT NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` TEXT NULL,
                `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `expires_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_user_sessions_user` (`user_id`),
                INDEX `idx_user_sessions_token` (`session_token`),
                INDEX `idx_user_sessions_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
        ");
        $result['fixes_applied'][] = "✅ تم إنشاء جدول user_sessions";
    } else {
        $result['fixes_applied'][] = "✓ جدول user_sessions موجود";
    }
    
    // إنشاء جدول activity_log إذا لم يكن موجوداً
    $activityTableCheck = $pdo->query("SHOW TABLES LIKE 'activity_log'")->fetch();
    if (!$activityTableCheck) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `activity_log` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NULL,
                `action` VARCHAR(100) NOT NULL,
                `category` VARCHAR(50) NOT NULL DEFAULT 'general',
                `description` TEXT NULL,
                `entity_type` VARCHAR(50) NULL,
                `entity_id` BIGINT UNSIGNED NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` TEXT NULL,
                `metadata` JSON NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_activity_user` (`user_id`),
                INDEX `idx_activity_action` (`action`),
                INDEX `idx_activity_category` (`category`),
                INDEX `idx_activity_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
        ");
        $result['fixes_applied'][] = "✅ تم إنشاء جدول activity_log";
    } else {
        $result['fixes_applied'][] = "✓ جدول activity_log موجود";
    }
    
    $result['success'] = true;
    $result['message'] = '🎉 تم إصلاح قاعدة البيانات بنجاح! جرب تسجيل الدخول الآن.';
    
} catch (PDOException $e) {
    $result['message'] = '❌ خطأ: ' . $e->getMessage();
} catch (Exception $e) {
    $result['message'] = '❌ خطأ عام: ' . $e->getMessage();
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
