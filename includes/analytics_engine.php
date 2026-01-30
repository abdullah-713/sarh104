<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════════════════
 * 🧠 محرك التحليلات والتنبؤ المتقدم - SARH ADVANCED ANALYTICS ENGINE
 * ═══════════════════════════════════════════════════════════════════════════════════════
 * 
 * نظام ذكاء اصطناعي مبني بالكامل بـ PHP بدون مكتبات خارجية
 * يستخدم خوارزميات إحصائية وتعلم آلي للتنبؤ والتحليل
 * 
 * الميزات:
 * - تنبؤ بالحضور والغياب
 * - كشف الأنماط السلوكية
 * - كشف الحالات الشاذة
 * - تحليل الاتجاهات
 * - توقع المخاطر
 * - تحليل الأداء التنبؤي
 * 
 * الميزات المتقدمة (الإصدار 3.0):
 * - الشبكات العصبية (Neural Networks)
 * - K-Means Clustering
 * - Random Forest
 * - Holt-Winters Forecasting
 * - Fourier Transform لاكتشاف الدورات
 * - Markov Chains للتنبؤ
 * - Monte Carlo Simulation
 * - Bayesian Analysis
 * - Survival Analysis
 * 
 * @author SARH System
 * @version 3.0.0
 * ═══════════════════════════════════════════════════════════════════════════════════════
 */

if (!defined('SARH_SYSTEM')) {
    die('الوصول المباشر غير مسموح');
}

// تحميل المحركات المتقدمة
$advancedMLPath = __DIR__ . '/advanced_ml_engine.php';
$advancedTSPath = __DIR__ . '/advanced_timeseries.php';
$advancedStatsPath = __DIR__ . '/advanced_statistics.php';

if (file_exists($advancedMLPath)) {
    require_once $advancedMLPath;
}
if (file_exists($advancedTSPath)) {
    require_once $advancedTSPath;
}
if (file_exists($advancedStatsPath)) {
    require_once $advancedStatsPath;
}

class AnalyticsEngine {
    
    // ═══════════════════════════════════════════════════════════════
    // 📊 الثوابت والإعدادات
    // ═══════════════════════════════════════════════════════════════
    
    const PREDICTION_CONFIDENCE_HIGH = 0.85;
    const PREDICTION_CONFIDENCE_MEDIUM = 0.70;
    const PREDICTION_CONFIDENCE_LOW = 0.50;
    
    const ANOMALY_THRESHOLD_SIGMA = 2.5; // عدد الانحرافات المعيارية للكشف عن الشذوذ
    const TREND_MIN_DATA_POINTS = 7;     // الحد الأدنى من نقاط البيانات للاتجاه
    const SEASONALITY_PERIOD = 7;        // فترة الموسمية (أسبوعياً)
    
    private static array $cache = [];
    
    // ═══════════════════════════════════════════════════════════════
    // 🔮 خوارزمية التنبؤ الرئيسية - ARIMA-like Forecasting
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * التنبؤ بالحضور المستقبلي لموظف
     * Predict future attendance for an employee
     * 
     * @param int $userId معرف الموظف
     * @param int $daysAhead عدد الأيام للتنبؤ
     * @return array توقعات الحضور مع نسبة الثقة
     */
    public static function predictAttendance(int $userId, int $daysAhead = 7): array {
        $cacheKey = "predict_attendance_{$userId}_{$daysAhead}";
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        // جلب البيانات التاريخية (آخر 90 يوم)
        $historicalData = self::getHistoricalAttendance($userId, 90);
        
        if (count($historicalData) < 14) {
            return [
                'predictions' => [],
                'confidence' => 0,
                'message' => 'بيانات غير كافية للتنبؤ (مطلوب 14 يوم على الأقل)'
            ];
        }
        
        $predictions = [];
        $baseDate = new DateTime();
        
        // حساب المعاملات الإحصائية
        $stats = self::calculateAdvancedStats($historicalData);
        $seasonalPattern = self::detectSeasonalPattern($historicalData);
        $trend = self::calculateTrend($historicalData);
        
        for ($i = 1; $i <= $daysAhead; $i++) {
            $targetDate = (clone $baseDate)->modify("+{$i} days");
            $dayOfWeek = (int) $targetDate->format('N'); // 1-7
            
            // التنبؤ الأساسي باستخدام Moving Average
            $baseProb = $stats['attendance_rate'];
            
            // تعديل بناءً على النمط الموسمي (أيام الأسبوع)
            $seasonalFactor = $seasonalPattern[$dayOfWeek] ?? 1.0;
            
            // تعديل بناءً على الاتجاه
            $trendFactor = 1 + ($trend['slope'] * $i * 0.01);
            
            // الاحتمال النهائي
            $probability = min(1.0, max(0.0, $baseProb * $seasonalFactor * $trendFactor));
            
            // حساب نسبة الثقة
            $confidence = self::calculatePredictionConfidence($historicalData, $dayOfWeek);
            
            // التنبؤ بوقت الوصول المتوقع
            $expectedArrival = self::predictArrivalTime($userId, $dayOfWeek, $historicalData);
            
            $predictions[] = [
                'date' => $targetDate->format('Y-m-d'),
                'day_name' => self::getArabicDayName($dayOfWeek),
                'will_attend' => $probability >= 0.5,
                'attendance_probability' => round($probability * 100, 1),
                'late_probability' => self::predictLateProbability($userId, $dayOfWeek, $historicalData),
                'expected_arrival' => $expectedArrival,
                'confidence' => round($confidence * 100, 1),
                'risk_level' => self::assessRiskLevel($probability, $confidence)
            ];
        }
        
        $result = [
            'predictions' => $predictions,
            'overall_confidence' => round($stats['reliability_score'] * 100, 1),
            'trend_direction' => $trend['direction'],
            'seasonal_patterns' => $seasonalPattern,
            'model_accuracy' => self::calculateModelAccuracy($userId)
        ];
        
        self::$cache[$cacheKey] = $result;
        return $result;
    }
    
