<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════════════════════════
 * 🚀 محرك التحليلات الفائق - SARH SUPER ANALYTICS ENGINE
 * ═══════════════════════════════════════════════════════════════════════════════════════════════
 * 
 * يجمع جميع خوارزميات التعلم الآلي والتحليلات المتقدمة
 * في واجهة موحدة سهلة الاستخدام
 * 
 * @author SARH System
 * @version 3.0.0
 * ═══════════════════════════════════════════════════════════════════════════════════════════════
 */

if (!defined('SARH_SYSTEM')) {
    die('الوصول المباشر غير مسموح');
}

// تحميل المحركات المتقدمة
require_once __DIR__ . '/advanced_ml_engine.php';
require_once __DIR__ . '/advanced_timeseries.php';
require_once __DIR__ . '/advanced_statistics.php';

/**
 * ═══════════════════════════════════════════════════════════════
 * 🎯 محرك التحليلات الفائق
 * Super Analytics Engine - الواجهة الرئيسية
 * ═══════════════════════════════════════════════════════════════
 */
class SuperAnalytics {
    
    private static array $cache = [];
    
    // ═══════════════════════════════════════════════════════════════
    // 🤖 تحليل الموظف بالذكاء الاصطناعي
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * تحليل شامل فائق للموظف باستخدام جميع الخوارزميات
     */
    public static function ultraAnalysis(int $userId, int $days = 90): array {
        $cacheKey = "ultra_analysis_{$userId}_{$days}";
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        $historical = self::getHistoricalData($userId, $days);
        
        if (count($historical) < 14) {
            return ['error' => 'بيانات غير كافية للتحليل المتقدم (مطلوب 14 يوم على الأقل)'];
        }
        
        // تحويل البيانات إلى سلسلة زمنية
        $attendanceTimeSeries = array_map(fn($r) => $r['check_in_time'] ? 1 : 0, $historical);
        $lateMinutesSeries = array_map(fn($r) => (float)($r['late_minutes'] ?? 0), $historical);
        
        $result = [
            'user_id' => $userId,
            'analysis_period' => $days,
            'data_points' => count($historical),
            'generated_at' => date('Y-m-d H:i:s'),
            
            // التحليلات الأساسية
            'basic_stats' => self::calculateBasicStats($historical),
            
            // التنبؤ بالذكاء الاصطناعي
            'ai_predictions' => self::getAIPredictions($userId, $historical),
            
            // تحليل السلاسل الزمنية المتقدم
            'timeseries_analysis' => self::advancedTimeSeriesAnalysis($attendanceTimeSeries),
            
            // تحليل الأنماط والدورات
            'pattern_analysis' => self::patternAnalysis($historical),
            
            // تحليل المخاطر
            'risk_analysis' => self::comprehensiveRiskAnalysis($userId, $historical),
            
            // التوصيات الذكية
            'smart_recommendations' => self::generateSmartRecommendations($historical),
            
            // مؤشرات الأداء الرئيسية
            'kpis' => self::calculateKPIs($historical),
            
            // التحليل السلوكي العميق
            'behavioral_deep_analysis' => self::behavioralDeepAnalysis($historical)
        ];
        
        self::$cache[$cacheKey] = $result;
        return $result;
    }
    
