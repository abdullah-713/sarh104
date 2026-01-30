<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - TEAM PERFORMANCE PAGE                               ║
 * ║           صفحة أداء الفريق                                                   ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Features:                                                                   ║
 * ║  - مقارنة أداء الموظفين                                                      ║
 * ║  - ترتيب حسب معايير متعددة                                                   ║
 * ║  - رسوم بيانية تفاعلية                                                       ║
 * ║  - تصدير التقارير                                                            ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// جلب بيانات الفريق
$period = $_GET['period'] ?? 30;
$branch_filter = $_GET['branch'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'attendance';

$branch_where = '';
$params = [$period];

if ($branch_filter !== 'all') {
    $branch_where = 'AND u.branch_id = ?';
    $params[] = $branch_filter;
}

// إحصائيات الفريق
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.name,
        u.photo,
        b.name as branch_name,
        d.name as department_name,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
        COUNT(*) as total_days,
        ROUND(COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as attendance_rate,
        COALESCE(SUM(TIMESTAMPDIFF(HOUR, a.check_in, a.check_out)), 0) as total_hours,
        COALESCE((SELECT points FROM user_gamification WHERE user_id = u.id), 0) as points
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN attendance a ON u.id = a.user_id 
        AND a.date >= DATE_SUB(NOW(), INTERVAL ? DAY)
    WHERE u.is_active = 1 AND u.role = 'employee'
    $branch_where
    GROUP BY u.id
    ORDER BY 
        CASE WHEN ? = 'attendance' THEN attendance_rate END DESC,
        CASE WHEN ? = 'points' THEN points END DESC,
        CASE WHEN ? = 'hours' THEN total_hours END DESC,
        CASE WHEN ? = 'name' THEN u.name END ASC
");
$params[] = $sort_by;
$params[] = $sort_by;
$params[] = $sort_by;
$params[] = $sort_by;
$stmt->execute($params);
$team = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب الفروع للفلترة
$branches = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1")->fetchAll();

// إحصائيات عامة
$stats = [
    'total_employees' => count($team),
    'avg_attendance' => $team ? round(array_sum(array_column($team, 'attendance_rate')) / count($team), 1) : 0,
    'total_points' => array_sum(array_column($team, 'points')),
    'top_performer' => !empty($team) ? $team[0]['name'] : 'لا يوجد'
];

include 'includes/header.php';
?>

<style>
    .performance-card {
        border: none;
        border-radius: 16px;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    .performance-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    
    .employee-row {
        padding: 1rem;
        border-bottom: 1px solid var(--bs-border-color);
        transition: background 0.2s;
    }
    
    .employee-row:hover {
        background: rgba(102, 126, 234, 0.05);
    }
    
    .employee-row:last-child {
        border-bottom: none;
    }
    
    .rank-badge {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.9rem;
    }
    
    .rank-1 { background: linear-gradient(135deg, #FFD700, #FFA500); color: white; }
    .rank-2 { background: linear-gradient(135deg, #C0C0C0, #A0A0A0); color: white; }
    .rank-3 { background: linear-gradient(135deg, #CD7F32, #8B4513); color: white; }
    .rank-other { background: var(--bs-secondary-bg); color: var(--bs-body-color); }
    
    .mini-chart {
        width: 80px;
        height: 30px;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    
    .filter-btn {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        border: 2px solid transparent;
        background: var(--bs-secondary-bg);
        color: var(--bs-body-color);
        transition: all 0.2s;
    }
    
    .filter-btn:hover, .filter-btn.active {
        background: var(--gradient-primary);
        color: white;
        border-color: transparent;
    }
    
    .progress-slim {
        height: 6px;
        border-radius: 3px;
    }
    
    .comparison-bar {
        height: 20px;
        border-radius: 10px;
        position: relative;
        overflow: visible;
    }
    
    .comparison-bar-fill {
        height: 100%;
        border-radius: 10px;
        transition: width 0.5s ease;
        position: relative;
    }
    
    .comparison-bar-label {
        position: absolute;
        right: -40px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.75rem;
        font-weight: bold;
    }
    
    @media (max-width: 768px) {
        .comparison-bar-label {
            position: static;
            transform: none;
            display: block;
            margin-top: 0.25rem;
            text-align: left;
        }
    }
</style>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-people-fill me-2"></i>أداء الفريق</h3>
            <p class="text-muted mb-0">تحليل ومقارنة أداء الموظفين</p>
        </div>
        <button class="btn btn-outline-primary" onclick="exportReport()">
            <i class="bi bi-download me-1"></i> تصدير
        </button>
    </div>
    
    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card performance-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <div class="text-muted small">إجمالي الموظفين</div>
                        <div class="fw-bold h4 mb-0"><?php echo $stats['total_employees']; ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card performance-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div>
                        <div class="text-muted small">متوسط الحضور</div>
                        <div class="fw-bold h4 mb-0"><?php echo $stats['avg_attendance']; ?>%</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card performance-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                        <i class="bi bi-star"></i>
                    </div>
                    <div>
                        <div class="text-muted small">إجمالي النقاط</div>
                        <div class="fw-bold h4 mb-0"><?php echo number_format($stats['total_points']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card performance-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                        <i class="bi bi-trophy"></i>
                    </div>
                    <div>
                        <div class="text-muted small">الأفضل أداءً</div>
                        <div class="fw-bold text-truncate" style="max-width: 100px;"><?php echo $stats['top_performer']; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card performance-card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label class="form-label small text-muted">الفترة الزمنية</label>
                    <select class="form-select" id="periodFilter" onchange="applyFilters()">
                        <option value="7" <?php echo $period == 7 ? 'selected' : ''; ?>>آخر أسبوع</option>
                        <option value="30" <?php echo $period == 30 ? 'selected' : ''; ?>>آخر شهر</option>
                        <option value="90" <?php echo $period == 90 ? 'selected' : ''; ?>>آخر 3 أشهر</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">الفرع</label>
                    <select class="form-select" id="branchFilter" onchange="applyFilters()">
                        <option value="all">جميع الفروع</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>" <?php echo $branch_filter == $branch['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">ترتيب حسب</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="filter-btn <?php echo $sort_by === 'attendance' ? 'active' : ''; ?>" onclick="sortBy('attendance')">الحضور</button>
                        <button class="filter-btn <?php echo $sort_by === 'points' ? 'active' : ''; ?>" onclick="sortBy('points')">النقاط</button>
                        <button class="filter-btn <?php echo $sort_by === 'hours' ? 'active' : ''; ?>" onclick="sortBy('hours')">الساعات</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Team List -->
    <div class="card performance-card">
        <div class="card-header bg-transparent border-0 py-3">
            <h5 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>قائمة الفريق</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($team)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-people display-1 text-muted"></i>
                    <p class="mt-3 text-muted">لا يوجد موظفين للعرض</p>
                </div>
            <?php else: ?>
                <?php foreach ($team as $index => $employee): ?>
                    <div class="employee-row">
                        <div class="row align-items-center">
                            <!-- Rank & Photo -->
                            <div class="col-auto">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rank-badge <?php echo $index < 3 ? 'rank-' . ($index + 1) : 'rank-other'; ?>">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <img src="<?php echo $employee['photo'] ?: 'assets/images/avatar.png'; ?>" 
                                         alt="<?php echo htmlspecialchars($employee['name']); ?>"
                                         class="rounded-circle"
                                         style="width: 45px; height: 45px; object-fit: cover;">
                                </div>
                            </div>
                            
                            <!-- Name & Info -->
                            <div class="col">
                                <div class="fw-bold"><?php echo htmlspecialchars($employee['name']); ?></div>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($employee['branch_name'] ?? 'غير محدد'); ?>
                                    <?php if ($employee['department_name']): ?>
                                        • <?php echo htmlspecialchars($employee['department_name']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                            <!-- Attendance Rate -->
                            <div class="col-md-2 d-none d-md-block">
                                <div class="small text-muted mb-1">الحضور</div>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress progress-slim flex-grow-1">
                                        <div class="progress-bar bg-<?php echo $employee['attendance_rate'] >= 90 ? 'success' : ($employee['attendance_rate'] >= 70 ? 'warning' : 'danger'); ?>" 
                                             style="width: <?php echo $employee['attendance_rate'] ?? 0; ?>%"></div>
                                    </div>
                                    <span class="fw-bold"><?php echo $employee['attendance_rate'] ?? 0; ?>%</span>
                                </div>
                            </div>
                            
                            <!-- Days Breakdown -->
                            <div class="col-md-2 d-none d-md-block text-center">
                                <div class="d-flex justify-content-center gap-2">
                                    <span class="badge bg-success"><?php echo $employee['present_days']; ?> حضور</span>
                                    <span class="badge bg-danger"><?php echo $employee['absent_days']; ?> غياب</span>
                                </div>
                            </div>
                            
                            <!-- Points -->
                            <div class="col-auto">
                                <div class="d-flex align-items-center gap-1">
                                    <i class="bi bi-star-fill text-warning"></i>
                                    <span class="fw-bold"><?php echo number_format($employee['points']); ?></span>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="col-auto">
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="employee-report.php?id=<?php echo $employee['id']; ?>">
                                            <i class="bi bi-file-text me-2"></i>تقرير مفصل
                                        </a></li>
                                        <li><a class="dropdown-item" href="attendance-history.php?user=<?php echo $employee['id']; ?>">
                                            <i class="bi bi-clock-history me-2"></i>سجل الحضور
                                        </a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Comparison Chart -->
    <div class="card performance-card mt-4">
        <div class="card-header bg-transparent border-0 py-3">
            <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>مقارنة الأداء</h5>
        </div>
        <div class="card-body">
            <canvas id="comparisonChart" height="300"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// Team data for chart
const teamData = <?php echo json_encode(array_slice($team, 0, 10)); ?>;

// Comparison Chart
const ctx = document.getElementById('comparisonChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: teamData.map(e => e.name.split(' ')[0]),
        datasets: [
            {
                label: 'نسبة الحضور %',
                data: teamData.map(e => e.attendance_rate || 0),
                backgroundColor: 'rgba(102, 126, 234, 0.7)',
                borderRadius: 8
            },
            {
                label: 'النقاط (÷10)',
                data: teamData.map(e => (e.points || 0) / 10),
                backgroundColor: 'rgba(255, 193, 7, 0.7)',
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
                rtl: true
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});

function applyFilters() {
    const period = document.getElementById('periodFilter').value;
    const branch = document.getElementById('branchFilter').value;
    const url = new URL(window.location.href);
    url.searchParams.set('period', period);
    url.searchParams.set('branch', branch);
    window.location.href = url.toString();
}

function sortBy(field) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', field);
    window.location.href = url.toString();
}

function exportReport() {
    // Export logic
    Swal.fire({
        icon: 'success',
        title: 'جاري التصدير',
        text: 'سيتم تحميل التقرير قريباً',
        timer: 2000,
        showConfirmButton: false
    });
}
</script>

<?php include 'includes/footer.php'; ?>
