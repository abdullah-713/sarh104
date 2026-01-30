<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * نظام صرح الإتقان - API تعيين الإشعار كمقروء
 * Sarh Al-Itqan - Mark Notification as Read API
 * ═══════════════════════════════════════════════════════════════════════════════
 * @version 2.0.0
 */

require_once dirname(dirname(__DIR__)) . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'غير مصرح'], 401);
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'طريقة غير مدعومة'], 405);
}

// التحقق من CSRF Token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrfToken)) {
    // محاولة من JSON body
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    $csrfToken = $payload['csrf_token'] ?? '';
    
    if (!verify_csrf($csrfToken)) {
        json_response(['success' => false, 'message' => 'رمز الأمان غير صالح'], 403);
    }
} else {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
}

if (!is_array($payload)) {
    json_response(['success' => false, 'message' => 'بيانات غير صالحة'], 400);
}

$userId = current_user_id();

try {
    // تعيين الكل كمقروء
    if (!empty($payload['all'])) {
        $result = Database::query(
            "UPDATE notifications 
             SET is_read = 1, read_at = NOW() 
             WHERE user_id = :user_id AND is_read = 0",
            ['user_id' => $userId]
        );
        
        // تحديث الجلسة
        $_SESSION['unread_notifications'] = 0;
        
        // تسجيل النشاط
        log_activity('mark_all_notifications_read', 'notifications', 'تم تعيين جميع الإشعارات كمقروءة');
        
        json_response([
            'success' => true,
            'message' => 'تم تعيين جميع الإشعارات كمقروءة',
            'unread_count' => 0
        ]);
    }
    
    // تعيين إشعار محدد كمقروء
    $notificationId = (int)($payload['id'] ?? 0);
    
    if ($notificationId <= 0) {
        json_response(['success' => false, 'message' => 'معرف الإشعار مطلوب'], 400);
    }
    
    // التحقق من ملكية الإشعار
    $notification = Database::fetchOne(
        "SELECT id, is_read FROM notifications WHERE id = :id AND user_id = :user_id",
        ['id' => $notificationId, 'user_id' => $userId]
    );
    
    if (!$notification) {
        json_response(['success' => false, 'message' => 'الإشعار غير موجود'], 404);
    }
    
    // تحديث حالة القراءة
    if (!$notification['is_read']) {
        Database::query(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = :id",
            ['id' => $notificationId]
        );
    }
    
    // جلب العدد الجديد
    $unreadCount = Database::fetchOne(
        "SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0",
        ['user_id' => $userId]
    );
    
    // تحديث الجلسة
    $_SESSION['unread_notifications'] = (int)($unreadCount['count'] ?? 0);
    
    json_response([
        'success' => true,
        'message' => 'تم تعيين الإشعار كمقروء',
        'unread_count' => (int)($unreadCount['count'] ?? 0)
    ]);
    
} catch (Exception $e) {
    error_log('[Mark Notification Read] Error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'حدث خطأ أثناء التحديث'], 500);
}
