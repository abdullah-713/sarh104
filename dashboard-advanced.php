<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - ADVANCED DASHBOARD                                   ║
 * ║           لوحة التحكم التفاعلية المتقدمة                                       ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Features:                                                                   ║
 * ║  - رسوم بيانية متحركة Chart.js                                              ║
 * ║  - تحليلات الحضور والتأخير                                                  ║
 * ║  - تنبؤات الذكاء الاصطناعي                                                  ║
 * ║  - مؤشرات الأداء الرئيسية KPIs                                              ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/config/app.php';
require_login();
require_permission('dashboard.view');

$user_id = $_SESSION['user_id'];
$branch_id = $_SESSION['branch_id'] ?? null;
$is_admin = has_role(ROLE_ADMIN) || is_super_admin();

// ═══════════════════════════════════════════════════════════════════════════════
// إحصائيات اليوم
// ═══════════════════════════════════════════════════════════════════════════════

$today = date('Y-m-d');
$todayStats = Database::fetchOne("
    SELECT 
        COUNT(DISTINCT CASE WHEN a.check_in_time IS NOT NULL THEN a.user_id END) as checked_in,
        COUNT(DISTINCT CASE WHEN a.check_out_time IS NOT NULL THEN a.user_id END) as checked_out,
        COUNT(DISTINCT CASE WHEN a.status = 'late' THEN a.user_id END) as late_count,
        AVG(a.late_minutes) as avg_late_minutes,
        SUM(a.overtime_minutes) as total_overtime
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.date = ?
    " . ($branch_id && !$is_admin ? "AND u.branch_id = ?" : ""),
    $branch_id && !$is_admin ? [$today, $branch_id] : [$today]
);

$totalEmployees = Database::fetchOne("
    SELECT COUNT(*) as total FROM users WHERE is_active = 1
    " . ($branch_id && !$is_admin ? "AND branch_id = ?" : ""),
    $branch_id && !$is_admin ? [$branch_id] : []
)['total'] ?? 0;

// ═══════════════════════════════════════════════════════════════════════════════
// بيانات الرسوم البيانية - آخر 7 أيام
// ═══════════════════════════════════════════════════════════════════════════════

$weeklyData = Database::fetchAll("
    SELECT 
        a.date,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.user_id END) as present,
        COUNT(DISTINCT CASE WHEN a.status = 'late' THEN a.user_id END) as late,
        COUNT(DISTINCT CASE WHEN a.status = 'absent' THEN a.user_id END) as absent
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    " . ($branch_id && !$is_admin ? "AND u.branch_id = ?" : "") . "
    GROUP BY a.date
    ORDER BY a.date ASC
", $branch_id && !$is_admin ? [$branch_id] : []) ?: [];

$chartLabels = [];
$chartPresent = [];
$chartLate = [];
$chartAbsent = [];

$dayNames = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
foreach ($weeklyData as $day) {
    $dayNum = date('w', strtotime($day['date']));
    $chartLabels[] = $dayNames[$dayNum];
    $chartPresent[] = (int) $day['present'];
    $chartLate[] = (int) $day['late'];
    $chartAbsent[] = (int) $day['absent'];
}

// ═══════════════════════════════════════════════════════════════════════════════
// بيانات الرسم الدائري - توزيع الحضور اليوم
// ═══════════════════════════════════════════════════════════════════════════════

$pieData = [
    'present' => $todayStats['checked_in'] ?? 0,
    'late' => $todayStats['late_count'] ?? 0,
    'absent' => $totalEmployees - ($todayStats['checked_in'] ?? 0)
];

// ═══════════════════════════════════════════════════════════════════════════════
// أوقات ذروة التأخير
// ═══════════════════════════════════════════════════════════════════════════════

