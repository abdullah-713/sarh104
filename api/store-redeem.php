<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - STORE REDEEM API / استبدال النقاط                    ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Endpoints:                                                                  ║
 * ║  POST - استبدال مكافأة بالنقاط                                               ║
 * ║  GET  - جلب سجل الاستبدال                                                    ║
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
            // جلب سجل الاستبدال
            $stmt = $pdo->prepare("
                SELECT rr.*, r.name as reward_name, r.icon, r.category
                FROM reward_redemptions rr
                JOIN rewards r ON rr.reward_id = r.id
                WHERE rr.user_id = ?
                ORDER BY rr.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$user_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // جلب نقاط المستخدم
            $points = getUserPoints($pdo, $user_id);
            
            echo json_encode([
                'success' => true,
                'history' => $history,
                'current_points' => $points
            ]);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $reward_id = $input['reward_id'] ?? 0;
            
            if (!$reward_id) {
                echo json_encode(['success' => false, 'message' => 'معرف المكافأة مطلوب']);
                exit;
            }
            
            // جلب تفاصيل المكافأة
            $stmt = $pdo->prepare("SELECT * FROM rewards WHERE id = ? AND is_active = 1");
            $stmt->execute([$reward_id]);
            $reward = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reward) {
                echo json_encode(['success' => false, 'message' => 'المكافأة غير موجودة']);
                exit;
            }
            
            // التحقق من المخزون
            if ($reward['stock'] !== null && $reward['stock'] <= 0) {
                echo json_encode(['success' => false, 'message' => 'نفذت الكمية المتاحة']);
                exit;
            }
            
            // التحقق من نقاط المستخدم
            $user_points = getUserPoints($pdo, $user_id);
            
            if ($user_points < $reward['points_cost']) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'نقاطك غير كافية. لديك ' . $user_points . ' نقطة وتحتاج ' . $reward['points_cost']
                ]);
                exit;
            }
            
            // بدء المعاملة
            $pdo->beginTransaction();
            
            try {
                // إنشاء طلب الاستبدال
                $stmt = $pdo->prepare("
                    INSERT INTO reward_redemptions (user_id, reward_id, points_spent, status)
                    VALUES (?, ?, ?, 'pending')
                ");
                $stmt->execute([$user_id, $reward_id, $reward['points_cost']]);
                $redemption_id = $pdo->lastInsertId();
                
                // خصم النقاط من المستخدم
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET points = GREATEST(0, points - ?)
                    WHERE id = ?
                ");
                $stmt->execute([$reward['points_cost'], $user_id]);
                
                // تحديث المخزون إذا كان محدوداً
                if ($reward['stock'] !== null) {
                    $stmt = $pdo->prepare("
                        UPDATE rewards SET stock = stock - 1 WHERE id = ? AND stock > 0
                    ");
                    $stmt->execute([$reward_id]);
                }
                
                $pdo->commit();
                
                // حساب النقاط المتبقية
                $remaining_points = $user_points - $reward['points_cost'];
                
                echo json_encode([
                    'success' => true,
                    'message' => 'تم استبدال المكافأة بنجاح! 🎉',
                    'redemption_id' => $redemption_id,
                    'reward_name' => $reward['name'],
                    'points_spent' => $reward['points_cost'],
                    'remaining_points' => $remaining_points
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()]);
}

/**
 * الحصول على نقاط المستخدم
 */
function getUserPoints($pdo, $user_id) {
    // Try from users table first
    $stmt = $pdo->prepare("SELECT COALESCE(points, 0) as points FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && isset($result['points'])) {
        return intval($result['points']);
    }
    
    // Fallback: Calculate from attendance
    $stmt = $pdo->prepare("
        SELECT COUNT(*) * 10 as points
        FROM attendance 
        WHERE user_id = ? 
        AND status = 'present'
        AND YEAR(date) = YEAR(NOW())
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return intval($result['points'] ?? 0);
}
