<?php
/**
 * =====================================================
 * نظام صرح الإتقان للسيطرة الميدانية
 * API: حفظ اشتراك إشعارات الدفع (Push)
 * =====================================================
 */

require_once dirname(dirname(__DIR__)) . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'غير مصرح'], 401);
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

$subscription = $payload['subscription'] ?? $payload;
$endpoint = $subscription['endpoint'] ?? null;
$keys = $subscription['keys'] ?? [];
$p256dh = $keys['p256dh'] ?? null;
$auth = $keys['auth'] ?? null;

if (!$endpoint || !$p256dh || !$auth) {
    json_response(['success' => false, 'message' => 'بيانات الاشتراك غير مكتملة'], 422);
}

try {
    Database::query(
        "CREATE TABLE IF NOT EXISTS `push_subscriptions` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;"
    );
} catch (Exception $e) {
    error_log('[Push Subscribe] Failed to ensure table: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'تعذر تجهيز قاعدة البيانات'], 500);
}

$endpointHash = hash('sha256', $endpoint);
$subscriptionJson = json_encode($subscription, JSON_UNESCAPED_UNICODE);
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
$deviceType = $payload['device_type'] ?? null;

try {
    $sql = "INSERT INTO `push_subscriptions`
            (`user_id`, `endpoint`, `endpoint_hash`, `p256dh`, `auth`, `subscription_json`, `user_agent`, `device_type`, `is_active`, `last_seen_at`)
            VALUES (:user_id, :endpoint, :endpoint_hash, :p256dh, :auth, :subscription_json, :user_agent, :device_type, 1, NOW())
            ON DUPLICATE KEY UPDATE
                `user_id` = VALUES(`user_id`),
                `p256dh` = VALUES(`p256dh`),
                `auth` = VALUES(`auth`),
                `subscription_json` = VALUES(`subscription_json`),
                `user_agent` = VALUES(`user_agent`),
                `device_type` = VALUES(`device_type`),
                `is_active` = 1,
                `last_seen_at` = NOW()";

    Database::query($sql, [
        'user_id' => current_user_id(),
        'endpoint' => $endpoint,
        'endpoint_hash' => $endpointHash,
        'p256dh' => $p256dh,
        'auth' => $auth,
        'subscription_json' => $subscriptionJson,
        'user_agent' => $userAgent,
        'device_type' => $deviceType
    ]);
} catch (Exception $e) {
    error_log('[Push Subscribe] Failed to store subscription: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'تعذر حفظ الاشتراك'], 500);
}

json_response(['success' => true, 'message' => 'تم حفظ الاشتراك']);
