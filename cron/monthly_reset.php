<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - MONTHLY RESET CRON                                   ║
 * ║           تصفير العدادات الشهرية وتحويل النقاط للرواتب                         ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Version: 1.0.0                                                              ║
 * ║  Schedule: 0 1 1 * * (أول كل شهر الساعة 1:00 صباحاً)                          ║
 * ║  Purpose: تصفير النقاط، أرشفة البيانات، تحويل للرواتب                          ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 * 
 * Crontab entry:
 * 0 1 1 * * php /path/to/app/cron/monthly_reset.php >> /path/to/logs/cron.log 2>&1
 */

if (php_sapi_name() !== 'cli' && !defined('CRON_INTERNAL')) {
    die('This script can only be run from command line.');
}

define('SARH_SYSTEM', true);
define('SARH_CRON', true);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting Monthly Reset...\n";

try {
    $last_month = date('Y-m', strtotime('-1 month'));
    $current_month = date('Y-m');
    $points_to_sar_rate = floatval(get_setting('points_to_sar_rate', '0.1'));
    $starting_points = intval(get_setting('monthly_starting_points', '1000'));
    
    // ═══════════════════════════════════════════════════════════════════════════
    // 1. أرشفة إحصائيات الشهر السابق لكل موظف
    // ═══════════════════════════════════════════════════════════════════════════
    
    echo "Step 1: Archiving last month statistics...\n";
    
    $employees = Database::fetchAll(
        "SELECT u.id, u.full_name, u.current_points, u.emp_code, u.branch_id
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.is_active = 1 AND r.role_level < 80"
    );
    
    $archived_count = 0;
    $total_salary_bonus = 0;
    
    foreach ($employees as $emp) {
        // إحصائيات الحضور للشهر السابق
        $stats = Database::fetchOne(
            "SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(late_minutes) as total_late_minutes,
                SUM(overtime_minutes) as total_overtime_minutes,
                SUM(penalty_points) as total_penalties,
                SUM(bonus_points) as total_bonuses,
                SUM(work_minutes) as total_work_minutes
             FROM attendance
             WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?",
            [$emp['id'], $last_month]
        );
        
        // حساب معدل الالتزام
        $working_days = intval($stats['total_days'] ?: 0);
        $attendance_rate = $working_days > 0 
            ? round(($stats['present_days'] / $working_days) * 100, 2) 
            : 0;
        
        // حساب إضافة الراتب (النقاط فوق 1000)
        $points_above_base = max(0, $emp['current_points'] - $starting_points);
        $salary_bonus = round($points_above_base * $points_to_sar_rate, 2);
        $total_salary_bonus += $salary_bonus;
        
        // أرشفة البيانات
        Database::insert('monthly_archive', [
            'user_id' => $emp['id'],
            'month' => $last_month,
            'branch_id' => $emp['branch_id'],
            'total_days' => $stats['total_days'] ?? 0,
            'present_days' => $stats['present_days'] ?? 0,
            'late_days' => $stats['late_days'] ?? 0,
            'absent_days' => $stats['absent_days'] ?? 0,
            'total_late_minutes' => $stats['total_late_minutes'] ?? 0,
            'total_overtime_minutes' => $stats['total_overtime_minutes'] ?? 0,
            'total_work_hours' => round(($stats['total_work_minutes'] ?? 0) / 60, 2),
            'total_penalties' => $stats['total_penalties'] ?? 0,
            'total_bonuses' => $stats['total_bonuses'] ?? 0,
            'starting_points' => $starting_points,
            'ending_points' => $emp['current_points'],
            'points_above_base' => $points_above_base,
            'salary_bonus' => $salary_bonus,
            'attendance_rate' => $attendance_rate,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // تحديث المحفظة بمبلغ الراتب
        if ($salary_bonus > 0) {
            Database::query(
                "INSERT INTO employee_wallets (user_id, balance, total_earned, created_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE 
                    balance = balance + VALUES(balance),
                    total_earned = total_earned + VALUES(balance)",
                [$emp['id'], $salary_bonus, $salary_bonus]
            );
            
            // تسجيل المعاملة
            Database::insert('wallet_transactions', [
                'user_id' => $emp['id'],
                'type' => 'salary_bonus',
                'amount' => $salary_bonus,
                'points_used' => $points_above_base,
                'description' => "إضافة راتب شهر {$last_month} - {$points_above_base} نقطة",
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        $archived_count++;
    }
    
    echo "  ✓ Archived {$archived_count} employee records.\n";
    echo "  ✓ Total salary bonus: {$total_salary_bonus} SAR\n";
    
    // ═══════════════════════════════════════════════════════════════════════════
    // 2. تصفير نقاط الموظفين
    // ═══════════════════════════════════════════════════════════════════════════
    
    echo "Step 2: Resetting employee points...\n";
    
    $reset_count = Database::query(
        "UPDATE users u
         JOIN roles r ON r.id = u.role_id
         SET u.current_points = ?,
             u.monthly_points_reset_at = NOW()
         WHERE u.is_active = 1 AND r.role_level < 80",
        [$starting_points]
    )->rowCount();
    
    echo "  ✓ Reset points for {$reset_count} employees to {$starting_points}.\n";
    
    // ═══════════════════════════════════════════════════════════════════════════
    // 3. تنظيف التأثيرات المنتهية
    // ═══════════════════════════════════════════════════════════════════════════
    
    echo "Step 3: Cleaning expired effects...\n";
    
    $expired_effects = Database::query(
        "DELETE FROM user_active_effects WHERE expires_at < NOW()"
    )->rowCount();
    
    $expired_purchases = Database::query(
        "UPDATE market_purchases SET status = 'expired' 
         WHERE status = 'active' AND expires_at < NOW()"
    )->rowCount();
    
    echo "  ✓ Cleaned {$expired_effects} expired effects.\n";
    echo "  ✓ Expired {$expired_purchases} market purchases.\n";
    
    // ═══════════════════════════════════════════════════════════════════════════
    // 4. إزالة الحصانة المنتهية
    // ═══════════════════════════════════════════════════════════════════════════
    
    echo "Step 4: Removing expired immunities...\n";
    
    $immunity_removed = Database::query(
        "UPDATE users SET has_immunity = 0, immunity_until = NULL 
         WHERE has_immunity = 1 AND immunity_until < NOW()"
    )->rowCount();
    
    echo "  ✓ Removed immunity from {$immunity_removed} users.\n";
    
    // ═══════════════════════════════════════════════════════════════════════════
    // 5. إرسال إشعارات للموظفين
    // ═══════════════════════════════════════════════════════════════════════════
    
    echo "Step 5: Sending notifications...\n";
    
    $notifications_sent = 0;
    
    foreach ($employees as $emp) {
        $archive = Database::fetchOne(
            "SELECT * FROM monthly_archive WHERE user_id = ? AND month = ?",
            [$emp['id'], $last_month]
        );
        
        $message = "🗓️ ملخص شهر {$last_month}:\n";
        $message .= "• أيام الحضور: {$archive['present_days']} يوم\n";
        $message .= "• معدل الالتزام: {$archive['attendance_rate']}%\n";
        $message .= "• النقاط المكتسبة: {$archive['ending_points']}\n";
        
        if ($archive['salary_bonus'] > 0) {
            $message .= "• 💰 إضافة للراتب: {$archive['salary_bonus']} ريال\n";
        }
        
        $message .= "\n✨ تم تصفير نقاطك لشهر جديد. نقاطك الحالية: {$starting_points}";
        
        Database::insert('notifications', [
            'user_id' => $emp['id'],
            'type' => 'monthly_summary',
            'title' => "📊 ملخص شهر {$last_month}",
            'message' => $message,
            'data' => json_encode($archive),
            'priority' => 'normal',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $notifications_sent++;
    }
    
    echo "  ✓ Sent {$notifications_sent} notifications.\n";
    
    // ═══════════════════════════════════════════════════════════════════════════
    // 6. تسجيل في سجل الأنشطة
    // ═══════════════════════════════════════════════════════════════════════════
    
    Database::insert('activity_log', [
        'user_id' => 0,
        'action' => 'cron_monthly_reset',
        'entity_type' => 'system',
        'entity_id' => 0,
        'description' => "Monthly reset completed for {$last_month}",
        'metadata' => json_encode([
            'month' => $last_month,
            'archived_count' => $archived_count,
            'reset_count' => $reset_count,
            'total_salary_bonus' => $total_salary_bonus,
            'starting_points' => $starting_points
        ]),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "[" . date('Y-m-d H:i:s') . "] Monthly Reset completed successfully.\n";
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    error_log("[SARH Cron Error] monthly_reset: " . $e->getMessage());
    exit(1);
}

exit(0);
