<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - MOOD SURVEY / استبيان المزاج                         ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  ميزات الصفحة:                                                               ║
 * ║  - تسجيل مزاج الموظف اليومي                                                  ║
 * ║  - تحليل أنماط المزاج عبر الوقت                                              ║
 * ║  - رؤى للإدارة                                                               ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

session_start();
require_once 'config/database.php';
require_once 'config/app.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title = 'استبيان المزاج';
$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// التحقق هل سجل المستخدم مزاجه اليوم
try {
    $stmt = $pdo->prepare("SELECT * FROM mood_surveys WHERE user_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$user_id, $today]);
    $today_mood = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $today_mood = null;
}

// جلب سجل المزاج للأسبوع الماضي
try {
    $stmt = $pdo->prepare("
        SELECT * FROM mood_surveys 
        WHERE user_id = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $mood_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $mood_history = [];
}

// حفظ المزاج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_mood'])) {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $mood_score = intval($_POST['mood_score']);
    $energy_level = intval($_POST['energy_level']);
    $stress_level = intval($_POST['stress_level']);
    $notes = htmlspecialchars(trim($_POST['notes'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    if ($mood_score >= 1 && $mood_score <= 5) {
        try {
            if ($today_mood) {
                // تحديث
                $stmt = $pdo->prepare("
                    UPDATE mood_surveys 
                    SET mood_score = ?, energy_level = ?, stress_level = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$mood_score, $energy_level, $stress_level, $notes, $today_mood['id']]);
            } else {
                // إضافة جديد
                $stmt = $pdo->prepare("
                    INSERT INTO mood_surveys (user_id, mood_score, energy_level, stress_level, notes)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $mood_score, $energy_level, $stress_level, $notes]);
            }
            
            header('Location: mood-survey.php?success=1');
            exit;
        } catch (Exception $e) {
            $error = 'حدث خطأ أثناء الحفظ';
        }
    }
}

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'includes/header.php';
?>

<style>
    .mood-selector {
        display: flex;
        justify-content: center;
        gap: 15px;
        margin: 20px 0;
        flex-wrap: wrap;
    }

    .mood-option {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        border: 3px solid #ddd;
        background: white;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
    }

    .mood-option:hover {
        transform: scale(1.1);
        border-color: var(--sarh-primary);
    }

    .mood-option.selected {
        border-color: var(--sarh-primary);
        background: var(--sarh-primary);
        transform: scale(1.15);
        box-shadow: 0 5px 20px rgba(255, 111, 0, 0.4);
    }

    .mood-option input {
        display: none;
    }

    .mood-label {
        text-align: center;
        font-size: 0.85rem;
        color: #666;
        margin-top: 5px;
    }

    .slider-container {
        margin: 25px 0;
    }

    .custom-slider {
        -webkit-appearance: none;
        width: 100%;
        height: 8px;
        border-radius: 5px;
        background: #e0e0e0;
        outline: none;
    }

    .custom-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: var(--sarh-primary);
        cursor: pointer;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }

    .slider-labels {
        display: flex;
        justify-content: space-between;
        margin-top: 8px;
        font-size: 0.8rem;
        color: #888;
    }

    .mood-history-item {
        display: flex;
        align-items: center;
        padding: 12px;
        border-bottom: 1px solid #eee;
        gap: 15px;
    }

    .mood-history-item:last-child {
        border-bottom: none;
    }

    .mood-emoji {
        font-size: 2rem;
    }

    .mood-date {
        font-size: 0.85rem;
        color: #888;
    }

    .mood-chart {
        height: 200px;
        background: #f8f9fa;
        border-radius: 10px;
        display: flex;
        align-items: flex-end;
        justify-content: space-around;
        padding: 20px 10px 10px;
    }

    .chart-bar {
        width: 30px;
        background: linear-gradient(to top, var(--sarh-primary), #ff9800);
        border-radius: 5px 5px 0 0;
        transition: height 0.5s ease;
    }

    .chart-day {
        text-align: center;
        font-size: 0.75rem;
        color: #666;
        margin-top: 5px;
    }

    .insights-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 20px;
    }

    .insight-item {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 10px 0;
        padding: 10px;
        background: rgba(255,255,255,0.15);
        border-radius: 10px;
    }

    .success-animation {
        animation: successPulse 0.5s ease;
    }

    @keyframes successPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
</style>

<div class="container py-4 mb-5">
    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-emoji-smile text-warning me-2"></i>
                كيف حالك اليوم؟
            </h4>
            <small class="text-muted"><?php echo date('l، j F Y', strtotime('now')); ?></small>
        </div>
        <a href="more.php" class="btn btn-sm btn-outline-secondary rounded-pill">
            <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show success-animation" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        تم حفظ مزاجك بنجاح! شكراً لمشاركتك 😊
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Mood Survey Form -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <form method="POST" id="moodForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="submit_mood" value="1">
                
                <!-- Mood Score -->
                <div class="text-center mb-4">
                    <h6 class="mb-3">ما هو مزاجك العام؟</h6>
                    <div class="mood-selector">
                        <?php 
                        $moods = [
                            1 => ['emoji' => '😞', 'label' => 'سيء جداً'],
                            2 => ['emoji' => '😔', 'label' => 'سيء'],
                            3 => ['emoji' => '😐', 'label' => 'عادي'],
                            4 => ['emoji' => '🙂', 'label' => 'جيد'],
                            5 => ['emoji' => '😄', 'label' => 'ممتاز']
                        ];
                        foreach ($moods as $score => $mood):
                            $checked = ($today_mood && $today_mood['mood_score'] == $score) ? 'checked' : '';
                            $selected = ($today_mood && $today_mood['mood_score'] == $score) ? 'selected' : '';
                        ?>
                        <div class="text-center">
                            <label class="mood-option <?php echo $selected; ?>">
                                <input type="radio" name="mood_score" value="<?php echo $score; ?>" <?php echo $checked; ?> required>
                                <?php echo $mood['emoji']; ?>
                            </label>
                            <div class="mood-label"><?php echo $mood['label']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Energy Level -->
                <div class="slider-container">
                    <label class="form-label">
                        <i class="bi bi-lightning-charge text-warning me-2"></i>
                        مستوى الطاقة
                    </label>
                    <input type="range" name="energy_level" class="custom-slider" min="1" max="5" 
                           value="<?php echo $today_mood['energy_level'] ?? 3; ?>">
                    <div class="slider-labels">
                        <span>منخفض 😴</span>
                        <span>متوسط 😊</span>
                        <span>مرتفع ⚡</span>
                    </div>
                </div>

                <!-- Stress Level -->
                <div class="slider-container">
                    <label class="form-label">
                        <i class="bi bi-thermometer-half text-danger me-2"></i>
                        مستوى الضغط
                    </label>
                    <input type="range" name="stress_level" class="custom-slider" min="1" max="5" 
                           value="<?php echo $today_mood['stress_level'] ?? 3; ?>">
                    <div class="slider-labels">
                        <span>منخفض 😌</span>
                        <span>متوسط 😐</span>
                        <span>مرتفع 😰</span>
                    </div>
                </div>

                <!-- Notes -->
                <div class="mb-4">
                    <label class="form-label">
                        <i class="bi bi-chat-text text-info me-2"></i>
                        ملاحظات (اختياري)
                    </label>
                    <textarea name="notes" class="form-control" rows="3" 
                              placeholder="هل تريد مشاركة شيء عن يومك؟"><?php echo $today_mood['notes'] ?? ''; ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-3 rounded-3">
                    <i class="bi bi-send me-2"></i>
                    <?php echo $today_mood ? 'تحديث المزاج' : 'حفظ المزاج'; ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Mood Insights -->
    <?php if (count($mood_history) >= 3): ?>
    <?php
        $avg_mood = round(array_sum(array_column($mood_history, 'mood_score')) / count($mood_history), 1);
        $avg_energy = round(array_sum(array_column($mood_history, 'energy_level')) / count($mood_history), 1);
        $avg_stress = round(array_sum(array_column($mood_history, 'stress_level')) / count($mood_history), 1);
    ?>
    <div class="insights-card mb-4">
        <h6 class="mb-3">
            <i class="bi bi-graph-up me-2"></i>
            رؤى الأسبوع
        </h6>
        <div class="insight-item">
            <span style="font-size: 1.5rem;">😊</span>
            <div>
                <strong>متوسط المزاج:</strong> <?php echo $avg_mood; ?>/5
                <div class="progress mt-1" style="height: 5px;">
                    <div class="progress-bar bg-light" style="width: <?php echo ($avg_mood/5)*100; ?>%"></div>
                </div>
            </div>
        </div>
        <div class="insight-item">
            <span style="font-size: 1.5rem;">⚡</span>
            <div>
                <strong>متوسط الطاقة:</strong> <?php echo $avg_energy; ?>/5
                <div class="progress mt-1" style="height: 5px;">
                    <div class="progress-bar bg-warning" style="width: <?php echo ($avg_energy/5)*100; ?>%"></div>
                </div>
            </div>
        </div>
        <div class="insight-item">
            <span style="font-size: 1.5rem;">😰</span>
            <div>
                <strong>متوسط الضغط:</strong> <?php echo $avg_stress; ?>/5
                <div class="progress mt-1" style="height: 5px;">
                    <div class="progress-bar bg-danger" style="width: <?php echo ($avg_stress/5)*100; ?>%"></div>
                </div>
            </div>
        </div>

        <?php if ($avg_stress > 3.5): ?>
        <div class="alert alert-warning mt-3 mb-0" style="background: rgba(255,193,7,0.2); border: none;">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <small>مستوى ضغطك مرتفع هذا الأسبوع. هل تحتاج للتحدث مع مديرك؟</small>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Mood History -->
    <?php if (!empty($mood_history)): ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0">
                <i class="bi bi-clock-history me-2"></i>
                سجل المزاج
            </h6>
        </div>
        <div class="card-body p-0">
            <?php foreach ($mood_history as $entry): ?>
            <div class="mood-history-item">
                <div class="mood-emoji">
                    <?php echo $moods[$entry['mood_score']]['emoji'] ?? '😐'; ?>
                </div>
                <div class="flex-grow-1">
                    <strong><?php echo $moods[$entry['mood_score']]['label'] ?? 'عادي'; ?></strong>
                    <div class="mood-date">
                        <?php echo date('l، j F', strtotime($entry['created_at'])); ?>
                        <?php if ($entry['notes']): ?>
                        <br><small class="text-muted">"<?php echo $entry['notes']; ?>"</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-end">
                    <small class="text-muted">
                        ⚡ <?php echo $entry['energy_level']; ?>
                        &nbsp;
                        😰 <?php echo $entry['stress_level']; ?>
                    </small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tips -->
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h6 class="text-primary mb-3">
                <i class="bi bi-lightbulb me-2"></i>
                نصائح لتحسين يومك
            </h6>
            <div class="row g-3">
                <div class="col-6">
                    <div class="d-flex gap-2 align-items-start">
                        <span style="font-size: 1.5rem;">🧘</span>
                        <small>خذ استراحات قصيرة كل ساعة</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="d-flex gap-2 align-items-start">
                        <span style="font-size: 1.5rem;">💧</span>
                        <small>حافظ على شرب الماء</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="d-flex gap-2 align-items-start">
                        <span style="font-size: 1.5rem;">🚶</span>
                        <small>تحرك وتمشى قليلاً</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="d-flex gap-2 align-items-start">
                        <span style="font-size: 1.5rem;">😊</span>
                        <small>ابتسم وكن إيجابياً</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Mood selector interaction
document.querySelectorAll('.mood-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.mood-option').forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');
    });
});

// Haptic feedback for sliders
document.querySelectorAll('.custom-slider').forEach(slider => {
    slider.addEventListener('input', function() {
        if (navigator.vibrate) {
            navigator.vibrate(10);
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
