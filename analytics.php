<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * 📊 لوحة التحليلات والتنبؤ الفائقة
 * Super Advanced Analytics & Prediction Dashboard
 * ═══════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/analytics_engine.php';
require_once INCLUDES_PATH . '/super_analytics.php';

check_login();

$pageTitle = 'التحليلات المتقدمة';
$currentPage = 'analytics';
$bodyClass = 'analytics-page';

$userId = current_user_id();
$roleLevel = current_role_level();
$branchId = $_SESSION['branch_id'] ?? 0;

// تحديد الموظف المراد تحليله
$targetUserId = $userId;
if ($roleLevel >= 3 && isset($_GET['user_id'])) {
    $targetUserId = (int) $_GET['user_id'];
}

// التبويب النشط
$activeTab = $_GET['tab'] ?? 'overview';

// جلب بيانات التحليل
try {
    // التحليل الفائق
    $ultraAnalysis = SuperAnalytics::ultraAnalysis($targetUserId, 90);
    
    // التحليلات الأساسية من المحرك القديم
    $basicAnalysis = AnalyticsEngine::comprehensivePerformanceAnalysis($targetUserId);
    
    // تحليل الفرع (للمدراء)
    $branchAnalysis = null;
    if ($roleLevel >= 3 && $branchId > 0) {
        $branchAnalysis = SuperAnalytics::branchUltraAnalysis($branchId, 30);
    }
    
    // جلب بيانات الموظف المستهدف
    $targetUser = Database::fetchOne(
        "SELECT u.*, b.name as branch_name, r.name as role_name 
         FROM users u 
         LEFT JOIN branches b ON u.branch_id = b.id 
         LEFT JOIN roles r ON u.role_id = r.id 
         WHERE u.id = :id",
        ['id' => $targetUserId]
    );
    
} catch (Exception $e) {
    $error = 'خطأ في تحميل البيانات: ' . $e->getMessage();
    error_log("Analytics Error: " . $e->getMessage());
}

include INCLUDES_PATH . '/header.php';
?>

<style>
/* ═══════════════════════════════════════════════════════════════ */
/* 🎨 تصميم صفحة التحليلات المتقدمة */
/* ═══════════════════════════════════════════════════════════════ */

:root {
    --analytics-primary: #6366f1;
    --analytics-secondary: #8b5cf6;
    --analytics-success: #10b981;
    --analytics-warning: #f59e0b;
    --analytics-danger: #ef4444;
    --analytics-info: #0ea5e9;
    --analytics-dark: #1e1b4b;
    --analytics-gradient: linear-gradient(135deg, #6366f1, #8b5cf6, #a855f7);
}

.analytics-page {
    background: linear-gradient(180deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
    min-height: 100vh;
}

/* الهيدر الرئيسي */
.super-header {
    background: var(--analytics-gradient);
    padding: 2rem;
    border-radius: 24px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(99, 102, 241, 0.3);
}

.super-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -30%;
    width: 80%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 50%);
    animation: float 15s ease-in-out infinite;
}

.super-header::after {
    content: '';
    position: absolute;
    bottom: -50%;
    left: -20%;
    width: 60%;
    height: 150%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 40%);
    animation: float 20s ease-in-out infinite reverse;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(5deg); }
}

.super-header h1 {
    position: relative;
    z-index: 1;
    color: white;
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.super-header p {
    position: relative;
    z-index: 1;
    color: rgba(255,255,255,0.85);
    margin: 0.5rem 0 0;
}

.ai-badge {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: 1px solid rgba(255,255,255,0.3);
}

/* التبويبات */
.analytics-tabs {
    display: flex;
    gap: 0.5rem;
    background: rgba(255,255,255,0.05);
    padding: 0.5rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    overflow-x: auto;
    backdrop-filter: blur(10px);
}

.tab-btn {
    padding: 0.75rem 1.5rem;
    border: none;
    background: transparent;
    color: rgba(255,255,255,0.6);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    white-space: nowrap;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tab-btn:hover {
    background: rgba(255,255,255,0.1);
    color: white;
}

.tab-btn.active {
    background: var(--analytics-gradient);
    color: white;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
}

/* بطاقات KPI */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.kpi-card {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid rgba(255,255,255,0.1);
    position: relative;
    overflow: hidden;
    transition: transform 0.3s, box-shadow 0.3s;
}

.kpi-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.3);
}

.kpi-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--analytics-gradient);
}