    /**
     * التنبؤ بالذكاء الاصطناعي
     */
    private static function getAIPredictions(int $userId, array $historical): array {
        $features = self::extractFeatures($historical);
        $target = array_map(fn($r) => $r['check_in_time'] ? 1 : 0, $historical);
        
        // تدريب نموذج Random Forest
        $rf = new RandomForest(5, 5);
        if (count($features) > 10) {
            $rf->fit($features, $target);
        }
        
        // تنبؤ للأيام القادمة
        $predictions = [];
        for ($day = 1; $day <= 7; $day++) {
            $futureFeatures = self::generateFutureFeatures($day, $historical);
            
            // تنبؤ متعدد النماذج
            $probabilities = [];
            
            // Random Forest prediction
            if (count($features) > 10) {
                $rfProba = $rf->predictProba($futureFeatures);
                $probabilities['random_forest'] = $rfProba[1] ?? 0.5;
            }
            
            // Bayesian prediction
            $bayesianResult = BayesianAnalysis::bayesianPrediction(
                array_map(fn($r, $f) => array_merge(['attended' => $r['check_in_time'] ? true : false], ['day_of_week' => $f[0] ?? 0]), $historical, $features),
                ['day_of_week' => $futureFeatures[0] ?? 0]
            );
            $probabilities['bayesian'] = $bayesianResult['predicted_probability'];
            
            // Historical average for this day
            $dayOfWeek = (new DateTime())->modify("+{$day} days")->format('N');
            $historicalDayData = array_filter($historical, fn($r) => date('N', strtotime($r['date'])) == $dayOfWeek);
            $historicalRate = count($historicalDayData) > 0 ? 
                count(array_filter($historicalDayData, fn($r) => $r['check_in_time'])) / count($historicalDayData) : 0.8;
            $probabilities['historical'] = $historicalRate;
            
            // Ensemble prediction (weighted average)
            $ensembleProbability = (
                ($probabilities['random_forest'] ?? 0.8) * 0.4 +
                $probabilities['bayesian'] * 0.3 +
                $probabilities['historical'] * 0.3
            );
            
            $targetDate = (new DateTime())->modify("+{$day} days");
            
            $predictions[] = [
                'day' => $day,
                'date' => $targetDate->format('Y-m-d'),
                'day_name' => self::getArabicDayName((int)$targetDate->format('N')),
                'probability' => round($ensembleProbability * 100, 1),
                'will_attend' => $ensembleProbability >= 0.5,
                'confidence' => round(min(100, (1 - abs(0.5 - $ensembleProbability)) * 200), 1),
                'model_predictions' => $probabilities,
                'risk_level' => $ensembleProbability < 0.3 ? 'high' : ($ensembleProbability < 0.6 ? 'medium' : 'low')
            ];
        }
        
        return [
            'next_7_days' => $predictions,
            'model_accuracy' => self::estimateModelAccuracy($historical),
            'prediction_confidence' => round(array_sum(array_column($predictions, 'confidence')) / 7, 1)
        ];
    }
    
    /**
     * تحليل السلاسل الزمنية المتقدم
     */
    private static function advancedTimeSeriesAnalysis(array $series): array {
        $results = [];
        
        // Holt-Winters
        if (count($series) >= 14) {
            $hw = new HoltWinters(0.3, 0.1, 0.1, 7);
            $hwResult = $hw->fit($series);
            $hwForecast = $hw->forecast(7);
            
            $results['holt_winters'] = [
                'model_fit' => [
                    'rmse' => round($hwResult['rmse'] ?? 0, 4),
                    'mae' => round($hwResult['mae'] ?? 0, 4)
                ],
                'forecast' => array_map(fn($f) => [
                    'step' => $f['step'],
                    'prediction' => round($f['prediction'], 3),
                    'lower_95' => round(max(0, $f['lower_95']), 3),
                    'upper_95' => round(min(1, $f['upper_95']), 3)
                ], $hwForecast),
                'trend' => round($hwResult['trend'] ?? 0, 4),
                'seasonal_pattern' => array_map(fn($s) => round($s, 3), $hwResult['seasonal'] ?? [])
            ];
        }
        
        // Fourier Analysis - كشف الدورات
        if (count($series) >= 14) {
            $cycles = FourierTransform::detectDominantCycles($series, 3);
            $results['dominant_cycles'] = $cycles;
        }
        
        // Seasonal Decomposition
        if (count($series) >= 14) {
            $decomposition = SeasonalDecomposition::decompose($series, 7);
            $results['decomposition'] = [
                'trend_strength' => $decomposition['trend_strength'] ?? 0,
                'seasonal_strength' => $decomposition['seasonal_strength'] ?? 0,
                'seasonal_pattern' => $decomposition['seasonal'] ?? []
            ];
        }
        
        // Changepoint Detection
        $changepoints = ChangepointDetection::detectChangepoints($series, 'cusum');
        $results['changepoints'] = [
            'detected' => $changepoints['changepoints'] ?? [],
            'segments' => $changepoints['segments'] ?? []
        ];
        
        // Kalman Filter Smoothing
        $kalman = new KalmanFilter(0.1, 0.5, $series[0] ?? 0.8);
        $kalmanResult = $kalman->filter($series);
        $results['kalman_smoothed'] = [
            'trend' => array_map(fn($v) => round($v, 3), $kalmanResult['filtered']),
            'final_estimate' => round($kalmanResult['final_estimate'], 3)
        ];
        
        return $results;
    }
    
