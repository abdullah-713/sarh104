<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - AI INSIGHTS API                                     ║
 * ║           واجهة برمجة رؤى الذكاء الاصطناعي                                   ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

session_start();
require_once '../config/database.php';
require_once '../includes/ai_predictions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

// التحقق من صلاحيات المدير
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'صلاحيات غير كافية']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$ai = new SarhAIPredictions($pdo);

try {
    switch ($action) {
        case 'predictions':
            // تنبؤات الغياب
            $days = intval($_GET['days'] ?? 7);
            $predictions = $ai->predictAbsences($days);
            
            echo json_encode([
                'success' => true,
                'data' => $predictions,
                'generated_at' => date('Y-m-d H:i:s'),
                'message' => 'تم توليد ' . count($predictions) . ' تنبؤ'
            ]);
            break;
            
        case 'patterns':
            // تحليل الأنماط
            $days = intval($_GET['days'] ?? 30);
            $patterns = $ai->analyzeCompanyPatterns($days);
            
            echo json_encode([
                'success' => true,
                'data' => $patterns,
                'period_days' => $days
            ]);
            break;
            
        case 'suggestions':
            // اقتراحات التحسين
            $suggestions = $ai->getImprovementSuggestions();
            
            echo json_encode([
                'success' => true,
                'data' => $suggestions,
                'count' => count($suggestions)
            ]);
            break;
            
        case 'anomalies':
            // كشف الشذوذ
            $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
            $anomalies = $ai->detectAnomalies($user_id);
            
            echo json_encode([
                'success' => true,
                'data' => $anomalies,
                'count' => count($anomalies)
            ]);
            break;
            
        case 'dashboard':
            // ملخص للوحة التحكم
            $predictions = $ai->predictAbsences(7);
            $suggestions = $ai->getImprovementSuggestions();
            $anomalies = $ai->detectAnomalies();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'predictions_summary' => [
                        'high_risk' => count(array_filter($predictions, fn($p) => $p['risk_score'] >= 70)),
                        'medium_risk' => count(array_filter($predictions, fn($p) => $p['risk_score'] >= 50 && $p['risk_score'] < 70)),
                        'low_risk' => count(array_filter($predictions, fn($p) => $p['risk_score'] >= 30 && $p['risk_score'] < 50)),
                        'top_3' => array_slice($predictions, 0, 3)
                    ],
                    'suggestions_count' => count($suggestions),
                    'top_suggestion' => !empty($suggestions) ? $suggestions[0] : null,
                    'anomalies_count' => count($anomalies),
                    'recent_anomalies' => array_slice($anomalies, 0, 3)
                ],
                'generated_at' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'advanced':
            // تنبؤ متقدم لموظف محدد
            $user_id = intval($_GET['user_id'] ?? 0);
            if (!$user_id) {
                throw new Exception('معرف الموظف مطلوب');
            }
            $prediction = $ai->advancedPrediction($user_id);
            
            echo json_encode([
                'success' => true,
                'data' => $prediction
            ]);
            break;
            
        case 'seasonal':
            // التحليل الموسمي
            $days = intval($_GET['days'] ?? 365);
            $seasonal = $ai->seasonalAnalysis($days);
            
            echo json_encode([
                'success' => true,
                'data' => $seasonal
            ]);
            break;
            
        case 'correlations':
            // تحليل الارتباطات
            $correlations = $ai->correlationAnalysis();
            
            echo json_encode([
                'success' => true,
                'data' => $correlations
            ]);
            break;
            
        case 'full-report':
            // تقرير AI الشامل
            $report = $ai->generateAIReport();
            
            echo json_encode([
                'success' => true,
                'data' => $report
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'إجراء غير صالح',
                'available_actions' => [
                    'predictions', 'patterns', 'suggestions', 'anomalies', 
                    'dashboard', 'advanced', 'seasonal', 'correlations', 'full-report'
                ]
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في المعالجة'
    ]);
}