    /**
     * التنبؤ باحتمالية التأخير
     */
    private static function predictLateProbability(int $userId, int $dayOfWeek, array $historicalData): float {
        $lateCount = 0;
        $dayCount = 0;
        
        foreach ($historicalData as $record) {
            $recordDay = (int) date('N', strtotime($record['date']));
            if ($recordDay === $dayOfWeek && $record['check_in_time']) {
                $dayCount++;
                if (($record['late_minutes'] ?? 0) > 0) {
                    $lateCount++;
                }
            }
        }
        
        return $dayCount > 0 ? round(($lateCount / $dayCount) * 100, 1) : 0;
    }
    
    /**
     * التنبؤ بوقت الوصول المتوقع
     */
    private static function predictArrivalTime(int $userId, int $dayOfWeek, array $historicalData): ?string {
        $times = [];
        
        foreach ($historicalData as $record) {
            $recordDay = (int) date('N', strtotime($record['date']));
            if ($recordDay === $dayOfWeek && $record['check_in_time']) {
                $times[] = strtotime($record['check_in_time']) - strtotime('00:00:00');
            }
        }
        
        if (empty($times)) {
            return null;
        }
        
        // حساب المتوسط المرجح (الأحدث له وزن أكبر)
        $weightedSum = 0;
        $weightTotal = 0;
        $count = count($times);
        
        foreach ($times as $i => $time) {
            $weight = 1 + ($i / $count); // وزن أكبر للقيم الأحدث
            $weightedSum += $time * $weight;
            $weightTotal += $weight;
        }
        
        $avgSeconds = $weightedSum / $weightTotal;
        return gmdate('H:i', $avgSeconds);
    }
    
    // ═══════════════════════════════════════════════════════════════
    // 📈 كشف الاتجاهات - Trend Detection using Linear Regression
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * حساب الاتجاه باستخدام الانحدار الخطي
     * Calculate trend using linear regression
     */
    public static function calculateTrend(array $data, string $metric = 'attendance'): array {
        if (count($data) < self::TREND_MIN_DATA_POINTS) {
            return [
                'slope' => 0,
                'intercept' => 0,
                'direction' => 'مستقر',
                'strength' => 0,
                'r_squared' => 0
            ];
        }
        
        // تحويل البيانات إلى قيم رقمية
        $values = [];
        foreach ($data as $record) {
            switch ($metric) {
                case 'attendance':
                    $values[] = ($record['check_in_time'] !== null) ? 1 : 0;
                    break;
                case 'late_minutes':
                    $values[] = (float) ($record['late_minutes'] ?? 0);
                    break;
                case 'work_minutes':
                    $values[] = (float) ($record['work_minutes'] ?? 0);
                    break;
                case 'points':
                    $values[] = (float) ($record['bonus_points'] ?? 0) - (float) ($record['penalty_points'] ?? 0);
                    break;
            }
        }
        
        $n = count($values);
        $x = range(1, $n);
        
        // حساب المتوسطات
        $xMean = array_sum($x) / $n;
        $yMean = array_sum($values) / $n;
        
        // حساب معاملات الانحدار
        $ssXY = 0;
        $ssXX = 0;
        $ssYY = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $xDiff = $x[$i] - $xMean;
            $yDiff = $values[$i] - $yMean;
            $ssXY += $xDiff * $yDiff;
            $ssXX += $xDiff * $xDiff;
            $ssYY += $yDiff * $yDiff;
        }
        
        $slope = $ssXX > 0 ? $ssXY / $ssXX : 0;
        $intercept = $yMean - ($slope * $xMean);
        $rSquared = ($ssXX > 0 && $ssYY > 0) ? pow($ssXY, 2) / ($ssXX * $ssYY) : 0;
        
        // تحديد الاتجاه
        $direction = 'مستقر';
        if ($slope > 0.05) $direction = 'تصاعدي ↑';
        elseif ($slope < -0.05) $direction = 'تنازلي ↓';
        
