<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - CHALLENGES API / التحديات                           ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Endpoints:                                                                  ║
 * ║  GET  - جلب التحديات النشطة                                                  ║
 * ║  POST join - الانضمام لتحدي                                                  ║
 * ║  POST progress - تحديث التقدم                                                ║
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
            $type = $_GET['type'] ?? 'active';
            
            if ($type === 'active') {
                // التحديات النشطة
                $stmt = $pdo->prepare("
                    SELECT c.*,
                           uc.progress,
                           uc.is_completed,
                           uc.joined_at,
                           (SELECT COUNT(*) FROM user_challenges WHERE challenge_id = c.id) as participants
                    FROM challenges c
                    LEFT JOIN user_challenges uc ON c.id = uc.challenge_id AND uc.user_id = ?
                    WHERE c.is_active = 1
                    AND c.start_date <= NOW()
                    AND c.end_date >= NOW()
                    ORDER BY c.end_date ASC
                ");
                $stmt->execute([$user_id]);
            } elseif ($type === 'my') {
                // تحدياتي
                $stmt = $pdo->prepare("
                    SELECT c.*,
                           uc.progress,
                           uc.is_completed,
                           uc.joined_at,
                           (SELECT COUNT(*) FROM user_challenges WHERE challenge_id = c.id) as participants
                    FROM challenges c
                    JOIN user_challenges uc ON c.id = uc.challenge_id AND uc.user_id = ?
                    ORDER BY uc.is_completed ASC, c.end_date ASC
                ");
                $stmt->execute([$user_id]);
            } else {
                // التحديات المكتملة
                $stmt = $pdo->prepare("
                    SELECT c.*,
                           uc.progress,
                           uc.is_completed,
                           uc.completed_at,
                           (SELECT COUNT(*) FROM user_challenges WHERE challenge_id = c.id AND is_completed = 1) as completions
                    FROM challenges c
                    JOIN user_challenges uc ON c.id = uc.challenge_id AND uc.user_id = ?
                    WHERE uc.is_completed = 1
                    ORDER BY uc.completed_at DESC
                ");
                $stmt->execute([$user_id]);
            }
            
            $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // حساب النسبة المئوية للتقدم
            foreach ($challenges as &$c) {
                if ($c['progress'] !== null && $c['target_value'] > 0) {
                    $c['progress_percent'] = min(100, round(($c['progress'] / $c['target_value']) * 100));
                } else {
                    $c['progress_percent'] = 0;
                }
                
                // حساب الوقت المتبقي
                if ($c['end_date']) {
                    $end = new DateTime($c['end_date']);
                    $now = new DateTime();
                    $diff = $now->diff($end);
                    
                    if ($now > $end) {
                        $c['time_remaining'] = 'انتهى';
                    } elseif ($diff->days > 0) {
                        $c['time_remaining'] = $diff->days . ' يوم';
                    } elseif ($diff->h > 0) {
                        $c['time_remaining'] = $diff->h . ' ساعة';
                    } else {
                        $c['time_remaining'] = $diff->i . ' دقيقة';
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'challenges' => $challenges
            ]);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? '';
            
            switch ($action) {
                case 'join':
                    $challenge_id = intval($input['challenge_id'] ?? 0);
                    
                    if (!$challenge_id) {
                        echo json_encode(['success' => false, 'message' => 'معرف التحدي مطلوب']);
                        exit;
                    }
                    
                    // التحقق من وجود التحدي ونشاطه
                    $stmt = $pdo->prepare("
                        SELECT * FROM challenges 
                        WHERE id = ? AND is_active = 1 AND start_date <= NOW() AND end_date >= NOW()
                    ");
                    $stmt->execute([$challenge_id]);
                    $challenge = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$challenge) {
                        echo json_encode(['success' => false, 'message' => 'التحدي غير موجود أو منتهي']);
                        exit;
                    }
                    
                    // التحقق من عدم الانضمام مسبقاً
                    $stmt = $pdo->prepare("
                        SELECT 1 FROM user_challenges WHERE challenge_id = ? AND user_id = ?
                    ");
                    $stmt->execute([$challenge_id, $user_id]);
                    
                    if ($stmt->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'أنت منضم لهذا التحدي بالفعل']);
                        exit;
                    }
                    
                    // الانضمام للتحدي
                    $stmt = $pdo->prepare("
                        INSERT INTO user_challenges (user_id, challenge_id, progress)
                        VALUES (?, ?, 0)
                    ");
                    $stmt->execute([$user_id, $challenge_id]);
                    
                    // منح نقاط للانضمام
                    $join_points = 5;
                    $stmt = $pdo->prepare("UPDATE users SET points = COALESCE(points, 0) + ? WHERE id = ?");
                    $stmt->execute([$join_points, $user_id]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'تم الانضمام للتحدي بنجاح! 🎯',
                        'points_earned' => $join_points
                    ]);
                    break;
                    
                case 'update_progress':
                    $challenge_id = intval($input['challenge_id'] ?? 0);
                    $progress = intval($input['progress'] ?? 0);
                    
                    if (!$challenge_id) {
                        echo json_encode(['success' => false, 'message' => 'معرف التحدي مطلوب']);
                        exit;
                    }
                    
                    // جلب التحدي والتقدم الحالي
                    $stmt = $pdo->prepare("
                        SELECT c.*, uc.progress as current_progress, uc.is_completed
                        FROM challenges c
                        JOIN user_challenges uc ON c.id = uc.challenge_id AND uc.user_id = ?
                        WHERE c.id = ?
                    ");
                    $stmt->execute([$user_id, $challenge_id]);
                    $data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$data) {
                        echo json_encode(['success' => false, 'message' => 'لم تنضم لهذا التحدي']);
                        exit;
                    }
                    
                    if ($data['is_completed']) {
                        echo json_encode(['success' => false, 'message' => 'التحدي مكتمل بالفعل']);
                        exit;
                    }
                    
                    // تحديث التقدم
                    $new_progress = $data['current_progress'] + $progress;
                    $is_completed = $new_progress >= $data['target_value'] ? 1 : 0;
                    
                    $stmt = $pdo->prepare("
                        UPDATE user_challenges 
                        SET progress = ?, 
                            is_completed = ?,
                            completed_at = IF(? = 1, NOW(), NULL)
                        WHERE user_id = ? AND challenge_id = ?
                    ");
                    $stmt->execute([$new_progress, $is_completed, $is_completed, $user_id, $challenge_id]);
                    
                    $points_earned = 0;
                    
                    // إذا اكتمل التحدي، منح المكافأة
                    if ($is_completed && !$data['is_completed']) {
                        $points_earned = $data['points_reward'];
                        $stmt = $pdo->prepare("UPDATE users SET points = COALESCE(points, 0) + ? WHERE id = ?");
                        $stmt->execute([$points_earned, $user_id]);
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'new_progress' => $new_progress,
                        'is_completed' => $is_completed,
                        'points_earned' => $points_earned,
                        'message' => $is_completed ? '🎉 أتممت التحدي بنجاح!' : 'تم تحديث التقدم'
                    ]);
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
    echo json_encode(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()]);
}