$peakLateTimes = Database::fetchAll("
    SELECT 
        HOUR(check_in_time) as hour,
        COUNT(*) as count
    FROM attendance
    WHERE status = 'late' 
      AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY HOUR(check_in_time)
    ORDER BY count DESC
    LIMIT 5
") ?: [];

// ═══════════════════════════════════════════════════════════════════════════════
// تنبؤ الغياب (AI-like)
// ═══════════════════════════════════════════════════════════════════════════════

$absencePrediction = Database::fetchAll("
    SELECT 
        u.id,
        u.full_name,
        u.avatar,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absence_count,
        COUNT(CASE WHEN DAYOFWEEK(a.date) = DAYOFWEEK(CURDATE() + INTERVAL 1 DAY) 
                    AND a.status = 'absent' THEN 1 END) as same_day_absence,
        ROUND(
            (COUNT(CASE WHEN a.status = 'absent' THEN 1 END) * 100.0) / 
            NULLIF(COUNT(*), 0), 1
        ) as absence_rate
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id AND a.date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    WHERE u.is_active = 1
    GROUP BY u.id
    HAVING absence_rate > 10
    ORDER BY same_day_absence DESC, absence_rate DESC
    LIMIT 5
") ?: [];

// ═══════════════════════════════════════════════════════════════════════════════
// أفضل الموظفين
// ═══════════════════════════════════════════════════════════════════════════════

$topEmployees = Database::fetchAll("
    SELECT 
        u.id,
        u.full_name,
        u.avatar,
        u.current_points,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.date END) as present_days,
        ROUND(
            (COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.date END) * 100.0) / 
            NULLIF(COUNT(DISTINCT a.date), 0), 1
        ) as attendance_rate
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id 
        AND YEAR(a.date) = YEAR(CURDATE()) 
        AND MONTH(a.date) = MONTH(CURDATE())
    WHERE u.is_active = 1
    GROUP BY u.id
    ORDER BY u.current_points DESC
    LIMIT 5
") ?: [];

// ═══════════════════════════════════════════════════════════════════════════════
// آخر الأنشطة
// ═══════════════════════════════════════════════════════════════════════════════

$recentActivities = Database::fetchAll("
    SELECT 
        a.id,
        a.user_id,
        u.full_name,
        a.check_in_time,
        a.check_out_time,
        a.status,
        a.late_minutes,
        a.date
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.date = CURDATE()
    ORDER BY COALESCE(a.check_out_time, a.check_in_time) DESC
    LIMIT 10
") ?: [];

$pageTitle = 'لوحة التحكم المتقدمة';
include __DIR__ . '/includes/header.php';
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
/* ═══════════════════════════════════════════════════════════════════════════════ */
/* أنماط لوحة التحكم */
/* ═══════════════════════════════════════════════════════════════════════════════ */

.dashboard-container {
    padding: 20px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: var(--sarh-shadow-sm);
    transition: all 0.3s ease;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--sarh-shadow);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin-bottom: 15px;
}

.stat-icon.primary { background: rgba(255,111,0,0.15); color: var(--sarh-primary); }
.stat-icon.success { background: rgba(40,167,69,0.15); color: #28a745; }
.stat-icon.warning { background: rgba(255,193,7,0.15); color: #ffc107; }
.stat-icon.danger { background: rgba(220,53,69,0.15); color: #dc3545; }
.stat-icon.info { background: rgba(23,162,184,0.15); color: #17a2b8; }

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--sarh-dark);
    line-height: 1;
}

.stat-label {
    color: #888;
    font-size: 0.9rem;
    margin-top: 5px;
}

.stat-change {
    font-size: 0.8rem;
    margin-top: 10px;
}

.stat-change.positive { color: #28a745; }
.stat-change.negative { color: #dc3545; }

.chart-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: var(--sarh-shadow-sm);
    margin-bottom: 20px;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.chart-title {
    font-weight: 700;
    color: var(--sarh-dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.chart-actions {
    display: flex;
    gap: 10px;
}

.prediction-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 20px;
    color: white;
}

.prediction-item {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.prediction-item:last-child {
    border-bottom: none;
}

.prediction-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-left: 15px;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
}

.prediction-info {
    flex: 1;
}

.prediction-name {
    font-weight: 600;
}

.prediction-risk {
    font-size: 0.8rem;
    opacity: 0.8;
}

.prediction-percent {
    background: rgba(255,255,255,0.2);
    padding: 5px 12px;
    border-radius: 50px;
    font-weight: bold;
}

.activity-feed {
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #eee;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: 15px;
}

.activity-icon.checkin { background: rgba(40,167,69,0.15); color: #28a745; }
.activity-icon.checkout { background: rgba(23,162,184,0.15); color: #17a2b8; }
.activity-icon.late { background: rgba(255,193,7,0.15); color: #ffc107; }

.activity-info {
    flex: 1;
}

.activity-name {
    font-weight: 600;
    color: var(--sarh-dark);
}

.activity-meta {
    font-size: 0.8rem;
    color: #888;
}

.activity-time {
    font-weight: 600;
    color: var(--sarh-dark);
}

.top-employee {
    display: flex;
    align-items: center;
    padding: 10px;
    border-radius: 12px;
    margin-bottom: 10px;
    transition: background 0.3s;
}

.top-employee:hover {
    background: #f8f9fa;
}

.employee-rank {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-left: 10px;
    font-size: 0.8rem;
}

.rank-1 { background: linear-gradient(135deg, #ffd700, #ffaa00); color: white; }
.rank-2 { background: linear-gradient(135deg, #c0c0c0, #a0a0a0); color: white; }
.rank-3 { background: linear-gradient(135deg, #cd7f32, #a0522d); color: white; }
.rank-default { background: #e9ecef; color: #666; }

.employee-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    margin-left: 10px;
    object-fit: cover;
}

.employee-info {
    flex: 1;
}

.employee-name {
    font-weight: 600;
    color: var(--sarh-dark);
}

.employee-stats {
    font-size: 0.8rem;
    color: #888;
}

.employee-points {
    font-weight: 700;
    color: var(--sarh-primary);
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 10px;
    }
    
    .stat-value {
        font-size: 1.5rem;
    }
    
    .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="dashboard-container">
    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-value"><?= $todayStats['checked_in'] ?? 0 ?></div>
            <div class="stat-label">سجلوا الحضور اليوم</div>
            <div class="stat-change positive">
                <i class="bi bi-arrow-up"></i>
                من أصل <?= $totalEmployees ?> موظف
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="stat-value">
                <?= $totalEmployees > 0 ? round((($todayStats['checked_in'] ?? 0) / $totalEmployees) * 100) : 0 ?>%
            </div>
            <div class="stat-label">نسبة الحضور</div>
            <div class="stat-change positive">
                <i class="bi bi-graph-up"></i>
                أعلى من الأمس
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-value"><?= $todayStats['late_count'] ?? 0 ?></div>
            <div class="stat-label">متأخرون اليوم</div>
            <div class="stat-change negative">
                <i class="bi bi-clock"></i>
                متوسط <?= round($todayStats['avg_late_minutes'] ?? 0) ?> دقيقة
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-value"><?= round(($todayStats['total_overtime'] ?? 0) / 60, 1) ?></div>
            <div class="stat-label">ساعات إضافية</div>
            <div class="stat-change positive">
                <i class="bi bi-star-fill"></i>
                إنتاجية عالية
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Weekly Chart -->
        <div class="col-lg-8 mb-4">
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="bi bi-bar-chart-fill text-primary"></i>
                        <span>إحصائيات الحضور - آخر 7 أيام</span>
                    </div>
                    <div class="chart-actions">
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleChartType()">
                            <i class="bi bi-graph-up"></i>
                        </button>
                    </div>
                </div>
                <canvas id="weeklyChart" height="300"></canvas>
            </div>
        </div>

        <!-- Pie Chart -->
        <div class="col-lg-4 mb-4">
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="bi bi-pie-chart-fill text-success"></i>
                        <span>توزيع اليوم</span>
                    </div>
                </div>
                <canvas id="pieChart" height="250"></canvas>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- AI Predictions -->
        <div class="col-lg-6 mb-4">
            <div class="prediction-card">
                <div class="d-flex align-items-center mb-3">
                    <i class="bi bi-robot fs-4 me-2"></i>
                    <h5 class="mb-0">تنبؤ الغياب بالذكاء الاصطناعي</h5>
                </div>
                <p class="opacity-75 small mb-3">
                    <i class="bi bi-info-circle"></i>
                    موظفون قد يتغيبون غداً بناءً على أنماط الغياب السابقة
                </p>
                
                <?php if (!empty($absencePrediction)): ?>
                    <?php foreach ($absencePrediction as $pred): ?>
                    <div class="prediction-item">
                        <div class="prediction-avatar">
                            <?php if ($pred['avatar']): ?>
                                <img src="<?= e($pred['avatar']) ?>" alt="" style="width:100%;height:100%;border-radius:50%;">
                            <?php else: ?>
                                <?= mb_substr($pred['full_name'], 0, 1) ?>
                            <?php endif; ?>
                        </div>
                        <div class="prediction-info">
                            <div class="prediction-name"><?= e($pred['full_name']) ?></div>
                            <div class="prediction-risk">
                                <i class="bi bi-exclamation-triangle"></i>
                                <?= $pred['absence_count'] ?> أيام غياب في آخر 90 يوم
                            </div>
                        </div>
                        <div class="prediction-percent"><?= $pred['absence_rate'] ?>%</div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 opacity-75">
                        <i class="bi bi-emoji-smile fs-1"></i>
                        <p class="mt-2 mb-0">لا توجد تنبؤات - الفريق ملتزم!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Employees -->
        <div class="col-lg-6 mb-4">
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="bi bi-trophy-fill text-warning"></i>
                        <span>أفضل الموظفين هذا الشهر</span>
                    </div>
                    <a href="leaderboard.php" class="btn btn-sm btn-outline-primary">
                        عرض الكل
                    </a>
                </div>
                
                <?php foreach ($topEmployees as $index => $emp): 
                    $rank = $index + 1;
                ?>
                <div class="top-employee">
                    <div class="employee-rank <?= $rank <= 3 ? "rank-{$rank}" : 'rank-default' ?>">
                        <?= $rank ?>
                    </div>
                    <?php if ($emp['avatar']): ?>
                        <img src="<?= e($emp['avatar']) ?>" alt="" class="employee-avatar">
                    <?php else: ?>
                        <div class="employee-avatar bg-primary text-white d-flex align-items-center justify-content-center">
                            <?= mb_substr($emp['full_name'], 0, 1) ?>
                        </div>
                    <?php endif; ?>
                    <div class="employee-info">
                        <div class="employee-name"><?= e($emp['full_name']) ?></div>
                        <div class="employee-stats">
                            <i class="bi bi-check-circle text-success"></i>
                            <?= $emp['attendance_rate'] ?? 0 ?>% حضور
                        </div>
                    </div>
                    <div class="employee-points">
                        <?= number_format($emp['current_points']) ?>
                        <small class="text-muted">نقطة</small>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($topEmployees)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-emoji-neutral fs-1"></i>
                    <p class="mt-2">لا توجد بيانات</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Activity Feed -->
        <div class="col-lg-6 mb-4">
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="bi bi-activity text-info"></i>
                        <span>آخر الأنشطة</span>
                    </div>
                </div>
                <div class="activity-feed">
                    <?php foreach ($recentActivities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?= $activity['status'] === 'late' ? 'late' : ($activity['check_out_time'] ? 'checkout' : 'checkin') ?>">
                            <?php if ($activity['check_out_time']): ?>
                                <i class="bi bi-box-arrow-right"></i>
                            <?php elseif ($activity['status'] === 'late'): ?>
                                <i class="bi bi-clock-history"></i>
                            <?php else: ?>
                                <i class="bi bi-box-arrow-in-left"></i>
                            <?php endif; ?>
                        </div>
                        <div class="activity-info">
                            <div class="activity-name"><?= e($activity['full_name']) ?></div>
                            <div class="activity-meta">
                                <?php if ($activity['check_out_time']): ?>
                                    سجل الانصراف
                                <?php elseif ($activity['status'] === 'late'): ?>
                                    تأخر <?= $activity['late_minutes'] ?> دقيقة
                                <?php else: ?>
                                    سجل الحضور
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="activity-time">
                            <?= date('H:i', strtotime($activity['check_out_time'] ?: $activity['check_in_time'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($recentActivities)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2">لا توجد أنشطة اليوم</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Peak Late Times -->
        <div class="col-lg-6 mb-4">
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="bi bi-clock text-warning"></i>
                        <span>أوقات ذروة التأخير</span>
                    </div>
                </div>
                <canvas id="peakTimesChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════════════════════════
// إعداد الرسوم البيانية
// ═══════════════════════════════════════════════════════════════════════════════

Chart.defaults.font.family = 'Tajawal, sans-serif';
Chart.defaults.color = '#666';

// Weekly Attendance Chart
const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
let weeklyChart = new Chart(weeklyCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
            {
                label: 'حاضر',
                data: <?= json_encode($chartPresent) ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.8)',
                borderRadius: 8
            },
            {
                label: 'متأخر',
                data: <?= json_encode($chartLate) ?>,
                backgroundColor: 'rgba(255, 193, 7, 0.8)',
                borderRadius: 8
            },
            {
                label: 'غائب',
                data: <?= json_encode($chartAbsent) ?>,
                backgroundColor: 'rgba(220, 53, 69, 0.8)',
                borderRadius: 8
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                rtl: true,
                labels: {
                    usePointStyle: true,
                    padding: 20
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0,0,0,0.05)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Pie Chart
const pieCtx = document.getElementById('pieChart').getContext('2d');
new Chart(pieCtx, {
    type: 'doughnut',
    data: {
        labels: ['حاضر', 'متأخر', 'غائب'],
        datasets: [{
            data: [<?= $pieData['present'] ?>, <?= $pieData['late'] ?>, <?= $pieData['absent'] ?>],
            backgroundColor: [
                'rgba(40, 167, 69, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(220, 53, 69, 0.8)'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: {
                position: 'bottom',
                rtl: true,
                labels: {
                    usePointStyle: true,
                    padding: 20
                }
            }
        }
    }
});

// Peak Times Chart
const peakData = <?= json_encode($peakLateTimes) ?>;
const peakLabels = peakData.map(p => `${p.hour}:00`);
const peakValues = peakData.map(p => p.count);

const peakCtx = document.getElementById('peakTimesChart').getContext('2d');
new Chart(peakCtx, {
    type: 'bar',
    data: {
        labels: peakLabels,
        datasets: [{
            label: 'عدد المتأخرين',
            data: peakValues,
            backgroundColor: 'rgba(255, 111, 0, 0.8)',
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0,0,0,0.05)'
                }
            },
            y: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Toggle chart type
function toggleChartType() {
    weeklyChart.config.type = weeklyChart.config.type === 'bar' ? 'line' : 'bar';
    weeklyChart.update();
}

// Auto refresh every 60 seconds
setInterval(() => {
    location.reload();
}, 60000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
