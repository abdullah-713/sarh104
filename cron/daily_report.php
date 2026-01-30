<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - DAILY MORNING REPORT CRON                            ║
 * ║           تقرير الصباح اليومي                                                 ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Version: 1.0.0                                                              ║
 * ║  Schedule: 0 8 * * * (يومياً الساعة 8:00 صباحاً)                              ║
 * ║  Purpose: إرسال تقرير الحضور للمدراء                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 * 
 * Crontab entry:
 * 0 8 * * * php /path/to/app/cron/daily_report.php >> /path/to/logs/cron.log 2>&1
 */

// منع الوصول عبر المتصفح
if (php_sapi_name() !== 'cli' && !defined('CRON_INTERNAL')) {
    die('This script can only be run from command line.');
}

define('SARH_SYSTEM', true);
define('SARH_CRON', true);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting Daily Morning Report...\n";

try {
    $today = date('Y-m-d');
    $now = date('H:i:s');
    
    // ═══════════════════════════════════════════════════════════════════════════
    // 1. جلب قائمة المدراء
    // ═══════════════════════════════════════════════════════════════════════════
    
    $managers = Database::fetchAll(
        "SELECT u.id, u.full_name, u.email, u.branch_id, u.preferences,
                r.role_level, b.name as branch_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         LEFT JOIN branches b ON b.id = u.branch_id
         WHERE u.is_active = 1 AND r.role_level >= 60
         ORDER BY r.role_level DESC"
    );
    
    echo "Found " . count($managers) . " managers to notify.\n";
    
    foreach ($managers as $manager) {
        $branch_filter = "";
        $branch_params = [];
        
        // المدراء العامون يرون كل الفروع
        if ($manager['role_level'] < 80 && $manager['branch_id']) {
            $branch_filter = " AND u.branch_id = ?";
            $branch_params = [$manager['branch_id']];
        }
        
        // ═══════════════════════════════════════════════════════════════════════
        // 2. إحصائيات الحضور
        // ═══════════════════════════════════════════════════════════════════════
        
        // إجمالي الموظفين
        $total_employees = Database::fetchValue(
            "SELECT COUNT(*) FROM users u 
             JOIN roles r ON r.id = u.role_id 
             WHERE u.is_active = 1 AND r.role_level < 60" . $branch_filter,
            $branch_params
        );
        
        // من سجّل حضور اليوم
        $checked_in = Database::fetchValue(
            "SELECT COUNT(DISTINCT a.user_id) FROM attendance a
             JOIN users u ON u.id = a.user_id
             WHERE a.date = ? AND a.check_in_time IS NOT NULL" . $branch_filter,
            array_merge([$today], $branch_params)
        );
        
        // المتأخرون
        $late_count = Database::fetchValue(
            "SELECT COUNT(*) FROM attendance a
             JOIN users u ON u.id = a.user_id
             WHERE a.date = ? AND a.late_minutes > 0" . $branch_filter,
            array_merge([$today], $branch_params)
        );
        
        // الغائبون (لم يسجلوا حتى الآن)
        $absent_count = $total_employees - $checked_in;
        
        // قائمة المتأخرين بالتفصيل
        $late_employees = Database::fetchAll(
            "SELECT u.full_name, u.emp_code, a.late_minutes, a.check_in_time,
                    b.name as branch_name
             FROM attendance a
             JOIN users u ON u.id = a.user_id
             LEFT JOIN branches b ON b.id = u.branch_id
             WHERE a.date = ? AND a.late_minutes > 15" . $branch_filter . "
             ORDER BY a.late_minutes DESC
             LIMIT 10",
            array_merge([$today], $branch_params)
        );
        
        // قائمة الغائبين
        $absent_employees = Database::fetchAll(
            "SELECT u.full_name, u.emp_code, b.name as branch_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN branches b ON b.id = u.branch_id
             WHERE u.is_active = 1 
               AND r.role_level < 60
               AND u.id NOT IN (SELECT user_id FROM attendance WHERE date = ?)" . $branch_filter . "
             LIMIT 20",
            array_merge([$today], $branch_params)
        );
        
        // ═══════════════════════════════════════════════════════════════════════
        // 3. بناء التقرير
        // ═══════════════════════════════════════════════════════════════════════
        
        $attendance_rate = $total_employees > 0 ? round(($checked_in / $total_employees) * 100, 1) : 0;
        
        $report_title = "📊 تقرير الصباح - " . date('Y/m/d');
        
        $report_body = "
مرحباً {$manager['full_name']}،

إليك ملخص الحضور حتى الساعة {$now}:

━━━━━━━━━━━━━━━━━━━━━━━━━━
📈 الإحصائيات العامة
━━━━━━━━━━━━━━━━━━━━━━━━━━
• إجمالي الموظفين: {$total_employees}
• حضروا: {$checked_in} ({$attendance_rate}%)
• متأخرون: {$late_count}
• لم يحضروا بعد: {$absent_count}
";
        
        if (!empty($late_employees)) {
            $report_body .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━\n⏰ أبرز المتأخرين\n━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            foreach ($late_employees as $emp) {
                $report_body .= "• {$emp['full_name']} - تأخر {$emp['late_minutes']} دقيقة (حضر: {$emp['check_in_time']})\n";
            }
        }
        
        if (!empty($absent_employees) && count($absent_employees) <= 10) {
            $report_body .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━\n❌ لم يحضروا بعد\n━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            foreach ($absent_employees as $emp) {
                $report_body .= "• {$emp['full_name']} ({$emp['emp_code']})\n";
            }
        }
        
        $report_body .= "\n\n🔗 للتفاصيل الكاملة: " . BASE_URL . "/reports.php?date={$today}";
        
        // ═══════════════════════════════════════════════════════════════════════
        // 4. إرسال الإشعار
        // ═══════════════════════════════════════════════════════════════════════
        
        Database::insert('notifications', [
            'user_id' => $manager['id'],
            'type' => 'daily_report',
            'title' => $report_title,
            'message' => $report_body,
            'data' => json_encode([
                'date' => $today,
                'total' => $total_employees,
                'present' => $checked_in,
                'late' => $late_count,
                'absent' => $absent_count,
                'rate' => $attendance_rate
            ]),
            'priority' => 'normal',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "  ✓ Sent report to: {$manager['full_name']}\n";
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // 5. تسجيل في سجل الأنشطة
    // ═══════════════════════════════════════════════════════════════════════════
    
    Database::insert('activity_log', [
        'user_id' => 0,
        'action' => 'cron_daily_report',
        'entity_type' => 'system',
        'entity_id' => 0,
        'description' => 'Daily morning report sent to ' . count($managers) . ' managers',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "[" . date('Y-m-d H:i:s') . "] Daily Report completed successfully.\n";
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    error_log("[SARH Cron Error] daily_report: " . $e->getMessage());
    exit(1);
}

exit(0);
