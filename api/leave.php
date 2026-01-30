<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - LEAVE REQUEST API / طلبات الإجازة                    ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Endpoints:                                                                  ║
 * ║  GET     - جلب طلبات الإجازة                                                 ║
 * ║  POST    - إنشاء طلب إجازة                                                   ║
 * ║  DELETE  - إلغاء طلب                                                         ║
 * ║  PATCH   - الموافقة/الرفض (للإداريين)                                        ║
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
$is_admin = is_super_admin($user_id);
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $type = $_GET['type'] ?? 'my';
            
            if ($type === 'pending' && $is_admin) {
                // الطلبات المعلقة (للإداريين)
                $stmt = $pdo->prepare("
                    SELECT lr.*, u.name as user_name, u.email
                    FROM leave_requests lr
                    JOIN users u ON lr.user_id = u.id
                    WHERE lr.status = 'pending'
                    ORDER BY lr.created_at ASC
                ");
                $stmt->execute();
            } elseif ($type === 'all' && $is_admin) {
                // جميع الطلبات (للإداريين)
                $limit = min(100, intval($_GET['limit'] ?? 50));
                $stmt = $pdo->prepare("
                    SELECT lr.*, 
                           u.name as user_name,
                           a.name as approver_name
                    FROM leave_requests lr
                    JOIN users u ON lr.user_id = u.id
                    LEFT JOIN users a ON lr.approved_by = a.id
                    ORDER BY lr.created_at DESC
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
            } else {
                // طلباتي
                $stmt = $pdo->prepare("
                    SELECT lr.*, a.name as approver_name
                    FROM leave_requests lr
                    LEFT JOIN users a ON lr.approved_by = a.id
                    WHERE lr.user_id = ?
                    ORDER BY lr.created_at DESC
                ");
                $stmt->execute([$user_id]);
            }
            
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // حساب أيام كل طلب
            foreach ($requests as &$r) {
                $start = new DateTime($r['start_date']);
                $end = new DateTime($r['end_date']);
                $r['days'] = $end->diff($start)->days + 1;
            }
            
            // جلب رصيد الإجازات
            $balance = getLeaveBalance($pdo, $type === 'my' ? $user_id : null);
            
            echo json_encode([
                'success' => true,
                'requests' => $requests,
                'balance' => $balance
            ]);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            $leave_type = htmlspecialchars(trim($input['leave_type'] ?? 'annual'), ENT_QUOTES, 'UTF-8');
            $start_date = $input['start_date'] ?? '';
            $end_date = $input['end_date'] ?? '';
            $reason = htmlspecialchars(trim($input['reason'] ?? ''), ENT_QUOTES, 'UTF-8');
            
            // التحقق من التواريخ
            if (!$start_date || !$end_date) {
                echo json_encode(['success' => false, 'message' => 'التواريخ مطلوبة']);
                exit;
            }
            
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $today = new DateTime();
            
            if ($start > $end) {
                echo json_encode(['success' => false, 'message' => 'تاريخ البداية يجب أن يكون قبل تاريخ النهاية']);
                exit;
            }
            
            if ($start < $today && $leave_type !== 'emergency') {
                echo json_encode(['success' => false, 'message' => 'لا يمكن تقديم طلب لتاريخ في الماضي']);
                exit;
            }
            
            $days = $end->diff($start)->days + 1;
            
            // التحقق من الرصيد
            $balance = getLeaveBalance($pdo, $user_id);
            if ($leave_type !== 'unpaid' && $days > $balance['remaining']) {
                echo json_encode([
                    'success' => false, 
                    'message' => "رصيدك غير كافٍ. لديك {$balance['remaining']} يوم فقط"
                ]);
                exit;
            }
            
            // التحقق من تضارب المواعيد
            $stmt = $pdo->prepare("
                SELECT id FROM leave_requests
                WHERE user_id = ?
                AND status IN ('pending', 'approved')
                AND (
                    (start_date BETWEEN ? AND ?)
                    OR (end_date BETWEEN ? AND ?)
                    OR (? BETWEEN start_date AND end_date)
                )
            ");
            $stmt->execute([$user_id, $start_date, $end_date, $start_date, $end_date, $start_date]);
            
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'لديك طلب إجازة متداخل مع هذه التواريخ']);
                exit;
            }
            
            // إنشاء الطلب
            $stmt = $pdo->prepare("
                INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$user_id, $leave_type, $start_date, $end_date, $reason]);
            $request_id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'request_id' => $request_id,
                'days' => $days,
                'message' => 'تم تقديم طلب الإجازة بنجاح!'
            ]);
            break;
            
        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            $request_id = intval($input['id'] ?? 0);
            
            if (!$request_id) {
                echo json_encode(['success' => false, 'message' => 'معرف الطلب مطلوب']);
                exit;
            }
            
            // التحقق من ملكية الطلب
            $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE id = ? AND user_id = ?");
            $stmt->execute([$request_id, $user_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                echo json_encode(['success' => false, 'message' => 'الطلب غير موجود']);
                exit;
            }
            
            if ($request['status'] !== 'pending') {
                echo json_encode(['success' => false, 'message' => 'لا يمكن إلغاء طلب تمت معالجته']);
                exit;
            }
            
            // إلغاء الطلب
            $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$request_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'تم إلغاء الطلب بنجاح'
            ]);
            break;
            
        case 'PATCH':
            // فقط الإداريين
            if (!$is_admin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'غير مصرح']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $request_id = intval($input['id'] ?? 0);
            $action = $input['action'] ?? '';
            $rejection_reason = htmlspecialchars(trim($input['rejection_reason'] ?? ''), ENT_QUOTES, 'UTF-8');
            
            if (!$request_id || !in_array($action, ['approve', 'reject'])) {
                echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
                exit;
            }
            
            // جلب الطلب
            $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE id = ? AND status = 'pending'");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                echo json_encode(['success' => false, 'message' => 'الطلب غير موجود أو تمت معالجته']);
                exit;
            }
            
            $new_status = $action === 'approve' ? 'approved' : 'rejected';
            
            // تحديث الطلب
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = ?, 
                    approved_by = ?,
                    rejection_reason = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $user_id, $rejection_reason, $request_id]);
            
            echo json_encode([
                'success' => true,
                'message' => $action === 'approve' ? 'تمت الموافقة على الطلب' : 'تم رفض الطلب'
            ]);
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
 * حساب رصيد الإجازات
 */
function getLeaveBalance($pdo, $user_id = null) {
    $annual = 30; // رصيد سنوي افتراضي
    
    if (!$user_id) {
        return ['annual' => $annual, 'used' => 0, 'remaining' => $annual];
    }
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(DATEDIFF(end_date, start_date) + 1), 0) as used
        FROM leave_requests
        WHERE user_id = ?
        AND status = 'approved'
        AND leave_type NOT IN ('unpaid', 'sick')
        AND YEAR(start_date) = YEAR(NOW())
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $used = intval($result['used'] ?? 0);
    
    return [
        'annual' => $annual,
        'used' => $used,
        'remaining' => max(0, $annual - $used)
    ];
}
