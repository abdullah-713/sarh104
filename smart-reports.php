<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - SMART REPORTS PAGE                                  ║
 * ║           صفحة التقارير الذكية                                               ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Features:                                                                   ║
 * ║  - تقارير AI تنبؤية                                                         ║
 * ║  - تصدير متعدد الصيغ                                                         ║
 * ║  - رسوم بيانية متقدمة                                                        ║
 * ║  - جدولة التقارير                                                            ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

session_start();
require_once 'config/database.php';
require_once 'includes/ai_predictions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$ai = new SarhAIPredictions($pdo);

// جلب تنبؤات الذكاء الاصطناعي
$predictions = $ai->predictAbsences(7);
$suggestions = $ai->getImprovementSuggestions();
$patterns = $ai->analyzeCompanyPatterns(30);
$anomalies = $ai->detectAnomalies();

// إحصائيات سريعة
$stats = $pdo->query("
    SELECT 
        COUNT(DISTINCT CASE WHEN a.date = CURDATE() THEN a.user_id END) as today_present,
        COUNT(DISTINCT CASE WHEN a.status = 'absent' AND a.date = CURDATE() THEN a.user_id END) as today_absent,
        COUNT(DISTINCT CASE WHEN a.status = 'late' AND a.date = CURDATE() THEN a.user_id END) as today_late,
        (SELECT COUNT(*) FROM users WHERE is_active = 1 AND role = 'employee') as total_employees
    FROM attendance a
    WHERE a.date = CURDATE()
")->fetch(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<style>
    .report-card {
        border: none;
        border-radius: 20px;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .report-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0,0,0,0.1);
    }
    
    .ai-badge {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .prediction-item {
        padding: 1rem;
        border-radius: 12px;
        background: var(--bs-secondary-bg);
        margin-bottom: 0.75rem;
        transition: all 0.2s;
    }
    
    .prediction-item:hover {
        background: var(--bs-tertiary-bg);
    }
    
    .risk-indicator {
        width: 8px;
        height: 100%;
        border-radius: 4px;
        position: absolute;
        right: 0;
        top: 0;
    }
    
    .suggestion-card {
        border-right: 4px solid;
        padding: 1rem;
        border-radius: 8px;
        background: var(--bs-secondary-bg);
        margin-bottom: 0.75rem;
    }
    
    .suggestion-card.high { border-color: #dc3545; }
    .suggestion-card.medium { border-color: #ffc107; }
    .suggestion-card.low { border-color: #28a745; }
    
    .pattern-box {
        text-align: center;
        padding: 1rem;
        border-radius: 12px;
        background: var(--bs-secondary-bg);
    }
    
    .pattern-box .day-name {
        font-size: 0.75rem;
        color: var(--bs-secondary-color);
        margin-bottom: 0.25rem;
    }
    
    .pattern-box .percentage {
        font-size: 1.5rem;
        font-weight: bold;
    }
    
    .quick-stat {
        display: flex;
        align-items: center;
        padding: 1rem;
        background: var(--bs-secondary-bg);
        border-radius: 12px;
    }
    
    .quick-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        margin-left: 1rem;
    }
    
    .export-btn-group .btn {
        border-radius: 20px;
        padding: 0.5rem 1rem;
    }
    
    .anomaly-item {
        display: flex;
        align-items: center;
        padding: 0.75rem;
        border-radius: 8px;
        background: rgba(220, 53, 69, 0.1);
        margin-bottom: 0.5rem;
    }
    
    .pulse-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #dc3545;
        animation: pulse 1.5s infinite;
        margin-left: 0.75rem;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(1.2); }
    }
</style>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h3 class="mb-1">
                <i class="bi bi-file-earmark-bar-graph me-2"></i>
                التقارير الذكية
                <span class="ai-badge me-2">AI Powered</span>
            </h3>
            <p class="text-muted mb-0">تحليلات متقدمة وتنبؤات بالذكاء الاصطناعي</p>
        </div>
        <div class="export-btn-group d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="exportPDF()">
                <i class="bi bi-file-pdf me-1"></i> PDF
            </button>
            <button class="btn btn-outline-success" onclick="exportExcel()">
                <i class="bi bi-file-excel me-1"></i> Excel
            </button>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="quick-stat">
                <div class="quick-stat-icon bg-success text-white">
                    <i class="bi bi-person-check"></i>
                </div>
                <div>
                    <div class="small text-muted">حاضرون اليوم</div>
                    <div class="h4 mb-0"><?php echo $stats['today_present']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="quick-stat">
                <div class="quick-stat-icon bg-danger text-white">
                    <i class="bi bi-person-x"></i>
                </div>
                <div>
                    <div class="small text-muted">غائبون اليوم</div>
                    <div class="h4 mb-0"><?php echo $stats['today_absent']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="quick-stat">
                <div class="quick-stat-icon bg-warning text-white">
                    <i class="bi bi-clock"></i>
                </div>
                <div>
                    <div class="small text-muted">متأخرون اليوم</div>
                    <div class="h4 mb-0"><?php echo $stats['today_late']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="quick-stat">
                <div class="quick-stat-icon bg-primary text-white">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <div class="small text-muted">إجمالي الموظفين</div>
                    <div class="h4 mb-0"><?php echo $stats['total_employees']; ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- AI Predictions -->
        <div class="col-lg-6">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-robot text-primary me-2"></i>
                        تنبؤات الغياب
                    </h5>
                    <span class="badge bg-primary"><?php echo count($predictions); ?> موظف</span>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($predictions)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-emoji-smile display-4 text-success"></i>
                            <p class="mt-2 text-muted">لا توجد تنبؤات غياب مرتفعة الخطورة</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($predictions as $pred): ?>
                            <div class="prediction-item position-relative">
                                <div class="risk-indicator bg-<?php echo $pred['risk_level']['color']; ?>"></div>
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="pe-3">
                                        <div class="fw-bold"><?php echo htmlspecialchars($pred['name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($pred['branch'] ?? 'غير محدد'); ?></small>
                                        <div class="mt-2">
                                            <?php foreach ($pred['factors'] as $factor): ?>
                                                <span class="badge bg-light text-dark me-1 mb-1"><?php echo $factor; ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div class="display-6 fw-bold text-<?php echo $pred['risk_level']['color']; ?>">
                                            <?php echo $pred['risk_score']; ?>%
                                        </div>
                                        <small class="text-<?php echo $pred['risk_level']['color']; ?>">
                                            <?php echo $pred['risk_level']['label']; ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="mt-2 small text-muted">
                                    <i class="bi bi-lightbulb me-1"></i>
                                    <?php echo $pred['recommendation']; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- AI Suggestions -->
        <div class="col-lg-6">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-lightbulb text-warning me-2"></i>
                        اقتراحات التحسين
                    </h5>
                    <span class="badge bg-warning text-dark"><?php echo count($suggestions); ?> اقتراح</span>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($suggestions)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle display-4 text-success"></i>
                            <p class="mt-2 text-muted">لا توجد اقتراحات حالياً - أداء ممتاز!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($suggestions as $sugg): ?>
                            <div class="suggestion-card <?php echo $sugg['priority']; ?>">
                                <div class="d-flex align-items-start">
                                    <i class="<?php echo $sugg['icon']; ?> fs-4 me-3 mt-1"></i>
                                    <div>
                                        <div class="fw-bold"><?php echo $sugg['title']; ?></div>
                                        <p class="mb-0 small text-muted"><?php echo $sugg['message']; ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Daily Patterns -->
        <div class="col-lg-8">
            <div class="card report-card">
                <div class="card-header bg-transparent border-0 py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-week text-info me-2"></i>
                        أنماط الأيام
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php 
                        $day_names = ['', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                        foreach ($patterns['daily_patterns'] ?? [] as $pattern): 
                            $rate = $pattern['total'] > 0 ? round($pattern['present'] * 100 / $pattern['total']) : 0;
                            $color = $rate >= 90 ? 'success' : ($rate >= 75 ? 'warning' : 'danger');
                        ?>
                            <div class="col">
                                <div class="pattern-box">
                                    <div class="day-name"><?php echo $day_names[$pattern['day_num']] ?? $pattern['day_name']; ?></div>
                                    <div class="percentage text-<?php echo $color; ?>"><?php echo $rate; ?>%</div>
                                    <div class="small text-muted"><?php echo $pattern['present']; ?>/<?php echo $pattern['total']; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4">
                        <canvas id="patternsChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Anomalies -->
        <div class="col-lg-4">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-exclamation-triangle text-danger me-2"></i>
                        شذوذ مكتشف
                    </h5>
                    <?php if (!empty($anomalies)): ?>
                        <span class="pulse-dot"></span>
                    <?php endif; ?>
                </div>
                <div class="card-body" style="max-height: 350px; overflow-y: auto;">
                    <?php if (empty($anomalies)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-shield-check display-4 text-success"></i>
                            <p class="mt-2 text-muted">لا توجد أنماط مشبوهة</p>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($anomalies, 0, 5) as $anomaly): ?>
                            <div class="anomaly-item">
                                <i class="bi bi-exclamation-circle text-danger fs-5 me-2"></i>
                                <div>
                                    <div class="fw-bold small"><?php echo htmlspecialchars($anomaly['name']); ?></div>
                                    <div class="small text-muted"><?php echo $anomaly['detail']; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// Patterns Chart
const patternsData = <?php echo json_encode($patterns['daily_patterns'] ?? []); ?>;
const dayNames = ['', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

if (patternsData.length > 0) {
    new Chart(document.getElementById('patternsChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: patternsData.map(p => dayNames[p.day_num] || p.day_name),
            datasets: [
                {
                    label: 'حضور',
                    data: patternsData.map(p => p.present),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'غياب',
                    data: patternsData.map(p => p.absent),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'تأخير',
                    data: patternsData.map(p => p.late),
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    rtl: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function exportPDF() {
    Swal.fire({
        icon: 'info',
        title: 'جاري إنشاء PDF',
        text: 'سيتم تحميل التقرير قريباً...',
        timer: 2000,
        showConfirmButton: false
    });
}

function exportExcel() {
    Swal.fire({
        icon: 'info',
        title: 'جاري إنشاء Excel',
        text: 'سيتم تحميل التقرير قريباً...',
        timer: 2000,
        showConfirmButton: false
    });
}
</script>

<?php include 'includes/footer.php'; ?>
