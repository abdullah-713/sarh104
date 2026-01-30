<?php
/**
 * =====================================================
 * نظام صرح الإتقان للسيطرة الميدانية
 * API: جلب عدد الإشعارات غير المقروءة
 * =====================================================
 */

// تحميل الإعدادات
require_once dirname(dirname(__DIR__)) . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'غير مصرح', 'count' => 0], 401);
}

// جلب عدد الإشعارات
$count = get_unread_notifications_count(current_user_id());

// إرسال الاستجابة
json_response([
    'success' => true,
    'count' => $count
]);
