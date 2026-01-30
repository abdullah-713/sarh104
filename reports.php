<?php
/**
 * التقارير - Reports Page
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

check_login();

$pageTitle = 'التقارير';
$currentPage = 'reports';

// الشهر الحالي
$month = $_GET['month'] ?? date('Y-m');
$userId = current_user_id();

// جلب إحصائيات الحضور
try {
    $stats = Database::fetchOne("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_days,
            SUM(late_minutes) as total_late_minutes,
            SUM(overtime_minutes) as total_overtime,
            SUM(penalty_points) as total_penalty,
            SUM(bonus_points) as total_bonus
        FROM attendance 
        WHERE user_id = :user_id 
        AND DATE_FORMAT(date, '%Y-%m') = :month
    ", ['user_id' => $userId, 'month' => $month]);
    
    $records = Database::fetchAll("
        SELECT * FROM attendance 
        WHERE user_id = :user_id 
        AND DATE_FORMAT(date, '%Y-%m') = :month
        ORDER BY date DESC
    ", ['user_id' => $userId, 'month' => $month]);
} catch (Exception $e) {
    $stats = null;
    $records = [];
}

include INCLUDES_PATH . '/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i class="bi bi-bar-chart-line-fill text-primary me-2"></i>
            التقارير
        </h4>
        <input type="month" class="form-control" style="width:auto;" value="<?= e($month) ?>" onchange="location.href='?month='+this.value">
    </div>
    
    <!-- بطاقات الإحصائيات -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center h-100 border-success">
                <div class="card-body py-3">
                    <i class="bi bi-check-circle-fill text-success fs-2"></i>
                    <h3 class="mb-0 mt-2"><?= (int)($stats['present_days'] ?? 0) ?></h3>
                    <small class="text-muted">أيام الحضور</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center h-100 border-danger">
                <div class="card-body py-3">
                    <i class="bi bi-x-circle-fill text-danger fs-2"></i>
                    <h3 class="mb-0 mt-2"><?= (int)($stats['absent_days'] ?? 0) ?></h3>
                    <small class="text-muted">أيام الغياب</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center h-100 border-warning">
                <div class="card-body py-3">
                    <i class="bi bi-clock-fill text-warning fs-2"></i>
                    <h3 class="mb-0 mt-2"><?= (int)($stats['total_late_minutes'] ?? 0) ?></h3>
                    <small class="text-muted">دقائق التأخير</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center h-100 border-info">
                <div class="card-body py-3">
                    <i class="bi bi-plus-circle-fill text-info fs-2"></i>
                    <h3 class="mb-0 mt-2"><?= (int)($stats['total_overtime'] ?? 0) ?></h3>
                    <small class="text-muted">دقائق إضافية</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ملخص النقاط -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row text-center">
                <div class="col-6">
                    <span class="text-danger fs-4 fw-bold">-<?= number_format($stats['total_penalty'] ?? 0, 1) ?></span>
                    <br><small class="text-muted">نقاط الخصم</small>
                </div>
                <div class="col-6">
                    <span class="text-success fs-4 fw-bold">+<?= number_format($stats['total_bonus'] ?? 0, 1) ?></span>
                    <br><small class="text-muted">نقاط المكافأة</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- سجل الحضور -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-calendar3 me-2"></i>
            سجل الحضور - <?= date('F Y', strtotime($month . '-01')) ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($records)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                لا توجد سجلات لهذا الشهر
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>التاريخ</th>
                            <th>الدخول</th>
                            <th>الخروج</th>
                            <th>الحالة</th>
                            <th>التأخير</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?= date('Y/m/d', strtotime($record['date'])) ?></td>
                            <td><?= $record['check_in_time'] ? date('H:i', strtotime($record['check_in_time'])) : '-' ?></td>
                            <td><?= $record['check_out_time'] ? date('H:i', strtotime($record['check_out_time'])) : '-' ?></td>
                            <td>
                                <?php
                                $statusColors = ['present' => 'success', 'absent' => 'danger', 'late' => 'warning', 'leave' => 'info', 'half_day' => 'secondary', 'holiday' => 'dark'];
                                $statusNames = ['present' => 'حاضر', 'absent' => 'غائب', 'late' => 'متأخر', 'leave' => 'إجازة', 'half_day' => 'نصف يوم', 'holiday' => 'عطلة'];
                                ?>
                                <span class="badge bg-<?= $statusColors[$record['status']] ?? 'secondary' ?>">
                                    <?= $statusNames[$record['status']] ?? $record['status'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($record['late_minutes'] > 0): ?>
                                <span class="text-danger"><?= $record['late_minutes'] ?> د</span>
                                <?php else: ?>
                                <span class="text-success">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
