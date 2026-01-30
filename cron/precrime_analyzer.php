<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - PRE-CRIME ANALYZER (العقل المحلل)                    ║
 * ║           محرك التنبؤ بالسلوك وتحليل الأنماط                                   ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Version: 1.0.0                                                              ║
 * ║  Schedule: 0 2 * * * (يومياً الساعة 2:00 صباحاً)                              ║
 * ║  Purpose: تحليل الأنماط السلوكية والتنبؤ بالمخاطر                              ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 * 
 * Crontab entry:
 * 0 2 * * * php /path/to/app/cron/precrime_analyzer.php >> /path/to/logs/cron.log 2>&1
 */

if (php_sapi_name() !== 'cli' && !defined('CRON_INTERNAL')) {
    die('This script can only be run from command line.');
}

define('SARH_SYSTEM', true);
define('SARH_CRON', true);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting Pre-Crime Analyzer...\n";

try {
    $analysis_date = date('Y-m-d');
    $lookback_days = 30; // تحليل آخر 30 يوم
    
    // ═══════════════════════════════════════════════════════════════════════════
    // 1. جلب جميع الموظفين للتحليل
    // ═══════════════════════════════════════════════════════════════════════════
    
    $employees = Database::fetchAll(
        "SELECT u.id, u.full_name, u.emp_code, u.branch_id, u.hire_date, u.current_points,
                pp.personality_type, pp.integrity_score, pp.commitment_index,
                DATEDIFF(NOW(), u.hire_date) as days_employed
         FROM users u
         JOIN roles r ON r.id = u.role_id
         LEFT JOIN psychological_profiles pp ON pp.user_id = u.id
         WHERE u.is_active = 1 AND r.role_level < 60"
    );
    
    echo "Analyzing " . count($employees) . " employees...\n";
    
    $high_risk_count = 0;
    $medium_risk_count = 0;
    
    foreach ($employees as $emp) {
        echo "  Analyzing: {$emp['full_name']}... ";
        
        // ═══════════════════════════════════════════════════════════════════════
        // 2. جمع بيانات الحضور
        // ═══════════════════════════════════════════════════════════════════════
        
        $attendance_stats = Database::fetchOne(
            "SELECT 
                COUNT(*) as total_records,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                AVG(late_minutes) as avg_late_minutes,
                MAX(late_minutes) as max_late_minutes,
                SUM(overtime_minutes) as total_overtime,
                SUM(penalty_points) as total_penalties,
                SUM(bonus_points) as total_bonuses,
                COUNT(DISTINCT CASE WHEN late_minutes > 30 THEN date END) as severe_late_days
             FROM attendance
             WHERE user_id = ? AND date >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$emp['id'], $lookback_days]
        );
        
        // ═══════════════════════════════════════════════════════════════════════
        // 3. تحليل أنماط التأخير (أيام محددة؟ تصاعدي؟)
        // ═══════════════════════════════════════════════════════════════════════
        
        $daily_pattern = Database::fetchAll(
            "SELECT DAYOFWEEK(date) as day_num, AVG(late_minutes) as avg_late
             FROM attendance
             WHERE user_id = ? AND date >= DATE_SUB(NOW(), INTERVAL ? DAY) AND late_minutes > 0
             GROUP BY DAYOFWEEK(date)",
            [$emp['id'], $lookback_days]
        );
        
        // تحديد اليوم الأسوأ
        $worst_day = null;
        $worst_avg = 0;
        foreach ($daily_pattern as $day) {
            if ($day['avg_late'] > $worst_avg) {
                $worst_avg = $day['avg_late'];
                $worst_day = $day['day_num'];
            }
        }
        
        // ═══════════════════════════════════════════════════════════════════════
        // 4. تحليل الاتجاه (تحسن أم تدهور؟)
        // ═══════════════════════════════════════════════════════════════════════
        
        $trend = Database::fetchOne(
            "SELECT 
                AVG(CASE WHEN date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN late_minutes END) as recent_avg,
                AVG(CASE WHEN date < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN late_minutes END) as older_avg
             FROM attendance
             WHERE user_id = ? AND date >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$emp['id'], $lookback_days]
        );
        
        $trend_direction = 'stable';
        $trend_score = 0;
        
        if ($trend['recent_avg'] && $trend['older_avg']) {
            $change = $trend['recent_avg'] - $trend['older_avg'];
            if ($change > 5) {
                $trend_direction = 'declining';
                $trend_score = min(30, $change); // حتى 30 نقطة خطورة
            } elseif ($change < -5) {
                $trend_direction = 'improving';
                $trend_score = max(-20, $change); // حتى -20 (تحسن)
            }
        }
        
        // ═══════════════════════════════════════════════════════════════════════
        // 5. تحليل الفخاخ والمخالفات
        // ═══════════════════════════════════════════════════════════════════════
        
        $trap_incidents = Database::fetchValue(
            "SELECT COUNT(*) FROM trap_incidents WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$emp['id'], $lookback_days]
        );
        
        // ═══════════════════════════════════════════════════════════════════════
        // 6. حساب درجة المخاطرة
        // ═══════════════════════════════════════════════════════════════════════
        
        $risk_score = 0;
        $risk_factors = [];
        
        // عامل التأخير
        $late_rate = $attendance_stats['total_records'] > 0 
            ? ($attendance_stats['late_count'] / $attendance_stats['total_records']) * 100 
            : 0;
        
        if ($late_rate > 50) {
            $risk_score += 25;
            $risk_factors[] = "معدل تأخير عالٍ ({$late_rate}%)";
        } elseif ($late_rate > 30) {
            $risk_score += 15;
            $risk_factors[] = "معدل تأخير متوسط ({$late_rate}%)";
        }
        
        // عامل الغياب
        $absent_rate = $attendance_stats['total_records'] > 0 
            ? ($attendance_stats['absent_count'] / $attendance_stats['total_records']) * 100 
            : 0;
        
        if ($absent_rate > 20) {
            $risk_score += 30;
            $risk_factors[] = "معدل غياب مرتفع ({$absent_rate}%)";
        } elseif ($absent_rate > 10) {
            $risk_score += 15;
            $risk_factors[] = "غياب ملحوظ ({$absent_rate}%)";
        }
        
        // عامل الاتجاه
        if ($trend_direction === 'declining') {
            $risk_score += $trend_score;
            $risk_factors[] = "اتجاه تدهور في الالتزام";
        }
        
        // عامل الفخاخ
        if ($trap_incidents > 3) {
            $risk_score += 20;
            $risk_factors[] = "وقوع متكرر في الفخاخ ({$trap_incidents} مرة)";
        } elseif ($trap_incidents > 0) {
            $risk_score += $trap_incidents * 5;
            $risk_factors[] = "مخالفات سابقة ({$trap_incidents})";
        }
        
        // عامل النقاط المنخفضة
        if ($emp['current_points'] < 500) {
            $risk_score += 15;
            $risk_factors[] = "نقاط منخفضة جداً ({$emp['current_points']})";
        } elseif ($emp['current_points'] < 800) {
            $risk_score += 8;
            $risk_factors[] = "نقاط تحت المتوسط ({$emp['current_points']})";
        }
        
        // عامل الموظف الجديد
        if ($emp['days_employed'] < 90) {
            $risk_score -= 10; // تخفيف للموظفين الجدد
        }
        
        // تحديد مستوى الخطورة
        $risk_score = max(0, min(100, $risk_score));
        
        $risk_level = 'low';
        if ($risk_score >= 60) {
            $risk_level = 'high';
            $high_risk_count++;
        } elseif ($risk_score >= 35) {
            $risk_level = 'medium';
            $medium_risk_count++;
        }
        
        // ═══════════════════════════════════════════════════════════════════════
        // 7. التنبؤ باحتمالية الاستقالة
        // ═══════════════════════════════════════════════════════════════════════
        
        $resignation_risk = 0;
        
        // مؤشرات الاستقالة
        if ($attendance_stats['total_overtime'] < 60 && $emp['days_employed'] > 180) {
            $resignation_risk += 10; // لا يعمل ساعات إضافية
        }
        
        if ($trend_direction === 'declining' && $risk_score > 40) {
            $resignation_risk += 20;
        }
        
        if ($emp['current_points'] < 600 && $emp['days_employed'] > 90) {
            $resignation_risk += 15;
        }
        
        // موظف قديم بأداء متدهور
        if ($emp['days_employed'] > 365 && $risk_score > 50) {
            $resignation_risk += 25;
        }
        
        $resignation_risk = min(100, $resignation_risk);
        
        // ═══════════════════════════════════════════════════════════════════════
        // 8. حفظ النتائج
        // ═══════════════════════════════════════════════════════════════════════
        
        Database::query(
            "INSERT INTO predictive_risk_scores 
             (user_id, analysis_date, risk_score, risk_level, resignation_probability,
              late_rate, absent_rate, trend_direction, risk_factors, worst_day,
              lookback_days, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                risk_score = VALUES(risk_score),
                risk_level = VALUES(risk_level),
                resignation_probability = VALUES(resignation_probability),
                late_rate = VALUES(late_rate),
                absent_rate = VALUES(absent_rate),
                trend_direction = VALUES(trend_direction),
                risk_factors = VALUES(risk_factors),
                worst_day = VALUES(worst_day),
                updated_at = NOW()",
            [
                $emp['id'],
                $analysis_date,
                $risk_score,
                $risk_level,
                $resignation_risk,
                round($late_rate, 2),
                round($absent_rate, 2),
                $trend_direction,
                json_encode($risk_factors, JSON_UNESCAPED_UNICODE),
                $worst_day,
                $lookback_days
            ]
        );
        
        // تحديث الملف النفسي
        Database::query(
            "UPDATE psychological_profiles 
             SET risk_level = ?,
                 last_analysis_date = ?,
                 resignation_probability = ?,
                 trend_direction = ?,
                 updated_at = NOW()
             WHERE user_id = ?",
            [$risk_level, $analysis_date, $resignation_risk, $trend_direction, $emp['id']]
        );
        
        echo "Risk: {$risk_score} ({$risk_level})\n";
        
        // ═══════════════════════════════════════════════════════════════════════
        // 9. إنشاء تنبيهات للمخاطر العالية
        // ═══════════════════════════════════════════════════════════════════════
        
        if ($risk_level === 'high') {
            // إشعار للمدراء
            $branch_managers = Database::fetchAll(
                "SELECT u.id FROM users u
                 JOIN roles r ON r.id = u.role_id
                 WHERE (u.branch_id = ? OR r.role_level >= 80)
                   AND r.role_level >= 60 AND u.is_active = 1",
                [$emp['branch_id']]
            );
            
            foreach ($branch_managers as $manager) {
                Database::insert('notifications', [
                    'user_id' => $manager['id'],
                    'type' => 'risk_alert',
                    'title' => '🚨 تنبيه خطورة عالية',
                    'message' => "الموظف {$emp['full_name']} ({$emp['emp_code']}) يظهر مؤشرات خطورة عالية:\n" . implode("\n", array_map(fn($f) => "• {$f}", $risk_factors)),
                    'data' => json_encode([
                        'employee_id' => $emp['id'],
                        'risk_score' => $risk_score,
                        'risk_factors' => $risk_factors
                    ]),
                    'priority' => 'urgent',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // 10. تحليل شبكة التأثير (Influence Graph)
    // ═══════════════════════════════════════════════════════════════════════════
    
    echo "\nStep 2: Analyzing influence patterns...\n";
    
    // البحث عن أنماط التأخير المشتركة (موظفون يتأخرون معاً)
    $shared_patterns = Database::fetchAll(
        "SELECT a1.user_id as user1, a2.user_id as user2, COUNT(*) as shared_late_days
         FROM attendance a1
         JOIN attendance a2 ON a1.date = a2.date 
              AND a1.user_id < a2.user_id
              AND a1.late_minutes > 15 AND a2.late_minutes > 15
         JOIN users u1 ON u1.id = a1.user_id AND u1.branch_id = (SELECT branch_id FROM users WHERE id = a2.user_id)
         WHERE a1.date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY a1.user_id, a2.user_id
         HAVING shared_late_days >= 5"
    );
    
    foreach ($shared_patterns as $pattern) {
        Database::query(
            "INSERT INTO influence_graph 
             (user_id_1, user_id_2, relationship_type, strength, evidence_count, last_incident, created_at)
             VALUES (?, ?, 'co_late', ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                strength = VALUES(strength),
                evidence_count = VALUES(evidence_count),
                last_incident = NOW()",
            [$pattern['user1'], $pattern['user2'], min(100, $pattern['shared_late_days'] * 10), $pattern['shared_late_days']]
        );
    }
    
    echo "  ✓ Found " . count($shared_patterns) . " influence patterns.\n";
    
    // ═══════════════════════════════════════════════════════════════════════════
    // 11. تسجيل في سجل الأنشطة
    // ═══════════════════════════════════════════════════════════════════════════
    
    Database::insert('activity_log', [
        'user_id' => 0,
        'action' => 'cron_precrime_analysis',
        'entity_type' => 'system',
        'entity_id' => 0,
        'description' => "Pre-crime analysis completed. High risk: {$high_risk_count}, Medium: {$medium_risk_count}",
        'metadata' => json_encode([
            'date' => $analysis_date,
            'total_analyzed' => count($employees),
            'high_risk' => $high_risk_count,
            'medium_risk' => $medium_risk_count,
            'influence_patterns' => count($shared_patterns)
        ]),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Pre-Crime Analysis completed.\n";
    echo "Summary: High Risk: {$high_risk_count}, Medium Risk: {$medium_risk_count}\n";
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    error_log("[SARH Cron Error] precrime_analyzer: " . $e->getMessage());
    exit(1);
}

exit(0);
