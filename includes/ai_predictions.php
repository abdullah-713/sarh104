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
    
    /**
     * خوارزمية التنبؤ المتقدمة - تحليل السلاسل الزمنية
     */
    public function advancedPrediction($user_id) {
        $prediction = [
            'next_absence_probability' => 0,
            'predicted_day' => null,
            'confidence' => 0,
            'factors' => []
        ];
        
        try {
            // جلب تاريخ الحضور الكامل
            $stmt = $this->pdo->prepare("
                SELECT date, status, DAYOFWEEK(date) as day_num
                FROM attendance 
                WHERE user_id = ? 
                AND date >= DATE_SUB(NOW(), INTERVAL 180 DAY)
                ORDER BY date DESC
            ");
            $stmt->execute([$user_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($history) < 30) {
                return $prediction;
            }
            
            // تحليل أنماط الأيام
            $day_patterns = [];
            foreach ($history as $record) {
                $day = $record['day_num'];
                if (!isset($day_patterns[$day])) {
                    $day_patterns[$day] = ['total' => 0, 'absent' => 0];
                }
                $day_patterns[$day]['total']++;
                if ($record['status'] === 'absent') {
                    $day_patterns[$day]['absent']++;
                }
            }
            
            // أسوأ يوم
            $worst_day = null;
            $worst_rate = 0;
            foreach ($day_patterns as $day => $data) {
                if ($data['total'] > 3) {
                    $rate = $data['absent'] / $data['total'];
                    if ($rate > $worst_rate) {
                        $worst_rate = $rate;
                        $worst_day = $day;
                    }
                }
            }
            
            // تحليل السلاسل - كشف الأنماط التكرارية
            $absence_gaps = [];
            $last_absence = null;
            foreach ($history as $record) {
                if ($record['status'] === 'absent') {
                    if ($last_absence) {
                        $gap = (strtotime($last_absence) - strtotime($record['date'])) / 86400;
                        if ($gap > 0) {
                            $absence_gaps[] = $gap;
                        }
                    }
                    $last_absence = $record['date'];
                }
            }
            
            // متوسط الفجوة بين الغيابات
            $avg_gap = !empty($absence_gaps) ? array_sum($absence_gaps) / count($absence_gaps) : 30;
            $days_since_last = $last_absence ? (time() - strtotime($last_absence)) / 86400 : 0;
            
            // حساب الاحتمالية
            $gap_factor = $avg_gap > 0 ? min(1, $days_since_last / $avg_gap) : 0;
            $pattern_factor = $worst_rate;
            
            // الوزن المرجح
            $probability = ($gap_factor * 0.4 + $pattern_factor * 0.4 + ($worst_rate * 0.2)) * 100;
            
            $prediction = [
                'next_absence_probability' => min(95, round($probability)),
                'predicted_day' => $worst_day,
                'average_gap_days' => round($avg_gap),
                'days_since_last_absence' => round($days_since_last),
                'confidence' => min(90, count($history) / 2),
                'factors' => [
                    'أسوأ يوم' => $this->getDayName($worst_day) . ' (' . round($worst_rate * 100) . '%)',
                    'متوسط الفجوة' => round($avg_gap) . ' يوم',
                    'منذ آخر غياب' => round($days_since_last) . ' يوم'
                ]
            ];
            
        } catch (Exception $e) {
            // Ignore
        }
        
        return $prediction;
    }
    
    /**
     * تحليل موسمي - كشف أنماط الأشهر
     */
    public function seasonalAnalysis($days = 365) {
        $analysis = [];
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    MONTH(date) as month_num,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absences,
                    COUNT(*) as total
                FROM attendance
                WHERE date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY MONTH(date)
                ORDER BY month_num
            ");
            $stmt->execute([$days]);
            $monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $month_names = [
                1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
                5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
                9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
            ];
            
            $worst_month = null;
            $best_month = null;
            $worst_rate = 0;
            $best_rate = 100;
            
            foreach ($monthly as $m) {
                $rate = $m['total'] > 0 ? ($m['absences'] / $m['total']) * 100 : 0;
                $m['absence_rate'] = round($rate, 1);
                $m['month_name'] = $month_names[$m['month_num']] ?? '';
                $analysis['months'][] = $m;
                
                if ($rate > $worst_rate) {
                    $worst_rate = $rate;
                    $worst_month = $m;
                }
                if ($rate < $best_rate && $m['total'] > 10) {
                    $best_rate = $rate;
                    $best_month = $m;
                }
            }
            
            $analysis['worst_month'] = $worst_month;
            $analysis['best_month'] = $best_month;
            
            // الشهر الحالي مقابل المعدل
            $current_month = date('n');
            $analysis['current_month_risk'] = 'normal';
            if ($worst_month && $worst_month['month_num'] == $current_month) {
                $analysis['current_month_risk'] = 'high';
            }
            
        } catch (Exception $e) {
            // Ignore
        }
        
        return $analysis;
    }
    
    /**
     * تحليل الارتباط - العلاقة بين العوامل
     */
    public function correlationAnalysis() {
        $correlations = [];
        
        try {
            // علاقة المسافة بالتأخير
            $stmt = $this->pdo->query("
                SELECT 
                    u.id,
                    u.name,
                    b.latitude as branch_lat,
                    b.longitude as branch_lng,
                    COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                    COUNT(*) as total
                FROM users u
                JOIN branches b ON u.branch_id = b.id
                LEFT JOIN attendance a ON u.id = a.user_id 
                    AND a.date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                WHERE u.is_active = 1
                GROUP BY u.id
                HAVING total > 20
            ");
            $distance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // علاقة الأقسام بالغياب
            $stmt = $this->pdo->query("
                SELECT 
                    d.name as department,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absences,
                    COUNT(*) as total,
                    ROUND(COUNT(CASE WHEN a.status = 'absent' THEN 1 END) * 100.0 / COUNT(*), 1) as rate
                FROM attendance a
                JOIN users u ON a.user_id = u.id
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE a.date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY d.id
                ORDER BY rate DESC
            ");
            $correlations['by_department'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // علاقة مدة الخدمة بالالتزام
            $stmt = $this->pdo->query("
                SELECT 
                    CASE 
                        WHEN DATEDIFF(NOW(), u.created_at) < 90 THEN 'جديد (أقل من 3 أشهر)'
                        WHEN DATEDIFF(NOW(), u.created_at) < 365 THEN 'متوسط (3-12 شهر)'
                        ELSE 'قديم (أكثر من سنة)'
                    END as tenure_group,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present,
                    COUNT(*) as total,
                    ROUND(COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*), 1) as rate
                FROM users u
                LEFT JOIN attendance a ON u.id = a.user_id 
                    AND a.date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                WHERE u.is_active = 1
                GROUP BY tenure_group
            ");
            $correlations['by_tenure'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            // Ignore
        }
        
        return $correlations;
    }
    
    /**
     * توليد تقرير AI شامل
     */
    public function generateAIReport() {
        return [
            'predictions' => $this->predictAbsences(7),
            'suggestions' => $this->getImprovementSuggestions(),
            'patterns' => $this->analyzeCompanyPatterns(30),
            'seasonal' => $this->seasonalAnalysis(365),
            'correlations' => $this->correlationAnalysis(),
            'anomalies' => $this->detectAnomalies(),
            'generated_at' => date('Y-m-d H:i:s'),
            'model_version' => '2.0'
        ];
    }
    
    /**
     * تحويل رقم اليوم لاسم
     */
    private function getDayName($day_num) {
        $days = [
            1 => 'الأحد', 2 => 'الاثنين', 3 => 'الثلاثاء',
            4 => 'الأربعاء', 5 => 'الخميس', 6 => 'الجمعة', 7 => 'السبت'
        ];
        return $days[$day_num] ?? 'غير معروف';
    }
}
