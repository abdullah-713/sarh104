<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - AUTO CHECKOUT CRON                                   ║
 * ║           إغلاق الانصراف المنسي تلقائياً                                       ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Version: 1.0.0                                                              ║
 * ║  Schedule: 0 0 * * * (يومياً منتصف الليل)                                     ║
 * ║  Purpose: إغلاق سجلات الحضور المفتوحة                                         ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 * 
 * Crontab entry:
 * 0 0 * * * php /path/to/app/cron/auto_checkout.php >> /path/to/logs/cron.log 2>&1
 */

if (php_sapi_name() !== 'cli' && !defined('CRON_INTERNAL')) {
    die('This script can only be run from command line.');
}

define('SARH_SYSTEM', true);
define('SARH_CRON', true);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting Auto Checkout...\n";

try {
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $auto_checkout_time = '23:59:59';
    $penalty_rate = floatval(get_setting('forgot_checkout_penalty', '10'));
    
    // ═══════════════════════════════════════════════════════════════════════════
    // 1. جلب السجلات المفتوحة (لم يسجل انصراف)
    // ═══════════════════════════════════════════════════════════════════════════
    
    $open_records = Database::fetchAll(
        "SELECT a.*, u.full_name, u.current_points,
                es.work_end_time
         FROM attendance a
         JOIN users u ON u.id = a.user_id
         LEFT JOIN employee_schedules es ON es.user_id = a.user_id AND es.is_active = 1
         WHERE a.date = ? 
           AND a.check_in_time IS NOT NULL 
           AND a.check_out_time IS NULL",
        [$yesterday]
    );
    
    echo "Found " . count($open_records) . " open attendance records.\n";
    
    $closed_count = 0;
    $total_penalty = 0;
    
    foreach ($open_records as $record) {
        // حساب ساعات العمل حتى نهاية الدوام الرسمي
        $work_end = $record['work_end_time'] ?? '17:00:00';
        $check_in = new DateTime($record['date'] . ' ' . $record['check_in_time']);
        $check_out = new DateTime($record['date'] . ' ' . $work_end);
        
        // إذا سجل دخول بعد نهاية الدوام، استخدم وقت الدخول
        if ($check_in > $check_out) {
            $check_out = clone $check_in;
            $check_out->modify('+30 minutes');
        }
        
        $work_minutes = intval(($check_out->getTimestamp() - $check_in->getTimestamp()) / 60);
        
        // تطبيق عقوبة نسيان الانصراف
        $penalty = $penalty_rate;
        
        // تحديث السجل
        Database::update('attendance', [
            'check_out_time' => $work_end,
            'work_minutes' => $work_minutes,
            'penalty_points' => $record['penalty_points'] + $penalty,
            'auto_checkout' => 1,
            'auto_checkout_reason' => 'forgot_checkout',
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $record['id']]);
        
        // خصم النقاط
        if ($penalty > 0) {
            Database::query(
                "UPDATE users SET current_points = GREATEST(0, current_points - ?) WHERE id = ?",
                [$penalty, $record['user_id']]
            );
            $total_penalty += $penalty;
        }
        
        // إرسال إشعار للموظف
        Database::insert('notifications', [
            'user_id' => $record['user_id'],
            'type' => 'auto_checkout',
            'title' => '⚠️ تم إغلاق انصرافك تلقائياً',
            'message' => "نسيت تسجيل الانصراف يوم {$yesterday}. تم إغلاقه تلقائياً الساعة {$work_end}. خصم: {$penalty} نقطة.",
            'data' => json_encode([
                'attendance_id' => $record['id'],
                'date' => $yesterday,
                'penalty' => $penalty
            ]),
            'priority' => 'high',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // تحديث الملف النفسي
        Database::query(
            "UPDATE psychological_profiles 
             SET forgot_checkout_count = COALESCE(forgot_checkout_count, 0) + 1,
                 last_incident_date = ?,
                 updated_at = NOW()
             WHERE user_id = ?",
            [$yesterday, $record['user_id']]
        );
        
        $closed_count++;
        echo "  ✓ Closed: {$record['full_name']} (penalty: {$penalty})\n";
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // 2. تسجيل في سجل الأنشطة
    // ═══════════════════════════════════════════════════════════════════════════
    
    if ($closed_count > 0) {
        Database::insert('activity_log', [
            'user_id' => 0,
            'action' => 'cron_auto_checkout',
            'entity_type' => 'system',
            'entity_id' => 0,
            'description' => "Auto-closed {$closed_count} attendance records. Total penalty: {$total_penalty} points.",
            'metadata' => json_encode([
                'date' => $yesterday,
                'closed_count' => $closed_count,
                'total_penalty' => $total_penalty
            ]),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Auto Checkout completed. Closed: {$closed_count}, Penalty: {$total_penalty}\n";
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    error_log("[SARH Cron Error] auto_checkout: " . $e->getMessage());
    exit(1);
}

exit(0);