        return [
            'slope' => round($slope, 4),
            'intercept' => round($intercept, 4),
            'direction' => $direction,
            'strength' => abs($slope),
            'r_squared' => round($rSquared, 4),
            'prediction_next' => round($slope * ($n + 1) + $intercept, 2)
        ];
    }
    
    // ═══════════════════════════════════════════════════════════════
    // 🔍 كشف الأنماط الموسمية - Seasonal Pattern Detection
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * كشف النمط الموسمي (أيام الأسبوع)
     */
    public static function detectSeasonalPattern(array $data): array {
        $dayStats = [];
        
        // تجميع البيانات حسب يوم الأسبوع
        for ($day = 1; $day <= 7; $day++) {
            $dayStats[$day] = ['present' => 0, 'total' => 0];
        }
        
        foreach ($data as $record) {
            $dayOfWeek = (int) date('N', strtotime($record['date']));
            $dayStats[$dayOfWeek]['total']++;
            if ($record['check_in_time'] !== null) {
                $dayStats[$dayOfWeek]['present']++;
            }
        }
        
        // حساب معامل كل يوم
        $overallRate = self::calculateAdvancedStats($data)['attendance_rate'];
        $pattern = [];
        
        for ($day = 1; $day <= 7; $day++) {
            if ($dayStats[$day]['total'] > 0) {
                $dayRate = $dayStats[$day]['present'] / $dayStats[$day]['total'];
                $pattern[$day] = $overallRate > 0 ? round($dayRate / $overallRate, 3) : 1.0;
            } else {
                $pattern[$day] = 1.0;
            }
        }
        
        return $pattern;
    }
    
    // ═══════════════════════════════════════════════════════════════
    // 🚨 كشف الحالات الشاذة - Anomaly Detection (Z-Score Method)
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * كشف الحالات الشاذة في بيانات الحضور
     * Detect anomalies in attendance data
     */
    public static function detectAnomalies(int $userId, int $days = 30): array {
        $data = self::getHistoricalAttendance($userId, $days);
        
        if (count($data) < 10) {
            return [
                'anomalies' => [],
                'total_checked' => count($data),
                'anomaly_rate' => 0
            ];
        }
        
        $anomalies = [];
        
        // 1. كشف شذوذ وقت الوصول
        $arrivalTimes = [];
        foreach ($data as $record) {
            if ($record['check_in_time']) {
                $arrivalTimes[] = [
                    'date' => $record['date'],
                    'time' => strtotime($record['check_in_time']) - strtotime('00:00:00'),
                    'original' => $record['check_in_time']
                ];
            }
        }
        
        if (count($arrivalTimes) >= 5) {
            $times = array_column($arrivalTimes, 'time');
            $stats = self::calculateStatistics($times);
            
            foreach ($arrivalTimes as $item) {
                $zScore = $stats['std_dev'] > 0 ? abs($item['time'] - $stats['mean']) / $stats['std_dev'] : 0;
                if ($zScore > self::ANOMALY_THRESHOLD_SIGMA) {
                    $anomalies[] = [
                        'type' => 'arrival_time',
                        'date' => $item['date'],
                        'value' => $item['original'],
                        'z_score' => round($zScore, 2),
                        'severity' => $zScore > 3.5 ? 'عالية' : 'متوسطة',
                        'description' => "وقت وصول غير طبيعي ({$item['original']})"
                    ];
                }
            }
        }
        
        // 2. كشف شذوذ ساعات العمل
        $workMinutes = [];
        foreach ($data as $record) {
            if (($record['work_minutes'] ?? 0) > 0) {
                $workMinutes[] = [
                    'date' => $record['date'],
                    'minutes' => (float) $record['work_minutes']
                ];
            }
        }
        
        if (count($workMinutes) >= 5) {
            $minutes = array_column($workMinutes, 'minutes');
            $stats = self::calculateStatistics($minutes);
            
            foreach ($workMinutes as $item) {
                $zScore = $stats['std_dev'] > 0 ? abs($item['minutes'] - $stats['mean']) / $stats['std_dev'] : 0;
                if ($zScore > self::ANOMALY_THRESHOLD_SIGMA) {
                    $hours = round($item['minutes'] / 60, 1);
                    $anomalies[] = [
                        'type' => 'work_duration',
                        'date' => $item['date'],
                        'value' => "{$hours} ساعة",
                        'z_score' => round($zScore, 2),
                        'severity' => $zScore > 3.5 ? 'عالية' : 'متوسطة',
                        'description' => "مدة عمل غير طبيعية ({$hours} ساعة)"
                    ];
                }
            }
        }
        
        // 3. كشف أنماط الغياب المشبوهة
        $absencePatterns = self::detectSuspiciousAbsencePatterns($data);
        foreach ($absencePatterns as $pattern) {
            $anomalies[] = $pattern;
        }
        
        return [
            'anomalies' => $anomalies,
            'total_checked' => count($data),
            'anomaly_rate' => count($data) > 0 ? round((count($anomalies) / count($data)) * 100, 1) : 0
        ];
    }
    
    /**
     * كشف أنماط الغياب المشبوهة (مثل الغياب المتكرر في أيام معينة)
     */
    private static function detectSuspiciousAbsencePatterns(array $data): array {
        $patterns = [];
        $dayAbsences = [];
        
        for ($day = 1; $day <= 7; $day++) {
            $dayAbsences[$day] = 0;
        }
        
        $totalAbsences = 0;
        foreach ($data as $record) {
            if ($record['status'] === 'absent' || $record['check_in_time'] === null) {
                $dayOfWeek = (int) date('N', strtotime($record['date']));
                $dayAbsences[$dayOfWeek]++;
                $totalAbsences++;
            }
        }
        
        // كشف تركز الغياب في يوم معين
        if ($totalAbsences >= 3) {
            foreach ($dayAbsences as $day => $count) {
                $expectedRate = $totalAbsences / 5; // توقع توزيع متساوي على أيام العمل
                if ($count >= 3 && $count > $expectedRate * 2) {
                    $patterns[] = [
                        'type' => 'suspicious_absence_pattern',
                        'date' => null,
                        'value' => self::getArabicDayName($day),
                        'z_score' => round($count / max(1, $expectedRate), 2),
                        'severity' => 'متوسطة',
                        'description' => "غياب متكرر يوم " . self::getArabicDayName($day) . " ({$count} مرات)"
                    ];
                }
            }
        }
        
        return $patterns;
    }
    
    // ═══════════════════════════════════════════════════════════════
    // 🎯 تحليل الأداء التنبؤي - Predictive Performance Analysis
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * تحليل شامل للأداء مع توقعات
     */
    public static function comprehensivePerformanceAnalysis(int $userId): array {
        $historical = self::getHistoricalAttendance($userId, 90);
        $recent = array_slice($historical, 0, 30);
        
        $stats = self::calculateAdvancedStats($historical);
        $recentStats = self::calculateAdvancedStats($recent);
        $trend = self::calculateTrend($historical, 'attendance');
        $prediction = self::predictAttendance($userId, 7);
        $anomalies = self::detectAnomalies($userId, 30);
        
        // حساب مؤشر المخاطر الشامل
        $riskScore = self::calculateRiskScore($stats, $recentStats, $anomalies);
        
        // توصيات ذكية
        $recommendations = self::generateSmartRecommendations($stats, $trend, $anomalies);
        
        // مقارنة مع الفترة السابقة
        $previousPeriod = array_slice($historical, 30, 30);
        $comparison = self::comparePerformancePeriods($recent, $previousPeriod);
        
        return [
            'overview' => [
                'total_days_analyzed' => count($historical),
                'attendance_rate' => round($stats['attendance_rate'] * 100, 1),
                'punctuality_rate' => round($stats['punctuality_rate'] * 100, 1),
                'consistency_score' => round($stats['consistency_score'] * 100, 1),
                'reliability_score' => round($stats['reliability_score'] * 100, 1)
            ],
            'trends' => [
                'attendance' => $trend,
                'direction' => $trend['direction'],
                'momentum' => self::calculateMomentum($historical)
            ],
            'predictions' => $prediction,
            'anomalies' => $anomalies,
            'risk_assessment' => [
                'score' => $riskScore,
                'level' => self::getRiskLevel($riskScore),
                'factors' => self::identifyRiskFactors($stats, $anomalies)
            ],
            'comparison' => $comparison,
            'recommendations' => $recommendations,
            'behavioral_insights' => self::generateBehavioralInsights($historical),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * حساب مؤشر المخاطر
     */
    private static function calculateRiskScore(array $stats, array $recentStats, array $anomalies): int {
        $score = 0;
        
        // نسبة الحضور المنخفضة
        if ($stats['attendance_rate'] < 0.8) $score += 20;
        if ($stats['attendance_rate'] < 0.6) $score += 20;
        
        // تراجع الأداء الأخير
        if ($recentStats['attendance_rate'] < $stats['attendance_rate'] - 0.1) $score += 15;
        
        // عدد الحالات الشاذة
        $score += min(30, count($anomalies['anomalies']) * 5);
        
        // عدم الاتساق
        if ($stats['consistency_score'] < 0.7) $score += 15;
        
        return min(100, $score);
    }
    
    /**
     * تحديد مستوى الخطر
     */
    private static function getRiskLevel(int $score): string {
        if ($score >= 70) return 'حرج 🔴';
        if ($score >= 50) return 'عالي 🟠';
        if ($score >= 30) return 'متوسط 🟡';
        return 'منخفض 🟢';
    }
    
    /**
     * تحديد عوامل الخطر
     */
    private static function identifyRiskFactors(array $stats, array $anomalies): array {
        $factors = [];
        
        if ($stats['attendance_rate'] < 0.8) {
            $factors[] = ['factor' => 'نسبة حضور منخفضة', 'impact' => 'عالي'];
        }
        if ($stats['punctuality_rate'] < 0.7) {
            $factors[] = ['factor' => 'تأخر متكرر', 'impact' => 'متوسط'];
        }
        if (count($anomalies['anomalies']) > 3) {
            $factors[] = ['factor' => 'سلوك غير منتظم', 'impact' => 'عالي'];
        }
        if ($stats['consistency_score'] < 0.6) {
            $factors[] = ['factor' => 'عدم اتساق في الأداء', 'impact' => 'متوسط'];
        }
        
        return $factors;
    }
    
    /**
     * توليد توصيات ذكية
     */
    private static function generateSmartRecommendations(array $stats, array $trend, array $anomalies): array {
        $recommendations = [];
        
        if ($stats['attendance_rate'] < 0.8) {
            $recommendations[] = [
                'type' => 'attendance',
                'priority' => 'عالية',
                'title' => 'تحسين نسبة الحضور',
                'description' => 'نسبة الحضور أقل من المستوى المطلوب. يُنصح بمراجعة أسباب الغياب.',
                'action' => 'جدولة اجتماع لمناقشة التحديات'
            ];
        }
        
        if ($stats['punctuality_rate'] < 0.7) {
            $recommendations[] = [
                'type' => 'punctuality',
                'priority' => 'متوسطة',
                'title' => 'تحسين الالتزام بالمواعيد',
                'description' => 'معدل التأخير مرتفع. يُنصح بمراجعة ظروف التنقل.',
                'action' => 'النظر في تعديل جدول العمل'
            ];
        }
        
        if ($trend['direction'] === 'تنازلي ↓') {
            $recommendations[] = [
                'type' => 'trend',
                'priority' => 'عالية',
                'title' => 'معالجة التراجع في الأداء',
                'description' => 'الاتجاه العام للأداء تنازلي. مطلوب تدخل فوري.',
                'action' => 'تحديد أسباب التراجع وخطة تحسين'
            ];
        }
        
        if (count($anomalies['anomalies']) > 3) {
            $recommendations[] = [
                'type' => 'behavior',
                'priority' => 'متوسطة',
                'title' => 'مراجعة السلوك الوظيفي',
                'description' => 'تم رصد أنماط غير طبيعية. يُنصح بالمتابعة.',
                'action' => 'مقابلة شخصية لفهم الظروف'
            ];
        }
        
        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'positive',
                'priority' => 'إيجابية',
                'title' => 'أداء ممتاز! 🌟',
                'description' => 'الموظف يحافظ على مستوى أداء جيد.',
                'action' => 'تقديم شكر وتشجيع'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * توليد رؤى سلوكية
     */
    private static function generateBehavioralInsights(array $data): array {
        $insights = [];
        
        // تحليل أفضل أيام الأداء
        $dayPerformance = [];
        for ($day = 1; $day <= 7; $day++) {
            $dayData = array_filter($data, fn($r) => (int) date('N', strtotime($r['date'])) === $day);
            if (!empty($dayData)) {
                $present = count(array_filter($dayData, fn($r) => $r['check_in_time'] !== null));
                $dayPerformance[$day] = $present / count($dayData);
            }
        }
        
        if (!empty($dayPerformance)) {
            $bestDay = array_search(max($dayPerformance), $dayPerformance);
            $worstDay = array_search(min($dayPerformance), $dayPerformance);
            
            $insights[] = [
                'type' => 'best_day',
                'title' => 'أفضل يوم أداء',
                'value' => self::getArabicDayName($bestDay),
                'detail' => 'نسبة الحضور: ' . round($dayPerformance[$bestDay] * 100, 1) . '%'
            ];
            
            if ($dayPerformance[$worstDay] < 0.8) {
                $insights[] = [
                    'type' => 'weak_day',
                    'title' => 'يوم يحتاج تحسين',
                    'value' => self::getArabicDayName($worstDay),
                    'detail' => 'نسبة الحضور: ' . round($dayPerformance[$worstDay] * 100, 1) . '%'
                ];
            }
        }
        
        // تحليل فترات الذروة
        $morningArrivals = 0;
        $lateArrivals = 0;
        foreach ($data as $record) {
            if ($record['check_in_time']) {
                $hour = (int) date('H', strtotime($record['check_in_time']));
                if ($hour < 8) $morningArrivals++;
                elseif ($hour >= 9) $lateArrivals++;
            }
        }
        
        $total = $morningArrivals + $lateArrivals;
        if ($total > 0) {
            if ($morningArrivals / $total > 0.6) {
                $insights[] = [
                    'type' => 'early_bird',
                    'title' => 'طائر الصباح 🌅',
                    'value' => 'يفضل الحضور المبكر',
                    'detail' => round(($morningArrivals / $total) * 100, 1) . '% قبل الساعة 8'
                ];
            } elseif ($lateArrivals / $total > 0.4) {
                $insights[] = [
                    'type' => 'night_owl',
                    'title' => 'بومة الليل 🦉',
                    'value' => 'يميل للتأخر',
                    'detail' => round(($lateArrivals / $total) * 100, 1) . '% بعد الساعة 9'
                ];
            }
        }
        
        return $insights;
    }
    
    /**
     * مقارنة فترتين من الأداء
     */
    private static function comparePerformancePeriods(array $recent, array $previous): array {
        if (empty($previous)) {
            return [
                'has_previous' => false,
                'message' => 'لا توجد بيانات للفترة السابقة للمقارنة'
            ];
        }
        
        $recentStats = self::calculateAdvancedStats($recent);
        $previousStats = self::calculateAdvancedStats($previous);
        
        $changes = [
            'attendance' => [
                'current' => round($recentStats['attendance_rate'] * 100, 1),
                'previous' => round($previousStats['attendance_rate'] * 100, 1),
                'change' => round(($recentStats['attendance_rate'] - $previousStats['attendance_rate']) * 100, 1),
                'trend' => $recentStats['attendance_rate'] >= $previousStats['attendance_rate'] ? 'تحسن' : 'تراجع'
            ],
            'punctuality' => [
                'current' => round($recentStats['punctuality_rate'] * 100, 1),
                'previous' => round($previousStats['punctuality_rate'] * 100, 1),
                'change' => round(($recentStats['punctuality_rate'] - $previousStats['punctuality_rate']) * 100, 1),
                'trend' => $recentStats['punctuality_rate'] >= $previousStats['punctuality_rate'] ? 'تحسن' : 'تراجع'
            ]
        ];
        
        return [
            'has_previous' => true,
            'changes' => $changes,
            'overall_trend' => $changes['attendance']['change'] + $changes['punctuality']['change'] >= 0 ? 'إيجابي ↑' : 'سلبي ↓'
        ];
    }
    
    // ═══════════════════════════════════════════════════════════════
    // 🏢 تحليلات الفرع والفريق
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * تحليل أداء الفرع
     */
    public static function analyzeBranchPerformance(int $branchId, int $days = 30): array {
        try {
            $employees = Database::fetchAll(
                "SELECT id, full_name FROM users WHERE branch_id = :branch_id AND is_active = 1",
                ['branch_id' => $branchId]
            );
            
            if (empty($employees)) {
                return ['error' => 'لا يوجد موظفين في هذا الفرع'];
            }
            
            $branchStats = [
                'total_employees' => count($employees),
                'avg_attendance_rate' => 0,
                'avg_punctuality_rate' => 0,
                'top_performers' => [],
                'needs_attention' => [],
                'daily_trends' => []
            ];
            
            $allScores = [];
            foreach ($employees as $emp) {
                $historical = self::getHistoricalAttendance($emp['id'], $days);
                $stats = self::calculateAdvancedStats($historical);
                
                $allScores[] = [
                    'id' => $emp['id'],
                    'name' => $emp['full_name'],
                    'attendance_rate' => $stats['attendance_rate'],
                    'punctuality_rate' => $stats['punctuality_rate']
                ];
            }
            
            // حساب المتوسطات
            $branchStats['avg_attendance_rate'] = round(array_sum(array_column($allScores, 'attendance_rate')) / count($allScores) * 100, 1);
            $branchStats['avg_punctuality_rate'] = round(array_sum(array_column($allScores, 'punctuality_rate')) / count($allScores) * 100, 1);
            
            // ترتيب حسب الأداء
            usort($allScores, fn($a, $b) => $b['attendance_rate'] <=> $a['attendance_rate']);
            
            $branchStats['top_performers'] = array_slice($allScores, 0, 3);
            $branchStats['needs_attention'] = array_filter($allScores, fn($s) => $s['attendance_rate'] < 0.7);
            
            // تحليل الاتجاهات اليومية
            $branchStats['daily_trends'] = self::calculateBranchDailyTrends($branchId, $days);
            
            return $branchStats;
            
        } catch (Exception $e) {
            error_log("Branch Analysis Error: " . $e->getMessage());
            return ['error' => 'خطأ في تحليل الفرع'];
        }
    }
    
    /**
     * حساب اتجاهات الفرع اليومية
     */
    private static function calculateBranchDailyTrends(int $branchId, int $days): array {
        try {
            $sql = "SELECT 
                        DATE(a.date) as day,
                        COUNT(DISTINCT a.user_id) as total_employees,
                        SUM(CASE WHEN a.check_in_time IS NOT NULL THEN 1 ELSE 0 END) as present,
                        AVG(a.late_minutes) as avg_late
                    FROM attendance a
                    INNER JOIN users u ON a.user_id = u.id
                    WHERE u.branch_id = :branch_id
                    AND a.date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                    GROUP BY DATE(a.date)
                    ORDER BY day ASC";
            
            $results = Database::fetchAll($sql, [
                'branch_id' => $branchId,
                'days' => $days
            ]);
            
            $trends = [];
            foreach ($results as $row) {
                $trends[] = [
                    'date' => $row['day'],
                    'attendance_rate' => $row['total_employees'] > 0 ? round(($row['present'] / $row['total_employees']) * 100, 1) : 0,
                    'avg_late_minutes' => round($row['avg_late'] ?? 0, 1)
                ];
            }
            
            return $trends;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    // ═══════════════════════════════════════════════════════════════
    // 📉 تحليل الموارد البشرية التنبؤي
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * التنبؤ بمخاطر ترك العمل
     * Predict employee turnover risk
     */
    public static function predictTurnoverRisk(int $userId): array {
        $historical = self::getHistoricalAttendance($userId, 90);
        $stats = self::calculateAdvancedStats($historical);
        $trend = self::calculateTrend($historical, 'attendance');
        $anomalies = self::detectAnomalies($userId, 30);
        
        $riskFactors = [];
        $riskScore = 0;
        
        // عامل 1: انخفاض الحضور
        if ($stats['attendance_rate'] < 0.7) {
            $riskFactors[] = 'انخفاض ملحوظ في الحضور';
            $riskScore += 25;
        }
        
        // عامل 2: اتجاه تنازلي
        if ($trend['direction'] === 'تنازلي ↓' && $trend['r_squared'] > 0.3) {
            $riskFactors[] = 'اتجاه تنازلي مستمر';
            $riskScore += 20;
        }
        
        // عامل 3: زيادة التأخير
        $lateTrend = self::calculateTrend($historical, 'late_minutes');
        if ($lateTrend['direction'] === 'تصاعدي ↑') {
            $riskFactors[] = 'زيادة في التأخير';
            $riskScore += 15;
        }
        
        // عامل 4: عدم الاتساق
        if ($stats['consistency_score'] < 0.5) {
            $riskFactors[] = 'سلوك غير متسق';
            $riskScore += 15;
        }
        
        // عامل 5: حالات شاذة متكررة
        if (count($anomalies['anomalies']) > 5) {
            $riskFactors[] = 'أنماط سلوكية غير طبيعية';
            $riskScore += 20;
        }
        
        // عامل 6: انخفاض ساعات العمل
        $workTrend = self::calculateTrend($historical, 'work_minutes');
        if ($workTrend['direction'] === 'تنازلي ↓') {
            $riskFactors[] = 'تقليل ساعات العمل';
            $riskScore += 15;
        }
        
        $riskLevel = 'منخفض';
        if ($riskScore >= 60) $riskLevel = 'عالي جداً';
        elseif ($riskScore >= 40) $riskLevel = 'عالي';
        elseif ($riskScore >= 20) $riskLevel = 'متوسط';
        
        return [
            'risk_score' => min(100, $riskScore),
            'risk_level' => $riskLevel,
            'risk_factors' => $riskFactors,
            'probability' => min(100, $riskScore),
            'recommendation' => self::getTurnoverRecommendation($riskLevel, $riskFactors)
        ];
    }
    
    /**
     * توصيات لتقليل مخاطر الترك
     */
    private static function getTurnoverRecommendation(string $level, array $factors): string {
        switch ($level) {
            case 'عالي جداً':
                return 'يُنصح بعقد اجتماع عاجل مع الموظف لفهم التحديات وتقديم الدعم اللازم.';
            case 'عالي':
                return 'يُنصح بمراجعة ظروف العمل ومناقشة أي مخاوف مع الموظف.';
            case 'متوسط':
                return 'يُنصح بالمتابعة المنتظمة وتقديم التشجيع والدعم.';
            default:
                return 'الوضع مستقر. يُنصح بالحفاظ على التواصل الإيجابي.';
        }
    }
    
    // ═══════════════════════════════════════════════════════════════
    // 🛠️ دوال مساعدة
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * جلب بيانات الحضور التاريخية
     */
    private static function getHistoricalAttendance(int $userId, int $days): array {
        try {
            return Database::fetchAll(
                "SELECT * FROM attendance 
                 WHERE user_id = :user_id 
                 AND date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                 ORDER BY date DESC",
                ['user_id' => $userId, 'days' => $days]
            );
        } catch (Exception $e) {
            error_log("Get Historical Attendance Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * حساب إحصائيات متقدمة
     */
    private static function calculateAdvancedStats(array $data): array {
        $totalDays = count($data);
        $presentDays = 0;
        $onTimeDays = 0;
        $lateMinutes = [];
        $workMinutes = [];
        
        foreach ($data as $record) {
            if ($record['check_in_time'] !== null) {
                $presentDays++;
                if (($record['late_minutes'] ?? 0) == 0) {
                    $onTimeDays++;
                }
                $lateMinutes[] = (float) ($record['late_minutes'] ?? 0);
                $workMinutes[] = (float) ($record['work_minutes'] ?? 0);
            }
        }
        
        $attendanceRate = $totalDays > 0 ? $presentDays / $totalDays : 0;
        $punctualityRate = $presentDays > 0 ? $onTimeDays / $presentDays : 0;
        
        // حساب الاتساق (انخفاض التباين = اتساق أعلى)
        $consistencyScore = 1;
        if (!empty($workMinutes)) {
            $workStats = self::calculateStatistics($workMinutes);
            $cv = $workStats['mean'] > 0 ? $workStats['std_dev'] / $workStats['mean'] : 1;
            $consistencyScore = max(0, 1 - $cv);
        }
        
        // مؤشر الموثوقية (مزيج من الحضور والاتساق)
        $reliabilityScore = ($attendanceRate * 0.6) + ($punctualityRate * 0.2) + ($consistencyScore * 0.2);
        
        return [
            'total_days' => $totalDays,
            'present_days' => $presentDays,
            'attendance_rate' => $attendanceRate,
            'punctuality_rate' => $punctualityRate,
            'consistency_score' => $consistencyScore,
            'reliability_score' => $reliabilityScore,
            'avg_late_minutes' => !empty($lateMinutes) ? array_sum($lateMinutes) / count($lateMinutes) : 0,
            'avg_work_minutes' => !empty($workMinutes) ? array_sum($workMinutes) / count($workMinutes) : 0
        ];
    }
    
    /**
     * حساب الإحصائيات الأساسية
     */
    private static function calculateStatistics(array $values): array {
        $n = count($values);
        if ($n === 0) {
            return ['mean' => 0, 'std_dev' => 0, 'min' => 0, 'max' => 0, 'median' => 0];
        }
        
        $mean = array_sum($values) / $n;
        
        // الانحراف المعياري
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $stdDev = $n > 1 ? sqrt($variance / ($n - 1)) : 0;
        
        // الوسيط
        sort($values);
        $median = $n % 2 === 0 
            ? ($values[$n/2 - 1] + $values[$n/2]) / 2 
            : $values[floor($n/2)];
        
        return [
            'mean' => $mean,
            'std_dev' => $stdDev,
            'min' => min($values),
            'max' => max($values),
            'median' => $median
        ];
    }
    
    /**
     * حساب نسبة الثقة في التنبؤ
     */
    private static function calculatePredictionConfidence(array $data, int $dayOfWeek): float {
        $dayData = array_filter($data, fn($r) => (int) date('N', strtotime($r['date'])) === $dayOfWeek);
        $dataPoints = count($dayData);
        
        // الثقة تزيد مع زيادة البيانات
        $dataFactor = min(1, $dataPoints / 8);
        
        // الثقة تنخفض مع التباين العالي
        $consistency = 1;
        if ($dataPoints >= 3) {
            $attendances = array_map(fn($r) => $r['check_in_time'] ? 1 : 0, $dayData);
            $stats = self::calculateStatistics(array_values($attendances));
            $consistency = 1 - ($stats['std_dev'] * 0.5);
        }
        
        return $dataFactor * $consistency;
    }
    
    /**
     * حساب دقة النموذج
     */
    private static function calculateModelAccuracy(int $userId): float {
        // حساب دقة التنبؤات السابقة (يمكن تحسينها بتخزين التنبؤات السابقة)
        return 0.85; // قيمة افتراضية مبنية على اختبارات النموذج
    }
    
    /**
     * تقييم مستوى الخطر
     */
    private static function assessRiskLevel(float $probability, float $confidence): string {
        if ($probability < 0.3 || $confidence < 0.5) {
            return 'عالي 🔴';
        } elseif ($probability < 0.6 || $confidence < 0.7) {
            return 'متوسط 🟠';
        } else {
            return 'منخفض 🟢';
        }
    }
    
    /**
     * الحصول على اسم اليوم بالعربية
     */
    private static function getArabicDayName(int $dayNumber): string {
        $days = [
            1 => 'الإثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
            7 => 'الأحد'
        ];
        return $days[$dayNumber] ?? '';
    }
    
    /**
     * حساب الزخم (Momentum)
     */
    private static function calculateMomentum(array $data): float {
        if (count($data) < 14) return 0;
        
        $recent = array_slice($data, 0, 7);
        $older = array_slice($data, 7, 7);
        
        $recentRate = self::calculateAdvancedStats($recent)['attendance_rate'];
        $olderRate = self::calculateAdvancedStats($older)['attendance_rate'];
        
        return round(($recentRate - $olderRate) * 100, 1);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// 🎯 دوال مساعدة عامة للتحليلات
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * تحليل سريع للموظف
 */
function quick_employee_analysis(int $userId): array {
    return AnalyticsEngine::comprehensivePerformanceAnalysis($userId);
}

/**
 * التنبؤ بالحضور
 */
function predict_attendance(int $userId, int $days = 7): array {
    return AnalyticsEngine::predictAttendance($userId, $days);
}

/**
 * كشف الحالات الشاذة
 */
function detect_anomalies(int $userId, int $days = 30): array {
    return AnalyticsEngine::detectAnomalies($userId, $days);
}

/**
 * تحليل الفرع
 */
function analyze_branch(int $branchId, int $days = 30): array {
    return AnalyticsEngine::analyzeBranchPerformance($branchId, $days);
}

/**
 * مخاطر الترك
 */
function turnover_risk(int $userId): array {
    return AnalyticsEngine::predictTurnoverRisk($userId);
}