.kpi-card.success::before { background: linear-gradient(90deg, #10b981, #34d399); }
.kpi-card.warning::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.kpi-card.danger::before { background: linear-gradient(90deg, #ef4444, #f87171); }
.kpi-card.info::before { background: linear-gradient(90deg, #0ea5e9, #38bdf8); }

.kpi-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
    background: rgba(99, 102, 241, 0.2);
}

.kpi-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: white;
    line-height: 1;
}

.kpi-label {
    color: rgba(255,255,255,0.6);
    font-size: 0.9rem;
    margin-top: 0.5rem;
}

.kpi-trend {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    margin-top: 0.5rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
}

.kpi-trend.up { background: rgba(16, 185, 129, 0.2); color: #34d399; }
.kpi-trend.down { background: rgba(239, 68, 68, 0.2); color: #f87171; }
.kpi-trend.stable { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.6); }

/* قسم التنبؤات */
.section-card {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid rgba(255,255,255,0.1);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: white;
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
}

.section-title i {
    font-size: 1.5rem;
    background: var(--analytics-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* بطاقات التنبؤ */
.prediction-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.75rem;
}

@media (max-width: 992px) {
    .prediction-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 576px) {
    .prediction-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.prediction-card {
    background: rgba(255,255,255,0.05);
    border-radius: 16px;
    padding: 1rem;
    text-align: center;
    border: 2px solid transparent;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}

.prediction-card.high-prob {
    border-color: rgba(16, 185, 129, 0.5);
    background: rgba(16, 185, 129, 0.1);
}

.prediction-card.medium-prob {
    border-color: rgba(245, 158, 11, 0.5);
    background: rgba(245, 158, 11, 0.1);
}

.prediction-card.low-prob {
    border-color: rgba(239, 68, 68, 0.5);
    background: rgba(239, 68, 68, 0.1);
}

.prediction-day {
    font-weight: 600;
    color: white;
    font-size: 0.9rem;
}

.prediction-date {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.5);
    margin: 0.25rem 0;
}

.prediction-value {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0.5rem 0;
}

.prediction-card.high-prob .prediction-value { color: #34d399; }
.prediction-card.medium-prob .prediction-value { color: #fbbf24; }
.prediction-card.low-prob .prediction-value { color: #f87171; }

.prediction-confidence {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.5);
}

/* مؤشر المخاطر الدائري */
.risk-gauge {
    position: relative;
    width: 200px;
    height: 200px;
    margin: 0 auto 1rem;
}

.risk-gauge svg {
    transform: rotate(-90deg);
}

.risk-gauge-bg {
    fill: none;
    stroke: rgba(255,255,255,0.1);
    stroke-width: 20;
}

.risk-gauge-fill {
    fill: none;
    stroke-width: 20;
    stroke-linecap: round;
    transition: stroke-dashoffset 1s ease-out, stroke 0.5s;
}

.risk-gauge-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.risk-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: white;
}

.risk-label {
    font-size: 0.9rem;
    color: rgba(255,255,255,0.6);
}

/* قسم الرؤى */
.insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.insight-card {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    border-radius: 16px;
    padding: 1.25rem;
    border: 1px solid rgba(99, 102, 241, 0.2);
    transition: all 0.3s;
}

.insight-card:hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
    transform: translateY(-3px);
}

.insight-icon {
    font-size: 2rem;
    margin-bottom: 0.75rem;
}

.insight-title {
    color: white;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.insight-value {
    color: #a5b4fc;
    font-size: 1.1rem;
}

.insight-detail {
    color: rgba(255,255,255,0.5);
    font-size: 0.8rem;
    margin-top: 0.5rem;
}

/* قسم التوصيات */
.recommendation-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: rgba(255,255,255,0.03);
    border-radius: 12px;
    margin-bottom: 0.75rem;
    border-right: 4px solid;
    transition: all 0.3s;
}

.recommendation-item:hover {
    background: rgba(255,255,255,0.06);
}

.recommendation-item.high { border-color: #ef4444; }
.recommendation-item.medium { border-color: #f59e0b; }
.recommendation-item.positive { border-color: #10b981; }

.recommendation-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

.recommendation-content h4 {
    color: white;
    margin: 0 0 0.25rem;
    font-size: 1rem;
}

.recommendation-content p {
    color: rgba(255,255,255,0.6);
    margin: 0;
    font-size: 0.85rem;
}

.recommendation-action {
    color: #a5b4fc;
    font-size: 0.8rem;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* مخطط الأنماط الأسبوعية */
.weekly-pattern {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 1rem;
    background: rgba(0,0,0,0.2);
    border-radius: 12px;
}

.day-bar {
    flex: 1;
    text-align: center;
}

.day-bar-fill {
    height: 100px;
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    position: relative;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.day-bar-value {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--analytics-gradient);
    border-radius: 8px;
    transition: height 1s ease-out;
}

.day-bar-label {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.6);
}

.day-bar-percent {
    font-size: 0.8rem;
    color: white;
    font-weight: 600;
}

/* قسم Monte Carlo */
.scenario-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

@media (max-width: 768px) {
    .scenario-cards {
        grid-template-columns: 1fr;
    }
}

.scenario-card {
    padding: 1.25rem;
    border-radius: 16px;
    text-align: center;
}

.scenario-card.best {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(52, 211, 153, 0.1));
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.scenario-card.expected {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.1));
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.scenario-card.worst {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(248, 113, 113, 0.1));
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.scenario-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.scenario-title {
    color: white;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.scenario-value {
    font-size: 2rem;
    font-weight: 700;
}

.scenario-card.best .scenario-value { color: #34d399; }
.scenario-card.expected .scenario-value { color: #a5b4fc; }
.scenario-card.worst .scenario-value { color: #f87171; }

.scenario-desc {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.5);
    margin-top: 0.5rem;
}

/* اختيار الموظف */
.employee-selector {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
    border: 1px solid rgba(255,255,255,0.1);
}

.employee-selector label {
    color: rgba(255,255,255,0.7);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.employee-selector select {
    flex: 1;
    min-width: 200px;
    padding: 0.75rem 1rem;
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 10px;
    background: rgba(0,0,0,0.3);
    color: white;
    font-size: 1rem;
}

.employee-selector select option {
    background: #1e1b4b;
    color: white;
}

/* الفوتر */
.analytics-footer {
    text-align: center;
    color: rgba(255,255,255,0.4);
    padding: 2rem 0;
    font-size: 0.85rem;
}

/* أنيميشن التحميل */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.loading {
    animation: pulse 1.5s ease-in-out infinite;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .super-header h1 {
        font-size: 1.5rem;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .kpi-value {
        font-size: 1.75rem;
    }
}
</style>

<div class="container py-4">
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.3); color: white;">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <?= e($error) ?>
    </div>
    <?php elseif (isset($ultraAnalysis['error'])): ?>
    <div class="alert alert-warning" style="background: rgba(245, 158, 11, 0.2); border-color: rgba(245, 158, 11, 0.3); color: white;">
        <i class="bi bi-info-circle me-2"></i>
        <?= e($ultraAnalysis['error']) ?>
    </div>
    <?php else: ?>
    
    <!-- الهيدر الرئيسي -->
    <div class="super-header">
        <h1>
            <span>📊 لوحة التحليلات الفائقة</span>
            <span class="ai-badge">
                <i class="bi bi-cpu"></i>
                مدعوم بالذكاء الاصطناعي
            </span>
        </h1>
        <p>تحليل متقدم باستخدام الشبكات العصبية • التعلم الآلي • التحليل الإحصائي</p>
    </div>
    
    <?php if ($roleLevel >= 3): ?>
    <!-- اختيار الموظف -->
    <div class="employee-selector">
        <label>
            <i class="bi bi-person-badge"></i>
            <span>تحليل موظف:</span>
        </label>
        <select onchange="window.location.href='?user_id='+this.value+'&tab=<?= e($activeTab) ?>'">
            <option value="<?= $userId ?>">تحليلي الشخصي</option>
            <?php
            $employees = Database::fetchAll(
                "SELECT id, full_name, emp_code FROM users WHERE branch_id = :branch_id AND is_active = 1 ORDER BY full_name",
                ['branch_id' => $branchId]
            );
            foreach ($employees as $emp):
                if ($emp['id'] == $userId) continue;
            ?>
            <option value="<?= $emp['id'] ?>" <?= $targetUserId == $emp['id'] ? 'selected' : '' ?>>
                <?= e($emp['full_name']) ?> (<?= e($emp['emp_code']) ?>)
            </option>
            <?php endforeach; ?>
        </select>
        <?php if ($targetUser && $targetUserId != $userId): ?>
        <span style="background: rgba(99, 102, 241, 0.2); color: #a5b4fc; padding: 0.5rem 1rem; border-radius: 20px;">
            <i class="bi bi-eye me-1"></i>
            <?= e($targetUser['full_name']) ?>
        </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- التبويبات -->
    <div class="analytics-tabs">
        <a href="?user_id=<?= $targetUserId ?>&tab=overview" class="tab-btn <?= $activeTab === 'overview' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i>
            نظرة عامة
        </a>
        <a href="?user_id=<?= $targetUserId ?>&tab=predictions" class="tab-btn <?= $activeTab === 'predictions' ? 'active' : '' ?>">
            <i class="bi bi-magic"></i>
            التنبؤات
        </a>
        <a href="?user_id=<?= $targetUserId ?>&tab=patterns" class="tab-btn <?= $activeTab === 'patterns' ? 'active' : '' ?>">
            <i class="bi bi-diagram-3"></i>
            الأنماط
        </a>
        <a href="?user_id=<?= $targetUserId ?>&tab=risks" class="tab-btn <?= $activeTab === 'risks' ? 'active' : '' ?>">
            <i class="bi bi-shield-exclamation"></i>
            المخاطر
        </a>
        <a href="?user_id=<?= $targetUserId ?>&tab=timeseries" class="tab-btn <?= $activeTab === 'timeseries' ? 'active' : '' ?>">
            <i class="bi bi-graph-up-arrow"></i>
            السلاسل الزمنية
        </a>
        <?php if ($roleLevel >= 3 && $branchAnalysis): ?>
        <a href="?user_id=<?= $targetUserId ?>&tab=branch" class="tab-btn <?= $activeTab === 'branch' ? 'active' : '' ?>">
            <i class="bi bi-building"></i>
            الفرع
        </a>
        <?php endif; ?>
    </div>
    
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- تبويب النظرة العامة -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <?php if ($activeTab === 'overview'): ?>
    
    <!-- مؤشرات الأداء الرئيسية -->
    <div class="kpi-grid">
        <div class="kpi-card <?= ($ultraAnalysis['kpis']['attendance_rate'] ?? 0) >= 90 ? 'success' : (($ultraAnalysis['kpis']['attendance_rate'] ?? 0) >= 75 ? 'warning' : 'danger') ?>">
            <div class="kpi-icon">📅</div>
            <div class="kpi-value"><?= $ultraAnalysis['kpis']['attendance_rate'] ?? 0 ?>%</div>
            <div class="kpi-label">معدل الحضور</div>
            <div class="kpi-trend <?= ($ultraAnalysis['kpis']['trend'] ?? '') === 'تصاعدي ↑' ? 'up' : (($ultraAnalysis['kpis']['trend'] ?? '') === 'تنازلي ↓' ? 'down' : 'stable') ?>">
                <?= $ultraAnalysis['kpis']['trend'] ?? 'مستقر' ?>
            </div>
        </div>
        
        <div class="kpi-card <?= ($ultraAnalysis['kpis']['punctuality_rate'] ?? 0) >= 85 ? 'success' : 'warning' ?>">
            <div class="kpi-icon">⏰</div>
            <div class="kpi-value"><?= $ultraAnalysis['kpis']['punctuality_rate'] ?? 0 ?>%</div>
            <div class="kpi-label">الالتزام بالمواعيد</div>
        </div>
        
        <div class="kpi-card info">
            <div class="kpi-icon">📊</div>
            <div class="kpi-value"><?= $ultraAnalysis['kpis']['overall_performance_index'] ?? 0 ?>%</div>
            <div class="kpi-label">مؤشر الأداء الشامل</div>
            <div class="kpi-trend stable"><?= $ultraAnalysis['kpis']['grade'] ?? '' ?></div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon">🎯</div>
            <div class="kpi-value"><?= $ultraAnalysis['kpis']['consistency_score'] ?? 0 ?>%</div>
            <div class="kpi-label">الاتساق</div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon">⏱️</div>
            <div class="kpi-value"><?= $ultraAnalysis['kpis']['avg_work_hours'] ?? 0 ?>h</div>
            <div class="kpi-label">متوسط ساعات العمل</div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-6">
            <!-- التحليل السلوكي -->
            <div class="section-card">
                <div class="section-title">
                    <i class="bi bi-person-badge"></i>
                    <span>التحليل السلوكي العميق</span>
                </div>
                
                <?php $behavior = $ultraAnalysis['behavioral_deep_analysis'] ?? []; ?>
                
                <div class="insights-grid">
                    <div class="insight-card">
                        <div class="insight-icon">🧠</div>
                        <div class="insight-title">نوع الشخصية</div>
                        <div class="insight-value"><?= $behavior['work_personality'] ?? 'غير محدد' ?></div>
                    </div>
                    
                    <div class="insight-card">
                        <div class="insight-icon">🎯</div>
                        <div class="insight-title">مؤشر الاستقرار</div>
                        <div class="insight-value"><?= $behavior['stability_index'] ?? 0 ?>%</div>
                    </div>
                    
                    <div class="insight-card">
                        <div class="insight-icon">🔮</div>
                        <div class="insight-title">قابلية التنبؤ</div>
                        <div class="insight-value"><?= $behavior['predictability'] ?? 'غير محدد' ?></div>
                    </div>
                    
                    <?php if (isset($behavior['arrival_analysis']['avg_arrival'])): ?>
                    <div class="insight-card">
                        <div class="insight-icon">🕐</div>
                        <div class="insight-title">متوسط الوصول</div>
                        <div class="insight-value"><?= $behavior['arrival_analysis']['avg_arrival'] ?></div>
                        <div class="insight-detail">الاتساق: <?= $behavior['arrival_analysis']['consistency'] ?? '' ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <!-- التوصيات الذكية -->
            <div class="section-card">
                <div class="section-title">
                    <i class="bi bi-lightbulb"></i>
                    <span>التوصيات الذكية</span>
                </div>
                
                <?php 
                $recommendations = $ultraAnalysis['smart_recommendations'] ?? [];
                if (empty($recommendations)): 
                ?>
                <p style="color: rgba(255,255,255,0.5); text-align: center; padding: 2rem;">
                    <i class="bi bi-emoji-smile fs-1 d-block mb-2"></i>
                    لا توجد توصيات حالياً - الأداء جيد!
                </p>
                <?php else: ?>
                <?php foreach (array_slice($recommendations, 0, 4) as $rec): ?>
                <div class="recommendation-item <?= $rec['priority'] === 'عالية' ? 'high' : ($rec['priority'] === 'متوسطة' ? 'medium' : 'positive') ?>">
                    <div class="recommendation-icon"><?= $rec['icon'] ?? '💡' ?></div>
                    <div class="recommendation-content">
                        <h4><?= e($rec['title']) ?></h4>
                        <p><?= e($rec['description']) ?></p>
                        <div class="recommendation-action">
                            <i class="bi bi-arrow-return-left"></i>
                            <?= e($rec['action']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
    
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- تبويب التنبؤات -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <?php if ($activeTab === 'predictions'): ?>
    
    <!-- تنبؤات الـ 7 أيام القادمة -->
    <div class="section-card">
        <div class="section-title">
            <i class="bi bi-magic"></i>
            <span>تنبؤات الحضور للأسبوع القادم</span>
            <small style="color: rgba(255,255,255,0.5); margin-right: auto;">
                دقة النموذج: <?= $ultraAnalysis['ai_predictions']['model_accuracy'] ?? 85 ?>%
            </small>
        </div>
        
        <div class="prediction-grid">
            <?php 
            $predictions = $ultraAnalysis['ai_predictions']['next_7_days'] ?? [];
            foreach ($predictions as $pred): 
                $probClass = $pred['probability'] >= 70 ? 'high-prob' : ($pred['probability'] >= 40 ? 'medium-prob' : 'low-prob');
            ?>
            <div class="prediction-card <?= $probClass ?>">
                <div class="prediction-day"><?= $pred['day_name'] ?></div>
                <div class="prediction-date"><?= date('m/d', strtotime($pred['date'])) ?></div>
                <div class="prediction-value"><?= $pred['probability'] ?>%</div>
                <div class="prediction-confidence">ثقة: <?= $pred['confidence'] ?>%</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- تنبؤات Holt-Winters -->
    <?php if (isset($ultraAnalysis['timeseries_analysis']['holt_winters'])): ?>
    <div class="section-card">
        <div class="section-title">
            <i class="bi bi-graph-up-arrow"></i>
            <span>تنبؤ Holt-Winters المتقدم</span>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 12px;">
                    <div class="d-flex justify-content-between align-items-end" style="height: 150px;">
                        <?php 
                        $hwForecast = $ultraAnalysis['timeseries_analysis']['holt_winters']['forecast'] ?? [];
                        foreach ($hwForecast as $f): 
                            $height = min(100, max(10, $f['prediction'] * 100));
                        ?>
                        <div style="flex: 1; padding: 0 0.25rem; text-align: center;">
                            <div style="height: <?= $height ?>%; background: var(--analytics-gradient); border-radius: 4px 4px 0 0; margin: 0 auto; width: 80%;"></div>
                            <small style="color: rgba(255,255,255,0.5); font-size: 0.7rem;">يوم <?= $f['step'] ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3">
                    <div class="mb-3">
                        <small style="color: rgba(255,255,255,0.5);">الاتجاه</small>
                        <div style="color: white; font-size: 1.25rem; font-weight: 600;">
                            <?= round(($ultraAnalysis['timeseries_analysis']['holt_winters']['trend'] ?? 0) * 100, 2) ?>%
                        </div>
                    </div>
                    <div>
                        <small style="color: rgba(255,255,255,0.5);">دقة النموذج (RMSE)</small>
                        <div style="color: white; font-size: 1.25rem; font-weight: 600;">
                            <?= $ultraAnalysis['timeseries_analysis']['holt_winters']['model_fit']['rmse'] ?? 0 ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
    
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- تبويب الأنماط -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <?php if ($activeTab === 'patterns'): ?>
    
    <!-- أنماط الأيام الأسبوعية -->
    <div class="section-card">
        <div class="section-title">
            <i class="bi bi-calendar-week"></i>
            <span>أنماط الحضور الأسبوعية</span>
        </div>
        
        <div class="weekly-pattern">
            <?php 
            $dayPatterns = $ultraAnalysis['pattern_analysis']['day_patterns'] ?? [];
            foreach ($dayPatterns as $day => $data): 
            ?>
            <div class="day-bar">
                <div class="day-bar-fill">
                    <div class="day-bar-value" style="height: <?= $data['attendance_rate'] ?>%;"></div>
                </div>
                <div class="day-bar-percent"><?= $data['attendance_rate'] ?>%</div>
                <div class="day-bar-label"><?= $day ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="insight-card">
                    <div class="insight-icon">🌟</div>
                    <div class="insight-title">أفضل يوم أداء</div>
                    <div class="insight-value"><?= $ultraAnalysis['pattern_analysis']['best_day'] ?? '-' ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="insight-card" style="border-color: rgba(239, 68, 68, 0.3); background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(248, 113, 113, 0.05));">
                    <div class="insight-icon">📉</div>
                    <div class="insight-title">يوم يحتاج تحسين</div>
                    <div class="insight-value" style="color: #f87171;"><?= $ultraAnalysis['pattern_analysis']['worst_day'] ?? '-' ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- تحليل Markov Chain -->
    <?php if (isset($ultraAnalysis['pattern_analysis']['markov_analysis'])): ?>
    <div class="section-card">
        <div class="section-title">
            <i class="bi bi-diagram-3"></i>
            <span>تحليل سلسلة ماركوف</span>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <h6 style="color: rgba(255,255,255,0.7);">مصفوفة الانتقال</h6>
                <div style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 12px;">
                    <?php 
                    $matrix = $ultraAnalysis['pattern_analysis']['markov_analysis']['transition_matrix'] ?? [];
                    ?>
                    <table style="width: 100%; color: white; font-size: 0.9rem;">
                        <tr>
                            <td></td>
                            <td style="text-align: center; color: #34d399;">→ حاضر</td>
                            <td style="text-align: center; color: #f87171;">→ غائب</td>
                        </tr>
                        <tr>
                            <td style="color: #34d399;">حاضر →</td>
                            <td style="text-align: center;"><?= round(($matrix['present']['present'] ?? 0) * 100, 1) ?>%</td>
                            <td style="text-align: center;"><?= round(($matrix['present']['absent'] ?? 0) * 100, 1) ?>%</td>
                        </tr>
                        <tr>
                            <td style="color: #f87171;">غائب →</td>
                            <td style="text-align: center;"><?= round(($matrix['absent']['present'] ?? 0) * 100, 1) ?>%</td>
                            <td style="text-align: center;"><?= round(($matrix['absent']['absent'] ?? 0) * 100, 1) ?>%</td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <h6 style="color: rgba(255,255,255,0.7);">التوزيع المستقر</h6>
                <div class="insight-card">
                    <div class="insight-icon">🔮</div>
                    <div class="insight-title">معدل الحضور طويل المدى</div>
                    <div class="insight-value" style="font-size: 2rem;">
                        <?= $ultraAnalysis['pattern_analysis']['markov_analysis']['predicted_long_term_attendance'] ?? 0 ?>%
                    </div>
                    <div class="insight-detail">التوقع بناءً على الأنماط الحالية</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
    
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- تبويب المخاطر -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <?php if ($activeTab === 'risks'): ?>
    
    <div class="row">
        <div class="col-lg-5">
            <!-- مؤشر المخاطر الدائري -->
            <div class="section-card">
                <div class="section-title">
                    <i class="bi bi-shield-exclamation"></i>
                    <span>مؤشر المخاطر الشامل</span>
                </div>
                
                <?php 
                $riskScore = $ultraAnalysis['risk_analysis']['risk_score'] ?? 0;
                $riskColor = $riskScore >= 70 ? '#ef4444' : ($riskScore >= 50 ? '#f59e0b' : ($riskScore >= 30 ? '#fbbf24' : '#10b981'));
                $circumference = 2 * M_PI * 80;
                $dashOffset = $circumference - ($riskScore / 100) * $circumference;
                ?>
                
                <div class="risk-gauge">
                    <svg width="200" height="200">
                        <circle class="risk-gauge-bg" cx="100" cy="100" r="80"/>
                        <circle class="risk-gauge-fill" cx="100" cy="100" r="80"
                                stroke="<?= $riskColor ?>"
                                stroke-dasharray="<?= $circumference ?>"
                                stroke-dashoffset="<?= $dashOffset ?>"/>
                    </svg>
                    <div class="risk-gauge-text">
                        <div class="risk-value"><?= $riskScore ?>%</div>
                        <div class="risk-label"><?= $ultraAnalysis['risk_analysis']['risk_level'] ?? 'غير محدد' ?></div>
                    </div>
                </div>
                
                <?php if (!empty($ultraAnalysis['risk_analysis']['risk_factors'])): ?>
                <div class="mt-3">
                    <h6 style="color: rgba(255,255,255,0.7);">عوامل الخطر:</h6>
                    <?php foreach ($ultraAnalysis['risk_analysis']['risk_factors'] as $factor): ?>
                    <div style="display: inline-block; margin: 0.25rem; padding: 0.5rem 1rem; background: rgba(239, 68, 68, 0.2); border-radius: 20px; font-size: 0.85rem; color: #f87171;">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        <?= e($factor['factor']) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-lg-7">
            <!-- محاكاة Monte Carlo -->
            <?php if (isset($ultraAnalysis['risk_analysis']['monte_carlo']) && !isset($ultraAnalysis['risk_analysis']['monte_carlo']['error'])): ?>
            <div class="section-card">
                <div class="section-title">
                    <i class="bi bi-dice-5"></i>
                    <span>محاكاة Monte Carlo</span>
                    <small style="color: rgba(255,255,255,0.5); margin-right: auto;">
                        <?= $ultraAnalysis['risk_analysis']['monte_carlo']['simulations'] ?? 0 ?> محاكاة
                    </small>
                </div>
                
                <div class="scenario-cards">
                    <?php $scenarios = $ultraAnalysis['risk_analysis']['monte_carlo']['scenarios'] ?? []; ?>
                    
                    <div class="scenario-card best">
                        <div class="scenario-icon">🌟</div>
                        <div class="scenario-title">أفضل سيناريو</div>
                        <div class="scenario-value"><?= round(($scenarios['best_case']['avg_rate'] ?? 0) * 100, 1) ?>%</div>
                        <div class="scenario-desc">احتمالية: 25%</div>
                    </div>
                    
                    <div class="scenario-card expected">
                        <div class="scenario-icon">📊</div>
                        <div class="scenario-title">السيناريو المتوقع</div>
                        <div class="scenario-value"><?= round(($scenarios['expected']['avg_rate'] ?? 0) * 100, 1) ?>%</div>
                        <div class="scenario-desc">احتمالية: 50%</div>
                    </div>
                    
                    <div class="scenario-card worst">
                        <div class="scenario-icon">⚠️</div>
                        <div class="scenario-title">أسوأ سيناريو</div>
                        <div class="scenario-value"><?= round(($scenarios['worst_case']['avg_rate'] ?? 0) * 100, 1) ?>%</div>
                        <div class="scenario-desc">احتمالية: 25%</div>
                    </div>
                </div>
                
                <!-- تحليل المخاطر الإضافي -->
                <?php $riskAnalysis = $ultraAnalysis['risk_analysis']['monte_carlo']['risk_analysis'] ?? []; ?>
                <div class="mt-4" style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 12px;">
                    <div class="row text-center">
                        <div class="col-4">
                            <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">احتمال أقل من 80%</div>
                            <div style="color: #fbbf24; font-size: 1.25rem; font-weight: 600;">
                                <?= round(($riskAnalysis['probability_below_80'] ?? 0) * 100, 1) ?>%
                            </div>
                        </div>
                        <div class="col-4">
                            <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">احتمال أقل من 70%</div>
                            <div style="color: #f59e0b; font-size: 1.25rem; font-weight: 600;">
                                <?= round(($riskAnalysis['probability_below_70'] ?? 0) * 100, 1) ?>%
                            </div>
                        </div>
                        <div class="col-4">
                            <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">احتمال أقل من 60%</div>
                            <div style="color: #ef4444; font-size: 1.25rem; font-weight: 600;">
                                <?= round(($riskAnalysis['probability_below_60'] ?? 0) * 100, 1) ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- توصيات الخطر -->
    <?php if (!empty($ultraAnalysis['risk_analysis']['recommendations'])): ?>
    <div class="section-card">
        <div class="section-title">
            <i class="bi bi-shield-check"></i>
            <span>توصيات تقليل المخاطر</span>
        </div>
        
        <?php foreach ($ultraAnalysis['risk_analysis']['recommendations'] as $rec): ?>
        <div class="recommendation-item high">
            <div class="recommendation-icon">🛡️</div>
            <div class="recommendation-content">
                <h4><?= e($rec) ?></h4>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
    
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- تبويب السلاسل الزمنية -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <?php if ($activeTab === 'timeseries'): ?>
    
    <!-- الدورات المكتشفة -->
    <?php if (!empty($ultraAnalysis['timeseries_analysis']['dominant_cycles'])): ?>
    <div class="section-card">
        <div class="section-title">
            <i class="bi bi-arrow-repeat"></i>
            <span>الدورات المكتشفة (تحليل فورييه)</span>
        </div>
        
        <div class="insights-grid">
            <?php foreach ($ultraAnalysis['timeseries_analysis']['dominant_cycles'] as $cycle): ?>
            <div class="insight-card">
                <div class="insight-icon">🔄</div>
                <div class="insight-title"><?= e($cycle['interpretation']) ?></div>
                <div class="insight-value">كل <?= round($cycle['period'], 1) ?> يوم</div>
                <div class="insight-detail">قوة الدورة: <?= $cycle['strength'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- تفكيك السلسلة الزمنية -->
    <?php if (isset($ultraAnalysis['timeseries_analysis']['decomposition'])): ?>
    <div class="section-card">
        <div class="section-title">
            <i class="bi bi-layers"></i>
            <span>تفكيك السلسلة الزمنية</span>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="insight-card">
                    <div class="insight-icon">📈</div>
                    <div class="insight-title">قوة الاتجاه</div>
                    <div class="insight-value">
                        <?= round(($ultraAnalysis['timeseries_analysis']['decomposition']['trend_strength'] ?? 0) * 100, 1) ?>%
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="insight-card">
                    <div class="insight-icon">🔄</div>
                    <div class="insight-title">قوة الموسمية</div>
                    <div class="insight-value">
                        <?= round(($ultraAnalysis['timeseries_analysis']['decomposition']['seasonal_strength'] ?? 0) * 100, 1) ?>%
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- نقاط التغيير -->
    <?php if (!empty($ultraAnalysis['timeseries_analysis']['changepoints']['detected'])): ?>
    <div class="section-card">
        <div class="section-title">
            <i class="bi bi-signpost-split"></i>
            <span>نقاط التغيير المكتشفة</span>
        </div>
        
        <p style="color: rgba(255,255,255,0.6);">
            تم اكتشاف <?= count($ultraAnalysis['timeseries_analysis']['changepoints']['detected']) ?> نقطة تغيير في السلوك
        </p>
        
        <?php foreach ($ultraAnalysis['timeseries_analysis']['changepoints']['segments'] ?? [] as $segment): ?>
        <div style="display: inline-block; margin: 0.25rem; padding: 0.5rem 1rem; background: rgba(99, 102, 241, 0.2); border-radius: 12px; font-size: 0.85rem; color: #a5b4fc;">
            أيام <?= $segment['start'] ?> - <?= $segment['end'] ?>: 
            متوسط <?= round($segment['mean'], 2) ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
    
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- تبويب الفرع -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <?php if ($activeTab === 'branch' && $branchAnalysis && !isset($branchAnalysis['error'])): ?>
    
    <!-- إحصائيات الفرع -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon">👥</div>
            <div class="kpi-value"><?= $branchAnalysis['total_employees'] ?></div>
            <div class="kpi-label">إجمالي الموظفين</div>
        </div>
        
        <div class="kpi-card <?= $branchAnalysis['avg_attendance_rate'] >= 85 ? 'success' : 'warning' ?>">
            <div class="kpi-icon">📊</div>
            <div class="kpi-value"><?= $branchAnalysis['avg_attendance_rate'] ?>%</div>
            <div class="kpi-label">متوسط الحضور</div>
        </div>
        
        <div class="kpi-card danger">
            <div class="kpi-icon">⚠️</div>
            <div class="kpi-value"><?= count($branchAnalysis['needs_attention'] ?? []) ?></div>
            <div class="kpi-label">يحتاجون متابعة</div>
        </div>
    </div>
    
    <!-- توزيع الأداء -->
    <div class="section-card">
        <div class="section-title">
            <i class="bi bi-pie-chart"></i>
            <span>توزيع الأداء</span>
        </div>
        
        <div class="row">
            <?php $dist = $branchAnalysis['performance_distribution'] ?? []; ?>
            <div class="col-3 text-center">
                <div style="font-size: 2rem; color: #34d399;"><?= $dist['excellent'] ?? 0 ?></div>
                <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">ممتاز (95%+)</div>
            </div>
            <div class="col-3 text-center">
                <div style="font-size: 2rem; color: #60a5fa;"><?= $dist['good'] ?? 0 ?></div>
                <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">جيد (85-94%)</div>
            </div>
            <div class="col-3 text-center">
                <div style="font-size: 2rem; color: #fbbf24;"><?= $dist['average'] ?? 0 ?></div>
                <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">متوسط (70-84%)</div>
            </div>
            <div class="col-3 text-center">
                <div style="font-size: 2rem; color: #f87171;"><?= $dist['poor'] ?? 0 ?></div>
                <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">ضعيف (<70%)</div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- أفضل الموظفين -->
        <div class="col-lg-6">
            <div class="section-card">
                <div class="section-title">
                    <i class="bi bi-trophy"></i>
                    <span>أفضل الموظفين</span>
                </div>
                
                <?php foreach (array_slice($branchAnalysis['top_performers'] ?? [], 0, 5) as $i => $performer): ?>
                <div style="display: flex; align-items: center; padding: 0.75rem; background: rgba(255,255,255,0.03); border-radius: 10px; margin-bottom: 0.5rem;">
                    <span style="font-size: 1.5rem; margin-left: 1rem;">
                        <?= ['🥇', '🥈', '🥉', '⭐', '⭐'][$i] ?>
                    </span>
                    <div style="flex: 1;">
                        <div style="color: white;"><?= e($performer['name']) ?></div>
                    </div>
                    <div style="color: #34d399; font-weight: 600;">
                        <?= round($performer['attendance_rate'], 1) ?>%
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- يحتاجون متابعة -->
        <div class="col-lg-6">
            <div class="section-card">
                <div class="section-title">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>يحتاجون متابعة</span>
                </div>
                
                <?php if (empty($branchAnalysis['needs_attention'])): ?>
                <p style="color: rgba(255,255,255,0.5); text-align: center; padding: 2rem;">
                    <i class="bi bi-emoji-smile fs-1 d-block mb-2"></i>
                    جميع الموظفين بحالة جيدة!
                </p>
                <?php else: ?>
                <?php foreach (array_slice($branchAnalysis['needs_attention'] ?? [], 0, 5) as $emp): ?>
                <div style="display: flex; align-items: center; padding: 0.75rem; background: rgba(239, 68, 68, 0.1); border-radius: 10px; margin-bottom: 0.5rem; border-right: 3px solid #ef4444;">
                    <div style="flex: 1;">
                        <div style="color: white;"><?= e($emp['name']) ?></div>
                    </div>
                    <div style="color: #f87171; font-weight: 600;">
                        <?= round($emp['attendance_rate'], 1) ?>%
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
    
    <!-- الفوتر -->
    <div class="analytics-footer">
        <i class="bi bi-clock me-1"></i>
        تم إنشاء التقرير: <?= $ultraAnalysis['generated_at'] ?? date('Y-m-d H:i:s') ?>
        <br>
        <small>تحليل <?= $ultraAnalysis['data_points'] ?? 0 ?> نقطة بيانات</small>
    </div>
    
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
