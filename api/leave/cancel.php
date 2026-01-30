<?php
/**
 * API لإلغاء طلب الإجازة
 */

session_start();
require_once '../../config/database.php';
require_once '../../config/app.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مسموح']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$request_id = intval($input['id'] ?? 0);

if (!$request_id) {
    echo json_encode(['success' => false, 'message' => 'معرف الطلب مطلوب']);
    exit;
}

try {
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
    $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$request_id]);
    
    echo json_encode(['success' => true, 'message' => 'تم إلغاء الطلب بنجاح']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطأ في الخادم']);
}
