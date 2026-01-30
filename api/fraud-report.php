<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - FRAUD REPORT API / تقرير الاحتيال                    ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Endpoints:                                                                  ║
 * ║  POST - تسجيل محاولة احتيال                                                  ║
 * ║  GET  - جلب سجل الاحتيال (للإداريين)                                         ║
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
            // فقط الإداريين يمكنهم رؤية السجل
            if (!is_super_admin($user_id)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'غير مصرح']);
                exit;
            }
            
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(50, intval($_GET['limit'] ?? 20));
            $offset = ($page - 1) * $limit;
            
            // جلب سجلات الاحتيال
            $stmt = $pdo->prepare("
                SELECT fdl.*, u.name as user_name, u.email
                FROM fraud_detection_logs fdl
                JOIN users u ON fdl.user_id = u.id
                ORDER BY fdl.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // إجمالي السجلات
            $stmt = $pdo->query("SELECT COUNT(*) FROM fraud_detection_logs");
            $total = $stmt->fetchColumn();
            
            // إحصائيات سريعة
            $stmt = $pdo->query("
                SELECT 
                    detection_type,
                    COUNT(*) as count
                FROM fraud_detection_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY detection_type
                ORDER BY count DESC
            ");
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'logs' => $logs,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $limit),
                'stats' => $stats
            ]);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            // التحقق من CSRF Token
            $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
            if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'رمز أمان غير صالح']);
                exit;
            }
            
            $detection_type = htmlspecialchars($input['type'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
            $details = $input['details'] ?? [];
            $suspicion_score = min(100, max(0, intval($input['score'] ?? 0)));
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            // Validate detection type
            $valid_types = ['mock_gps', 'vpn', 'emulator', 'devtools', 'location_jump', 'battery_anomaly', 'sensor_anomaly', 'multiple'];
            if (!in_array($detection_type, $valid_types)) {
                $detection_type = 'unknown';
            }
            
            // تسجيل محاولة الاحتيال
            $stmt = $pdo->prepare("
                INSERT INTO fraud_detection_logs 
                (user_id, detection_type, details, suspicion_score, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $detection_type,
                json_encode($details, JSON_UNESCAPED_UNICODE),
                $suspicion_score,
                $ip_address,
                substr($user_agent, 0, 500)
            ]);
            
            $log_id = $pdo->lastInsertId();
            
            // إذا كان مستوى الشك مرتفع جداً، نبلغ الإدارة
            if ($suspicion_score >= 80) {
                notifyAdmins($pdo, $user_id, $detection_type, $suspicion_score);
            }
            
            // تحديد الإجراء المطلوب
            $action = 'allow';
            $message = '';
            
            if ($suspicion_score >= 90) {
                $action = 'block';
                $message = 'تم اكتشاف نشاط مريب. يرجى التواصل مع الإدارة.';
            } elseif ($suspicion_score >= 70) {
                $action = 'warn';
                $message = 'تحذير: تم اكتشاف نشاط غير طبيعي.';
            } elseif ($suspicion_score >= 50) {
                $action = 'log';
            }
            
            echo json_encode([
                'success' => true,
                'log_id' => $log_id,
                'action' => $action,
                'message' => $message
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
 * إخطار الإداريين بمحاولة احتيال خطيرة
 */
function notifyAdmins($pdo, $user_id, $type, $score) {
    try {
        // جلب اسم المستخدم
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_name = $user['name'] ?? 'مستخدم غير معروف';
        
        // ترجمة نوع الكشف
        $type_names = [
            'mock_gps' => 'موقع GPS مزيف',
            'vpn' => 'استخدام VPN',
            'emulator' => 'محاكي',
            'devtools' => 'أدوات المطورين',
            'location_jump' => 'قفزة في الموقع',
            'battery_anomaly' => 'خلل في البطارية',
            'sensor_anomaly' => 'خلل في المستشعرات',
            'multiple' => 'عدة مؤشرات'
        ];
        
        $type_name = $type_names[$type] ?? $type;
        
        // إنشاء إعلان تحذيري للإدارة
        $stmt = $pdo->prepare("
            INSERT INTO announcements (title, content, priority, is_pinned, target_role, created_by)
            VALUES (?, ?, 4, 0, 'admin', 1)
        ");
        $stmt->execute([
            '⚠️ تنبيه أمني: محاولة احتيال',
            "تم اكتشاف محاولة احتيال محتملة:\n\n" .
            "المستخدم: $user_name\n" .
            "النوع: $type_name\n" .
            "درجة الشك: $score%\n" .
            "الوقت: " . date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        // Fail silently
    }
}
