<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - AI PREDICTIONS ENGINE                               ║
 * ║           محرك التنبؤات بالذكاء الاصطناعي                                    ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Features:                                                                   ║
 * ║  - تنبؤ الغياب المحتمل                                                       ║
 * ║  - تحليل أنماط الحضور                                                        ║
 * ║  - توصيات ذكية                                                               ║
 * ║  - كشف الشذوذ                                                                ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

class SarhAIPredictions {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * تنبؤ الغياب للأسبوع القادم
     */
    public function predictAbsences($days_ahead = 7) {
        $predictions = [];
        
        try {
            // جلب الموظفين مع تاريخ حضورهم
            $stmt = $this->pdo->query("
                SELECT 
                    u.id,
                    u.name,
                    b.name as branch_name,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absence_count,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                    COUNT(*) as total_records,
                    MAX(a.date) as last_attendance,
                    DATEDIFF(NOW(), MAX(a.date)) as days_since_last
                FROM users u
                LEFT JOIN branches b ON u.branch_id = b.id
                LEFT JOIN attendance a ON u.id = a.user_id AND a.date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                WHERE u.is_active = 1
                GROUP BY u.id
                HAVING total_records > 5
            ");
            
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($employees as $emp) {
                $risk_score = $this->calculateAbsenceRisk($emp);
                
                if ($risk_score >= 30) { // فقط المخاطر المرتفعة
                    $predictions[] = [
                        'user_id' => $emp['id'],
                        'name' => $emp['name'],
                        'branch' => $emp['branch_name'],
                        'risk_score' => $risk_score,
                        'risk_level' => $this->getRiskLevel($risk_score),
                        'factors' => $this->getAbsenceFactors($emp),
                        'recommendation' => $this->getRecommendation($emp, $risk_score)
                    ];
                }
            }
            
            // ترتيب حسب درجة الخطورة
            usort($predictions, fn($a, $b) => $b['risk_score'] - $a['risk_score']);
            
        } catch (Exception $e) {
            // Log error
        }
        
        return array_slice($predictions, 0, 10); // أعلى 10
    }
    
    /**
     * حساب مخاطر الغياب
     */
    private function calculateAbsenceRisk($employee) {
        $risk = 0;
        
        // نسبة الغياب التاريخية (0-40 نقطة)
        if ($employee['total_records'] > 0) {
            $absence_rate = ($employee['absence_count'] / $employee['total_records']) * 100;
            $risk += min(40, $absence_rate * 2);
        }
        
        // التأخير المتكرر (0-20 نقطة)
        if ($employee['total_records'] > 0) {
            $late_rate = ($employee['late_count'] / $employee['total_records']) * 100;
            $risk += min(20, $late_rate);
        }
        
        // أيام منذ آخر حضور (0-20 نقطة)
        $days_since = $employee['days_since_last'] ?? 0;
        if ($days_since > 3) {
            $risk += min(20, $days_since * 2);
        }
        
        // نمط اليوم من الأسبوع (0-20 نقطة)
        $risk += $this->getDayPatternRisk($employee['id']);
        
        return min(100, round($risk));
    }
    
    /**
     * تحليل نمط أيام الأسبوع
     */
    private function getDayPatternRisk($user_id) {
        try {
            // اليوم الحالي أو غداً
            $tomorrow = date('w', strtotime('+1 day'));
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    DAYOFWEEK(date) as day_num,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absences,
                    COUNT(*) as total
                FROM attendance
                WHERE user_id = ?
                AND date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY DAYOFWEEK(date)
            ");
            $stmt->execute([$user_id]);
            $patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($patterns as $p) {
                if ($p['day_num'] == $tomorrow && $p['total'] > 0) {
                    $absence_rate = ($p['absences'] / $p['total']) * 100;
                    return min(20, $absence_rate / 2);
                }
            }
        } catch (Exception $e) {
            // Ignore
        }
        
        return 0;
    }
    
    /**
     * تحديد مستوى الخطورة
     */
    private function getRiskLevel($score) {
        if ($score >= 70) return ['level' => 'high', 'label' => 'مرتفع', 'color' => 'danger'];
        if ($score >= 50) return ['level' => 'medium', 'label' => 'متوسط', 'color' => 'warning'];
        return ['level' => 'low', 'label' => 'منخفض', 'color' => 'success'];
    }
    
    /**
     * عوامل الغياب
     */
    private function getAbsenceFactors($employee) {
        $factors = [];
        
        $absence_rate = $employee['total_records'] > 0 
            ? ($employee['absence_count'] / $employee['total_records']) * 100 
            : 0;
        
        if ($absence_rate > 15) {
            $factors[] = 'نسبة غياب مرتفعة (' . round($absence_rate) . '%)';
        }
        
        $late_rate = $employee['total_records'] > 0 
            ? ($employee['late_count'] / $employee['total_records']) * 100 
            : 0;
        
        if ($late_rate > 20) {
            $factors[] = 'تأخير متكرر (' . round($late_rate) . '%)';
        }
        
        if (($employee['days_since_last'] ?? 0) > 3) {
            $factors[] = 'غياب منذ ' . $employee['days_since_last'] . ' يوم';
        }
        
        if (empty($factors)) {
            $factors[] = 'نمط غير منتظم';
        }
        
        return $factors;
    }
    
    /**
     * توصيات ذكية
     */
    private function getRecommendation($employee, $risk_score) {
        if ($risk_score >= 70) {
            return 'تواصل مباشر مع الموظف وتحقق من أي مشاكل';
        }
        if ($risk_score >= 50) {
            return 'مراقبة قريبة وتذكير بأهمية الحضور';
        }
        return 'متابعة دورية';
    }
    
    /**
     * تحليل أنماط الحضور للشركة
     */
    public function analyzeCompanyPatterns($days = 30) {
        $analysis = [];
        
        try {
            // أفضل/أسوأ أيام الأسبوع
            $stmt = $this->pdo->prepare("
                SELECT 
                    DAYOFWEEK(date) as day_num,
                    DAYNAME(date) as day_name,
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late,
                    COUNT(*) as total
                FROM attendance
                WHERE date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DAYOFWEEK(date)
                ORDER BY day_num
            ");
            $stmt->execute([$days]);
            $analysis['daily_patterns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // أوقات الذروة للتأخير
            $stmt = $this->pdo->prepare("
                SELECT 
                    HOUR(check_in) as hour,
                    COUNT(*) as count
                FROM attendance
                WHERE date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND status = 'late'
                AND check_in IS NOT NULL
                GROUP BY HOUR(check_in)
                ORDER BY count DESC
                LIMIT 5
            ");
            $stmt->execute([$days]);
            $analysis['peak_late_hours'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // الفروع الأكثر إشكالية
            $stmt = $this->pdo->prepare("
                SELECT 
                    b.name as branch_name,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absences,
                    COUNT(*) as total,
                    ROUND(COUNT(CASE WHEN a.status = 'absent' THEN 1 END) * 100.0 / COUNT(*), 1) as absence_rate
                FROM attendance a
                JOIN users u ON a.user_id = u.id
                JOIN branches b ON u.branch_id = b.id
                WHERE a.date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY b.id
                ORDER BY absence_rate DESC
                LIMIT 5
            ");
            $stmt->execute([$days]);
            $analysis['branch_issues'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // اتجاه الحضور (تحسن/تراجع)
            $stmt = $this->pdo->prepare("
                SELECT 
                    WEEK(date) as week_num,
                    ROUND(COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / COUNT(*), 1) as attendance_rate
                FROM attendance
                WHERE date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY WEEK(date)
                ORDER BY week_num
            ");
            $stmt->execute([$days]);
            $weekly = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($weekly) >= 2) {
                $first = $weekly[0]['attendance_rate'];
                $last = end($weekly)['attendance_rate'];
                $analysis['trend'] = [
                    'direction' => $last > $first ? 'improving' : ($last < $first ? 'declining' : 'stable'),
                    'change' => round($last - $first, 1),
                    'weeks' => $weekly
                ];
            }
            
        } catch (Exception $e) {
            // Log error
        }
        
        return $analysis;
    }
    
    /**
     * توصيات لتحسين الحضور
     */
    public function getImprovementSuggestions() {
        $suggestions = [];
        
        try {
            $analysis = $this->analyzeCompanyPatterns(30);
            
            // اقتراحات بناءً على أنماط اليوم
            if (!empty($analysis['daily_patterns'])) {
                $worst_day = null;
                $worst_rate = 100;
                
                foreach ($analysis['daily_patterns'] as $day) {
                    $rate = $day['total'] > 0 ? ($day['present'] / $day['total']) * 100 : 0;
                    if ($rate < $worst_rate) {
                        $worst_rate = $rate;
                        $worst_day = $day;
                    }
                }
                
                if ($worst_day && $worst_rate < 80) {
                    $suggestions[] = [
                        'type' => 'day_pattern',
                        'priority' => 'high',
                        'title' => 'يوم إشكالي',
                        'message' => "يوم {$worst_day['day_name']} لديه أقل معدل حضور (" . round($worst_rate) . "%). فكر في اجتماعات تحفيزية هذا اليوم.",
                        'icon' => 'bi-calendar-x'
                    ];
                }
            }
            
            // اقتراحات بناءً على أوقات التأخير
            if (!empty($analysis['peak_late_hours'])) {
                $peak = $analysis['peak_late_hours'][0];
                $suggestions[] = [
                    'type' => 'late_pattern',
                    'priority' => 'medium',
                    'title' => 'ذروة التأخير',
                    'message' => "معظم التأخيرات تحدث في الساعة {$peak['hour']}:00. فكر في مرونة بداية الدوام.",
                    'icon' => 'bi-clock-history'
                ];
            }
            
            // اقتراحات بناءً على الفروع
            if (!empty($analysis['branch_issues']) && $analysis['branch_issues'][0]['absence_rate'] > 10) {
                $branch = $analysis['branch_issues'][0];
                $suggestions[] = [
                    'type' => 'branch_issue',
                    'priority' => 'high',
                    'title' => 'فرع يحتاج اهتمام',
                    'message' => "فرع {$branch['branch_name']} لديه أعلى نسبة غياب ({$branch['absence_rate']}%). تحقق من المشاكل.",
                    'icon' => 'bi-building-exclamation'
                ];
            }
            
            // اقتراحات بناءً على الاتجاه
            if (!empty($analysis['trend']) && $analysis['trend']['direction'] === 'declining') {
                $suggestions[] = [
                    'type' => 'trend',
                    'priority' => 'high',
                    'title' => 'تراجع في الحضور',
                    'message' => "معدل الحضور تراجع بنسبة {$analysis['trend']['change']}% خلال الشهر. حان وقت التدخل!",
                    'icon' => 'bi-graph-down-arrow'
                ];
            }
            
        } catch (Exception $e) {
            // Log error
        }
        
        // ترتيب حسب الأولوية
        usort($suggestions, function($a, $b) {
            $priority_order = ['high' => 0, 'medium' => 1, 'low' => 2];
            return $priority_order[$a['priority']] - $priority_order[$b['priority']];
        });
        
        return $suggestions;
    }
    
    /**
     * كشف الشذوذ في الحضور
     */
    public function detectAnomalies($user_id = null) {
        $anomalies = [];
        
        try {
            // كشف أنماط غريبة في التسجيل
            $sql = "
                SELECT 
                    u.id,
                    u.name,
                    a.date,
                    a.check_in,
                    a.check_out,
                    TIMESTAMPDIFF(MINUTE, a.check_in, a.check_out) as work_minutes,
                    a.check_in_lat,
                    a.check_in_lng
                FROM attendance a
                JOIN users u ON a.user_id = u.id
                WHERE a.date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ";
            
            if ($user_id) {
                $sql .= " AND u.id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$user_id]);
            } else {
                $stmt = $this->pdo->query($sql);
            }
            
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($records as $record) {
                // ساعات عمل غير طبيعية
                if ($record['work_minutes'] && ($record['work_minutes'] < 60 || $record['work_minutes'] > 720)) {
                    $anomalies[] = [
                        'type' => 'unusual_hours',
                        'user_id' => $record['id'],
                        'name' => $record['name'],
                        'date' => $record['date'],
                        'detail' => 'ساعات عمل غير طبيعية: ' . round($record['work_minutes'] / 60, 1) . ' ساعة',
                        'severity' => 'medium'
                    ];
                }
                
                // تسجيل خارج أوقات العمل الطبيعية
                if ($record['check_in']) {
                    $hour = (int)date('H', strtotime($record['check_in']));
                    if ($hour < 5 || $hour > 22) {
                        $anomalies[] = [
                            'type' => 'odd_time',
                            'user_id' => $record['id'],
                            'name' => $record['name'],
                            'date' => $record['date'],
                            'detail' => 'تسجيل في وقت غير معتاد: ' . date('H:i', strtotime($record['check_in'])),
                            'severity' => 'low'
                        ];
                    }
                }
            }
            
        } catch (Exception $e) {
            // Log error
        }
        
        return $anomalies;
    }
}
