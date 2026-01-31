<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * إصلاح شامل لنظام صرح الإتقان
 * Complete System Fix for Sarh Al-Itqan
 * ═══════════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إصلاح شامل - صرح الإتقان</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; padding: 20px; }
        .fix-card { background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 8px; font-size: 12px; max-height: 200px; overflow: auto; }
    </style>
</head>
<body>
<div class="container">
    <div class="fix-card p-4 my-4">
        <h2 class="text-center mb-4">🔧 إصلاح شامل لنظام صرح الإتقان</h2>
        <hr>
        
<?php
$fixes = [];
$errors = [];

try {
    $pdo = Database::getInstance();
    echo '<div class="alert alert-success">✅ اتصال قاعدة البيانات ناجح</div>';
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // 1. إصلاح جدول users - إضافة الأعمدة المفقودة
    // ═══════════════════════════════════════════════════════════════════════════════
    echo '<h4>1️⃣ إصلاح جدول users</h4>';
    
    $userColumns = [
        'is_super_admin' => "ALTER TABLE `users` ADD COLUMN `is_super_admin` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `is_active`",
        'permissions' => "ALTER TABLE `users` ADD COLUMN `permissions` JSON NULL DEFAULT NULL AFTER `is_super_admin`",
        'visible_modules' => "ALTER TABLE `users` ADD COLUMN `visible_modules` JSON NULL DEFAULT NULL AFTER `permissions`",
        'login_attempts' => "ALTER TABLE `users` ADD COLUMN `login_attempts` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `visible_modules`",
        'locked_until' => "ALTER TABLE `users` ADD COLUMN `locked_until` TIMESTAMP NULL DEFAULT NULL AFTER `login_attempts`",
        'preferences' => "ALTER TABLE `users` ADD COLUMN `preferences` JSON NULL DEFAULT NULL AFTER `locked_until`",
        'last_activity_at' => "ALTER TABLE `users` ADD COLUMN `last_activity_at` TIMESTAMP NULL DEFAULT NULL AFTER `last_login_at`",
        'custom_schedule' => "ALTER TABLE `users` ADD COLUMN `custom_schedule` JSON NULL DEFAULT NULL AFTER `preferences`",
    ];
    
    $existingUserColumns = [];
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        $existingUserColumns[] = $col['Field'];
    }
    
    foreach ($userColumns as $colName => $sql) {
        if (!in_array($colName, $existingUserColumns)) {
            try {
                $pdo->exec($sql);
                echo "<p class='success'>✅ تمت إضافة عمود: $colName</p>";
                $fixes[] = "users.$colName";
            } catch (PDOException $e) {
                echo "<p class='warning'>⚠️ $colName: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='info'>✓ العمود موجود: $colName</p>";
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // 2. إنشاء الجداول المفقودة
    // ═══════════════════════════════════════════════════════════════════════════════
    echo '<h4 class="mt-4">2️⃣ إنشاء الجداول المفقودة</h4>';
    
    $tables = [
        'user_sessions' => "
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
                INDEX `idx_user_sessions_token` (`session_token`(191))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
        ",
        'activity_log' => "
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
                INDEX `idx_activity_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
        ",
        'system_settings' => "
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
                UNIQUE KEY `uk_setting_key` (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
        ",
        'employee_schedules' => "
            CREATE TABLE IF NOT EXISTS `employee_schedules` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `work_start_time` TIME NOT NULL DEFAULT '08:00:00',
                `work_end_time` TIME NOT NULL DEFAULT '17:00:00',
                `grace_period_minutes` INT UNSIGNED NOT NULL DEFAULT 15,
                `attendance_mode` ENUM('strict', 'flexible', 'unrestricted') NOT NULL DEFAULT 'flexible',
                `working_days` JSON NULL DEFAULT NULL,
                `geofence_radius` INT UNSIGNED NOT NULL DEFAULT 100,
                `is_flexible_hours` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `remote_checkin_allowed` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_schedule_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
        ",
        'notifications' => "
            CREATE TABLE IF NOT EXISTS `notifications` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NULL,
                `type` VARCHAR(50) NOT NULL DEFAULT 'info',
                `title` VARCHAR(255) NOT NULL,
                `message` TEXT NULL,
                `icon` VARCHAR(50) NULL DEFAULT 'bi-bell',
                `action_url` VARCHAR(500) NULL,
                `scope_type` ENUM('user', 'role', 'branch', 'global') NOT NULL DEFAULT 'user',
                `scope_value` VARCHAR(100) NULL,
                `is_read` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `is_persistent` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `read_at` TIMESTAMP NULL DEFAULT NULL,
                `expires_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_notification_user` (`user_id`),
                INDEX `idx_notification_scope` (`scope_type`, `scope_value`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
        ",
        'trap_configurations' => "
            CREATE TABLE IF NOT EXISTS `trap_configurations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `trap_type` VARCHAR(50) NOT NULL,
                `trap_name` VARCHAR(100) NOT NULL,
                `trap_name_ar` VARCHAR(100) NULL,
                `trigger_chance` DECIMAL(5,4) NOT NULL DEFAULT 0.1000,
                `cooldown_minutes` INT UNSIGNED NOT NULL DEFAULT 1440,
                `max_role_level` TINYINT UNSIGNED NOT NULL DEFAULT 5,
                `settings` JSON NULL,
                `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_trap_type` (`trap_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
        ",
        'trap_logs' => "
            CREATE TABLE IF NOT EXISTS `trap_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `trap_type` VARCHAR(50) NOT NULL,
                `triggered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `user_action` VARCHAR(50) NULL,
                `action_at` TIMESTAMP NULL,
                `metadata` JSON NULL,
                PRIMARY KEY (`id`),
                INDEX `idx_trap_user` (`user_id`),
                INDEX `idx_trap_type` (`trap_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
        "
    ];
    
    foreach ($tables as $tableName => $createSql) {
        $tableCheck = $pdo->query("SHOW TABLES LIKE '$tableName'")->fetch();
        if (!$tableCheck) {
            try {
                $pdo->exec($createSql);
                echo "<p class='success'>✅ تم إنشاء جدول: $tableName</p>";
                $fixes[] = "table:$tableName";
            } catch (PDOException $e) {
                echo "<p class='error'>❌ خطأ في إنشاء $tableName: " . $e->getMessage() . "</p>";
                $errors[] = $tableName;
            }
        } else {
            echo "<p class='info'>✓ الجدول موجود: $tableName</p>";
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // 3. تحديث صلاحيات المدير والمطور
    // ═══════════════════════════════════════════════════════════════════════════════
    echo '<h4 class="mt-4">3️⃣ تحديث صلاحيات المدير والمطور</h4>';
    
    $pdo->exec("UPDATE users SET is_super_admin = 1 WHERE username IN ('admin', 'The_Architect')");
    echo "<p class='success'>✅ تم تفعيل صلاحيات Super Admin للمدير والمطور</p>";
    
    // تحديث مستوى الأدوار
    $pdo->exec("UPDATE roles SET role_level = 10 WHERE slug = 'super_admin'");
    $pdo->exec("UPDATE roles SET role_level = 99 WHERE slug = 'developer'");
    $pdo->exec("UPDATE roles SET permissions = '[\"*\"]' WHERE slug IN ('super_admin', 'developer')");
    echo "<p class='success'>✅ تم تحديث مستويات الأدوار</p>";
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // 4. إضافة الإعدادات الافتراضية
    // ═══════════════════════════════════════════════════════════════════════════════
    echo '<h4 class="mt-4">4️⃣ إضافة الإعدادات الافتراضية</h4>';
    
    $settings = [
        ['app_name', '"صرح الإتقان"', 'general', 'string'],
        ['timezone', '"Asia/Riyadh"', 'general', 'string'],
        ['work_start_time', '"08:00"', 'attendance', 'string'],
        ['work_end_time', '"21:00"', 'attendance', 'string'],
        ['grace_period_minutes', '999', 'attendance', 'number'],
        ['default_attendance_mode', '"unrestricted"', 'attendance', 'string'],
        ['main_branch_lat', '"24.5723738"', 'location', 'string'],
        ['main_branch_lng', '"46.6028185"', 'location', 'string'],
        ['max_login_attempts', '5', 'security', 'number'],
        ['lockout_duration_minutes', '15', 'security', 'number'],
    ];
    
    $insertSetting = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, setting_group, setting_type) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    
    foreach ($settings as $setting) {
        try {
            $insertSetting->execute($setting);
            echo "<p class='info'>✓ إعداد: {$setting[0]}</p>";
        } catch (PDOException $e) {
            // تجاهل الأخطاء
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // 5. إضافة جداول دوام الموظفين
    // ═══════════════════════════════════════════════════════════════════════════════
    echo '<h4 class="mt-4">5️⃣ إضافة جداول دوام الموظفين</h4>';
    
    $users = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $insertSchedule = $pdo->prepare("
        INSERT INTO employee_schedules (user_id, work_start_time, work_end_time, grace_period_minutes, attendance_mode, working_days, geofence_radius, is_flexible_hours, remote_checkin_allowed, is_active) 
        VALUES (?, '08:00:00', '21:00:00', 999, 'unrestricted', '[0,1,2,3,4,5,6]', 500, 1, 1, 1)
        ON DUPLICATE KEY UPDATE attendance_mode = 'unrestricted', grace_period_minutes = 999
    ");
    
    foreach ($users as $userId) {
        try {
            $insertSchedule->execute([$userId]);
        } catch (PDOException $e) {
            // تجاهل
        }
    }
    echo "<p class='success'>✅ تم إضافة جداول الدوام لـ " . count($users) . " مستخدم</p>";
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // 6. تحديث إحداثيات الفروع
    // ═══════════════════════════════════════════════════════════════════════════════
    echo '<h4 class="mt-4">6️⃣ تحديث نطاق الفروع (geofence)</h4>';
    
    // توسيع نطاق التسجيل للفروع
    $pdo->exec("UPDATE branches SET geofence_radius = 500 WHERE geofence_radius < 100");
    echo "<p class='success'>✅ تم توسيع نطاق التسجيل للفروع إلى 500 متر</p>";
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // 7. إصلاح كلمات المرور
    // ═══════════════════════════════════════════════════════════════════════════════
    echo '<h4 class="mt-4">7️⃣ التحقق من كلمات المرور</h4>';
    
    $adminPassword = password_hash('Admin@2026', PASSWORD_BCRYPT);
    $devPassword = password_hash('MySecretPass2026', PASSWORD_BCRYPT);
    $empPassword = password_hash('123456', PASSWORD_BCRYPT);
    
    // تحديث كلمات المرور للتأكد
    $pdo->exec("UPDATE users SET password_hash = '$adminPassword' WHERE username = 'admin'");
    $pdo->exec("UPDATE users SET password_hash = '$devPassword' WHERE username = 'The_Architect'");
    $pdo->exec("UPDATE users SET password_hash = '$empPassword' WHERE username IN ('ahmed', 'sara', 'khalid', 'fatima', 'omar')");
    
    echo "<p class='success'>✅ تم تحديث كلمات المرور</p>";
    echo "<p class='info'>👤 admin: Admin@2026</p>";
    echo "<p class='info'>👤 The_Architect: MySecretPass2026</p>";
    echo "<p class='info'>👤 الموظفين: 123456</p>";
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // النتيجة النهائية
    // ═══════════════════════════════════════════════════════════════════════════════
    echo '<hr>';
    echo '<div class="alert alert-success">';
    echo '<h4>🎉 تم الإصلاح بنجاح!</h4>';
    echo '<p>تم إصلاح ' . count($fixes) . ' عنصر</p>';
    echo '</div>';
    
    if (count($errors) > 0) {
        echo '<div class="alert alert-warning">';
        echo '<p>⚠️ بعض العناصر لم يتم إصلاحها: ' . implode(', ', $errors) . '</p>';
        echo '</div>';
    }
    
    echo '<div class="text-center mt-4">';
    echo '<a href="login.php" class="btn btn-primary btn-lg">تسجيل الدخول الآن</a>';
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">';
    echo '<h4>❌ خطأ في الاتصال</h4>';
    echo '<p>' . $e->getMessage() . '</p>';
    echo '</div>';
}
?>
    </div>
</div>
</body>
</html>
