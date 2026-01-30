<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════════════════════════
 * 🧠 محرك التعلم الآلي المتقدم - SARH ADVANCED ML ENGINE
 * ═══════════════════════════════════════════════════════════════════════════════════════════════
 * 
 * نظام ذكاء اصطناعي متقدم مبني بالكامل بـ PHP بدون مكتبات خارجية
 * يتضمن خوارزميات تعلم آلي وتعلم عميق متقدمة
 * 
 * الخوارزميات المضمنة:
 * - الشبكات العصبية (Neural Networks) - Perceptron متعدد الطبقات
 * - K-Means Clustering - تجميع البيانات
 * - Random Forest - غابات عشوائية للتنبؤ
 * - Decision Trees - أشجار القرار
 * - Naive Bayes - التصنيف البايزي
 * - Support Vector Machine (SVM) مبسط
 * - Gradient Boosting - تعزيز التدرج
 * - Ensemble Learning - التعلم الجماعي
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
 * 🧠 شبكة عصبية اصطناعية متعددة الطبقات
 * Multi-Layer Perceptron Neural Network
 * ═══════════════════════════════════════════════════════════════
 */
class NeuralNetwork {
    private array $layers;
    private array $weights;
    private array $biases;
    private float $learningRate;
    private string $activationFunction;
    
    public function __construct(array $layerSizes, float $learningRate = 0.1, string $activation = 'sigmoid') {
        $this->layers = $layerSizes;
        $this->learningRate = $learningRate;
        $this->activationFunction = $activation;
        $this->initializeWeights();
    }
    
    private function initializeWeights(): void {
        $this->weights = [];
        $this->biases = [];
        
        for ($i = 0; $i < count($this->layers) - 1; $i++) {
            $rows = $this->layers[$i + 1];
            $cols = $this->layers[$i];
            
            // Xavier initialization
            $scale = sqrt(2.0 / ($cols + $rows));
            
            $this->weights[$i] = [];
            $this->biases[$i] = [];
            
            for ($r = 0; $r < $rows; $r++) {
                $this->weights[$i][$r] = [];
                for ($c = 0; $c < $cols; $c++) {
                    $this->weights[$i][$r][$c] = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $scale;
                }
                $this->biases[$i][$r] = 0.0;
            }
        }
    }
    
    private function activate(float $x): float {
        switch ($this->activationFunction) {
            case 'sigmoid':
                return 1 / (1 + exp(-max(-500, min(500, $x))));
            case 'tanh':
                return tanh($x);
            case 'relu':
                return max(0, $x);
            case 'leaky_relu':
                return $x > 0 ? $x : 0.01 * $x;
            default:
                return 1 / (1 + exp(-$x));
        }
    }
    
    private function activateDerivative(float $x): float {
        switch ($this->activationFunction) {
            case 'sigmoid':
                $s = $this->activate($x);
                return $s * (1 - $s);
            case 'tanh':
                return 1 - pow(tanh($x), 2);
            case 'relu':
                return $x > 0 ? 1 : 0;
            case 'leaky_relu':
                return $x > 0 ? 1 : 0.01;
            default:
                $s = $this->activate($x);
                return $s * (1 - $s);
        }
    }
    
    public function forward(array $input): array {
        $activations = [$input];
        $current = $input;
        
        for ($layer = 0; $layer < count($this->weights); $layer++) {
            $next = [];
            for ($j = 0; $j < count($this->weights[$layer]); $j++) {
                $sum = $this->biases[$layer][$j];
                for ($k = 0; $k < count($current); $k++) {
                    $sum += $current[$k] * $this->weights[$layer][$j][$k];
                }
                $next[] = $this->activate($sum);
            }
            $activations[] = $next;
            $current = $next;
        }
        
        return $activations;
    }
    
