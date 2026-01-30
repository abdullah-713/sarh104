<?php
/**
 * =====================================================
 * نظام صرح الإتقان للسيطرة الميدانية
 * API: إرسال إشعارات الدفع
 * =====================================================
 */

require_once dirname(dirname(__DIR__)) . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

// التحقق من تسجيل الدخول والصلاحيات
if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'غير مصرح'], 401);
}

// فقط المدراء يمكنهم إرسال الإشعارات
if (current_role_level() < 5) {
    json_response(['success' => false, 'message' => 'لا تملك صلاحية إرسال الإشعارات'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'طريقة غير مدعومة'], 405);
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrfToken)) {
    json_response(['success' => false, 'message' => 'رمز الأمان غير صالح'], 403);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    json_response(['success' => false, 'message' => 'JSON غير صالح'], 400);
}

$title = trim($payload['title'] ?? '');
$body = trim($payload['body'] ?? '');
$url = trim($payload['url'] ?? '/app/notifications.php');
$userIds = $payload['user_ids'] ?? []; // مصفوفة معرفات المستخدمين
$sendToAll = $payload['send_to_all'] ?? false;
$branchId = $payload['branch_id'] ?? null;

if (empty($title) || empty($body)) {
    json_response(['success' => false, 'message' => 'العنوان والمحتوى مطلوبان'], 422);
}

/**
 * إرسال إشعار Push باستخدام Web Push Protocol
 */
function sendPushNotification($subscription, $payload) {
    $endpoint = $subscription['endpoint'];
    $p256dh = $subscription['p256dh'];
    $auth = $subscription['auth'];
    
    // تحضير الـ payload
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
    
    // إرسال عبر cURL
    $ch = curl_init($endpoint);
    
    $headers = [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payloadJson),
        'TTL: 86400', // صلاحية 24 ساعة
    ];
    
    // إضافة VAPID headers إذا كان مطلوباً
    if (defined('PWA_VAPID_PUBLIC_KEY') && !empty(PWA_VAPID_PUBLIC_KEY)) {
        // للتبسيط، نستخدم طريقة بدون تشفير VAPID الكامل
        // في الإنتاج، استخدم مكتبة web-push-php
        $headers[] = 'Authorization: vapid t=' . base64_encode(PWA_VAPID_PUBLIC_KEY);
    }
    
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payloadJson,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error
    ];
}

try {
    // بناء الاستعلام
    $sql = "SELECT id, user_id, endpoint, p256dh, auth, subscription_json 
            FROM push_subscriptions 
            WHERE is_active = 1";
    $params = [];
    
    if ($sendToAll) {
        // إرسال للجميع أو لفرع محدد
        if ($branchId) {
            $sql .= " AND user_id IN (SELECT id FROM users WHERE branch_id = :branch_id AND is_active = 1)";
            $params['branch_id'] = $branchId;
        }
    } else {
        // إرسال لمستخدمين محددين
        if (empty($userIds)) {
            json_response(['success' => false, 'message' => 'يجب تحديد المستخدمين'], 422);
        }
        
        $placeholders = [];
        foreach ($userIds as $i => $userId) {
            $key = "user_id_{$i}";
            $placeholders[] = ":{$key}";
            $params[$key] = $userId;
        }
        $sql .= " AND user_id IN (" . implode(',', $placeholders) . ")";
    }
    
    $subscriptions = Database::fetchAll($sql, $params);
    
    if (empty($subscriptions)) {
        json_response([
            'success' => false, 
            'message' => 'لا توجد اشتراكات نشطة للمستخدمين المحددين'
        ], 404);
    }
    
    // تحضير payload الإشعار
    $notificationPayload = [
        'title' => $title,
        'body' => $body,
        'icon' => '/app/assets/images/pwa/icon-192.png',
        'badge' => '/app/assets/images/pwa/badge-72.png',
        'url' => $url,
        'tag' => 'sarh-notification-' . time(),
        'requireInteraction' => true,
        'timestamp' => time() * 1000
    ];
    
    // إضافة بيانات إضافية
    if (!empty($payload['image'])) {
        $notificationPayload['image'] = $payload['image'];
    }
    
    $sent = 0;
    $failed = 0;
    $errors = [];
    
    foreach ($subscriptions as $sub) {
        $result = sendPushNotification($sub, $notificationPayload);
        
        if ($result['success']) {
            $sent++;
            
            // تحديث last_seen
            Database::update('push_subscriptions', 
                ['last_seen_at' => date('Y-m-d H:i:s')],
                'id = :id',
                ['id' => $sub['id']]
            );
        } else {
            $failed++;
            
            // إذا كان الخطأ 404 أو 410، إلغاء الاشتراك
            if (in_array($result['http_code'], [404, 410])) {
                Database::update('push_subscriptions',
                    ['is_active' => 0],
                    'id = :id',
                    ['id' => $sub['id']]
                );
            }
            
            $errors[] = [
                'user_id' => $sub['user_id'],
                'http_code' => $result['http_code'],
                'error' => $result['error']
            ];
        }
    }
    
    // تسجيل النشاط
    log_activity('send_push_notification', 'notification', json_encode([
        'title' => $title,
        'sent' => $sent,
        'failed' => $failed,
        'total' => count($subscriptions)
    ]));
    
    json_response([
        'success' => true,
        'message' => "تم إرسال {$sent} إشعار بنجاح",
        'data' => [
            'sent' => $sent,
            'failed' => $failed,
            'total' => count($subscriptions)
        ]
    ]);
    
} catch (Exception $e) {
    error_log('[Push Send] Error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'حدث خطأ أثناء إرسال الإشعارات'], 500);
}