    /**
     * تحليل الأنماط
     */
    private static function patternAnalysis(array $historical): array {
        // تحليل أنماط الأيام
        $dayPatterns = [];
        for ($day = 1; $day <= 7; $day++) {
            $dayData = array_filter($historical, fn($r) => date('N', strtotime($r['date'])) == $day);
            $present = count(array_filter($dayData, fn($r) => $r['check_in_time']));
            $total = count($dayData);
            
            $dayPatterns[self::getArabicDayName($day)] = [
                'attendance_rate' => $total > 0 ? round($present / $total * 100, 1) : 0,
                'avg_arrival_time' => self::calculateAvgArrivalTime($dayData),
                'late_frequency' => $total > 0 ? 
                    round(count(array_filter($dayData, fn($r) => ($r['late_minutes'] ?? 0) > 0)) / $total * 100, 1) : 0
            ];
        }
        
        // Markov Chain للحالات
        $states = array_map(fn($r) => $r['check_in_time'] ? 'present' : 'absent', $historical);
        $markov = new MarkovChain();
        $markov->fit($states);
        
        $stationaryDist = $markov->getStationaryDistribution();
        $transitionMatrix = $markov->getTransitionMatrix();
        
        // K-Means Clustering للسلوك
        $behaviorFeatures = self::extractBehaviorFeatures($historical);
        $clustering = null;
        if (count($behaviorFeatures) >= 7) {
            $kmeans = new KMeansClustering(3);
            $clustering = $kmeans->fit($behaviorFeatures);
        }
        
        return [
            'day_patterns' => $dayPatterns,
            'best_day' => self::findBestDay($dayPatterns),
            'worst_day' => self::findWorstDay($dayPatterns),
            'markov_analysis' => [
                'stationary_distribution' => $stationaryDist,
                'transition_matrix' => $transitionMatrix,
                'predicted_long_term_attendance' => round(($stationaryDist['present'] ?? 0.8) * 100, 1)
            ],
            'behavior_clustering' => $clustering
        ];
    }
    
    /**
     * تحليل المخاطر الشامل
     */
    private static function comprehensiveRiskAnalysis(int $userId, array $historical): array {
        // Monte Carlo Simulation
        $rates = [];
        for ($i = 0; $i < count($historical) - 6; $i++) {
            $weekData = array_slice($historical, $i, 7);
            $rate = count(array_filter($weekData, fn($r) => $r['check_in_time'])) / 7;
            $rates[] = $rate;
        }
        
        $monteCarlo = !empty($rates) ? 
            MonteCarloSimulation::simulateAttendanceScenarios($rates, 30, 500) :
            ['error' => 'بيانات غير كافية'];
        
        // حساب مؤشر المخاطر المركب
        $riskFactors = [];
        $riskScore = 0;
        
        // عامل 1: معدل الحضور
        $attendanceRate = self::calculateBasicStats($historical)['attendance_rate'];
        if ($attendanceRate < 70) {
            $riskFactors[] = ['factor' => 'معدل حضور منخفض', 'severity' => 'high', 'score' => 30];
            $riskScore += 30;
        } elseif ($attendanceRate < 85) {
            $riskFactors[] = ['factor' => 'معدل حضور متوسط', 'severity' => 'medium', 'score' => 15];
            $riskScore += 15;
        }
        
        // عامل 2: الاتجاه
        $trend = self::calculateTrend($historical);
        if ($trend < -0.02) {
            $riskFactors[] = ['factor' => 'اتجاه تنازلي في الحضور', 'severity' => 'high', 'score' => 25];
            $riskScore += 25;
        } elseif ($trend < 0) {
            $riskFactors[] = ['factor' => 'اتجاه سلبي طفيف', 'severity' => 'medium', 'score' => 10];
            $riskScore += 10;
        }
        
        // عامل 3: التذبذب
        $volatility = self::calculateVolatility($historical);
        if ($volatility > 0.3) {
            $riskFactors[] = ['factor' => 'تذبذب عالي في الحضور', 'severity' => 'medium', 'score' => 15];
            $riskScore += 15;
        }
        
        // عامل 4: الغياب المتتالي
        $maxConsecutiveAbsent = self::maxConsecutiveAbsent($historical);
        if ($maxConsecutiveAbsent >= 3) {
            $riskFactors[] = ['factor' => 'غياب متتالي متكرر', 'severity' => 'high', 'score' => 20];
            $riskScore += 20;
        }
        
        // عامل 5: التأخير المتكرر
        $lateRate = self::calculateLateRate($historical);
        if ($lateRate > 0.3) {
            $riskFactors[] = ['factor' => 'تأخير متكرر', 'severity' => 'medium', 'score' => 10];
            $riskScore += 10;
        }
        
        $riskLevel = 'منخفض 🟢';
        if ($riskScore >= 70) $riskLevel = 'حرج 🔴';
        elseif ($riskScore >= 50) $riskLevel = 'عالي 🟠';
        elseif ($riskScore >= 30) $riskLevel = 'متوسط 🟡';
        
        return [
            'risk_score' => min(100, $riskScore),
            'risk_level' => $riskLevel,
            'risk_factors' => $riskFactors,
            'monte_carlo' => $monteCarlo,
            'probability_of_absence_next_week' => round(1 - ($monteCarlo['percentiles']['p50'] ?? 0.8), 3),
            'worst_case_scenario' => $monteCarlo['scenarios']['worst_case'] ?? null,
            'recommendations' => self::getRiskRecommendations($riskFactors)
        ];
    }
    
