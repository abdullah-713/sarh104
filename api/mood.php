<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - MOOD SURVEY API / استبيان المزاج                     ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Endpoints:                                                                  ║
 * ║  POST - حفظ استبيان المزاج                                                   ║
 * ║  GET  - جلب سجل المزاج                                                       ║
 * ║  GET stats - إحصائيات المزاج (للإداريين)                                     ║
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
$today = date('Y-m-d');

try {
    switch ($method) {
        case 'GET':
            $type = $_GET['type'] ?? 'history';
            
            if ($type === 'stats' && is_super_admin($user_id)) {
                // إحصائيات المزاج للإدارة
                $period = $_GET['period'] ?? '7';
                
                // متوسط المزاج اليومي
                $stmt = $pdo->prepare("
                    SELECT 
                        DATE(created_at) as date,
                        ROUND(AVG(mood_score), 2) as avg_mood,
                        ROUND(AVG(energy_level), 2) as avg_energy,
                        ROUND(AVG(stress_level), 2) as avg_stress,
                        COUNT(*) as responses
                    FROM mood_surveys
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date DESC
                ");
                $stmt->execute([$period]);
                $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // توزيع المزاج
                $stmt = $pdo->prepare("
                    SELECT 
                        mood_score,
                        COUNT(*) as count
                    FROM mood_surveys
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY mood_score
                    ORDER BY mood_score
                ");
                $stmt->execute([$period]);
                $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // الموظفين الأكثر ضغطاً
                $stmt = $pdo->prepare("
                    SELECT 
                        u.id,
                        u.name,
                        ROUND(AVG(ms.stress_level), 2) as avg_stress,
                        COUNT(*) as surveys
                    FROM mood_surveys ms
                    JOIN users u ON ms.user_id = u.id
                    WHERE ms.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY u.id
                    HAVING avg_stress >= 4
                    ORDER BY avg_stress DESC
                    LIMIT 10
                ");
                $stmt->execute([$period]);
                $high_stress = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'daily_stats' => $daily_stats,
                    'distribution' => $distribution,
                    'high_stress_employees' => $high_stress
                ]);
                
            } elseif ($type === 'today') {
                // استبيان اليوم
                $stmt = $pdo->prepare("
                    SELECT * FROM mood_surveys 
                    WHERE user_id = ? AND DATE(created_at) = ?
                ");
                $stmt->execute([$user_id, $today]);
                $survey = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'survey' => $survey,
                    'submitted_today' => $survey !== false
                ]);
                
            } else {
                // سجل المزاج
                $days = min(90, intval($_GET['days'] ?? 30));
                
                $stmt = $pdo->prepare("
                    SELECT * FROM mood_surveys 
                    WHERE user_id = ? 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$user_id, $days]);
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // حساب المتوسطات
                $avg_mood = $avg_energy = $avg_stress = 0;
                $count = count($history);
                
                if ($count > 0) {
                    $avg_mood = round(array_sum(array_column($history, 'mood_score')) / $count, 2);
                    $avg_energy = round(array_sum(array_column($history, 'energy_level')) / $count, 2);
                    $avg_stress = round(array_sum(array_column($history, 'stress_level')) / $count, 2);
                }
                
                echo json_encode([
                    'success' => true,
                    'history' => $history,
                    'averages' => [
                        'mood' => $avg_mood,
                        'energy' => $avg_energy,
                        'stress' => $avg_stress
                    ],
                    'total_entries' => $count
                ]);
            }
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
            
            $mood_score = min(5, max(1, intval($input['mood_score'] ?? 3)));
            $energy_level = min(5, max(1, intval($input['energy_level'] ?? 3)));
            $stress_level = min(5, max(1, intval($input['stress_level'] ?? 3)));
            $notes = htmlspecialchars(trim($input['notes'] ?? ''), ENT_QUOTES, 'UTF-8');
            
            // التحقق هل سجل اليوم
            $stmt = $pdo->prepare("
                SELECT id FROM mood_surveys 
                WHERE user_id = ? AND DATE(created_at) = ?
            ");
            $stmt->execute([$user_id, $today]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // تحديث
                $stmt = $pdo->prepare("
                    UPDATE mood_surveys 
                    SET mood_score = ?, energy_level = ?, stress_level = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$mood_score, $energy_level, $stress_level, $notes, $existing['id']]);
                $survey_id = $existing['id'];
                $action = 'updated';
            } else {
                // إضافة جديد
                $stmt = $pdo->prepare("
                    INSERT INTO mood_surveys (user_id, mood_score, energy_level, stress_level, notes)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $mood_score, $energy_level, $stress_level, $notes]);
                $survey_id = $pdo->lastInsertId();
                $action = 'created';
                
                // منح نقاط للمشاركة اليومية
                $points = 2;
                $stmt = $pdo->prepare("UPDATE users SET points = COALESCE(points, 0) + ? WHERE id = ?");
                $stmt->execute([$points, $user_id]);
            }
            
            // تحليل المزاج وإعطاء نصيحة
            $tip = getMoodTip($mood_score, $energy_level, $stress_level);
            
            echo json_encode([
                'success' => true,
                'survey_id' => $survey_id,
                'action' => $action,
                'tip' => $tip,
                'message' => 'شكراً لمشاركتك! 😊'
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
 * الحصول على نصيحة بناءً على المزاج
 */
function getMoodTip($mood, $energy, $stress) {
    $tips = [];
    
    if ($mood <= 2) {
        $tips[] = '🌟 كل يوم جديد فرصة جديدة. حاول التركيز على الأشياء الإيجابية.';
        $tips[] = '💬 لا تتردد في التحدث مع شخص تثق به عن مشاعرك.';
    }
    
    if ($energy <= 2) {
        $tips[] = '☕ حاول أخذ استراحة قصيرة والتحرك قليلاً لتنشيط جسمك.';
        $tips[] = '😴 تأكد من حصولك على قسط كافٍ من النوم الليلة.';
    }
    
    if ($stress >= 4) {
        $tips[] = '🧘 خذ نفساً عميقاً وحاول الاسترخاء لبضع دقائق.';
        $tips[] = '📋 قسّم مهامك الكبيرة إلى مهام صغيرة يمكن إنجازها.';
    }
    
    if (empty($tips)) {
        $tips = [
            '👏 أنت تقوم بعمل رائع! استمر!',
            '🌈 يومك يبدو جيداً. استمتع به!',
            '💪 طاقتك إيجابية. انشرها حولك!'
        ];
    }
    
    return $tips[array_rand($tips)];
}