    public function train(array $inputs, array $targets, int $epochs = 1000): array {
        $losses = [];
        
        for ($epoch = 0; $epoch < $epochs; $epoch++) {
            $totalLoss = 0;
            
            for ($sample = 0; $sample < count($inputs); $sample++) {
                $activations = $this->forward($inputs[$sample]);
                $output = end($activations);
                $target = $targets[$sample];
                
                // حساب الخسارة
                for ($i = 0; $i < count($output); $i++) {
                    $totalLoss += pow($target[$i] - $output[$i], 2);
                }
                
                // Backpropagation
                $deltas = [];
                
                // Output layer delta
                $outputDelta = [];
                for ($i = 0; $i < count($output); $i++) {
                    $error = $target[$i] - $output[$i];
                    $outputDelta[] = $error * $this->activateDerivative($output[$i]);
                }
                $deltas[] = $outputDelta;
                
                // Hidden layers deltas
                for ($layer = count($this->weights) - 2; $layer >= 0; $layer--) {
                    $layerDelta = [];
                    $nextDelta = $deltas[0];
                    
                    for ($j = 0; $j < count($activations[$layer + 1]); $j++) {
                        $error = 0;
                        for ($k = 0; $k < count($nextDelta); $k++) {
                            $error += $nextDelta[$k] * $this->weights[$layer + 1][$k][$j];
                        }
                        $layerDelta[] = $error * $this->activateDerivative($activations[$layer + 1][$j]);
                    }
                    array_unshift($deltas, $layerDelta);
                }
                
                // Update weights
                for ($layer = 0; $layer < count($this->weights); $layer++) {
                    for ($j = 0; $j < count($this->weights[$layer]); $j++) {
                        for ($k = 0; $k < count($this->weights[$layer][$j]); $k++) {
                            $this->weights[$layer][$j][$k] += 
                                $this->learningRate * $deltas[$layer][$j] * $activations[$layer][$k];
                        }
                        $this->biases[$layer][$j] += $this->learningRate * $deltas[$layer][$j];
                    }
                }
            }
            
            $losses[] = $totalLoss / count($inputs);
            
            // Early stopping
            if ($epoch > 100 && $losses[$epoch] < 0.001) {
                break;
            }
        }
        
        return ['losses' => $losses, 'final_loss' => end($losses)];
    }
    
    public function predict(array $input): array {
        $activations = $this->forward($input);
        return end($activations);
    }
    
    public function getWeights(): array {
        return $this->weights;
    }
    