    /**
     * التوصيات الذكية
     */
    private static function generateSmartRecommendations(array $historical): array {
        $recommendations = [];
        $stats = self::calculateBasicStats($historical);
        
        // توصيات بناءً على الحضور
        if ($stats['attendance_rate'] < 80) {
            $recommendations[] = [
                'priority' => 'عالية',
                'category' => 'attendance',
                'title' => 'تحسين معدل الحضور',
                'description' => 'معدل الحضور أقل من المستوى المطلوب (' . $stats['attendance_rate'] . '%).',
                'action' => 'عقد اجتماع فردي لمناقشة التحديات وإيجاد حلول',
                'expected_impact' => 'تحسين الإنتاجية بنسبة 15-25%',
                'icon' => '📈'
            ];
        }
        
        // توصيات بناءً على التأخير
        if ($stats['punctuality_rate'] < 70) {
            $recommendations[] = [
                'priority' => 'متوسطة',
                'category' => 'punctuality',
                'title' => 'تحسين الالتزام بالمواعيد',
                'description' => 'معدل التأخير مرتفع. ' . (100 - $stats['punctuality_rate']) . '% من أيام الحضور بها تأخير.',
                'action' => 'مراجعة ظروف التنقل أو النظر في تعديل الجدول',
                'expected_impact' => 'تقليل وقت العمل الضائع بنسبة 10-15%',
                'icon' => '⏰'
            ];
        }
        
        // توصيات بناءً على أنماط الأيام
        $dayPatterns = [];
        for ($day = 1; $day <= 7; $day++) {
            $dayData = array_filter($historical, fn($r) => date('N', strtotime($r['date'])) == $day);
            $present = count(array_filter($dayData, fn($r) => $r['check_in_time']));
            $total = count($dayData);
            if ($total > 0) {
                $dayPatterns[$day] = $present / $total;
            }
        }
        
        if (!empty($dayPatterns)) {
            $worstDay = array_search(min($dayPatterns), $dayPatterns);
            if ($dayPatterns[$worstDay] < 0.7) {
                $recommendations[] = [
                    'priority' => 'متوسطة',
                    'category' => 'pattern',
                    'title' => 'نمط غياب يوم ' . self::getArabicDayName($worstDay),
                    'description' => 'لوحظ غياب متكرر يوم ' . self::getArabicDayName($worstDay) . 
                                   ' (معدل الحضور: ' . round($dayPatterns[$worstDay] * 100, 1) . '%).',
                    'action' => 'التحقق من أسباب الغياب في هذا اليوم تحديداً',
                    'expected_impact' => 'زيادة أيام العمل الفعلية',
                    'icon' => '📅'
                ];
            }
        }
        
        // توصية إيجابية إذا كان الأداء ممتاز
        if ($stats['attendance_rate'] >= 95 && $stats['punctuality_rate'] >= 90) {
            $recommendations[] = [
                'priority' => 'إيجابية',
                'category' => 'recognition',
                'title' => 'أداء متميز! 🌟',
                'description' => 'الموظف يحافظ على معدلات حضور والتزام ممتازة.',
                'action' => 'تقديم شكر وتقدير، والنظر في مكافأة',
                'expected_impact' => 'تعزيز الولاء وتحفيز الآخرين',
                'icon' => '🏆'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * حساب مؤشرات الأداء الرئيسية
     */
    private static function calculateKPIs(array $historical): array {
        $stats = self::calculateBasicStats($historical);
        
        // حساب مؤشر الأداء الشامل
        $performanceIndex = (
            $stats['attendance_rate'] * 0.4 +
            $stats['punctuality_rate'] * 0.25 +
            $stats['consistency_score'] * 0.20 +
            $stats['reliability_score'] * 0.15
        );
        
        return [
            'overall_performance_index' => round($performanceIndex, 1),
            'attendance_rate' => $stats['attendance_rate'],
            'punctuality_rate' => $stats['punctuality_rate'],
            'consistency_score' => $stats['consistency_score'],
            'reliability_score' => $stats['reliability_score'],
            'avg_work_hours' => round($stats['avg_work_minutes'] / 60, 1),
            'avg_late_minutes' => round($stats['avg_late_minutes'], 1),
            'trend' => self::calculateTrend($historical) > 0 ? 'تصاعدي ↑' : 
                      (self::calculateTrend($historical) < -0.01 ? 'تنازلي ↓' : 'مستقر →'),
            'grade' => self::calculateGrade($performanceIndex)
        ];
    }
    
    /**
     * التحليل السلوكي العميق
     */
    private static function behavioralDeepAnalysis(array $historical): array {
        // تحليل أوقات الوصول
        $arrivalTimes = [];
        foreach ($historical as $record) {
            if ($record['check_in_time']) {
                $arrivalTimes[] = strtotime($record['check_in_time']) - strtotime('00:00:00');
            }
        }
        
        $arrivalAnalysis = [];
        if (!empty($arrivalTimes)) {
            $avgArrival = array_sum($arrivalTimes) / count($arrivalTimes);
            $stdArrival = sqrt(array_sum(array_map(fn($t) => pow($t - $avgArrival, 2), $arrivalTimes)) / count($arrivalTimes));
            
            $arrivalAnalysis = [
                'avg_arrival' => gmdate('H:i', $avgArrival),
                'std_minutes' => round($stdArrival / 60, 1),
                'earliest' => gmdate('H:i', min($arrivalTimes)),
                'latest' => gmdate('H:i', max($arrivalTimes)),
                'consistency' => $stdArrival < 1800 ? 'عالي' : ($stdArrival < 3600 ? 'متوسط' : 'منخفض')
            ];
        }
        
        // تحليل أنماط العمل
        $workPatterns = [
            'early_bird' => 0,  // قبل 7:30
            'on_time' => 0,     // 7:30-8:00
            'slightly_late' => 0, // 8:00-8:30
            'late' => 0         // بعد 8:30
        ];
        
        foreach ($arrivalTimes as $time) {
            $hour = $time / 3600;
            if ($hour < 7.5) $workPatterns['early_bird']++;
            elseif ($hour < 8) $workPatterns['on_time']++;
            elseif ($hour < 8.5) $workPatterns['slightly_late']++;
            else $workPatterns['late']++;
        }
        
        $total = array_sum($workPatterns);
        if ($total > 0) {
            $workPatterns = array_map(fn($v) => round($v / $total * 100, 1), $workPatterns);
        }
        
        // تحديد نوع الشخصية العملية
        $workPersonality = 'متوازن';
        if ($workPatterns['early_bird'] > 50) {
            $workPersonality = 'طائر الصباح 🌅';
        } elseif ($workPatterns['late'] > 30) {
            $workPersonality = 'متأخر معتاد ⏰';
        } elseif ($workPatterns['on_time'] > 60) {
            $workPersonality = 'منضبط تماماً ✅';
        }
        
        return [
            'arrival_analysis' => $arrivalAnalysis,
            'work_patterns' => $workPatterns,
            'work_personality' => $workPersonality,
            'stability_index' => round(100 - (self::calculateVolatility($historical) * 100), 1),
            'predictability' => self::calculatePredictability($historical)
        ];
    }
    
    // ═══════════════════════════════════════════════════════════════
    // 🏢 تحليلات الفريق والفرع
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * تحليل شامل للفرع
     */
    public static function branchUltraAnalysis(int $branchId, int $days = 30): array {
        try {
            $employees = Database::fetchAll(
                "SELECT id, full_name, hire_date FROM users WHERE branch_id = :branch_id AND is_active = 1",
                ['branch_id' => $branchId]
            );
            
            if (empty($employees)) {
                return ['error' => 'لا يوجد موظفين في هذا الفرع'];
            }
            
            $employeeAnalyses = [];
            $allRates = [];
            
            foreach ($employees as $emp) {
                $historical = self::getHistoricalData($emp['id'], $days);
                if (count($historical) >= 7) {
                    $stats = self::calculateBasicStats($historical);
                    $employeeAnalyses[] = [
                        'id' => $emp['id'],
                        'name' => $emp['full_name'],
                        'hire_date' => $emp['hire_date'],
                        'attendance_rate' => $stats['attendance_rate'],
                        'punctuality_rate' => $stats['punctuality_rate'],
                        'risk_level' => $stats['attendance_rate'] < 70 ? 'high' : 
                                       ($stats['attendance_rate'] < 85 ? 'medium' : 'low')
                    ];
                    $allRates[] = $stats['attendance_rate'];
                }
            }
            
            // تجميع الموظفين حسب الأداء
            $features = array_map(fn($e) => [$e['attendance_rate'], $e['punctuality_rate']], $employeeAnalyses);
            $clustering = null;
            if (count($features) >= 5) {
                $kmeans = new KMeansClustering(min(3, count($features)));
                $clustering = $kmeans->fit($features);
            }
            
            // تحليل Cohort
            $cohortData = array_map(fn($e) => [
                'hire_date' => $e['hire_date'],
                'left' => false,
                'tenure_days' => (new DateTime())->diff(new DateTime($e['hire_date']))->days
            ], $employeeAnalyses);
            
            $cohortAnalysis = CohortAnalysis::employeeRetention($cohortData);
            
            // ترتيب حسب الأداء
            usort($employeeAnalyses, fn($a, $b) => $b['attendance_rate'] <=> $a['attendance_rate']);
            
            return [
                'branch_id' => $branchId,
                'total_employees' => count($employees),
                'analyzed_employees' => count($employeeAnalyses),
                'avg_attendance_rate' => round(array_sum($allRates) / count($allRates), 1),
                'top_performers' => array_slice($employeeAnalyses, 0, 5),
                'needs_attention' => array_filter($employeeAnalyses, fn($e) => $e['risk_level'] === 'high'),
                'performance_distribution' => [
                    'excellent' => count(array_filter($employeeAnalyses, fn($e) => $e['attendance_rate'] >= 95)),
                    'good' => count(array_filter($employeeAnalyses, fn($e) => $e['attendance_rate'] >= 85 && $e['attendance_rate'] < 95)),
                    'average' => count(array_filter($employeeAnalyses, fn($e) => $e['attendance_rate'] >= 70 && $e['attendance_rate'] < 85)),
                    'poor' => count(array_filter($employeeAnalyses, fn($e) => $e['attendance_rate'] < 70))
                ],
                'clustering' => $clustering,
                'cohort_analysis' => $cohortAnalysis,
                'recommendations' => self::generateBranchRecommendations($employeeAnalyses)
            ];
            
        } catch (Exception $e) {
            return ['error' => 'خطأ في تحليل الفرع: ' . $e->getMessage()];
        }
    }
    
    // ═══════════════════════════════════════════════════════════════
    // 🛠️ دوال مساعدة
    // ═══════════════════════════════════════════════════════════════
    
    private static function getHistoricalData(int $userId, int $days): array {
        try {
            return Database::fetchAll(
                "SELECT * FROM attendance 
                 WHERE user_id = :user_id 
                 AND date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                 ORDER BY date DESC",
                ['user_id' => $userId, 'days' => $days]
            );
        } catch (Exception $e) {
            return [];
        }
    }
    
    private static function calculateBasicStats(array $data): array {
        $totalDays = count($data);
        $presentDays = count(array_filter($data, fn($r) => $r['check_in_time']));
        $onTimeDays = count(array_filter($data, fn($r) => $r['check_in_time'] && ($r['late_minutes'] ?? 0) == 0));
        
        $lateMinutes = array_filter(array_column($data, 'late_minutes'), fn($m) => $m !== null);
        $workMinutes = array_filter(array_column($data, 'work_minutes'), fn($m) => $m !== null);
        
        $attendanceRate = $totalDays > 0 ? $presentDays / $totalDays * 100 : 0;
        $punctualityRate = $presentDays > 0 ? $onTimeDays / $presentDays * 100 : 0;
        
        // Consistency Score
        $consistencyScore = 100;
        if (!empty($workMinutes)) {
            $avgWork = array_sum($workMinutes) / count($workMinutes);
            $variance = array_sum(array_map(fn($m) => pow($m - $avgWork, 2), $workMinutes)) / count($workMinutes);
            $cv = $avgWork > 0 ? sqrt($variance) / $avgWork : 0;
            $consistencyScore = max(0, (1 - $cv) * 100);
        }
        
        // Reliability Score
        $reliabilityScore = ($attendanceRate * 0.5) + ($punctualityRate * 0.3) + ($consistencyScore * 0.2);
        
        return [
            'total_days' => $totalDays,
            'present_days' => $presentDays,
            'attendance_rate' => round($attendanceRate, 1),
            'punctuality_rate' => round($punctualityRate, 1),
            'consistency_score' => round($consistencyScore, 1),
            'reliability_score' => round($reliabilityScore, 1),
            'avg_late_minutes' => !empty($lateMinutes) ? array_sum($lateMinutes) / count($lateMinutes) : 0,
            'avg_work_minutes' => !empty($workMinutes) ? array_sum($workMinutes) / count($workMinutes) : 0
        ];
    }
    
    private static function extractFeatures(array $historical): array {
        $features = [];
        foreach ($historical as $record) {
            $dayOfWeek = date('N', strtotime($record['date']));
            $weekOfMonth = ceil(date('d', strtotime($record['date'])) / 7);
            $isMonthStart = date('d', strtotime($record['date'])) <= 5 ? 1 : 0;
            $isMonthEnd = date('d', strtotime($record['date'])) >= 25 ? 1 : 0;
            
            $features[] = [
                $dayOfWeek,
                $weekOfMonth,
                $isMonthStart,
                $isMonthEnd,
                ($record['late_minutes'] ?? 0) > 0 ? 1 : 0
            ];
        }
        return $features;
    }
    
    private static function extractBehaviorFeatures(array $historical): array {
        $features = [];
        $windowSize = 7;
        
        for ($i = 0; $i <= count($historical) - $windowSize; $i++) {
            $window = array_slice($historical, $i, $windowSize);
            $attendance = count(array_filter($window, fn($r) => $r['check_in_time'])) / $windowSize;
            $avgLate = array_sum(array_column($window, 'late_minutes')) / $windowSize;
            
            $features[] = [$attendance, $avgLate / 60]; // normalize late minutes to hours
        }
        
        return $features;
    }
    
    private static function generateFutureFeatures(int $daysAhead, array $historical): array {
        $targetDate = (new DateTime())->modify("+{$daysAhead} days");
        $dayOfWeek = (int)$targetDate->format('N');
        $weekOfMonth = ceil((int)$targetDate->format('d') / 7);
        $isMonthStart = (int)$targetDate->format('d') <= 5 ? 1 : 0;
        $isMonthEnd = (int)$targetDate->format('d') >= 25 ? 1 : 0;
        
        // Recent late pattern
        $recentRecords = array_slice($historical, 0, 7);
        $recentLatePattern = count(array_filter($recentRecords, fn($r) => ($r['late_minutes'] ?? 0) > 0)) > 3 ? 1 : 0;
        
        return [$dayOfWeek, $weekOfMonth, $isMonthStart, $isMonthEnd, $recentLatePattern];
    }
    
    private static function calculateTrend(array $historical): float {
        if (count($historical) < 7) return 0;
        
        $recent = array_slice($historical, 0, 7);
        $older = array_slice($historical, 7, 7);
        
        if (empty($older)) return 0;
        
        $recentRate = count(array_filter($recent, fn($r) => $r['check_in_time'])) / 7;
        $olderRate = count(array_filter($older, fn($r) => $r['check_in_time'])) / 7;
        
        return $recentRate - $olderRate;
    }
    
    private static function calculateVolatility(array $historical): float {
        if (count($historical) < 7) return 0;
        
        $rates = [];
        for ($i = 0; $i < count($historical) - 6; $i++) {
            $window = array_slice($historical, $i, 7);
            $rates[] = count(array_filter($window, fn($r) => $r['check_in_time'])) / 7;
        }
        
        if (empty($rates)) return 0;
        
        $avg = array_sum($rates) / count($rates);
        $variance = array_sum(array_map(fn($r) => pow($r - $avg, 2), $rates)) / count($rates);
        
        return sqrt($variance);
    }
    
    private static function maxConsecutiveAbsent(array $historical): int {
        $max = 0;
        $current = 0;
        
        foreach ($historical as $record) {
            if (!$record['check_in_time']) {
                $current++;
                $max = max($max, $current);
            } else {
                $current = 0;
            }
        }
        
        return $max;
    }
    
    private static function calculateLateRate(array $historical): float {
        $present = array_filter($historical, fn($r) => $r['check_in_time']);
        if (empty($present)) return 0;
        
        $late = count(array_filter($present, fn($r) => ($r['late_minutes'] ?? 0) > 0));
        return $late / count($present);
    }
    
    private static function calculateAvgArrivalTime(array $dayData): ?string {
        $times = [];
        foreach ($dayData as $record) {
            if ($record['check_in_time']) {
                $times[] = strtotime($record['check_in_time']) - strtotime('00:00:00');
            }
        }
        
        if (empty($times)) return null;
        
        return gmdate('H:i', array_sum($times) / count($times));
    }
    
    private static function findBestDay(array $dayPatterns): string {
        $best = '';
        $bestRate = 0;
        foreach ($dayPatterns as $day => $data) {
            if ($data['attendance_rate'] > $bestRate) {
                $bestRate = $data['attendance_rate'];
                $best = $day;
            }
        }
        return $best;
    }
    
    private static function findWorstDay(array $dayPatterns): string {
        $worst = '';
        $worstRate = 100;
        foreach ($dayPatterns as $day => $data) {
            if ($data['attendance_rate'] < $worstRate && $data['attendance_rate'] > 0) {
                $worstRate = $data['attendance_rate'];
                $worst = $day;
            }
        }
        return $worst;
    }
    
    private static function getRiskRecommendations(array $riskFactors): array {
        $recommendations = [];
        
        foreach ($riskFactors as $factor) {
            switch ($factor['factor']) {
                case 'معدل حضور منخفض':
                    $recommendations[] = 'عقد اجتماع عاجل لمناقشة أسباب الغياب';
                    break;
                case 'اتجاه تنازلي في الحضور':
                    $recommendations[] = 'مراجعة ظروف العمل والتحقق من وجود مشاكل';
                    break;
                case 'تذبذب عالي في الحضور':
                    $recommendations[] = 'وضع خطة متابعة أسبوعية';
                    break;
                case 'غياب متتالي متكرر':
                    $recommendations[] = 'تطبيق سياسة الإنذار المبكر';
                    break;
                case 'تأخير متكرر':
                    $recommendations[] = 'النظر في تعديل ساعات العمل';
                    break;
            }
        }
        
        return array_unique($recommendations);
    }
    
    private static function estimateModelAccuracy(array $historical): float {
        // Cross-validation estimate
        if (count($historical) < 20) return 75;
        
        $correct = 0;
        $total = 0;
        
        for ($i = 7; $i < count($historical); $i++) {
            $previousWeek = array_slice($historical, $i - 7, 7);
            $predictedRate = count(array_filter($previousWeek, fn($r) => $r['check_in_time'])) / 7;
            $actual = $historical[$i]['check_in_time'] ? 1 : 0;
            $predicted = $predictedRate >= 0.5 ? 1 : 0;
            
            if ($actual == $predicted) {
                $correct++;
            }
            $total++;
        }
        
        return $total > 0 ? round($correct / $total * 100, 1) : 75;
    }
    
    private static function calculatePredictability(array $historical): string {
        $volatility = self::calculateVolatility($historical);
        
        if ($volatility < 0.1) return 'عالية جداً';
        if ($volatility < 0.2) return 'عالية';
        if ($volatility < 0.3) return 'متوسطة';
        return 'منخفضة';
    }
    
    private static function calculateGrade(float $score): string {
        if ($score >= 95) return 'A+ ممتاز';
        if ($score >= 90) return 'A ممتاز';
        if ($score >= 85) return 'B+ جيد جداً';
        if ($score >= 80) return 'B جيد';
        if ($score >= 75) return 'C+ مقبول';
        if ($score >= 70) return 'C مقبول';
        return 'D ضعيف';
    }
    
    private static function getArabicDayName(int $day): string {
        $days = [
            1 => 'الإثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء',
            4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت', 7 => 'الأحد'
        ];
        return $days[$day] ?? '';
    }
    
    private static function generateBranchRecommendations(array $employeeAnalyses): array {
        $recommendations = [];
        
        $highRisk = array_filter($employeeAnalyses, fn($e) => $e['risk_level'] === 'high');
        if (count($highRisk) > 0) {
            $recommendations[] = [
                'priority' => 'عالية',
                'title' => 'موظفون يحتاجون متابعة عاجلة',
                'description' => count($highRisk) . ' موظف بمعدل حضور منخفض',
                'action' => 'عقد اجتماعات فردية وتقديم الدعم اللازم'
            ];
        }
        
        $avgRate = array_sum(array_column($employeeAnalyses, 'attendance_rate')) / count($employeeAnalyses);
        if ($avgRate < 85) {
            $recommendations[] = [
                'priority' => 'متوسطة',
                'title' => 'تحسين معدل الحضور العام',
                'description' => 'معدل حضور الفرع (' . round($avgRate, 1) . '%) أقل من المطلوب',
                'action' => 'مراجعة سياسات الحضور وتحفيز الموظفين'
            ];
        }
        
        return $recommendations;
    }
}
