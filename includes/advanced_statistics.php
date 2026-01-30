<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════════════════════════
 * 📊 محرك الإحصائيات المتقدمة - SARH ADVANCED STATISTICS ENGINE
 * ═══════════════════════════════════════════════════════════════════════════════════════════════
 * 
 * تحليلات إحصائية متقدمة بدون مكتبات خارجية
 * 
 * الخوارزميات المضمنة:
 * - Bayesian Analysis - التحليل البايزي
 * - Monte Carlo Simulation - محاكاة مونتي كارلو
 * - Survival Analysis (Kaplan-Meier) - تحليل البقاء
 * - Correlation Analysis - تحليل الارتباط
 * - Cohort Analysis - تحليل الأفواج
 * - Hypothesis Testing - اختبار الفرضيات
 * - Bootstrap Methods - طرق Bootstrap
 * - A/B Testing - اختبار A/B
 * 
 * @author SARH System
 * @version 3.0.0
 * ═══════════════════════════════════════════════════════════════════════════════════════════════
 */

if (!defined('SARH_SYSTEM')) {
    die('الوصول المباشر غير مسموح');
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 🎲 Bayesian Analysis - التحليل البايزي
 * ═══════════════════════════════════════════════════════════════
 */
class BayesianAnalysis {
    
    /**
     * تحديث احتمالية الحضور باستخدام نظرية بايز
     * Update attendance probability using Bayes' theorem
     */
    public static function updateAttendanceProbability(
        float $priorProbability,
        array $newEvidence,
        array $likelihoodTable
    ): array {
        $posteriorProbability = $priorProbability;
        $updates = [];
        
        foreach ($newEvidence as $evidence => $observed) {
            if (!isset($likelihoodTable[$evidence])) continue;
            
            // P(attend|evidence) = P(evidence|attend) * P(attend) / P(evidence)
            $likelihoodIfAttend = $likelihoodTable[$evidence]['if_attend'] ?? 0.5;
            $likelihoodIfAbsent = $likelihoodTable[$evidence]['if_absent'] ?? 0.5;
            
            // Calculate marginal probability
            $marginal = $likelihoodIfAttend * $posteriorProbability + 
                       $likelihoodIfAbsent * (1 - $posteriorProbability);
            
            if ($observed && $marginal > 0) {
                $posteriorProbability = ($likelihoodIfAttend * $posteriorProbability) / $marginal;
            } elseif (!$observed && $marginal > 0) {
                $posteriorProbability = ((1 - $likelihoodIfAttend) * $posteriorProbability) / 
                                       ((1 - $likelihoodIfAttend) * $posteriorProbability + 
                                        (1 - $likelihoodIfAbsent) * (1 - $posteriorProbability));
            }
            
            $updates[] = [
                'evidence' => $evidence,
                'observed' => $observed,
                'likelihood' => $likelihoodIfAttend,
                'updated_probability' => round($posteriorProbability, 4)
            ];
        }
        
        return [
            'prior' => round($priorProbability, 4),
            'posterior' => round($posteriorProbability, 4),
            'updates' => $updates,
            'confidence' => self::calculateCredibleInterval($posteriorProbability, count($newEvidence))
        ];
    }
    
    /**
     * حساب فاصل الثقة البايزي
     */
    private static function calculateCredibleInterval(float $probability, int $observations): array {
        // Simplified credible interval using Beta distribution approximation
        $alpha = $probability * $observations + 1;
        $beta = (1 - $probability) * $observations + 1;
        
        // Approximation of Beta percentiles
        $lower = max(0, $probability - 1.96 * sqrt($probability * (1 - $probability) / ($observations + 2)));
        $upper = min(1, $probability + 1.96 * sqrt($probability * (1 - $probability) / ($observations + 2)));
        
        return [
            'lower_95' => round($lower, 4),
            'upper_95' => round($upper, 4),
            'alpha' => $alpha,
            'beta' => $beta
        ];
    }
    
    /**
     * التنبؤ البايزي للسلوك المستقبلي
     */
    public static function bayesianPrediction(array $historicalData, array $features): array {
        // Calculate class probabilities
        $attendCount = count(array_filter($historicalData, fn($d) => $d['attended']));
        $total = count($historicalData);
        
        $priorAttend = $total > 0 ? $attendCount / $total : 0.5;
        
        // Calculate feature likelihoods
        $likelihoods = [];
        
        foreach ($features as $feature => $value) {
            $attendWithFeature = 0;
            $absentWithFeature = 0;
            
            foreach ($historicalData as $record) {
                if (isset($record[$feature]) && $record[$feature] == $value) {
                    if ($record['attended']) {
                        $attendWithFeature++;
                    } else {
                        $absentWithFeature++;
                    }
                }
            }
            
            // Laplace smoothing
            $likelihoods[$feature] = [
                'if_attend' => ($attendWithFeature + 1) / ($attendCount + 2),
                'if_absent' => ($absentWithFeature + 1) / (($total - $attendCount) + 2)
            ];
        }
        
        // Calculate posterior
        $result = self::updateAttendanceProbability($priorAttend, $features, $likelihoods);
        
        return [
            'predicted_probability' => $result['posterior'],
            'will_attend' => $result['posterior'] >= 0.5,
            'confidence_interval' => $result['confidence'],
            'feature_contributions' => $result['updates']
        ];
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 🎯 Monte Carlo Simulation - محاكاة مونتي كارلو
 * ═══════════════════════════════════════════════════════════════
 */
class MonteCarloSimulation {
    
    /**
     * محاكاة سيناريوهات الحضور المستقبلية
     */
    public static function simulateAttendanceScenarios(
        array $historicalRates,
        int $days = 30,
        int $simulations = 1000
    ): array {
        // Calculate distribution parameters from historical data
        $mean = array_sum($historicalRates) / count($historicalRates);
        $variance = 0;
        foreach ($historicalRates as $rate) {
            $variance += pow($rate - $mean, 2);
        }
        $variance /= count($historicalRates);
        $stdDev = sqrt($variance);
        
        // Run simulations
        $results = [];
        $totalAttendance = [];
        
        for ($sim = 0; $sim < $simulations; $sim++) {
            $scenarioTotal = 0;
            $dailyRates = [];
            
            for ($d = 0; $d < $days; $d++) {
                // Generate random rate using Box-Muller transform
                $rate = self::randomNormal($mean, $stdDev);
                $rate = max(0, min(1, $rate)); // Clamp to [0, 1]
                $dailyRates[] = $rate;
                $scenarioTotal += $rate;
            }
            
            $results[] = $dailyRates;
            $totalAttendance[] = $scenarioTotal / $days;
        }
        
        // Calculate percentiles
        sort($totalAttendance);
        $percentiles = [
            'p5' => $totalAttendance[(int)($simulations * 0.05)],
            'p25' => $totalAttendance[(int)($simulations * 0.25)],
            'p50' => $totalAttendance[(int)($simulations * 0.50)],
            'p75' => $totalAttendance[(int)($simulations * 0.75)],
            'p95' => $totalAttendance[(int)($simulations * 0.95)]
        ];
        
        // Scenario classification
        $bestCase = array_filter($totalAttendance, fn($t) => $t >= $percentiles['p75']);
        $worstCase = array_filter($totalAttendance, fn($t) => $t <= $percentiles['p25']);
        
        return [
            'simulations' => $simulations,
            'days' => $days,
            'expected_rate' => round($mean, 4),
            'std_deviation' => round($stdDev, 4),
            'percentiles' => array_map(fn($p) => round($p, 4), $percentiles),
            'scenarios' => [
                'best_case' => [
                    'probability' => 0.25,
                    'avg_rate' => round(array_sum($bestCase) / count($bestCase), 4),
                    'description' => 'سيناريو متفائل: أداء فوق المتوسط'
                ],
                'expected' => [
                    'probability' => 0.50,
                    'avg_rate' => round($percentiles['p50'], 4),
                    'description' => 'السيناريو المتوقع: أداء طبيعي'
                ],
                'worst_case' => [
                    'probability' => 0.25,
                    'avg_rate' => round(array_sum($worstCase) / count($worstCase), 4),
                    'description' => 'سيناريو متشائم: أداء أقل من المتوقع'
                ]
            ],
            'risk_analysis' => [
                'probability_below_80' => round(count(array_filter($totalAttendance, fn($t) => $t < 0.8)) / $simulations, 4),
                'probability_below_70' => round(count(array_filter($totalAttendance, fn($t) => $t < 0.7)) / $simulations, 4),
                'probability_below_60' => round(count(array_filter($totalAttendance, fn($t) => $t < 0.6)) / $simulations, 4)
            ]
        ];
    }
    
    /**
     * Generate random number from normal distribution using Box-Muller
     */
    private static function randomNormal(float $mean, float $stdDev): float {
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();
        
        $z = sqrt(-2 * log(max($u1, 1e-10))) * cos(2 * M_PI * $u2);
        
        return $mean + $stdDev * $z;
    }
    
    /**
     * محاكاة مخاطر الإنتاجية
     */
    public static function simulateProductivityRisk(
        array $teamData,
        int $criticalThreshold = 5,
        int $simulations = 1000
    ): array {
        $results = [];
        
        for ($sim = 0; $sim < $simulations; $sim++) {
            $absentToday = 0;
            
            foreach ($teamData as $employee) {
                $attendanceProbability = $employee['attendance_rate'] ?? 0.9;
                if ((mt_rand() / mt_getrandmax()) > $attendanceProbability) {
                    $absentToday++;
                }
            }
            
            $results[] = $absentToday;
        }
        
        // Analysis
        $criticalDays = count(array_filter($results, fn($r) => $r >= $criticalThreshold));
        
        // Distribution of absences
        $distribution = array_count_values($results);
        ksort($distribution);
        
        $avgAbsences = array_sum($results) / $simulations;
        $maxAbsences = max($results);
        
        return [
            'avg_daily_absences' => round($avgAbsences, 2),
            'max_absences_observed' => $maxAbsences,
            'critical_probability' => round($criticalDays / $simulations, 4),
            'distribution' => $distribution,
            'risk_level' => $criticalDays / $simulations > 0.1 ? 'عالي' : 
                           ($criticalDays / $simulations > 0.05 ? 'متوسط' : 'منخفض'),
            'recommendation' => $criticalDays / $simulations > 0.1 ? 
                'يُنصح بزيادة عدد الموظفين الاحتياطيين' :
                'مستوى المخاطر مقبول'
        ];
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 📈 Survival Analysis (Kaplan-Meier) - تحليل البقاء
 * ═══════════════════════════════════════════════════════════════
 */
class SurvivalAnalysis {
    
    /**
     * تحليل Kaplan-Meier لبقاء الموظفين
     */
    public static function kaplanMeier(array $employeeData): array {
        // Sort by tenure
        usort($employeeData, fn($a, $b) => $a['tenure_days'] <=> $b['tenure_days']);
        
        $survivalCurve = [];
        $atRisk = count($employeeData);
        $survivalProbability = 1.0;
        
        $currentTime = 0;
        
        foreach ($employeeData as $employee) {
            $time = $employee['tenure_days'];
            $event = $employee['left'] ?? false; // true if employee left
            
            if ($event) {
                // Calculate survival probability at this time
                $survivalProbability *= ($atRisk - 1) / $atRisk;
                
                $survivalCurve[] = [
                    'time' => $time,
                    'survival_probability' => round($survivalProbability, 4),
                    'at_risk' => $atRisk,
                    'events' => 1,
                    'cumulative_events' => count(array_filter($survivalCurve, fn($s) => isset($s['events']))) + 1
                ];
            }
            
            $atRisk--;
        }
        
        // Calculate median survival time
        $medianTime = null;
        foreach ($survivalCurve as $point) {
            if ($point['survival_probability'] <= 0.5) {
                $medianTime = $point['time'];
                break;
            }
        }
        
        // Calculate hazard rate
        $hazardRates = [];
        for ($i = 0; $i < count($survivalCurve) - 1; $i++) {
            $timeInterval = $survivalCurve[$i + 1]['time'] - $survivalCurve[$i]['time'];
            if ($timeInterval > 0) {
                $hazard = ($survivalCurve[$i]['survival_probability'] - $survivalCurve[$i + 1]['survival_probability']) /
                         ($timeInterval * $survivalCurve[$i]['survival_probability']);
                $hazardRates[] = [
                    'time' => $survivalCurve[$i]['time'],
                    'hazard_rate' => round($hazard, 6)
                ];
            }
        }
        
        return [
            'survival_curve' => $survivalCurve,
            'median_survival_time' => $medianTime,
            'hazard_rates' => $hazardRates,
            'total_employees' => count($employeeData),
            'total_events' => count(array_filter($employeeData, fn($e) => $e['left'] ?? false)),
            'retention_at_30_days' => self::getSurvivalAtTime($survivalCurve, 30),
            'retention_at_90_days' => self::getSurvivalAtTime($survivalCurve, 90),
            'retention_at_180_days' => self::getSurvivalAtTime($survivalCurve, 180),
            'retention_at_365_days' => self::getSurvivalAtTime($survivalCurve, 365)
        ];
    }
    
    private static function getSurvivalAtTime(array $curve, int $time): ?float {
        $lastSurvival = 1.0;
        
        foreach ($curve as $point) {
            if ($point['time'] > $time) {
                break;
            }
            $lastSurvival = $point['survival_probability'];
        }
        
        return round($lastSurvival, 4);
    }
    
    /**
     * تحليل مخاطر الترك بناءً على الخصائص
     */
    public static function coxProportionalHazards(array $employeeData, array $covariates): array {
        // Simplified Cox model - calculate hazard ratios
        $baselineHazard = count(array_filter($employeeData, fn($e) => $e['left'] ?? false)) / count($employeeData);
        
        $hazardRatios = [];
        
        foreach ($covariates as $covariate) {
            // Split data by covariate
            $withCovariate = array_filter($employeeData, fn($e) => ($e[$covariate] ?? 0) > 0);
            $withoutCovariate = array_filter($employeeData, fn($e) => ($e[$covariate] ?? 0) == 0);
            
            $hazardWith = count($withCovariate) > 0 ? 
                count(array_filter($withCovariate, fn($e) => $e['left'] ?? false)) / count($withCovariate) : 0;
            $hazardWithout = count($withoutCovariate) > 0 ? 
                count(array_filter($withoutCovariate, fn($e) => $e['left'] ?? false)) / count($withoutCovariate) : 0;
            
            $hazardRatio = $hazardWithout > 0 ? $hazardWith / $hazardWithout : 1;
            
            $hazardRatios[$covariate] = [
                'hazard_ratio' => round($hazardRatio, 3),
                'interpretation' => $hazardRatio > 1.5 ? 'يزيد المخاطر بشكل كبير' :
                                   ($hazardRatio > 1.1 ? 'يزيد المخاطر قليلاً' :
                                   ($hazardRatio < 0.7 ? 'يقلل المخاطر بشكل كبير' :
                                   ($hazardRatio < 0.9 ? 'يقلل المخاطر قليلاً' : 'تأثير محايد')))
            ];
        }
        
        return [
            'baseline_hazard' => round($baselineHazard, 4),
            'hazard_ratios' => $hazardRatios,
            'high_risk_factors' => array_keys(array_filter($hazardRatios, fn($h) => $h['hazard_ratio'] > 1.5)),
            'protective_factors' => array_keys(array_filter($hazardRatios, fn($h) => $h['hazard_ratio'] < 0.7))
        ];
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 📊 Correlation Analysis - تحليل الارتباط
 * ═══════════════════════════════════════════════════════════════
 */
class CorrelationAnalysis {
    
    /**
     * حساب مصفوفة الارتباط
     */
    public static function correlationMatrix(array $data, array $variables): array {
        $n = count($variables);
        $matrix = [];
        
        for ($i = 0; $i < $n; $i++) {
            $matrix[$variables[$i]] = [];
            for ($j = 0; $j < $n; $j++) {
                $x = array_column($data, $variables[$i]);
                $y = array_column($data, $variables[$j]);
                $matrix[$variables[$i]][$variables[$j]] = round(self::pearsonCorrelation($x, $y), 4);
            }
        }
        
        return [
            'matrix' => $matrix,
            'strong_positive' => self::findStrongCorrelations($matrix, 0.7),
            'strong_negative' => self::findStrongCorrelations($matrix, -0.7, true),
            'key_insights' => self::generateCorrelationInsights($matrix)
        ];
    }
    
    /**
     * حساب معامل ارتباط بيرسون
     */
    public static function pearsonCorrelation(array $x, array $y): float {
        $n = min(count($x), count($y));
        if ($n < 2) return 0;
        
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        $sumY2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
            $sumY2 += $y[$i] * $y[$i];
        }
        
        $denominator = sqrt(($n * $sumX2 - $sumX * $sumX) * ($n * $sumY2 - $sumY * $sumY));
        
        if ($denominator == 0) return 0;
        
        return ($n * $sumXY - $sumX * $sumY) / $denominator;
    }
    
    /**
     * حساب معامل ارتباط سبيرمان
     */
    public static function spearmanCorrelation(array $x, array $y): float {
        $rankX = self::rankData($x);
        $rankY = self::rankData($y);
        
        return self::pearsonCorrelation($rankX, $rankY);
    }
    
    private static function rankData(array $data): array {
        $indexed = [];
        foreach ($data as $i => $value) {
            $indexed[] = ['index' => $i, 'value' => $value];
        }
        
        usort($indexed, fn($a, $b) => $a['value'] <=> $b['value']);
        
        $ranks = [];
        foreach ($indexed as $rank => $item) {
            $ranks[$item['index']] = $rank + 1;
        }
        
        ksort($ranks);
        return array_values($ranks);
    }
    
    private static function findStrongCorrelations(array $matrix, float $threshold, bool $negative = false): array {
        $strong = [];
        $variables = array_keys($matrix);
        
        foreach ($variables as $i => $var1) {
            foreach ($variables as $j => $var2) {
                if ($i >= $j) continue;
                
                $corr = $matrix[$var1][$var2];
                
                if (!$negative && $corr >= $threshold) {
                    $strong[] = ['var1' => $var1, 'var2' => $var2, 'correlation' => $corr];
                } elseif ($negative && $corr <= $threshold) {
                    $strong[] = ['var1' => $var1, 'var2' => $var2, 'correlation' => $corr];
                }
            }
        }
        
        return $strong;
    }
    
    private static function generateCorrelationInsights(array $matrix): array {
        $insights = [];
        $variables = array_keys($matrix);
        
        foreach ($variables as $i => $var1) {
            foreach ($variables as $j => $var2) {
                if ($i >= $j) continue;
                
                $corr = $matrix[$var1][$var2];
                
                if (abs($corr) >= 0.8) {
                    $insights[] = [
                        'type' => $corr > 0 ? 'positive_strong' : 'negative_strong',
                        'variables' => [$var1, $var2],
                        'correlation' => $corr,
                        'interpretation' => $corr > 0 ? 
                            "علاقة طردية قوية جداً بين $var1 و $var2" :
                            "علاقة عكسية قوية جداً بين $var1 و $var2"
                    ];
                } elseif (abs($corr) >= 0.6) {
                    $insights[] = [
                        'type' => $corr > 0 ? 'positive_moderate' : 'negative_moderate',
                        'variables' => [$var1, $var2],
                        'correlation' => $corr,
                        'interpretation' => $corr > 0 ? 
                            "علاقة طردية متوسطة بين $var1 و $var2" :
                            "علاقة عكسية متوسطة بين $var1 و $var2"
                    ];
                }
            }
        }
        
        return $insights;
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 👥 Cohort Analysis - تحليل الأفواج
 * ═══════════════════════════════════════════════════════════════
 */
class CohortAnalysis {
    
    /**
     * تحليل الاحتفاظ بالموظفين حسب الأفواج
     */
    public static function employeeRetention(array $employees, string $cohortBy = 'hire_month'): array {
        // Group employees by cohort
        $cohorts = [];
        
        foreach ($employees as $employee) {
            $hireDate = $employee['hire_date'] ?? date('Y-m-d');
            
            switch ($cohortBy) {
                case 'hire_month':
                    $cohortKey = date('Y-m', strtotime($hireDate));
                    break;
                case 'hire_quarter':
                    $month = (int)date('m', strtotime($hireDate));
                    $quarter = ceil($month / 3);
                    $cohortKey = date('Y', strtotime($hireDate)) . '-Q' . $quarter;
                    break;
                default:
                    $cohortKey = date('Y-m', strtotime($hireDate));
            }
            
            if (!isset($cohorts[$cohortKey])) {
                $cohorts[$cohortKey] = [];
            }
            $cohorts[$cohortKey][] = $employee;
        }
        
        // Calculate retention for each cohort over time
        $retentionMatrix = [];
        $currentDate = new DateTime();
        
        foreach ($cohorts as $cohortKey => $cohortEmployees) {
            $cohortDate = new DateTime($cohortKey . '-01');
            $retentionMatrix[$cohortKey] = [
                'initial_size' => count($cohortEmployees),
                'retention' => []
            ];
            
            // Calculate retention at different periods
            for ($month = 0; $month <= 12; $month++) {
                $checkDate = (clone $cohortDate)->modify("+{$month} months");
                
                if ($checkDate > $currentDate) break;
                
                $retained = 0;
                foreach ($cohortEmployees as $employee) {
                    $stillEmployed = !($employee['left'] ?? false);
                    $leftDate = isset($employee['left_date']) ? new DateTime($employee['left_date']) : null;
                    
                    if ($stillEmployed || ($leftDate && $leftDate >= $checkDate)) {
                        $retained++;
                    }
                }
                
                $retentionMatrix[$cohortKey]['retention'][$month] = [
                    'month' => $month,
                    'retained' => $retained,
                    'retention_rate' => round($retained / count($cohortEmployees), 4)
                ];
            }
        }
        
        // Calculate average retention by period
        $avgRetention = [];
        for ($month = 0; $month <= 12; $month++) {
            $rates = [];
            foreach ($retentionMatrix as $cohort) {
                if (isset($cohort['retention'][$month])) {
                    $rates[] = $cohort['retention'][$month]['retention_rate'];
                }
            }
            
            if (!empty($rates)) {
                $avgRetention[$month] = round(array_sum($rates) / count($rates), 4);
            }
        }
        
        return [
            'cohorts' => $retentionMatrix,
            'average_retention' => $avgRetention,
            'best_cohort' => self::findBestCohort($retentionMatrix),
            'worst_cohort' => self::findWorstCohort($retentionMatrix),
            'insights' => self::generateCohortInsights($retentionMatrix, $avgRetention)
        ];
    }
    
    private static function findBestCohort(array $matrix): ?string {
        $best = null;
        $bestRate = 0;
        
        foreach ($matrix as $key => $cohort) {
            $finalRetention = end($cohort['retention'])['retention_rate'] ?? 0;
            if ($finalRetention > $bestRate) {
                $bestRate = $finalRetention;
                $best = $key;
            }
        }
        
        return $best;
    }
    
    private static function findWorstCohort(array $matrix): ?string {
        $worst = null;
        $worstRate = 1;
        
        foreach ($matrix as $key => $cohort) {
            $finalRetention = end($cohort['retention'])['retention_rate'] ?? 1;
            if ($finalRetention < $worstRate && count($cohort['retention']) > 3) {
                $worstRate = $finalRetention;
                $worst = $key;
            }
        }
        
        return $worst;
    }
    
    private static function generateCohortInsights(array $matrix, array $avgRetention): array {
        $insights = [];
        
        // Check for concerning drop-off points
        $previousRate = 1;
        foreach ($avgRetention as $month => $rate) {
            if ($month > 0 && ($previousRate - $rate) > 0.1) {
                $insights[] = [
                    'type' => 'warning',
                    'message' => "انخفاض كبير في الاحتفاظ عند الشهر $month (من " . round($previousRate * 100, 1) . "% إلى " . round($rate * 100, 1) . "%)",
                    'recommendation' => "مراجعة تجربة الموظفين خلال هذه الفترة"
                ];
            }
            $previousRate = $rate;
        }
        
        // Compare recent vs old cohorts
        $cohortKeys = array_keys($matrix);
        if (count($cohortKeys) >= 4) {
            $recentCohorts = array_slice($cohortKeys, -2);
            $olderCohorts = array_slice($cohortKeys, 0, 2);
            
            $recentAvg = 0;
            $olderAvg = 0;
            $recentCount = 0;
            $olderCount = 0;
            
            foreach ($recentCohorts as $key) {
                if (isset($matrix[$key]['retention'][3])) {
                    $recentAvg += $matrix[$key]['retention'][3]['retention_rate'];
                    $recentCount++;
                }
            }
            
            foreach ($olderCohorts as $key) {
                if (isset($matrix[$key]['retention'][3])) {
                    $olderAvg += $matrix[$key]['retention'][3]['retention_rate'];
                    $olderCount++;
                }
            }
            
            if ($recentCount > 0 && $olderCount > 0) {
                $recentAvg /= $recentCount;
                $olderAvg /= $olderCount;
                
                if ($recentAvg > $olderAvg + 0.05) {
                    $insights[] = [
                        'type' => 'positive',
                        'message' => 'تحسن ملحوظ في معدلات الاحتفاظ للأفواج الحديثة',
                        'recommendation' => 'الاستمرار في الممارسات الحالية'
                    ];
                } elseif ($recentAvg < $olderAvg - 0.05) {
                    $insights[] = [
                        'type' => 'warning',
                        'message' => 'تراجع في معدلات الاحتفاظ للأفواج الحديثة',
                        'recommendation' => 'مراجعة عملية التوظيف والتأهيل'
                    ];
                }
            }
        }
        
        return $insights;
    }
    
    /**
     * تحليل أداء الأفواج
     */
    public static function performanceByCohort(array $employees, array $attendanceData): array {
        $cohorts = [];
        
        foreach ($employees as $employee) {
            $hireDate = $employee['hire_date'] ?? date('Y-m-d');
            $cohortKey = date('Y-m', strtotime($hireDate));
            
            if (!isset($cohorts[$cohortKey])) {
                $cohorts[$cohortKey] = [];
            }
            
            // Find attendance for this employee
            $empAttendance = array_filter($attendanceData, fn($a) => $a['user_id'] == $employee['id']);
            
            $cohorts[$cohortKey][] = [
                'employee_id' => $employee['id'],
                'attendance_rate' => self::calculateAttendanceRate($empAttendance),
                'avg_late_minutes' => self::calculateAvgLateMinutes($empAttendance)
            ];
        }
        
        // Aggregate by cohort
        $cohortPerformance = [];
        foreach ($cohorts as $key => $employees) {
            $rates = array_column($employees, 'attendance_rate');
            $lateMinutes = array_column($employees, 'avg_late_minutes');
            
            $cohortPerformance[$key] = [
                'size' => count($employees),
                'avg_attendance_rate' => round(array_sum($rates) / count($rates), 4),
                'avg_late_minutes' => round(array_sum($lateMinutes) / count($lateMinutes), 1)
            ];
        }
        
        return $cohortPerformance;
    }
    
    private static function calculateAttendanceRate(array $attendance): float {
        if (empty($attendance)) return 0;
        
        $present = count(array_filter($attendance, fn($a) => $a['check_in_time'] !== null));
        return $present / count($attendance);
    }
    
    private static function calculateAvgLateMinutes(array $attendance): float {
        $lateMinutes = array_column($attendance, 'late_minutes');
        $lateMinutes = array_filter($lateMinutes, fn($m) => $m !== null);
        
        return empty($lateMinutes) ? 0 : array_sum($lateMinutes) / count($lateMinutes);
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 🧪 Hypothesis Testing - اختبار الفرضيات
 * ═══════════════════════════════════════════════════════════════
 */
class HypothesisTesting {
    
    /**
     * اختبار t للعينتين
     */
    public static function twoSampleTTest(array $sample1, array $sample2): array {
        $n1 = count($sample1);
        $n2 = count($sample2);
        
        if ($n1 < 2 || $n2 < 2) {
            return ['error' => 'حجم العينة غير كافٍ'];
        }
        
        $mean1 = array_sum($sample1) / $n1;
        $mean2 = array_sum($sample2) / $n2;
        
        $var1 = 0;
        $var2 = 0;
        
        foreach ($sample1 as $v) {
            $var1 += pow($v - $mean1, 2);
        }
        foreach ($sample2 as $v) {
            $var2 += pow($v - $mean2, 2);
        }
        
        $var1 /= ($n1 - 1);
        $var2 /= ($n2 - 1);
        
        // Pooled variance
        $pooledVar = (($n1 - 1) * $var1 + ($n2 - 1) * $var2) / ($n1 + $n2 - 2);
        $se = sqrt($pooledVar * (1/$n1 + 1/$n2));
        
        $tStatistic = ($mean1 - $mean2) / max($se, 1e-10);
        $df = $n1 + $n2 - 2;
        
        // Approximate p-value using normal approximation for large samples
        $pValue = 2 * (1 - self::normalCdf(abs($tStatistic)));
        
        return [
            'mean1' => round($mean1, 4),
            'mean2' => round($mean2, 4),
            'mean_difference' => round($mean1 - $mean2, 4),
            't_statistic' => round($tStatistic, 4),
            'degrees_of_freedom' => $df,
            'p_value' => round($pValue, 4),
            'significant_at_05' => $pValue < 0.05,
            'significant_at_01' => $pValue < 0.01,
            'effect_size' => round(abs($mean1 - $mean2) / sqrt($pooledVar), 4),
            'interpretation' => $pValue < 0.05 ? 
                'الفرق ذو دلالة إحصائية' : 
                'الفرق ليس ذو دلالة إحصائية'
        ];
    }
    
    /**
     * اختبار Chi-Square
     */
    public static function chiSquareTest(array $observed, array $expected = null): array {
        $n = count($observed);
        
        if ($expected === null) {
            // Assume uniform distribution
            $total = array_sum($observed);
            $expected = array_fill(0, $n, $total / $n);
        }
        
        $chiSquare = 0;
        for ($i = 0; $i < $n; $i++) {
            if ($expected[$i] > 0) {
                $chiSquare += pow($observed[$i] - $expected[$i], 2) / $expected[$i];
            }
        }
        
        $df = $n - 1;
        
        // Approximate p-value using chi-square distribution approximation
        $pValue = 1 - self::chiSquareCdf($chiSquare, $df);
        
        return [
            'chi_square' => round($chiSquare, 4),
            'degrees_of_freedom' => $df,
            'p_value' => round($pValue, 4),
            'significant_at_05' => $pValue < 0.05,
            'interpretation' => $pValue < 0.05 ? 
                'التوزيع يختلف بشكل معنوي عن المتوقع' : 
                'التوزيع لا يختلف بشكل معنوي عن المتوقع'
        ];
    }
    
    /**
     * Normal CDF approximation
     */
    private static function normalCdf(float $x): float {
        $b1 =  0.319381530;
        $b2 = -0.356563782;
        $b3 =  1.781477937;
        $b4 = -1.821255978;
        $b5 =  1.330274429;
        $p  =  0.2316419;
        
        $t = 1.0 / (1.0 + $p * abs($x));
        $poly = ((((($b5 * $t + $b4) * $t + $b3) * $t + $b2) * $t + $b1) * $t);
        $cdf = 1.0 - exp(-$x * $x / 2) * $poly / sqrt(2 * M_PI);
        
        return $x >= 0 ? $cdf : 1 - $cdf;
    }
    
    /**
     * Chi-Square CDF approximation
     */
    private static function chiSquareCdf(float $x, int $k): float {
        if ($x <= 0) return 0;
        
        // Wilson-Hilferty approximation
        $z = pow($x / $k, 1/3) - (1 - 2 / (9 * $k));
        $z /= sqrt(2 / (9 * $k));
        
        return self::normalCdf($z);
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 🔄 Bootstrap Methods - طرق Bootstrap
 * ═══════════════════════════════════════════════════════════════
 */
class Bootstrap {
    
    /**
     * Bootstrap confidence interval
     */
    public static function confidenceInterval(
        array $data,
        callable $statistic,
        int $nBootstrap = 1000,
        float $confidenceLevel = 0.95
    ): array {
        $bootstrapStats = [];
        $n = count($data);
        
        for ($i = 0; $i < $nBootstrap; $i++) {
            // Resample with replacement
            $sample = [];
            for ($j = 0; $j < $n; $j++) {
                $sample[] = $data[mt_rand(0, $n - 1)];
            }
            
            $bootstrapStats[] = $statistic($sample);
        }
        
        sort($bootstrapStats);
        
        $alpha = 1 - $confidenceLevel;
        $lowerIdx = (int)($alpha / 2 * $nBootstrap);
        $upperIdx = (int)((1 - $alpha / 2) * $nBootstrap);
        
        return [
            'point_estimate' => $statistic($data),
            'lower_bound' => $bootstrapStats[$lowerIdx],
            'upper_bound' => $bootstrapStats[$upperIdx],
            'confidence_level' => $confidenceLevel,
            'std_error' => self::standardDeviation($bootstrapStats),
            'bias' => $statistic($data) - array_sum($bootstrapStats) / $nBootstrap
        ];
    }
    
    /**
     * Bootstrap hypothesis test
     */
    public static function hypothesisTest(
        array $data1,
        array $data2,
        callable $statistic,
        int $nPermutations = 1000
    ): array {
        $observedDiff = $statistic($data1) - $statistic($data2);
        $combined = array_merge($data1, $data2);
        $n1 = count($data1);
        
        $permutationDiffs = [];
        
        for ($i = 0; $i < $nPermutations; $i++) {
            shuffle($combined);
            $permSample1 = array_slice($combined, 0, $n1);
            $permSample2 = array_slice($combined, $n1);
            
            $permutationDiffs[] = $statistic($permSample1) - $statistic($permSample2);
        }
        
        // Calculate p-value
        $moreExtreme = count(array_filter($permutationDiffs, fn($d) => abs($d) >= abs($observedDiff)));
        $pValue = $moreExtreme / $nPermutations;
        
        return [
            'observed_difference' => $observedDiff,
            'p_value' => $pValue,
            'significant_at_05' => $pValue < 0.05,
            'permutation_distribution' => [
                'mean' => array_sum($permutationDiffs) / $nPermutations,
                'std' => self::standardDeviation($permutationDiffs)
            ]
        ];
    }
    
    private static function standardDeviation(array $data): float {
        $n = count($data);
        if ($n < 2) return 0;
        
        $mean = array_sum($data) / $n;
        $sum = 0;
        
        foreach ($data as $value) {
            $sum += pow($value - $mean, 2);
        }
        
        return sqrt($sum / ($n - 1));
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 📈 A/B Testing - اختبار A/B
 * ═══════════════════════════════════════════════════════════════
 */
class ABTesting {
    
    /**
     * تحليل نتائج اختبار A/B
     */
    public static function analyzeExperiment(
        array $controlGroup,
        array $treatmentGroup,
        string $metric = 'conversion'
    ): array {
        $controlSuccess = count(array_filter($controlGroup, fn($x) => $x[$metric] ?? false));
        $treatmentSuccess = count(array_filter($treatmentGroup, fn($x) => $x[$metric] ?? false));
        
        $controlN = count($controlGroup);
        $treatmentN = count($treatmentGroup);
        
        $controlRate = $controlN > 0 ? $controlSuccess / $controlN : 0;
        $treatmentRate = $treatmentN > 0 ? $treatmentSuccess / $treatmentN : 0;
        
        $lift = $controlRate > 0 ? ($treatmentRate - $controlRate) / $controlRate : 0;
        
        // Z-test for proportions
        $pooledRate = ($controlSuccess + $treatmentSuccess) / ($controlN + $treatmentN);
        $se = sqrt($pooledRate * (1 - $pooledRate) * (1/$controlN + 1/$treatmentN));
        
        $zScore = $se > 0 ? ($treatmentRate - $controlRate) / $se : 0;
        $pValue = 2 * (1 - HypothesisTesting::normalCdf(abs($zScore)));
        
        // Bayesian probability that treatment is better
        $bayesianProb = self::bayesianProbabilityBetter(
            $controlSuccess, $controlN,
            $treatmentSuccess, $treatmentN
        );
        
        return [
            'control' => [
                'sample_size' => $controlN,
                'successes' => $controlSuccess,
                'rate' => round($controlRate, 4)
            ],
            'treatment' => [
                'sample_size' => $treatmentN,
                'successes' => $treatmentSuccess,
                'rate' => round($treatmentRate, 4)
            ],
            'lift' => round($lift * 100, 2) . '%',
            'absolute_difference' => round(($treatmentRate - $controlRate) * 100, 2) . '%',
            'z_score' => round($zScore, 4),
            'p_value' => round($pValue, 4),
            'statistically_significant' => $pValue < 0.05,
            'bayesian_probability_better' => round($bayesianProb, 4),
            'recommendation' => self::getRecommendation($pValue, $lift, $bayesianProb),
            'required_sample_size' => self::calculateRequiredSampleSize($controlRate, $lift)
        ];
    }
    
    private static function bayesianProbabilityBetter(
        int $successA, int $nA,
        int $successB, int $nB,
        int $simulations = 10000
    ): float {
        $betterCount = 0;
        
        for ($i = 0; $i < $simulations; $i++) {
            // Sample from Beta distributions
            $sampleA = self::betaSample($successA + 1, $nA - $successA + 1);
            $sampleB = self::betaSample($successB + 1, $nB - $successB + 1);
            
            if ($sampleB > $sampleA) {
                $betterCount++;
            }
        }
        
        return $betterCount / $simulations;
    }
    
    private static function betaSample(float $alpha, float $beta): float {
        // Simplified beta sampling using gamma distribution approximation
        $x = self::gammaSample($alpha);
        $y = self::gammaSample($beta);
        
        return $x / ($x + $y);
    }
    
    private static function gammaSample(float $shape): float {
        // Marsaglia and Tsang's method for gamma > 1
        if ($shape < 1) {
            return self::gammaSample(1 + $shape) * pow(mt_rand() / mt_getrandmax(), 1 / $shape);
        }
        
        $d = $shape - 1/3;
        $c = 1 / sqrt(9 * $d);
        
        while (true) {
            $x = MonteCarloSimulation::randomNormal(0, 1);
            $v = pow(1 + $c * $x, 3);
            
            if ($v > 0) {
                $u = mt_rand() / mt_getrandmax();
                if ($u < 1 - 0.0331 * pow($x, 4) ||
                    log($u) < 0.5 * $x * $x + $d * (1 - $v + log($v))) {
                    return $d * $v;
                }
            }
        }
    }
    
    private static function getRecommendation(float $pValue, float $lift, float $bayesianProb): string {
        if ($pValue < 0.05 && $lift > 0 && $bayesianProb > 0.95) {
            return 'النتائج واضحة وإيجابية. يُنصح بتطبيق التغيير.';
        } elseif ($pValue < 0.05 && $lift < 0) {
            return 'النتائج سلبية. يُنصح بالتراجع عن التغيير.';
        } elseif ($bayesianProb > 0.9) {
            return 'احتمالية التحسن عالية. يمكن الاستمرار مع المزيد من البيانات.';
        } elseif ($bayesianProb < 0.1) {
            return 'احتمالية التحسن منخفضة. يُنصح بالتوقف.';
        } else {
            return 'النتائج غير حاسمة. يُنصح بجمع المزيد من البيانات.';
        }
    }
    
    private static function calculateRequiredSampleSize(float $baselineRate, float $expectedLift, float $power = 0.8): int {
        if ($baselineRate <= 0 || $baselineRate >= 1) return 0;
        
        $expectedRate = $baselineRate * (1 + $expectedLift);
        $pooledRate = ($baselineRate + $expectedRate) / 2;
        
        // Z-values for 95% confidence and desired power
        $zAlpha = 1.96;  // Two-tailed 95%
        $zBeta = 0.84;   // 80% power
        
        $effectSize = abs($expectedRate - $baselineRate);
        if ($effectSize == 0) return PHP_INT_MAX;
        
        $n = 2 * $pooledRate * (1 - $pooledRate) * pow($zAlpha + $zBeta, 2) / pow($effectSize, 2);
        
        return (int)ceil($n);
    }
}