    public function setWeights(array $weights): void {
        $this->weights = $weights;
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 📊 K-Means Clustering Algorithm
 * خوارزمية التجميع K-Means
 * ═══════════════════════════════════════════════════════════════
 */
class KMeansClustering {
    private int $k;
    private int $maxIterations;
    private array $centroids;
    private array $clusters;
    
    public function __construct(int $k = 3, int $maxIterations = 100) {
        $this->k = $k;
        $this->maxIterations = $maxIterations;
        $this->centroids = [];
        $this->clusters = [];
    }
    
    public function fit(array $data): array {
        if (empty($data)) return ['clusters' => [], 'centroids' => []];
        
        $n = count($data);
        $dim = count($data[0]);
        
        // Initialize centroids using K-Means++
        $this->initializeCentroidsPlusPlus($data);
        
        $prevCentroids = [];
        
        for ($iteration = 0; $iteration < $this->maxIterations; $iteration++) {
            // Assign points to clusters
            $this->clusters = array_fill(0, $this->k, []);
            
            foreach ($data as $idx => $point) {
                $minDist = PHP_FLOAT_MAX;
                $closestCentroid = 0;
                
                foreach ($this->centroids as $cIdx => $centroid) {
                    $dist = $this->euclideanDistance($point, $centroid);
                    if ($dist < $minDist) {
                        $minDist = $dist;
                        $closestCentroid = $cIdx;
                    }
                }
                
                $this->clusters[$closestCentroid][] = $idx;
            }
            
            // Update centroids
            $prevCentroids = $this->centroids;
            
            foreach ($this->clusters as $cIdx => $cluster) {
                if (empty($cluster)) continue;
                
                $newCentroid = array_fill(0, $dim, 0);
                foreach ($cluster as $pointIdx) {
                    for ($d = 0; $d < $dim; $d++) {
                        $newCentroid[$d] += $data[$pointIdx][$d];
                    }
                }
                
                for ($d = 0; $d < $dim; $d++) {
                    $newCentroid[$d] /= count($cluster);
                }
                
                $this->centroids[$cIdx] = $newCentroid;
            }
            
            // Check convergence
            if ($this->hasConverged($prevCentroids, $this->centroids)) {
                break;
            }
        }
        
        // Calculate cluster statistics
        return $this->getClusterStats($data);
    }
    
    private function initializeCentroidsPlusPlus(array $data): void {
        $n = count($data);
        
        // First centroid: random point
        $this->centroids = [$data[mt_rand(0, $n - 1)]];
        
        // Remaining centroids
        for ($c = 1; $c < $this->k; $c++) {
            $distances = [];
            $totalDist = 0;
            
            foreach ($data as $point) {
                $minDist = PHP_FLOAT_MAX;
                foreach ($this->centroids as $centroid) {
                    $dist = $this->euclideanDistance($point, $centroid);
                    $minDist = min($minDist, $dist);
                }
                $distances[] = $minDist * $minDist;
                $totalDist += $minDist * $minDist;
            }
            
            // Weighted random selection
            $threshold = (mt_rand() / mt_getrandmax()) * $totalDist;
            $cumulative = 0;
            
            foreach ($data as $idx => $point) {
                $cumulative += $distances[$idx];
                if ($cumulative >= $threshold) {
                    $this->centroids[] = $point;
                    break;
                }
            }
        }
    }
    
    private function euclideanDistance(array $a, array $b): float {
        $sum = 0;
        for ($i = 0; $i < count($a); $i++) {
            $sum += pow($a[$i] - $b[$i], 2);
        }
        return sqrt($sum);
    }
    
    private function hasConverged(array $old, array $new, float $tolerance = 1e-6): bool {
        if (empty($old)) return false;
        
        foreach ($old as $idx => $centroid) {
            if ($this->euclideanDistance($centroid, $new[$idx]) > $tolerance) {
                return false;
            }
        }
        return true;
    }
    
    private function getClusterStats(array $data): array {
        $stats = [];
        
        foreach ($this->clusters as $cIdx => $cluster) {
            $clusterData = array_map(fn($idx) => $data[$idx], $cluster);
            
            // Calculate within-cluster sum of squares (WCSS)
            $wcss = 0;
            foreach ($cluster as $pointIdx) {
                $wcss += pow($this->euclideanDistance($data[$pointIdx], $this->centroids[$cIdx]), 2);
            }
            
            $stats[] = [
                'cluster_id' => $cIdx,
                'size' => count($cluster),
                'centroid' => $this->centroids[$cIdx],
                'members' => $cluster,
                'wcss' => $wcss,
                'density' => count($cluster) > 0 ? $wcss / count($cluster) : 0
            ];
        }
        
        return [
            'clusters' => $stats,
            'centroids' => $this->centroids,
            'total_wcss' => array_sum(array_column($stats, 'wcss'))
        ];
    }
    
    public function predict(array $point): int {
        $minDist = PHP_FLOAT_MAX;
        $closestCentroid = 0;
        
        foreach ($this->centroids as $cIdx => $centroid) {
            $dist = $this->euclideanDistance($point, $centroid);
            if ($dist < $minDist) {
                $minDist = $dist;
                $closestCentroid = $cIdx;
            }
        }
        
        return $closestCentroid;
    }
    
    public function getCentroids(): array {
        return $this->centroids;
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 🌲 Decision Tree & Random Forest
 * شجرة القرار والغابات العشوائية
 * ═══════════════════════════════════════════════════════════════
 */
class DecisionTree {
    private int $maxDepth;
    private int $minSamples;
    private ?array $tree;
    
    public function __construct(int $maxDepth = 10, int $minSamples = 2) {
        $this->maxDepth = $maxDepth;
        $this->minSamples = $minSamples;
        $this->tree = null;
    }
    
    public function fit(array $X, array $y): void {
        $this->tree = $this->buildTree($X, $y, 0);
    }
    
    private function buildTree(array $X, array $y, int $depth): array {
        $nSamples = count($y);
        $nFeatures = !empty($X) ? count($X[0]) : 0;
        
        // Check stopping criteria
        if ($depth >= $this->maxDepth || $nSamples < $this->minSamples || count(array_unique($y)) === 1) {
            return ['type' => 'leaf', 'value' => $this->mostCommonClass($y)];
        }
        
        // Find best split
        $bestGain = -PHP_FLOAT_MAX;
        $bestFeature = 0;
        $bestThreshold = 0;
        
        for ($feature = 0; $feature < $nFeatures; $feature++) {
            $values = array_column($X, $feature);
            $thresholds = array_unique($values);
            
            foreach ($thresholds as $threshold) {
                $gain = $this->informationGain($X, $y, $feature, $threshold);
                if ($gain > $bestGain) {
                    $bestGain = $gain;
                    $bestFeature = $feature;
                    $bestThreshold = $threshold;
                }
            }
        }
        
        if ($bestGain <= 0) {
            return ['type' => 'leaf', 'value' => $this->mostCommonClass($y)];
        }
        
        // Split data
        $leftX = []; $leftY = [];
        $rightX = []; $rightY = [];
        
        for ($i = 0; $i < $nSamples; $i++) {
            if ($X[$i][$bestFeature] <= $bestThreshold) {
                $leftX[] = $X[$i];
                $leftY[] = $y[$i];
            } else {
                $rightX[] = $X[$i];
                $rightY[] = $y[$i];
            }
        }
        
        if (empty($leftY) || empty($rightY)) {
            return ['type' => 'leaf', 'value' => $this->mostCommonClass($y)];
        }
        
        return [
            'type' => 'node',
            'feature' => $bestFeature,
            'threshold' => $bestThreshold,
            'left' => $this->buildTree($leftX, $leftY, $depth + 1),
            'right' => $this->buildTree($rightX, $rightY, $depth + 1)
        ];
    }
    
    private function informationGain(array $X, array $y, int $feature, float $threshold): float {
        $parentEntropy = $this->entropy($y);
        
        $leftY = []; $rightY = [];
        for ($i = 0; $i < count($y); $i++) {
            if ($X[$i][$feature] <= $threshold) {
                $leftY[] = $y[$i];
            } else {
                $rightY[] = $y[$i];
            }
        }
        
        if (empty($leftY) || empty($rightY)) return 0;
        
        $n = count($y);
        $childEntropy = (count($leftY) / $n) * $this->entropy($leftY) +
                        (count($rightY) / $n) * $this->entropy($rightY);
        
        return $parentEntropy - $childEntropy;
    }
    
    private function entropy(array $y): float {
        $counts = array_count_values($y);
        $n = count($y);
        $entropy = 0;
        
        foreach ($counts as $count) {
            $p = $count / $n;
            if ($p > 0) {
                $entropy -= $p * log($p, 2);
            }
        }
        
        return $entropy;
    }
    
    private function mostCommonClass(array $y): mixed {
        $counts = array_count_values($y);
        arsort($counts);
        return array_key_first($counts);
    }
    
    public function predict(array $x): mixed {
        return $this->traverse($this->tree, $x);
    }
    
    private function traverse(array $node, array $x): mixed {
        if ($node['type'] === 'leaf') {
            return $node['value'];
        }
        
        if ($x[$node['feature']] <= $node['threshold']) {
            return $this->traverse($node['left'], $x);
        } else {
            return $this->traverse($node['right'], $x);
        }
    }
    
    public function getTree(): ?array {
        return $this->tree;
    }
}

class RandomForest {
    private int $nTrees;
    private int $maxDepth;
    private float $sampleRatio;
    private array $trees;
    
    public function __construct(int $nTrees = 10, int $maxDepth = 10, float $sampleRatio = 0.8) {
        $this->nTrees = $nTrees;
        $this->maxDepth = $maxDepth;
        $this->sampleRatio = $sampleRatio;
        $this->trees = [];
    }
    
    public function fit(array $X, array $y): void {
        $n = count($X);
        $sampleSize = (int)($n * $this->sampleRatio);
        
        for ($t = 0; $t < $this->nTrees; $t++) {
            // Bootstrap sampling
            $indices = [];
            for ($i = 0; $i < $sampleSize; $i++) {
                $indices[] = mt_rand(0, $n - 1);
            }
            
            $sampleX = array_map(fn($i) => $X[$i], $indices);
            $sampleY = array_map(fn($i) => $y[$i], $indices);
            
            $tree = new DecisionTree($this->maxDepth);
            $tree->fit($sampleX, $sampleY);
            $this->trees[] = $tree;
        }
    }
    
    public function predict(array $x): mixed {
        $predictions = [];
        
        foreach ($this->trees as $tree) {
            $predictions[] = $tree->predict($x);
        }
        
        // Majority voting
        $counts = array_count_values($predictions);
        arsort($counts);
        return array_key_first($counts);
    }
    
    public function predictProba(array $x): array {
        $predictions = [];
        
        foreach ($this->trees as $tree) {
            $predictions[] = $tree->predict($x);
        }
        
        $counts = array_count_values($predictions);
        $total = count($predictions);
        
        $proba = [];
        foreach ($counts as $class => $count) {
            $proba[$class] = $count / $total;
        }
        
        return $proba;
    }
    
    public function featureImportance(array $X, array $y): array {
        $nFeatures = count($X[0]);
        $baseScore = $this->score($X, $y);
        $importance = [];
        
        for ($f = 0; $f < $nFeatures; $f++) {
            // Permute feature
            $permutedX = $X;
            $values = array_column($X, $f);
            shuffle($values);
            
            foreach ($permutedX as $i => &$row) {
                $row[$f] = $values[$i];
            }
            
            $permutedScore = $this->score($permutedX, $y);
            $importance[$f] = $baseScore - $permutedScore;
        }
        
        return $importance;
    }
    
    public function score(array $X, array $y): float {
        $correct = 0;
        for ($i = 0; $i < count($X); $i++) {
            if ($this->predict($X[$i]) == $y[$i]) {
                $correct++;
            }
        }
        return $correct / count($X);
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 📈 Naive Bayes Classifier
 * المصنف البايزي الساذج
 * ═══════════════════════════════════════════════════════════════
 */
class NaiveBayes {
    private array $classPriors;
    private array $featureMeans;
    private array $featureVariances;
    private array $classes;
    
    public function fit(array $X, array $y): void {
        $this->classes = array_unique($y);
        $n = count($y);
        
        foreach ($this->classes as $class) {
            // Filter samples for this class
            $classX = [];
            foreach ($X as $i => $sample) {
                if ($y[$i] === $class) {
                    $classX[] = $sample;
                }
            }
            
            $classCount = count($classX);
            $this->classPriors[$class] = $classCount / $n;
            
            // Calculate mean and variance for each feature
            $nFeatures = count($X[0]);
            $this->featureMeans[$class] = [];
            $this->featureVariances[$class] = [];
            
            for ($f = 0; $f < $nFeatures; $f++) {
                $values = array_column($classX, $f);
                $mean = array_sum($values) / $classCount;
                
                $variance = 0;
                foreach ($values as $v) {
                    $variance += pow($v - $mean, 2);
                }
                $variance = $classCount > 1 ? $variance / ($classCount - 1) : 1e-9;
                $variance = max($variance, 1e-9); // Prevent division by zero
                
                $this->featureMeans[$class][$f] = $mean;
                $this->featureVariances[$class][$f] = $variance;
            }
        }
    }
    
    public function predict(array $x): mixed {
        $proba = $this->predictProba($x);
        arsort($proba);
        return array_key_first($proba);
    }
    
    public function predictProba(array $x): array {
        $proba = [];
        
        foreach ($this->classes as $class) {
            $logProb = log($this->classPriors[$class]);
            
            foreach ($x as $f => $value) {
                $mean = $this->featureMeans[$class][$f];
                $variance = $this->featureVariances[$class][$f];
                
                // Gaussian probability
                $logProb += $this->logGaussianPdf($value, $mean, $variance);
            }
            
            $proba[$class] = $logProb;
        }
        
        // Convert log probabilities to probabilities
        $maxLog = max($proba);
        $sum = 0;
        foreach ($proba as &$p) {
            $p = exp($p - $maxLog);
            $sum += $p;
        }
        foreach ($proba as &$p) {
            $p /= $sum;
        }
        
        return $proba;
    }
    
    private function logGaussianPdf(float $x, float $mean, float $variance): float {
        return -0.5 * (log(2 * M_PI * $variance) + pow($x - $mean, 2) / $variance);
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 🎯 Gradient Boosting
 * تعزيز التدرج
 * ═══════════════════════════════════════════════════════════════
 */
class GradientBoosting {
    private int $nEstimators;
    private float $learningRate;
    private int $maxDepth;
    private array $trees;
    private float $initialPrediction;
    
    public function __construct(int $nEstimators = 50, float $learningRate = 0.1, int $maxDepth = 3) {
        $this->nEstimators = $nEstimators;
        $this->learningRate = $learningRate;
        $this->maxDepth = $maxDepth;
        $this->trees = [];
    }
    
    public function fit(array $X, array $y): void {
        $this->initialPrediction = array_sum($y) / count($y);
        $predictions = array_fill(0, count($y), $this->initialPrediction);
        
        for ($i = 0; $i < $this->nEstimators; $i++) {
            // Calculate residuals
            $residuals = [];
            for ($j = 0; $j < count($y); $j++) {
                $residuals[] = $y[$j] - $predictions[$j];
            }
            
            // Fit tree on residuals
            $tree = new DecisionTree($this->maxDepth);
            $tree->fit($X, $residuals);
            $this->trees[] = $tree;
            
            // Update predictions
            for ($j = 0; $j < count($X); $j++) {
                $predictions[$j] += $this->learningRate * $tree->predict($X[$j]);
            }
        }
    }
    
    public function predict(array $x): float {
        $prediction = $this->initialPrediction;
        
        foreach ($this->trees as $tree) {
            $prediction += $this->learningRate * $tree->predict($x);
        }
        
        return $prediction;
    }
    
    public function predictClass(array $x): int {
        return $this->predict($x) >= 0.5 ? 1 : 0;
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 🔗 خوارزمية Markov Chain
 * سلسلة ماركوف للتنبؤ
 * ═══════════════════════════════════════════════════════════════
 */
class MarkovChain {
    private array $transitionMatrix;
    private array $states;
    private int $order;
    
    public function __construct(int $order = 1) {
        $this->order = $order;
        $this->transitionMatrix = [];
        $this->states = [];
    }
    
    public function fit(array $sequence): void {
        $this->states = array_unique($sequence);
        
        // Initialize transition counts
        foreach ($this->states as $from) {
            $this->transitionMatrix[$from] = [];
            foreach ($this->states as $to) {
                $this->transitionMatrix[$from][$to] = 0;
            }
        }
        
        // Count transitions
        for ($i = 0; $i < count($sequence) - $this->order; $i++) {
            $from = $sequence[$i];
            $to = $sequence[$i + $this->order];
            $this->transitionMatrix[$from][$to]++;
        }
        
        // Normalize to probabilities
        foreach ($this->transitionMatrix as $from => &$transitions) {
            $total = array_sum($transitions);
            if ($total > 0) {
                foreach ($transitions as &$count) {
                    $count /= $total;
                }
            }
        }
    }
    
    public function predictNext(string $currentState): array {
        if (!isset($this->transitionMatrix[$currentState])) {
            return ['state' => null, 'probability' => 0];
        }
        
        $transitions = $this->transitionMatrix[$currentState];
        arsort($transitions);
        
        return [
            'most_likely' => array_key_first($transitions),
            'probability' => reset($transitions),
            'all_probabilities' => $transitions
        ];
    }
    
    public function generateSequence(string $startState, int $length): array {
        $sequence = [$startState];
        $current = $startState;
        
        for ($i = 0; $i < $length - 1; $i++) {
            $next = $this->sampleNext($current);
            if ($next === null) break;
            $sequence[] = $next;
            $current = $next;
        }
        
        return $sequence;
    }
    
    private function sampleNext(string $currentState): ?string {
        if (!isset($this->transitionMatrix[$currentState])) {
            return null;
        }
        
        $rand = mt_rand() / mt_getrandmax();
        $cumulative = 0;
        
        foreach ($this->transitionMatrix[$currentState] as $state => $prob) {
            $cumulative += $prob;
            if ($rand <= $cumulative) {
                return $state;
            }
        }
        
        return array_key_first($this->transitionMatrix[$currentState]);
    }
    
    public function getTransitionMatrix(): array {
        return $this->transitionMatrix;
    }
    
    public function getStationaryDistribution(): array {
        // Power iteration method
        $n = count($this->states);
        $distribution = array_fill_keys($this->states, 1 / $n);
        
        for ($iter = 0; $iter < 100; $iter++) {
            $newDist = [];
            foreach ($this->states as $to) {
                $newDist[$to] = 0;
                foreach ($this->states as $from) {
                    $newDist[$to] += $distribution[$from] * ($this->transitionMatrix[$from][$to] ?? 0);
                }
            }
            
            // Check convergence
            $diff = 0;
            foreach ($this->states as $s) {
                $diff += abs($newDist[$s] - $distribution[$s]);
            }
            
            $distribution = $newDist;
            
            if ($diff < 1e-9) break;
        }
        
        return $distribution;
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 📊 Principal Component Analysis (PCA)
 * تحليل المكونات الرئيسية
 * ═══════════════════════════════════════════════════════════════
 */
class PCA {
    private int $nComponents;
    private array $components;
    private array $mean;
    private array $explainedVariance;
    
    public function __construct(int $nComponents = 2) {
        $this->nComponents = $nComponents;
    }
    
    public function fit(array $X): void {
        $n = count($X);
        $d = count($X[0]);
        
        // Calculate mean
        $this->mean = array_fill(0, $d, 0);
        foreach ($X as $sample) {
            for ($i = 0; $i < $d; $i++) {
                $this->mean[$i] += $sample[$i];
            }
        }
        for ($i = 0; $i < $d; $i++) {
            $this->mean[$i] /= $n;
        }
        
        // Center data
        $centered = [];
        foreach ($X as $sample) {
            $centeredSample = [];
            for ($i = 0; $i < $d; $i++) {
                $centeredSample[] = $sample[$i] - $this->mean[$i];
            }
            $centered[] = $centeredSample;
        }
        
        // Calculate covariance matrix
        $cov = [];
        for ($i = 0; $i < $d; $i++) {
            $cov[$i] = [];
            for ($j = 0; $j < $d; $j++) {
                $sum = 0;
                foreach ($centered as $sample) {
                    $sum += $sample[$i] * $sample[$j];
                }
                $cov[$i][$j] = $sum / ($n - 1);
            }
        }
        
        // Power iteration for eigenvectors
        $this->components = [];
        $this->explainedVariance = [];
        
        for ($c = 0; $c < min($this->nComponents, $d); $c++) {
            $eigenvector = $this->powerIteration($cov, 100);
            $eigenvalue = $this->rayleighQuotient($cov, $eigenvector);
            
            $this->components[] = $eigenvector;
            $this->explainedVariance[] = $eigenvalue;
            
            // Deflate covariance matrix
            for ($i = 0; $i < $d; $i++) {
                for ($j = 0; $j < $d; $j++) {
                    $cov[$i][$j] -= $eigenvalue * $eigenvector[$i] * $eigenvector[$j];
                }
            }
        }
    }
    
    private function powerIteration(array $matrix, int $iterations): array {
        $d = count($matrix);
        $v = [];
        for ($i = 0; $i < $d; $i++) {
            $v[] = mt_rand() / mt_getrandmax();
        }
        
        for ($iter = 0; $iter < $iterations; $iter++) {
            // Matrix-vector multiplication
            $newV = array_fill(0, $d, 0);
            for ($i = 0; $i < $d; $i++) {
                for ($j = 0; $j < $d; $j++) {
                    $newV[$i] += $matrix[$i][$j] * $v[$j];
                }
            }
            
            // Normalize
            $norm = 0;
            foreach ($newV as $val) {
                $norm += $val * $val;
            }
            $norm = sqrt($norm);
            
            if ($norm > 0) {
                for ($i = 0; $i < $d; $i++) {
                    $newV[$i] /= $norm;
                }
            }
            
            $v = $newV;
        }
        
        return $v;
    }
    
    private function rayleighQuotient(array $matrix, array $v): float {
        $d = count($v);
        $av = array_fill(0, $d, 0);
        
        for ($i = 0; $i < $d; $i++) {
            for ($j = 0; $j < $d; $j++) {
                $av[$i] += $matrix[$i][$j] * $v[$j];
            }
        }
        
        $vav = 0;
        $vv = 0;
        for ($i = 0; $i < $d; $i++) {
            $vav += $v[$i] * $av[$i];
            $vv += $v[$i] * $v[$i];
        }
        
        return $vv > 0 ? $vav / $vv : 0;
    }
    
    public function transform(array $X): array {
        $transformed = [];
        
        foreach ($X as $sample) {
            $centered = [];
            for ($i = 0; $i < count($sample); $i++) {
                $centered[] = $sample[$i] - $this->mean[$i];
            }
            
            $projected = [];
            foreach ($this->components as $component) {
                $dot = 0;
                for ($i = 0; $i < count($centered); $i++) {
                    $dot += $centered[$i] * $component[$i];
                }
                $projected[] = $dot;
            }
            
            $transformed[] = $projected;
        }
        
        return $transformed;
    }
    
    public function getExplainedVarianceRatio(): array {
        $total = array_sum($this->explainedVariance);
        return array_map(fn($v) => $total > 0 ? $v / $total : 0, $this->explainedVariance);
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 🎲 Ensemble Learning - التعلم الجماعي
 * ═══════════════════════════════════════════════════════════════
 */
class EnsembleLearning {
    private array $models;
    private array $weights;
    private string $votingMethod;
    
    public function __construct(string $votingMethod = 'soft') {
        $this->models = [];
        $this->weights = [];
        $this->votingMethod = $votingMethod;
    }
    
    public function addModel($model, float $weight = 1.0): void {
        $this->models[] = $model;
        $this->weights[] = $weight;
    }
    
    public function predict(array $x): mixed {
        if ($this->votingMethod === 'hard') {
            return $this->hardVoting($x);
        } else {
            return $this->softVoting($x);
        }
    }
    
    private function hardVoting(array $x): mixed {
        $votes = [];
        
        foreach ($this->models as $i => $model) {
            $prediction = $model->predict($x);
            if (!isset($votes[$prediction])) {
                $votes[$prediction] = 0;
            }
            $votes[$prediction] += $this->weights[$i];
        }
        
        arsort($votes);
        return array_key_first($votes);
    }
    
    private function softVoting(array $x): mixed {
        $probaSums = [];
        $totalWeight = array_sum($this->weights);
        
        foreach ($this->models as $i => $model) {
            if (method_exists($model, 'predictProba')) {
                $proba = $model->predictProba($x);
                foreach ($proba as $class => $p) {
                    if (!isset($probaSums[$class])) {
                        $probaSums[$class] = 0;
                    }
                    $probaSums[$class] += $p * $this->weights[$i] / $totalWeight;
                }
            } else {
                $prediction = $model->predict($x);
                if (!isset($probaSums[$prediction])) {
                    $probaSums[$prediction] = 0;
                }
                $probaSums[$prediction] += $this->weights[$i] / $totalWeight;
            }
        }
        
        arsort($probaSums);
        return array_key_first($probaSums);
    }
    
    public function getModels(): array {
        return $this->models;
    }
}
