<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - ATTENDANCE LEADERBOARD API                           ║
 * ║           قائمة المتصدرين - أول وآخر من سجل حضور من كل فرع                    ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../../config/app.php';

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'unauthorized']));
}

$today = date('Y-m-d');

try {
    // ═══════════════════════════════════════════════════════════════════════════════
    // أول من سجل حضور من كل فرع (المتصدرين) 🏆
    // ═══════════════════════════════════════════════════════════════════════════════
    $firstCheckins = Database::fetchAll("
        SELECT 
            u.id as user_id,
            u.full_name,
            u.avatar,
            b.id as branch_id,
            b.name as branch_name,
            a.check_in_time,
            TIME_FORMAT(a.check_in_time, '%H:%i') as check_in_formatted
        FROM attendance a
        INNER JOIN users u ON a.user_id = u.id
        INNER JOIN branches b ON a.branch_id = b.id
        WHERE a.date = ?
          AND a.check_in_time IS NOT NULL
          AND a.check_in_time = (
              SELECT MIN(a2.check_in_time)
              FROM attendance a2
              WHERE a2.date = a.date
                AND a2.branch_id = a.branch_id
                AND a2.check_in_time IS NOT NULL
          )
        ORDER BY a.check_in_time ASC
        LIMIT 10
    ", [$today]);

    // ═══════════════════════════════════════════════════════════════════════════════
    // آخر من سجل حضور من كل فرع (المتأخرين) 🐢
    // ═══════════════════════════════════════════════════════════════════════════════
    $lastCheckins = Database::fetchAll("
        SELECT 
            u.id as user_id,
            u.full_name,
            u.avatar,
            b.id as branch_id,
            b.name as branch_name,
            a.check_in_time,
            TIME_FORMAT(a.check_in_time, '%H:%i') as check_in_formatted,
            a.late_minutes
        FROM attendance a
        INNER JOIN users u ON a.user_id = u.id
        INNER JOIN branches b ON a.branch_id = b.id
        WHERE a.date = ?
          AND a.check_in_time IS NOT NULL
          AND a.check_in_time = (
              SELECT MAX(a2.check_in_time)
              FROM attendance a2
              WHERE a2.date = a.date
                AND a2.branch_id = a.branch_id
                AND a2.check_in_time IS NOT NULL
          )
          AND a.late_minutes > 0
        ORDER BY a.check_in_time DESC
        LIMIT 10
    ", [$today]);

    echo json_encode([
        'success' => true,
        'leaders' => $firstCheckins,    // المتصدرين 🏆
        'latecomers' => $lastCheckins,  // المتأخرين 🐢
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('[SARH Leaderboard] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'server_error',
        'message' => 'حدث خطأ في جلب البيانات'
    ], JSON_UNESCAPED_UNICODE);
}
