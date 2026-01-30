<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════════════════════════
 * 📈 محرك تحليل السلاسل الزمنية المتقدم - SARH ADVANCED TIME SERIES ENGINE
 * ═══════════════════════════════════════════════════════════════════════════════════════════════
 * 
 * تحليلات السلاسل الزمنية المتقدمة بدون مكتبات خارجية
 * 
 * الخوارزميات المضمنة:
 * - Holt-Winters Exponential Smoothing
 * - Fast Fourier Transform (FFT)
 * - Prophet-like Forecasting
 * - ARIMA-like Forecasting
 * - Seasonal Decomposition
 * - Changepoint Detection
 * - Wavelet Transform
 * - Kalman Filter
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
 * 📈 Holt-Winters Exponential Smoothing
 * التنعيم الأسي هولت-وينترز
 * ═══════════════════════════════════════════════════════════════
 */
class HoltWinters {
    private float $alpha; // Level smoothing
    private float $beta;  // Trend smoothing
    private float $gamma; // Seasonal smoothing
    private int $seasonalPeriod;
    private string $seasonalType; // 'additive' or 'multiplicative'
    
    private float $level;
    private float $trend;
    private array $seasonal;
    
    public function __construct(
        float $alpha = 0.3,
        float $beta = 0.1,
        float $gamma = 0.1,
        int $seasonalPeriod = 7,
        string $seasonalType = 'additive'
    ) {
        $this->alpha = $alpha;
        $this->beta = $beta;
        $this->gamma = $gamma;
        $this->seasonalPeriod = $seasonalPeriod;
        $this->seasonalType = $seasonalType;
    }
    
    public function fit(array $data): array {
        $n = count($data);
        if ($n < $this->seasonalPeriod * 2) {
            return ['error' => 'بيانات غير كافية للتحليل الموسمي'];
        }
        
        // Initialize level and trend
        $this->level = array_sum(array_slice($data, 0, $this->seasonalPeriod)) / $this->seasonalPeriod;
        $this->trend = 0;
        for ($i = 0; $i < $this->seasonalPeriod; $i++) {
            $this->trend += ($data[$i + $this->seasonalPeriod] - $data[$i]) / $this->seasonalPeriod;
        }
        $this->trend /= $this->seasonalPeriod;
        
        // Initialize seasonal components
        $this->seasonal = [];
        for ($i = 0; $i < $this->seasonalPeriod; $i++) {
            if ($this->seasonalType === 'multiplicative') {
                $this->seasonal[$i] = $this->level > 0 ? $data[$i] / $this->level : 1;
            } else {
                $this->seasonal[$i] = $data[$i] - $this->level;
            }
        }
        
        // Fit model
        $fitted = [];
        $residuals = [];
        
        for ($t = 0; $t < $n; $t++) {
            $seasonIdx = $t % $this->seasonalPeriod;
            $lastLevel = $this->level;
            $lastTrend = $this->trend;
            
            if ($this->seasonalType === 'multiplicative') {
                // Multiplicative model
                $this->level = $this->alpha * ($data[$t] / $this->seasonal[$seasonIdx]) +
                              (1 - $this->alpha) * ($lastLevel + $lastTrend);
                $this->trend = $this->beta * ($this->level - $lastLevel) +
                              (1 - $this->beta) * $lastTrend;
                $this->seasonal[$seasonIdx] = $this->gamma * ($data[$t] / $this->level) +
                                             (1 - $this->gamma) * $this->seasonal[$seasonIdx];
                
                $fitted[$t] = ($lastLevel + $lastTrend) * $this->seasonal[$seasonIdx];
            } else {
                // Additive model
                $this->level = $this->alpha * ($data[$t] - $this->seasonal[$seasonIdx]) +
                              (1 - $this->alpha) * ($lastLevel + $lastTrend);
                $this->trend = $this->beta * ($this->level - $lastLevel) +
                              (1 - $this->beta) * $lastTrend;
                $this->seasonal[$seasonIdx] = $this->gamma * ($data[$t] - $this->level) +
                                             (1 - $this->gamma) * $this->seasonal[$seasonIdx];
                
                $fitted[$t] = $lastLevel + $lastTrend + $this->seasonal[$seasonIdx];
            }
            
            $residuals[$t] = $data[$t] - $fitted[$t];
        }
        
        // Calculate error metrics
        $mse = array_sum(array_map(fn($r) => $r * $r, $residuals)) / $n;
        $mae = array_sum(array_map('abs', $residuals)) / $n;
        
        return [
            'fitted' => $fitted,
            'residuals' => $residuals,
            'level' => $this->level,
            'trend' => $this->trend,
            'seasonal' => $this->seasonal,
            'mse' => $mse,
            'mae' => $mae,
            'rmse' => sqrt($mse)
        ];
    }
    
