<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - NOTIFICATIONS API / الإشعارات الذكية                 ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Endpoints:                                                                  ║
 * ║  GET  - جلب الإشعارات                                                        ║
 * ║  POST mark_read - تعليم كمقروء                                               ║
 * ║  POST subscribe - اشتراك Push                                                ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

session_start();
require_once '../config/database.php';
require_once '../config/app.php';

header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مسموح']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // جلب الإشعارات
            $type = $_GET['type'] ?? 'all';
            $limit = min(intval($_GET['limit'] ?? 20), 100);
            $unread_only = isset($_GET['unread']) && $_GET['unread'] === '1';
            
            $notifications = getNotifications($pdo, $user_id, $type, $limit, $unread_only);
            $unread_count = getUnreadCount($pdo, $user_id);
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unread_count
            ]);
            break;
            
        case 'POST':
            // التحقق من CSRF Token
            $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
            if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'رمز أمان غير صالح']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? '';
            
            switch ($action) {
                case 'mark_read':
                    $notification_id = $input['id'] ?? 0;
                    if ($notification_id) {
                        markAsRead($pdo, $user_id, $notification_id);
                    } else {
                        markAllAsRead($pdo, $user_id);
                    }
                    echo json_encode(['success' => true]);
                    break;
                    
                case 'subscribe':
                    $subscription = $input['subscription'] ?? null;
                    if ($subscription) {
                        saveSubscription($pdo, $user_id, $subscription);
                        echo json_encode(['success' => true]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
                    }
                    break;
                    
                case 'unsubscribe':
                    removeSubscription($pdo, $user_id);
                    echo json_encode(['success' => true]);
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطأ في الخادم']);
}

/**
 * جلب الإشعارات للمستخدم
 */
function getNotifications($pdo, $user_id, $type, $limit, $unread_only) {
    $notifications = [];
    
    // إشعارات الإعلانات
    if ($type === 'all' || $type === 'announcements') {
        $sql = "
            SELECT 
                a.id,
                'announcement' as type,
                a.title,
                a.content as message,
                a.priority,
                a.created_at,
                (SELECT 1 FROM announcement_reads WHERE announcement_id = a.id AND user_id = ?) as is_read
            FROM announcements a
            WHERE a.is_active = 1
            AND (a.expires_at IS NULL OR a.expires_at > NOW())
        ";
        
        if ($unread_only) {
            $sql .= " AND NOT EXISTS (SELECT 1 FROM announcement_reads WHERE announcement_id = a.id AND user_id = ?)";
            $stmt = $pdo->prepare($sql . " ORDER BY a.created_at DESC LIMIT ?");
            $stmt->execute([$user_id, $user_id, $limit]);
        } else {
            $stmt = $pdo->prepare($sql . " ORDER BY a.created_at DESC LIMIT ?");
            $stmt->execute([$user_id, $limit]);
        }
        
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($announcements as &$a) {
            $a['icon'] = getNotificationIcon('announcement', $a['priority']);
            $a['is_read'] = (bool)$a['is_read'];
        }
        $notifications = array_merge($notifications, $announcements);
    }
    
    // إشعارات التحديات
    if ($type === 'all' || $type === 'challenges') {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    c.id,
                    'challenge' as type,
                    c.title,
                    CONCAT('تحدي جديد: ', c.description) as message,
                    2 as priority,
                    c.start_date as created_at,
                    0 as is_read
                FROM challenges c
                WHERE c.is_active = 1
                AND c.start_date <= NOW()
                AND c.end_date >= NOW()
                AND NOT EXISTS (SELECT 1 FROM user_challenges WHERE challenge_id = c.id AND user_id = ?)
                ORDER BY c.start_date DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($challenges as &$c) {
                $c['icon'] = getNotificationIcon('challenge');
                $c['is_read'] = false;
            }
            $notifications = array_merge($notifications, $challenges);
        } catch (Exception $e) {
            // Table might not exist
        }
    }
    
    // إشعارات الشارات
    if ($type === 'all' || $type === 'badges') {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    ub.id,
                    'badge' as type,
                    b.name as title,
                    CONCAT('🎉 حصلت على شارة: ', b.name) as message,
                    3 as priority,
                    ub.earned_at as created_at,
                    1 as is_read
                FROM user_badges ub
                JOIN badges b ON ub.badge_id = b.id
                WHERE ub.user_id = ?
                ORDER BY ub.earned_at DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            $badges = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($badges as &$b) {
                $b['icon'] = getNotificationIcon('badge');
                $b['is_read'] = true;
            }
            $notifications = array_merge($notifications, $badges);
        } catch (Exception $e) {
            // Table might not exist
        }
    }
    
    // إشعارات طلبات الإجازة
    if ($type === 'all' || $type === 'leaves') {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    'leave' as type,
                    CONCAT('طلب إجازة - ', leave_type) as title,
                    CASE status
                        WHEN 'approved' THEN 'تم قبول طلب إجازتك ✅'
                        WHEN 'rejected' THEN 'تم رفض طلب إجازتك ❌'
                        ELSE 'طلب إجازة قيد المراجعة'
                    END as message,
                    CASE status WHEN 'approved' THEN 3 WHEN 'rejected' THEN 2 ELSE 1 END as priority,
                    updated_at as created_at,
                    1 as is_read
                FROM leave_requests
                WHERE user_id = ?
                AND status != 'pending'
                AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY updated_at DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($leaves as &$l) {
                $l['icon'] = getNotificationIcon('leave');
                $l['is_read'] = true;
            }
            $notifications = array_merge($notifications, $leaves);
        } catch (Exception $e) {
            // Table might not exist
        }
    }
    
    // ترتيب حسب التاريخ
    usort($notifications, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return array_slice($notifications, 0, $limit);
}

