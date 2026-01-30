<?php
/**
 * فحص قاعدة بيانات الدردشة
 */
require_once dirname(__DIR__) . '/config/app.php';

header('Content-Type: text/html; charset=utf-8');

echo "<html dir='rtl'><head><meta charset='utf-8'><title>فحص الدردشة</title>";
echo "<style>body{font-family:Tahoma;padding:20px;background:#1a1a2e;color:#eee;max-width:900px;margin:0 auto;}
.result{background:#2d2d44;padding:15px;margin:10px 0;border-radius:8px;}</style></head><body>";

echo "<h1>فحص قاعدة بيانات الدردشة</h1>";

try {
    // 1. فحص الغرف
    $rooms = Database::fetchAll("SELECT * FROM chat_rooms");
    echo "<div class='result'>";
    echo "<h3>الغرف الموجودة: " . count($rooms) . "</h3>";
    foreach ($rooms as $r) {
        echo "<p>- [{$r['id']}] {$r['name']} ({$r['type']}) - نشط: {$r['is_active']}</p>";
    }
    echo "</div>";
    
    // 2. فحص الأعضاء
    $membersCount = Database::fetchOne("SELECT COUNT(*) as cnt FROM chat_room_members")['cnt'] ?? 0;
    echo "<div class='result'>";
    echo "<h3>عدد العضويات الكلي: {$membersCount}</h3>";
    echo "</div>";
    
    // 3. المستخدم الحالي
    $currentUserId = $_SESSION['user_id'] ?? null;
    echo "<div class='result'>";
    echo "<h3>المستخدم الحالي</h3>";
    if (!$currentUserId) {
        echo "<p style='color:orange;'>لم يتم تسجيل الدخول</p>";
    } else {
        echo "<p>User ID: {$currentUserId}</p>";
        
        $userRooms = Database::fetchAll("SELECT cr.name, crm.role FROM chat_room_members crm 
                                         JOIN chat_rooms cr ON crm.room_id = cr.id 
                                         WHERE crm.user_id = :uid", ['uid' => $currentUserId]);
        echo "<p>عضو في " . count($userRooms) . " غرفة</p>";
        
        if (empty($userRooms)) {
            echo "<p style='color:red;'>المستخدم ليس عضواً في أي غرفة!</p>";
            
            // إضافة للغرف العامة
            $publicRooms = Database::fetchAll("SELECT id, name FROM chat_rooms WHERE type = 'public' AND is_active = 1");
            foreach ($publicRooms as $room) {
                try {
                    Database::insert('chat_room_members', [
                        'room_id' => $room['id'],
                        'user_id' => $currentUserId,
                        'role' => 'member'
                    ]);
                    echo "<p style='color:lime;'>تم إضافته لغرفة: {$room['name']}</p>";
                } catch (Exception $e) {
                    echo "<p style='color:yellow;'>{$room['name']}: " . $e->getMessage() . "</p>";
                }
            }
        } else {
            foreach ($userRooms as $ur) {
                echo "<p>- {$ur['name']} ({$ur['role']})</p>";
            }
        }
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='result' style='background:#dc3545;'>";
    echo "<p>خطأ: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<p style='margin-top:20px;'><a href='../chat.php' style='background:#5865f2;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;'>الذهاب إلى الدردشة</a></p>";

echo "</body></html>";
