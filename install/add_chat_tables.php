<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * 🗄️ إنشاء جداول نظام الدردشة - Chat System Tables Creation
 * ═══════════════════════════════════════════════════════════════
 * 
 * تشغيل هذا الملف مرة واحدة لإنشاء الجداول المطلوبة
 */

require_once dirname(__DIR__) . '/config/app.php';

// عرض الأخطاء للتشخيص
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>";
echo "<html dir='rtl' lang='ar'><head><meta charset='utf-8'><title>إنشاء جداول الشات</title>";
echo "<style>
body{font-family:Tahoma,Arial,sans-serif;padding:20px;background:#1a1a2e;color:#eee;max-width:900px;margin:0 auto;}
h1{color:#5865f2;border-bottom:2px solid #5865f2;padding-bottom:10px;}
h2{color:#7289da;margin-top:30px;}
.success{background:linear-gradient(135deg,#28a745,#20c997);color:#fff;padding:12px 15px;margin:8px 0;border-radius:8px;box-shadow:0 2px 10px rgba(40,167,69,0.3);}
.error{background:linear-gradient(135deg,#dc3545,#c82333);color:#fff;padding:12px 15px;margin:8px 0;border-radius:8px;box-shadow:0 2px 10px rgba(220,53,69,0.3);}
.info{background:linear-gradient(135deg,#17a2b8,#138496);color:#fff;padding:12px 15px;margin:8px 0;border-radius:8px;}
.warning{background:linear-gradient(135deg,#ffc107,#e0a800);color:#000;padding:12px 15px;margin:8px 0;border-radius:8px;}
pre{background:#2d2d44;padding:15px;border-radius:8px;overflow-x:auto;font-size:12px;border:1px solid #444;}
.box{background:rgba(255,255,255,0.05);padding:20px;border-radius:12px;margin:20px 0;border:1px solid rgba(255,255,255,0.1);}
a{color:#5865f2;text-decoration:none;}
a:hover{text-decoration:underline;}
.emoji{font-size:1.5em;margin-left:8px;}
</style></head><body>";

echo "<h1><span class='emoji'>💬</span> إنشاء جداول نظام الدردشة الجماعية</h1>";
echo "<div class='box'>";

try {
    $pdo = Database::getInstance();
    
    // التحقق من وجود جدول users ونوع الـ id
    echo "<h2>📋 فحص قاعدة البيانات</h2>";
    
    $userTable = $pdo->query("SHOW COLUMNS FROM users WHERE Field = 'id'")->fetch(PDO::FETCH_ASSOC);
    if ($userTable) {
        echo "<div class='info'>✓ جدول users موجود - نوع id: <strong>{$userTable['Type']}</strong></div>";
    }
    
    // تحديد نوع المفتاح الأجنبي بناءً على نوع id في جدول users
    $userIdType = 'INT UNSIGNED';
    if (strpos(strtolower($userTable['Type']), 'bigint') !== false) {
        $userIdType = 'BIGINT UNSIGNED';
    }
    
    // التحقق من وجود جدول branches
    $branchExists = $pdo->query("SHOW TABLES LIKE 'branches'")->fetch();
    
    echo "<hr style='border-color:#333;margin:20px 0;'>";
    
    // ═══════════════════════════════════════════════════════════════
    // حذف الجداول القديمة إذا طُلب ذلك
    // ═══════════════════════════════════════════════════════════════
    if (isset($_GET['reset']) && $_GET['reset'] === '1') {
        echo "<h2>🗑️ حذف الجداول القديمة</h2>";
        
        // تعطيل فحص المفاتيح الأجنبية مؤقتاً
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        $dropTables = ['chat_notifications', 'chat_typing', 'chat_pinned_messages', 'chat_messages', 'chat_room_members', 'chat_rooms'];
        foreach ($dropTables as $table) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
                echo "<div class='warning'>⚠ تم حذف جدول {$table}</div>";
            } catch (PDOException $e) {
                echo "<div class='error'>✗ خطأ في حذف {$table}: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo "<hr style='border-color:#333;margin:20px 0;'>";
    }
    
    // ═══════════════════════════════════════════════════════════════
    // إنشاء الجداول
    // ═══════════════════════════════════════════════════════════════
    echo "<h2>🏗️ إنشاء الجداول</h2>";
    
    $tables = [];
    
    // ─────────────────────────────────────────────────────────────────
    // 1. جدول غرف الدردشة
    // ─────────────────────────────────────────────────────────────────
    $tables['chat_rooms'] = "
        CREATE TABLE IF NOT EXISTS `chat_rooms` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT NULL,
            `type` ENUM('public', 'private', 'branch', 'department', 'direct') DEFAULT 'private',
            `avatar` VARCHAR(255) NULL,
            `branch_id` INT UNSIGNED NULL,
            `department_id` INT UNSIGNED NULL,
            `created_by` {$userIdType} NOT NULL,
            `settings` JSON NULL COMMENT 'إعدادات الغرفة',
            `last_message_at` DATETIME NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_type` (`type`),
            INDEX `idx_branch` (`branch_id`),
            INDEX `idx_created_by` (`created_by`),
            INDEX `idx_active` (`is_active`),
            INDEX `idx_last_message` (`last_message_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    // ─────────────────────────────────────────────────────────────────
    // 2. جدول أعضاء الغرف
    // ─────────────────────────────────────────────────────────────────
    $tables['chat_room_members'] = "
        CREATE TABLE IF NOT EXISTS `chat_room_members` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `room_id` INT UNSIGNED NOT NULL,
            `user_id` {$userIdType} NOT NULL,
            `role` ENUM('owner', 'admin', 'moderator', 'member') DEFAULT 'member',
            `nickname` VARCHAR(50) NULL,
            `notifications_enabled` TINYINT(1) DEFAULT 1,
            `is_muted` TINYINT(1) DEFAULT 0,
            `muted_until` DATETIME NULL,
            `last_read_at` DATETIME NULL,
            `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_room_member` (`room_id`, `user_id`),
            INDEX `idx_user` (`user_id`),
            INDEX `idx_role` (`role`),
            INDEX `idx_room_user` (`room_id`, `user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    // ─────────────────────────────────────────────────────────────────
    // 3. جدول الرسائل
    // ─────────────────────────────────────────────────────────────────
    $tables['chat_messages'] = "
        CREATE TABLE IF NOT EXISTS `chat_messages` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `room_id` INT UNSIGNED NOT NULL,
            `user_id` {$userIdType} NOT NULL,
            `message_type` ENUM('text', 'image', 'file', 'voice', 'video', 'location', 'system', 'reply') DEFAULT 'text',
            `content` TEXT NOT NULL,
            `reply_to_id` BIGINT UNSIGNED NULL,
            `attachments` JSON NULL COMMENT 'قائمة المرفقات',
            `reactions` JSON NULL COMMENT 'التفاعلات',
            `mentions` JSON NULL COMMENT 'الإشارات للمستخدمين',
            `is_edited` TINYINT(1) DEFAULT 0,
            `edited_at` DATETIME NULL,
            `is_deleted` TINYINT(1) DEFAULT 0,
            `deleted_at` DATETIME NULL,
            `deleted_by` {$userIdType} NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_room` (`room_id`),
            INDEX `idx_user` (`user_id`),
            INDEX `idx_created` (`created_at`),
            INDEX `idx_deleted` (`is_deleted`),
            INDEX `idx_reply` (`reply_to_id`),
            INDEX `idx_room_created` (`room_id`, `created_at`),
            INDEX `idx_type` (`message_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    // ─────────────────────────────────────────────────────────────────
    // 4. جدول الرسائل المثبتة
    // ─────────────────────────────────────────────────────────────────
    $tables['chat_pinned_messages'] = "
        CREATE TABLE IF NOT EXISTS `chat_pinned_messages` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `room_id` INT UNSIGNED NOT NULL,
            `message_id` BIGINT UNSIGNED NOT NULL,
            `pinned_by` {$userIdType} NOT NULL,
            `pinned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_pinned` (`room_id`, `message_id`),
            INDEX `idx_room` (`room_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    // ─────────────────────────────────────────────────────────────────
    // 5. جدول حالة الكتابة
    // ─────────────────────────────────────────────────────────────────
    $tables['chat_typing'] = "
        CREATE TABLE IF NOT EXISTS `chat_typing` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `room_id` INT UNSIGNED NOT NULL,
            `user_id` {$userIdType} NOT NULL,
            `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_typing` (`room_id`, `user_id`),
            INDEX `idx_room` (`room_id`),
            INDEX `idx_started` (`started_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    // ─────────────────────────────────────────────────────────────────
    // 6. جدول الإشعارات
    // ─────────────────────────────────────────────────────────────────
    $tables['chat_notifications'] = "
        CREATE TABLE IF NOT EXISTS `chat_notifications` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` {$userIdType} NOT NULL,
            `room_id` INT UNSIGNED NULL,
            `message_id` BIGINT UNSIGNED NULL,
            `sender_id` {$userIdType} NULL,
            `type` ENUM('mention', 'reply', 'reaction', 'added_to_room', 'removed_from_room', 'new_message', 'room_update') NOT NULL,
            `title` VARCHAR(100) NULL,
            `content` VARCHAR(500) NOT NULL,
            `data` JSON NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `read_at` DATETIME NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user` (`user_id`),
            INDEX `idx_read` (`is_read`),
            INDEX `idx_created` (`created_at`),
            INDEX `idx_user_read` (`user_id`, `is_read`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    // ─────────────────────────────────────────────────────────────────
    // 7. جدول قراءة الرسائل (للتتبع الدقيق)
    // ─────────────────────────────────────────────────────────────────
    $tables['chat_message_reads'] = "
        CREATE TABLE IF NOT EXISTS `chat_message_reads` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `message_id` BIGINT UNSIGNED NOT NULL,
            `user_id` {$userIdType} NOT NULL,
            `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_read` (`message_id`, `user_id`),
            INDEX `idx_message` (`message_id`),
            INDEX `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    // تنفيذ إنشاء الجداول
    $created = 0;
    $existed = 0;
    $errors = 0;
    
    foreach ($tables as $name => $sql) {
        try {
            // التحقق من وجود الجدول
            $check = $pdo->query("SHOW TABLES LIKE '{$name}'")->fetch();
            
            if ($check) {
                echo "<div class='info'>ℹ️ الجدول <strong>{$name}</strong> موجود بالفعل</div>";
                $existed++;
            } else {
                $pdo->exec($sql);
                echo "<div class='success'>✅ تم إنشاء جدول <strong>{$name}</strong> بنجاح</div>";
                $created++;
            }
        } catch (PDOException $e) {
            echo "<div class='error'>❌ خطأ في جدول <strong>{$name}</strong>: " . htmlspecialchars($e->getMessage()) . "</div>";
            echo "<pre>" . htmlspecialchars($sql) . "</pre>";
            $errors++;
        }
    }
    
    echo "</div>"; // end box
    
    // ═══════════════════════════════════════════════════════════════
    // إنشاء غرفة عامة افتراضية
    // ═══════════════════════════════════════════════════════════════
    if ($errors === 0) {
        echo "<div class='box'>";
        echo "<h2>🏠 إعداد الغرف الافتراضية</h2>";
        
        $hasRooms = Database::fetchOne("SELECT id FROM chat_rooms LIMIT 1");
        
        if (!$hasRooms) {
            // جلب أول أدمن
            $admin = Database::fetchOne("SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE role_level >= 5) ORDER BY id LIMIT 1");
            $adminId = $admin ? $admin['id'] : 1;
            
            // إنشاء الغرفة العامة
            Database::insert('chat_rooms', [
                'name' => '🌍 الغرفة العامة',
                'description' => 'غرفة الدردشة العامة لجميع الموظفين - مرحباً بالجميع!',
                'type' => 'public',
                'created_by' => $adminId,
                'settings' => json_encode([
                    'allow_reactions' => true, 
                    'allow_replies' => true,
                    'allow_mentions' => true,
                    'allow_files' => true,
                    'max_file_size' => 5242880 // 5MB
                ])
            ]);
            
            $publicRoomId = Database::getInstance()->lastInsertId();
            
            // إضافة المنشئ كمالك
            Database::insert('chat_room_members', [
                'room_id' => $publicRoomId,
                'user_id' => $adminId,
                'role' => 'owner'
            ]);
            
            // رسالة ترحيب
            Database::insert('chat_messages', [
                'room_id' => $publicRoomId,
                'user_id' => $adminId,
                'message_type' => 'system',
                'content' => '🎉 مرحباً بكم في الغرفة العامة! هنا يمكنكم التواصل مع جميع الزملاء.'
            ]);
            
            // تحديث آخر رسالة
            Database::update('chat_rooms', ['last_message_at' => date('Y-m-d H:i:s')], ['id' => $publicRoomId]);
            
            echo "<div class='success'>✅ تم إنشاء الغرفة العامة بنجاح</div>";
            
            // إنشاء غرفة الإعلانات
            Database::insert('chat_rooms', [
                'name' => '📢 الإعلانات',
                'description' => 'إعلانات الإدارة والأخبار المهمة',
                'type' => 'public',
                'created_by' => $adminId,
                'settings' => json_encode([
                    'allow_reactions' => true, 
                    'allow_replies' => false,
                    'admin_only_post' => true
                ])
            ]);
            
            $announcementsRoomId = Database::getInstance()->lastInsertId();
            
            Database::insert('chat_room_members', [
                'room_id' => $announcementsRoomId,
                'user_id' => $adminId,
                'role' => 'owner'
            ]);
            
            Database::insert('chat_messages', [
                'room_id' => $announcementsRoomId,
                'user_id' => $adminId,
                'message_type' => 'system',
                'content' => '📢 هذه الغرفة مخصصة للإعلانات الرسمية من الإدارة.'
            ]);
            
            Database::update('chat_rooms', ['last_message_at' => date('Y-m-d H:i:s')], ['id' => $announcementsRoomId]);
            
            echo "<div class='success'>✅ تم إنشاء غرفة الإعلانات بنجاح</div>";
            
            // إضافة جميع المستخدمين النشطين للغرفة العامة
            $activeUsers = Database::fetchAll("SELECT id FROM users WHERE is_active = 1");
            $addedCount = 0;
            
            foreach ($activeUsers as $user) {
                if ($user['id'] == $adminId) continue;
                
                try {
                    Database::insert('chat_room_members', [
                        'room_id' => $publicRoomId,
                        'user_id' => $user['id'],
                        'role' => 'member'
                    ]);
                    
                    Database::insert('chat_room_members', [
                        'room_id' => $announcementsRoomId,
                        'user_id' => $user['id'],
                        'role' => 'member'
                    ]);
                    
                    $addedCount++;
                } catch (Exception $e) {
                    // تجاهل الأخطاء (قد يكون المستخدم موجود مسبقاً)
                }
            }
            
            echo "<div class='info'>ℹ️ تم إضافة <strong>{$addedCount}</strong> موظف للغرف الافتراضية</div>";
            
        } else {
            echo "<div class='info'>ℹ️ الغرف الافتراضية موجودة مسبقاً</div>";
        }
        
        echo "</div>"; // end box
    }
    
    // ═══════════════════════════════════════════════════════════════
    // الملخص النهائي
    // ═══════════════════════════════════════════════════════════════
    echo "<div class='box' style='text-align:center;'>";
    echo "<h2>📊 ملخص العملية</h2>";
    echo "<div style='display:flex;justify-content:center;gap:30px;margin:20px 0;'>";
    echo "<div><span style='font-size:2em;display:block;color:#28a745;'>{$created}</span>جدول جديد</div>";
    echo "<div><span style='font-size:2em;display:block;color:#17a2b8;'>{$existed}</span>موجود مسبقاً</div>";
    echo "<div><span style='font-size:2em;display:block;color:#dc3545;'>{$errors}</span>أخطاء</div>";
    echo "</div>";
    
    if ($errors === 0) {
        echo "<div class='success' style='font-size:1.3em;padding:20px;'>🎉 اكتمل الإعداد بنجاح!</div>";
        echo "<p style='margin-top:20px;'>";
        echo "<a href='../chat.php' style='background:#5865f2;color:#fff;padding:12px 25px;border-radius:8px;display:inline-block;'>💬 الذهاب إلى الدردشة</a>";
        echo "</p>";
    } else {
        echo "<div class='warning' style='padding:20px;'>";
        echo "⚠️ يوجد بعض الأخطاء. يمكنك محاولة <a href='?reset=1' style='color:#000;text-decoration:underline;'>إعادة الإنشاء من البداية</a>";
        echo "</div>";
    }
    
    echo "</div>"; // end box
    
} catch (Exception $e) {
    echo "<div class='error'>❌ خطأ عام: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<p style='text-align:center;margin-top:30px;color:#666;font-size:0.9em;'>";
echo "نظام صرح الإتقان للسيطرة الميدانية © " . date('Y');
echo "</p>";

echo "</body></html>";