/**
 * الحصول على أيقونة الإشعار
 */
function getNotificationIcon($type, $priority = 1) {
    $icons = [
        'announcement' => [
            1 => ['icon' => 'bi-info-circle', 'color' => 'secondary'],
            2 => ['icon' => 'bi-bell', 'color' => 'info'],
            3 => ['icon' => 'bi-exclamation-triangle', 'color' => 'warning'],
            4 => ['icon' => 'bi-megaphone', 'color' => 'danger']
        ],
        'challenge' => ['icon' => 'bi-trophy', 'color' => 'warning'],
        'badge' => ['icon' => 'bi-award', 'color' => 'primary'],
        'leave' => ['icon' => 'bi-calendar-check', 'color' => 'success'],
        'points' => ['icon' => 'bi-star', 'color' => 'warning']
    ];
    
    if (isset($icons[$type][$priority])) {
        return $icons[$type][$priority];
    }
    return $icons[$type] ?? ['icon' => 'bi-bell', 'color' => 'primary'];
}

/**
 * عدد الإشعارات غير المقروءة
 */
function getUnreadCount($pdo, $user_id) {
    $count = 0;
    
    // إعلانات غير مقروءة
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM announcements a
            WHERE a.is_active = 1
            AND (a.expires_at IS NULL OR a.expires_at > NOW())
            AND NOT EXISTS (SELECT 1 FROM announcement_reads WHERE announcement_id = a.id AND user_id = ?)
        ");
        $stmt->execute([$user_id]);
        $count += $stmt->fetchColumn();
    } catch (Exception $e) {}
    
    // تحديات غير منضم إليها
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM challenges c
            WHERE c.is_active = 1
            AND c.start_date <= NOW()
            AND c.end_date >= NOW()
            AND NOT EXISTS (SELECT 1 FROM user_challenges WHERE challenge_id = c.id AND user_id = ?)
        ");
        $stmt->execute([$user_id]);
        $count += $stmt->fetchColumn();
    } catch (Exception $e) {}
    
    return $count;
}

/**
 * تعليم إشعار كمقروء
 */
function markAsRead($pdo, $user_id, $notification_id) {
    // For announcements
    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO announcement_reads (announcement_id, user_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$notification_id, $user_id]);
    } catch (Exception $e) {}
}

/**
 * تعليم كل الإشعارات كمقروءة
 */
function markAllAsRead($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO announcement_reads (announcement_id, user_id)
            SELECT id, ? FROM announcements WHERE is_active = 1
        ");
        $stmt->execute([$user_id]);
    } catch (Exception $e) {}
}

/**
 * حفظ اشتراك Push
 */
function saveSubscription($pdo, $user_id, $subscription) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_key)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE endpoint = ?, p256dh_key = ?, auth_key = ?
        ");
        $stmt->execute([
            $user_id,
            $subscription['endpoint'],
            $subscription['keys']['p256dh'] ?? '',
            $subscription['keys']['auth'] ?? '',
            $subscription['endpoint'],
            $subscription['keys']['p256dh'] ?? '',
            $subscription['keys']['auth'] ?? ''
        ]);
    } catch (Exception $e) {
        // Table might not exist
    }
}

/**
 * إزالة اشتراك Push
 */
function removeSubscription($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } catch (Exception $e) {}
}
