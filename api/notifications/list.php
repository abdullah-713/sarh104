<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * نظام صرح الإتقان - API جلب قائمة الإشعارات
 * Sarh Al-Itqan - Notifications List API
 * ═══════════════════════════════════════════════════════════════════════════════
 * @version 2.0.0
 */

require_once dirname(dirname(__DIR__)) . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'غير مصرح', 'notifications' => []], 401);
}

// المتغيرات
$userId = current_user_id();
$limit = min((int)($_GET['limit'] ?? 10), 50); // الحد الأقصى 50
$offset = max((int)($_GET['offset'] ?? 0), 0);
$type = $_GET['type'] ?? null;
$unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

// آخر وقت جلب (لمعرفة الإشعارات الجديدة)
$lastFetch = $_GET['last_fetch'] ?? null;

try {
    // ═══════════════════════════════════════════════════════════════════════════
    // بناء الاستعلام المتوافق مع Schema v1.8.0
    // Schema: notifications (scope_type, scope_id) + user_notification_reads
    // ═══════════════════════════════════════════════════════════════════════════
    
    $sql = "SELECT 
                n.id,
                n.type,
                n.title,
                n.message,
                n.action_url,
                n.icon,
                n.scope_type,
                n.scope_id,
                n.is_persistent,
                n.expires_at,
                n.created_at,
                CASE WHEN unr.id IS NOT NULL THEN 1 ELSE 0 END as is_read,
                unr.read_at
            FROM notifications n
            LEFT JOIN user_notification_reads unr ON (n.id = unr.notification_id AND unr.user_id = :user_id)
            WHERE (
                -- إشعارات عامة (global)
                (n.scope_type = 'global')
                OR 
                -- إشعارات للمستخدم مباشرة
                (n.scope_type = 'user' AND n.scope_id = :user_id)
                OR
                -- إشعارات لفرع المستخدم
                (n.scope_type = 'branch' AND n.scope_id = :user_branch_id)
            )
            AND (n.expires_at IS NULL OR n.expires_at > NOW())";
    
    // جلب branch_id للمستخدم
    $userBranch = Database::fetchOne(
        "SELECT branch_id FROM users WHERE id = ?",
        [$userId]
    );
    $userBranchId = $userBranch['branch_id'] ?? null;
    
    $params = [
        'user_id' => $userId,
        'user_branch_id' => $userBranchId
    ];
    
    // فلترة حسب النوع
    if ($type) {
        $sql .= " AND n.type = :type";
        $params['type'] = $type;
    }
    
    // فلترة غير المقروءة فقط
    if ($unreadOnly) {
        $sql .= " AND unr.id IS NULL"; // لم يتم قراءته = لا يوجد سجل في user_notification_reads
    }
    
    // إضافة LIMIT و OFFSET مباشرة في الاستعلام (آمن لأنها integers)
    $sql .= " ORDER BY n.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    
    // جلب الإشعارات
    $notifications = Database::fetchAll($sql, $params);
    
    // تنسيق البيانات
    $formattedNotifications = array_map(function($notif) {
        return [
            'id' => (int)$notif['id'],
            'type' => $notif['type'] ?? 'info',
            'title' => $notif['title'],
            'message' => $notif['message'],
            'url' => $notif['action_url'] ?? '/app/notifications.php', // استخدام action_url
            'icon' => $notif['icon'] ?? 'bi-bell',
            'data' => null, // لا يوجد عمود data في Schema - يمكن إضافة لاحقاً
            'is_read' => (bool)($notif['is_read'] ?? 0),
            'read_at' => $notif['read_at'],
            'created_at' => $notif['created_at'],
            'time_ago' => time_ago($notif['created_at'])
        ];
    }, $notifications);
    
    // جلب عدد غير المقروءة (إشعارات بدون سجل في user_notification_reads)
    $unreadSql = "SELECT COUNT(*) as count 
                  FROM notifications n
                  LEFT JOIN user_notification_reads unr ON (n.id = unr.notification_id AND unr.user_id = :user_id)
                  WHERE (
                      (n.scope_type = 'global')
                      OR (n.scope_type = 'user' AND n.scope_id = :user_id)
                      OR (n.scope_type = 'branch' AND n.scope_id = :user_branch_id)
                  )
                  AND (n.expires_at IS NULL OR n.expires_at > NOW())
                  AND unr.id IS NULL";
    
    $unreadResult = Database::fetchOne($unreadSql, $params);
    $unreadCount = $unreadResult ? (int)$unreadResult['count'] : 0;
    
    // جلب الإشعارات الجديدة (إذا تم تقديم last_fetch)
    $newNotifications = [];
    if ($lastFetch) {
        $newNotifsSql = "SELECT n.id, n.type, n.title, n.message, n.action_url, n.created_at 
                         FROM notifications n
                         LEFT JOIN user_notification_reads unr ON (n.id = unr.notification_id AND unr.user_id = :user_id)
                         WHERE (
                             (n.scope_type = 'global')
                             OR (n.scope_type = 'user' AND n.scope_id = :user_id)
                             OR (n.scope_type = 'branch' AND n.scope_id = :user_branch_id)
                         )
                         AND n.created_at > :last_fetch
                         AND unr.id IS NULL
                         AND (n.expires_at IS NULL OR n.expires_at > NOW())
                         ORDER BY n.created_at DESC 
                         LIMIT 5";
        
        $newNotifs = Database::fetchAll($newNotifsSql, array_merge($params, ['last_fetch' => $lastFetch]));
        
        $newNotifications = array_map(function($n) {
            return [
                'id' => (int)$n['id'],
                'type' => $n['type'] ?? 'info',
                'title' => $n['title'],
                'message' => $n['message'],
                'url' => $n['action_url'] ?? '/app/notifications.php',
                'created_at' => $n['created_at']
            ];
        }, $newNotifs);
    }
    
    // تحديث عدد الإشعارات في الجلسة
    $_SESSION['unread_notifications'] = $unreadCount;
    
    // الاستجابة
    json_response([
        'success' => true,
        'notifications' => $formattedNotifications,
        'unread_count' => $unreadCount,
        'new_notifications' => $newNotifications,
        'has_more' => count($formattedNotifications) === $limit,
        'server_time' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log('[Notifications List] Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    
    // في بيئة التطوير، إرجاع تفاصيل الخطأ
    $errorMessage = ENVIRONMENT === 'development' 
        ? 'حدث خطأ: ' . $e->getMessage() 
        : 'حدث خطأ أثناء جلب الإشعارات';
    
    json_response([
        'success' => false, 
        'message' => $errorMessage,
        'notifications' => [],
        'unread_count' => 0
    ], 500);
}