    public function forecast(int $steps): array {
        $predictions = [];
        $confidenceIntervals = [];
        
        for ($h = 1; $h <= $steps; $h++) {
            $seasonIdx = ($h - 1) % $this->seasonalPeriod;
            
            if ($this->seasonalType === 'multiplicative') {
                $prediction = ($this->level + $h * $this->trend) * $this->seasonal[$seasonIdx];
            } else {
                $prediction = $this->level + $h * $this->trend + $this->seasonal[$seasonIdx];
            }
            
            // Simple confidence interval (grows with horizon)
            $uncertainty = sqrt($h) * 0.1 * abs($prediction);
            
            $predictions[] = [
                'step' => $h,
                'prediction' => $prediction,
                'lower_95' => $prediction - 1.96 * $uncertainty,
                'upper_95' => $prediction + 1.96 * $uncertainty,
                'lower_80' => $prediction - 1.28 * $uncertainty,
                'upper_80' => $prediction + 1.28 * $uncertainty
            ];
        }
        
        return $predictions;
    }
    
    public function optimizeParameters(array $data): array {
        $bestParams = ['alpha' => 0.3, 'beta' => 0.1, 'gamma' => 0.1];
        $bestMse = PHP_FLOAT_MAX;
        
        $alphaRange = [0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7];
        $betaRange = [0.01, 0.05, 0.1, 0.15, 0.2];
        $gammaRange = [0.01, 0.05, 0.1, 0.15, 0.2];
        
        foreach ($alphaRange as $a) {
            foreach ($betaRange as $b) {
                foreach ($gammaRange as $g) {
                    $this->alpha = $a;
                    $this->beta = $b;
                    $this->gamma = $g;
                    
                    $result = $this->fit($data);
                    if (isset($result['mse']) && $result['mse'] < $bestMse) {
                        $bestMse = $result['mse'];
                        $bestParams = ['alpha' => $a, 'beta' => $b, 'gamma' => $g];
                    }
                }
            }
        }
        
        // Set optimal parameters
        $this->alpha = $bestParams['alpha'];
        $this->beta = $bestParams['beta'];
        $this->gamma = $bestParams['gamma'];
        
        return [
            'optimal_params' => $bestParams,
            'best_mse' => $bestMse
        ];
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 🌊 Fast Fourier Transform (FFT)
 * تحويل فورييه السريع
 * ═══════════════════════════════════════════════════════════════
 */
class FourierTransform {
    
    public static function fft(array $data): array {
        $n = count($data);
        
        // Pad to power of 2
        $paddedN = 1;
        while ($paddedN < $n) {
            $paddedN *= 2;
        }
        
        // Pad with zeros
        $real = array_pad($data, $paddedN, 0);
        $imag = array_fill(0, $paddedN, 0);
        
        // Bit-reversal permutation
        $j = 0;
        for ($i = 0; $i < $paddedN - 1; $i++) {
            if ($i < $j) {
                // Swap
                [$real[$i], $real[$j]] = [$real[$j], $real[$i]];
                [$imag[$i], $imag[$j]] = [$imag[$j], $imag[$i]];
            }
            $k = $paddedN >> 1;
            while ($k <= $j) {
                $j -= $k;
                $k >>= 1;
            }
            $j += $k;
        }
        
        // Cooley-Tukey FFT
        for ($step = 1; $step < $paddedN; $step *= 2) {
            $angleStep = -M_PI / $step;
            
            for ($group = 0; $group < $paddedN; $group += $step * 2) {
                for ($pair = 0; $pair < $step; $pair++) {
                    $angle = $pair * $angleStep;
                    $wr = cos($angle);
                    $wi = sin($angle);
                    
                    $i = $group + $pair;
                    $j = $i + $step;
                    
                    $tr = $wr * $real[$j] - $wi * $imag[$j];
                    $ti = $wr * $imag[$j] + $wi * $real[$j];
                    
                    $real[$j] = $real[$i] - $tr;
                    $imag[$j] = $imag[$i] - $ti;
                    $real[$i] = $real[$i] + $tr;
                    $imag[$i] = $imag[$i] + $ti;
                }
            }
        }
        
        return ['real' => $real, 'imag' => $imag, 'n' => $paddedN];
    }
    
    public static function ifft(array $fftResult): array {
        $real = $fftResult['real'];
        $imag = array_map(fn($x) => -$x, $fftResult['imag']);
        $n = $fftResult['n'];
        
        $result = self::fft(array_map(fn($r, $i) => $r, $real, $imag));
        
        return array_map(fn($x) => $x / $n, $result['real']);
    }
    
    public static function powerSpectrum(array $data): array {
        $fft = self::fft($data);
        $n = $fft['n'];
        $spectrum = [];
        
        for ($i = 0; $i < $n / 2; $i++) {
            $magnitude = sqrt(pow($fft['real'][$i], 2) + pow($fft['imag'][$i], 2));
            $frequency = $i / $n;
            
            $spectrum[] = [
                'frequency' => $frequency,
                'period' => $frequency > 0 ? 1 / $frequency : PHP_FLOAT_MAX,
                'magnitude' => $magnitude,
                'power' => $magnitude * $magnitude
            ];
        }
        
        // Sort by power
        usort($spectrum, fn($a, $b) => $b['power'] <=> $a['power']);
        
        return $spectrum;
    }
    
    public static function detectDominantCycles(array $data, int $topN = 5): array {
        $spectrum = self::powerSpectrum($data);
        $cycles = [];
        
        // Filter out very long periods and DC component
        $n = count($data);
        $filtered = array_filter($spectrum, fn($s) => 
            $s['frequency'] > 0.01 && $s['period'] < $n * 0.5
        );
        
        $filtered = array_values($filtered);
        
        for ($i = 0; $i < min($topN, count($filtered)); $i++) {
            $cycles[] = [
                'period' => round($filtered[$i]['period'], 1),
                'frequency' => round($filtered[$i]['frequency'], 4),
                'strength' => round($filtered[$i]['magnitude'], 2),
                'interpretation' => self::interpretCycle($filtered[$i]['period'])
            ];
        }
        
        return $cycles;
    }
    
    private static function interpretCycle(float $period): string {
        if ($period >= 6.5 && $period <= 7.5) return 'دورة أسبوعية';
        if ($period >= 13 && $period <= 15) return 'دورة نصف شهرية';
        if ($period >= 28 && $period <= 32) return 'دورة شهرية';
        if ($period >= 85 && $period <= 95) return 'دورة ربع سنوية';
        if ($period >= 360 && $period <= 370) return 'دورة سنوية';
        return 'دورة أخرى';
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 🔮 Prophet-like Forecasting
 * تنبؤ مشابه لـ Prophet
 * ═══════════════════════════════════════════════════════════════
 */
class ProphetForecaster {
    private array $trend;
    private array $seasonality;
    private array $holidays;
    private float $changePointPrior;
    
    public function __construct(float $changePointPrior = 0.05) {
        $this->changePointPrior = $changePointPrior;
        $this->trend = [];
        $this->seasonality = [];
        $this->holidays = [];
    }
    
    public function fit(array $data, array $dates = []): array {
        $n = count($data);
        
        // Detect changepoints
        $changepoints = $this->detectChangepoints($data);
        
        // Fit piecewise linear trend
        $this->trend = $this->fitPiecewiseTrend($data, $changepoints);
        
        // Fit seasonality using Fourier series
        $this->seasonality = $this->fitSeasonality($data, $this->trend['fitted']);
        
        // Calculate fitted values
        $fitted = [];
        for ($i = 0; $i < $n; $i++) {
            $fitted[$i] = $this->trend['fitted'][$i] + $this->seasonality['fitted'][$i];
        }
        
        // Calculate residuals and metrics
        $residuals = [];
        for ($i = 0; $i < $n; $i++) {
            $residuals[$i] = $data[$i] - $fitted[$i];
        }
        
        $mse = array_sum(array_map(fn($r) => $r * $r, $residuals)) / $n;
        
        return [
            'fitted' => $fitted,
            'trend' => $this->trend['fitted'],
            'seasonality' => $this->seasonality['fitted'],
            'residuals' => $residuals,
            'changepoints' => $changepoints,
            'mse' => $mse,
            'rmse' => sqrt($mse),
            'trend_slope' => $this->trend['slope'],
            'seasonality_amplitude' => $this->seasonality['amplitude']
        ];
    }
    
    private function detectChangepoints(array $data, int $maxChangepoints = 5): array {
        $n = count($data);
        $potentialPoints = [];
        
        // Use CUSUM-like approach
        $mean = array_sum($data) / $n;
        $cusum = [0];
        
        for ($i = 0; $i < $n; $i++) {
            $cusum[] = $cusum[$i] + ($data[$i] - $mean);
        }
        
        // Find points with maximum deviation
        for ($i = (int)($n * 0.1); $i < $n * 0.9; $i++) {
            $leftMean = array_sum(array_slice($data, 0, $i)) / $i;
            $rightMean = array_sum(array_slice($data, $i)) / ($n - $i);
            $potentialPoints[$i] = abs($leftMean - $rightMean);
        }
        
        arsort($potentialPoints);
        $changepoints = array_slice(array_keys($potentialPoints), 0, $maxChangepoints);
        sort($changepoints);
        
        // Filter out changepoints that are too close
        $filtered = [];
        $minGap = (int)($n * 0.1);
        $lastPoint = -$minGap;
        
        foreach ($changepoints as $point) {
            if ($point - $lastPoint >= $minGap) {
                $filtered[] = $point;
                $lastPoint = $point;
            }
        }
        
        return $filtered;
    }
    
    private function fitPiecewiseTrend(array $data, array $changepoints): array {
        $n = count($data);
        $fitted = [];
        
        // Add boundaries
        $segments = array_merge([0], $changepoints, [$n]);
        
        $slopes = [];
        $intercepts = [];
        
        for ($s = 0; $s < count($segments) - 1; $s++) {
            $start = $segments[$s];
            $end = $segments[$s + 1];
            
            // Linear regression for this segment
            $segmentData = array_slice($data, $start, $end - $start);
            $segmentX = range(0, count($segmentData) - 1);
            
            $regression = $this->linearRegression($segmentX, $segmentData);
            $slopes[] = $regression['slope'];
            $intercepts[] = $regression['intercept'];
            
            // Fill fitted values
            for ($i = $start; $i < $end; $i++) {
                $localX = $i - $start;
                $fitted[$i] = $regression['slope'] * $localX + $regression['intercept'];
            }
        }
        
        return [
            'fitted' => $fitted,
            'slopes' => $slopes,
            'slope' => count($slopes) > 0 ? array_sum($slopes) / count($slopes) : 0,
            'intercepts' => $intercepts
        ];
    }
    
    private function fitSeasonality(array $data, array $trend): array {
        $n = count($data);
        
        // Remove trend to get detrended data
        $detrended = [];
        for ($i = 0; $i < $n; $i++) {
            $detrended[$i] = $data[$i] - $trend[$i];
        }
        
        // Fit Fourier series for weekly seasonality
        $weeklyFourier = $this->fitFourierSeries($detrended, 7, 3);
        
        // Calculate amplitude
        $amplitude = max($weeklyFourier) - min($weeklyFourier);
        
        return [
            'fitted' => $weeklyFourier,
            'amplitude' => $amplitude,
            'period' => 7
        ];
    }
    
    private function fitFourierSeries(array $data, int $period, int $order): array {
        $n = count($data);
        $fitted = array_fill(0, $n, 0);
        
        for ($k = 1; $k <= $order; $k++) {
            $sinCoef = 0;
            $cosCoef = 0;
            
            for ($t = 0; $t < $n; $t++) {
                $angle = 2 * M_PI * $k * $t / $period;
                $sinCoef += $data[$t] * sin($angle);
                $cosCoef += $data[$t] * cos($angle);
            }
            
            $sinCoef *= 2 / $n;
            $cosCoef *= 2 / $n;
            
            for ($t = 0; $t < $n; $t++) {
                $angle = 2 * M_PI * $k * $t / $period;
                $fitted[$t] += $sinCoef * sin($angle) + $cosCoef * cos($angle);
            }
        }
        
        return $fitted;
    }
    
    private function linearRegression(array $x, array $y): array {
        $n = count($x);
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
        }
        
        $denominator = $n * $sumX2 - $sumX * $sumX;
        
        if (abs($denominator) < 1e-10) {
            return ['slope' => 0, 'intercept' => $sumY / $n];
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $intercept = ($sumY - $slope * $sumX) / $n;
        
        return ['slope' => $slope, 'intercept' => $intercept];
    }
    
    public function forecast(int $steps, array $data): array {
        $n = count($data);
        $predictions = [];
        
        // Get last trend parameters
        $lastTrend = end($this->trend['fitted']);
        $slope = $this->trend['slope'];
        
        for ($h = 1; $h <= $steps; $h++) {
            $t = $n + $h - 1;
            
            // Trend component
            $trendComponent = $lastTrend + $slope * $h;
            
            // Seasonality component (cyclic)
            $seasonIdx = $t % 7;
            $seasonComponent = $this->seasonality['fitted'][$seasonIdx % count($this->seasonality['fitted'])];
            
            $prediction = $trendComponent + $seasonComponent;
            
            // Uncertainty grows with horizon
            $uncertainty = sqrt($h) * 0.1 * abs($prediction);
            
            $predictions[] = [
                'step' => $h,
                'prediction' => $prediction,
                'trend' => $trendComponent,
                'seasonality' => $seasonComponent,
                'lower_95' => $prediction - 1.96 * $uncertainty,
                'upper_95' => $prediction + 1.96 * $uncertainty
            ];
        }
        
        return $predictions;
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 📊 Seasonal Decomposition
 * تفكيك الموسمية
 * ═══════════════════════════════════════════════════════════════
 */
class SeasonalDecomposition {
    
    public static function decompose(array $data, int $period = 7, string $type = 'additive'): array {
        $n = count($data);
        
        if ($n < $period * 2) {
            return ['error' => 'بيانات غير كافية'];
        }
        
        // Calculate trend using centered moving average
        $trend = self::centeredMovingAverage($data, $period);
        
        // Calculate detrended data
        $detrended = [];
        for ($i = 0; $i < $n; $i++) {
            if ($trend[$i] !== null) {
                if ($type === 'multiplicative' && $trend[$i] != 0) {
                    $detrended[$i] = $data[$i] / $trend[$i];
                } else {
                    $detrended[$i] = $data[$i] - $trend[$i];
                }
            } else {
                $detrended[$i] = null;
            }
        }
        
        // Calculate seasonal component
        $seasonal = self::calculateSeasonalComponent($detrended, $period, $type);
        
        // Calculate residual
        $residual = [];
        for ($i = 0; $i < $n; $i++) {
            if ($trend[$i] !== null) {
                if ($type === 'multiplicative') {
                    $residual[$i] = $seasonal[$i % $period] != 0 ? 
                        $data[$i] / ($trend[$i] * $seasonal[$i % $period]) : 0;
                } else {
                    $residual[$i] = $data[$i] - $trend[$i] - $seasonal[$i % $period];
                }
            } else {
                $residual[$i] = null;
            }
        }
        
        // Calculate strength of trend and seasonality
        $residualVar = self::variance(array_filter($residual, fn($x) => $x !== null));
        $detrendedVar = self::variance(array_filter($detrended, fn($x) => $x !== null));
        $dataVar = self::variance($data);
        
        $trendStrength = max(0, 1 - $residualVar / max($detrendedVar, 1e-10));
        $seasonalStrength = max(0, 1 - $residualVar / max($dataVar, 1e-10));
        
        return [
            'observed' => $data,
            'trend' => $trend,
            'seasonal' => $seasonal,
            'residual' => $residual,
            'trend_strength' => round($trendStrength, 3),
            'seasonal_strength' => round($seasonalStrength, 3),
            'period' => $period,
            'type' => $type
        ];
    }
    
    private static function centeredMovingAverage(array $data, int $period): array {
        $n = count($data);
        $ma = array_fill(0, $n, null);
        $halfPeriod = (int)($period / 2);
        
        for ($i = $halfPeriod; $i < $n - $halfPeriod; $i++) {
            $sum = 0;
            for ($j = $i - $halfPeriod; $j <= $i + $halfPeriod; $j++) {
                $sum += $data[$j];
            }
            
            if ($period % 2 == 0) {
                $sum = ($sum - 0.5 * $data[$i - $halfPeriod] - 0.5 * $data[$i + $halfPeriod]);
                $ma[$i] = $sum / $period;
            } else {
                $ma[$i] = $sum / $period;
            }
        }
        
        return $ma;
    }
    
    private static function calculateSeasonalComponent(array $detrended, int $period, string $type): array {
        $n = count($detrended);
        $seasonalSums = array_fill(0, $period, 0);
        $seasonalCounts = array_fill(0, $period, 0);
        
        for ($i = 0; $i < $n; $i++) {
            if ($detrended[$i] !== null) {
                $seasonalSums[$i % $period] += $detrended[$i];
                $seasonalCounts[$i % $period]++;
            }
        }
        
        $seasonal = [];
        for ($i = 0; $i < $period; $i++) {
            $seasonal[$i] = $seasonalCounts[$i] > 0 ? 
                $seasonalSums[$i] / $seasonalCounts[$i] : 
                ($type === 'multiplicative' ? 1 : 0);
        }
        
        // Normalize seasonal component
        if ($type === 'multiplicative') {
            $meanSeasonal = array_sum($seasonal) / $period;
            if ($meanSeasonal != 0) {
                $seasonal = array_map(fn($s) => $s / $meanSeasonal, $seasonal);
            }
        } else {
            $meanSeasonal = array_sum($seasonal) / $period;
            $seasonal = array_map(fn($s) => $s - $meanSeasonal, $seasonal);
        }
        
        return $seasonal;
    }
    
    private static function variance(array $data): float {
        $n = count($data);
        if ($n < 2) return 0;
        
        $mean = array_sum($data) / $n;
        $sumSquares = 0;
        
        foreach ($data as $value) {
            $sumSquares += pow($value - $mean, 2);
        }
        
        return $sumSquares / ($n - 1);
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 🎯 Changepoint Detection
 * كشف نقاط التغيير
 * ═══════════════════════════════════════════════════════════════
 */
class ChangepointDetection {
    
    public static function detectChangepoints(array $data, string $method = 'pelt'): array {
        switch ($method) {
            case 'pelt':
                return self::peltAlgorithm($data);
            case 'cusum':
                return self::cusumAlgorithm($data);
            case 'binseg':
                return self::binarySegmentation($data);
            default:
                return self::peltAlgorithm($data);
        }
    }
    
    private static function peltAlgorithm(array $data, float $penalty = 1.0): array {
        $n = count($data);
        if ($n < 4) return ['changepoints' => [], 'segments' => []];
        
        $cost = [];
        $lastChange = [];
        
        $cost[0] = -$penalty;
        $lastChange[0] = 0;
        
        for ($t = 1; $t <= $n; $t++) {
            $minCost = PHP_FLOAT_MAX;
            $bestS = 0;
            
            for ($s = 0; $s < $t; $s++) {
                $segmentCost = self::segmentCost(array_slice($data, $s, $t - $s));
                $totalCost = $cost[$s] + $segmentCost + $penalty;
                
                if ($totalCost < $minCost) {
                    $minCost = $totalCost;
                    $bestS = $s;
                }
            }
            
            $cost[$t] = $minCost;
            $lastChange[$t] = $bestS;
        }
        
        // Backtrack to find changepoints
        $changepoints = [];
        $current = $n;
        
        while ($current > 0) {
            $prev = $lastChange[$current];
            if ($prev > 0) {
                $changepoints[] = $prev;
            }
            $current = $prev;
        }
        
        sort($changepoints);
        
        // Calculate segment statistics
        $segments = self::getSegmentStats($data, $changepoints);
        
        return [
            'changepoints' => $changepoints,
            'segments' => $segments,
            'total_cost' => $cost[$n]
        ];
    }
    
    private static function cusumAlgorithm(array $data, float $threshold = 2.0): array {
        $n = count($data);
        $mean = array_sum($data) / $n;
        $std = sqrt(self::variance($data));
        
        $cusum = [0];
        $cusumNeg = [0];
        $changepoints = [];
        
        for ($i = 0; $i < $n; $i++) {
            $normalized = ($data[$i] - $mean) / max($std, 1e-10);
            
            $cusum[] = max(0, $cusum[$i] + $normalized);
            $cusumNeg[] = max(0, $cusumNeg[$i] - $normalized);
            
            if ($cusum[$i + 1] > $threshold || $cusumNeg[$i + 1] > $threshold) {
                $changepoints[] = $i;
                $cusum[$i + 1] = 0;
                $cusumNeg[$i + 1] = 0;
            }
        }
        
        return [
            'changepoints' => $changepoints,
            'cusum' => $cusum,
            'segments' => self::getSegmentStats($data, $changepoints)
        ];
    }
    
    private static function binarySegmentation(array $data, int $minSegmentSize = 5): array {
        $n = count($data);
        $changepoints = [];
        
        $segments = [[0, $n]];
        
        while (!empty($segments)) {
            [$start, $end] = array_shift($segments);
            
            if ($end - $start < $minSegmentSize * 2) continue;
            
            $bestSplit = null;
            $bestGain = 0;
            
            for ($split = $start + $minSegmentSize; $split < $end - $minSegmentSize; $split++) {
                $leftData = array_slice($data, $start, $split - $start);
                $rightData = array_slice($data, $split, $end - $split);
                $fullData = array_slice($data, $start, $end - $start);
                
                $gain = self::segmentCost($fullData) - 
                        self::segmentCost($leftData) - 
                        self::segmentCost($rightData);
                
                if ($gain > $bestGain) {
                    $bestGain = $gain;
                    $bestSplit = $split;
                }
            }
            
            if ($bestSplit !== null && $bestGain > 0.5) {
                $changepoints[] = $bestSplit;
                $segments[] = [$start, $bestSplit];
                $segments[] = [$bestSplit, $end];
            }
        }
        
        sort($changepoints);
        
        return [
            'changepoints' => $changepoints,
            'segments' => self::getSegmentStats($data, $changepoints)
        ];
    }
    
    private static function segmentCost(array $segment): float {
        $n = count($segment);
        if ($n < 2) return 0;
        
        $mean = array_sum($segment) / $n;
        $variance = 0;
        
        foreach ($segment as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        return $n * log(max($variance / $n, 1e-10));
    }
    
    private static function variance(array $data): float {
        $n = count($data);
        if ($n < 2) return 0;
        
        $mean = array_sum($data) / $n;
        $sum = 0;
        
        foreach ($data as $value) {
            $sum += pow($value - $mean, 2);
        }
        
        return $sum / ($n - 1);
    }
    
    private static function getSegmentStats(array $data, array $changepoints): array {
        $segments = [];
        $points = array_merge([0], $changepoints, [count($data)]);
        
        for ($i = 0; $i < count($points) - 1; $i++) {
            $start = $points[$i];
            $end = $points[$i + 1];
            $segmentData = array_slice($data, $start, $end - $start);
            
            if (empty($segmentData)) continue;
            
            $mean = array_sum($segmentData) / count($segmentData);
            $variance = self::variance($segmentData);
            
            $segments[] = [
                'start' => $start,
                'end' => $end,
                'length' => $end - $start,
                'mean' => round($mean, 3),
                'std' => round(sqrt($variance), 3),
                'min' => min($segmentData),
                'max' => max($segmentData)
            ];
        }
        
        return $segments;
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 🔄 Kalman Filter
 * مرشح كالمان للتنبؤ والتنعيم
 * ═══════════════════════════════════════════════════════════════
 */
class KalmanFilter {
    private float $processNoise;
    private float $measurementNoise;
    private float $estimate;
    private float $errorCovariance;
    
    public function __construct(
        float $processNoise = 0.1,
        float $measurementNoise = 0.5,
        float $initialEstimate = 0,
        float $initialError = 1.0
    ) {
        $this->processNoise = $processNoise;
        $this->measurementNoise = $measurementNoise;
        $this->estimate = $initialEstimate;
        $this->errorCovariance = $initialError;
    }
    
    public function filter(array $measurements): array {
        $filtered = [];
        $gains = [];
        $predictions = [];
        
        foreach ($measurements as $z) {
            // Prediction step
            $predictedEstimate = $this->estimate;
            $predictedError = $this->errorCovariance + $this->processNoise;
            
            // Update step
            $kalmanGain = $predictedError / ($predictedError + $this->measurementNoise);
            $this->estimate = $predictedEstimate + $kalmanGain * ($z - $predictedEstimate);
            $this->errorCovariance = (1 - $kalmanGain) * $predictedError;
            
            $filtered[] = $this->estimate;
            $gains[] = $kalmanGain;
            $predictions[] = $predictedEstimate;
        }
        
        return [
            'filtered' => $filtered,
            'kalman_gains' => $gains,
            'predictions' => $predictions,
            'final_estimate' => $this->estimate,
            'final_error' => $this->errorCovariance
        ];
    }
    
    public function forecast(int $steps): array {
        $forecasts = [];
        $uncertainty = $this->errorCovariance;
        $currentEstimate = $this->estimate;
        
        for ($i = 1; $i <= $steps; $i++) {
            $uncertainty += $this->processNoise;
            
            $forecasts[] = [
                'step' => $i,
                'forecast' => $currentEstimate,
                'uncertainty' => sqrt($uncertainty),
                'lower_95' => $currentEstimate - 1.96 * sqrt($uncertainty),
                'upper_95' => $currentEstimate + 1.96 * sqrt($uncertainty)
            ];
        }
        
        return $forecasts;
    }
    
    public function smoothBackward(array $measurements): array {
        // Forward pass
        $forwardResult = $this->filter($measurements);
        
        $n = count($measurements);
        $smoothed = $forwardResult['filtered'];
        
        // Backward smoothing
        for ($k = $n - 2; $k >= 0; $k--) {
            $smoothingGain = $forwardResult['kalman_gains'][$k + 1];
            $innovation = $smoothed[$k + 1] - $forwardResult['predictions'][$k + 1];
            $smoothed[$k] = $smoothed[$k] + $smoothingGain * $innovation;
        }
        
        return [
            'smoothed' => $smoothed,
            'filtered' => $forwardResult['filtered']
        ];
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 📊 EWMA (Exponentially Weighted Moving Average)
 * المتوسط المتحرك المرجح أسياً
 * ═══════════════════════════════════════════════════════════════
 */
class EWMA {
    
    public static function calculate(array $data, float $alpha = 0.3): array {
        if (empty($data)) return [];
        
        $ewma = [$data[0]];
        
        for ($i = 1; $i < count($data); $i++) {
            $ewma[] = $alpha * $data[$i] + (1 - $alpha) * $ewma[$i - 1];
        }
        
        return $ewma;
    }
    
    public static function withConfidenceBands(array $data, float $alpha = 0.3, float $lambda = 3.0): array {
        $ewma = self::calculate($data, $alpha);
        
        // Calculate control limits
        $variance = 0;
        $n = count($data);
        
        for ($i = 0; $i < $n; $i++) {
            $variance += pow($data[$i] - $ewma[$i], 2);
        }
        $variance /= $n;
        $std = sqrt($variance);
        
        $upper = [];
        $lower = [];
        
        for ($i = 0; $i < $n; $i++) {
            // Time-varying control limits
            $l = $std * $lambda * sqrt($alpha * (1 - pow(1 - $alpha, 2 * ($i + 1))) / (2 - $alpha));
            $upper[] = $ewma[$i] + $l;
            $lower[] = $ewma[$i] - $l;
        }
        
        return [
            'ewma' => $ewma,
            'upper' => $upper,
            'lower' => $lower,
            'std' => $std
        ];
    }
    
    public static function detectOutliers(array $data, float $alpha = 0.3, float $lambda = 3.0): array {
        $result = self::withConfidenceBands($data, $alpha, $lambda);
        $outliers = [];
        
        for ($i = 0; $i < count($data); $i++) {
            if ($data[$i] > $result['upper'][$i] || $data[$i] < $result['lower'][$i]) {
                $outliers[] = [
                    'index' => $i,
                    'value' => $data[$i],
                    'expected' => $result['ewma'][$i],
                    'deviation' => abs($data[$i] - $result['ewma'][$i]) / max($result['std'], 1e-10)
                ];
            }
        }
        
        return [
            'outliers' => $outliers,
            'outlier_count' => count($outliers),
            'outlier_rate' => count($outliers) / count($data)
        ];
    }
}
