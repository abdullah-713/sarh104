<?php
/**
 * صفحة حضور الفريق - Team Attendance
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

check_login();
require_permission('view_team_attendance');

$pageTitle = 'حضور الفريق';
$currentPage = 'team-attendance';

$today = date('Y-m-d');
$selectedDate = $_GET['date'] ?? $today;

// جلب حضور الفريق
try {
    $branchId = $_SESSION['branch_id'] ?? null;
    
    $teamAttendance = Database::fetchAll("
        SELECT 
            u.id, u.full_name, u.emp_code, u.is_online,
            a.check_in_at, a.check_out_at, a.status, a.late_minutes,
            r.name as role_name, r.color as role_color
        FROM users u
        LEFT JOIN attendance a ON u.id = a.user_id AND a.attendance_date = :date
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.branch_id = :branch_id AND u.is_active = 1
        ORDER BY u.full_name
    ", ['date' => $selectedDate, 'branch_id' => $branchId]);
    
    // إحصائيات
    $stats = [
        'total' => count($teamAttendance),
        'present' => 0,
        'late' => 0,
        'absent' => 0,
        'online' => 0
    ];
    
    foreach ($teamAttendance as $emp) {
        if ($emp['check_in_at']) {
            $stats['present']++;
            if ($emp['late_minutes'] > 0) $stats['late']++;
        } else {
            $stats['absent']++;
        }
        if ($emp['is_online']) $stats['online']++;
    }
    
} catch (Exception $e) {
    $teamAttendance = [];
    $stats = ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'online' => 0];
}

include INCLUDES_PATH . '/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i class="bi bi-people-fill text-primary me-2"></i>
            حضور الفريق
        </h4>
        <form method="GET" class="d-flex gap-2">
            <input type="date" name="date" class="form-control" value="<?= e($selectedDate) ?>" max="<?= $today ?>">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search"></i>
            </button>
        </form>
    </div>
    
    <!-- إحصائيات -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center py-3">
                    <h3 class="mb-0"><?= $stats['total'] ?></h3>
                    <small>إجمالي الموظفين</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center py-3">
                    <h3 class="mb-0"><?= $stats['present'] ?></h3>
                    <small>حاضرين</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center py-3">
                    <h3 class="mb-0"><?= $stats['late'] ?></h3>
                    <small>متأخرين</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center py-3">
                    <h3 class="mb-0"><?= $stats['absent'] ?></h3>
                    <small>غائبين</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- جدول الحضور -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                <i class="bi bi-calendar-check me-2"></i>
                سجل الحضور - <?= format_arabic_date($selectedDate, false) ?>
            </span>
            <span class="badge bg-info">
                <i class="bi bi-circle-fill text-success me-1" style="font-size:0.5rem;"></i>
                <?= $stats['online'] ?> متصل الآن
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>الموظف</th>
                        <th>الدور</th>
                        <th>الدخول</th>
                        <th>الخروج</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teamAttendance)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox display-6 d-block mb-2"></i>
                            لا توجد بيانات
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($teamAttendance as $emp): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-2 position-relative" style="width:35px;height:35px;background:#6c757d;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                                    <i class="bi bi-person text-white"></i>
                                    <?php if ($emp['is_online']): ?>
                                    <span class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle" style="width:10px;height:10px;"></span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong><?= e($emp['full_name']) ?></strong>
                                    <br><small class="text-muted"><?= e($emp['emp_code']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge" style="background:<?= e($emp['role_color'] ?? '#6c757d') ?>">
                                <?= e($emp['role_name'] ?? '-') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($emp['check_in_at']): ?>
                            <span class="text-success">
                                <i class="bi bi-box-arrow-in-left me-1"></i>
                                <?= date('h:i A', strtotime($emp['check_in_at'])) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($emp['check_out_at']): ?>
                            <span class="text-danger">
                                <i class="bi bi-box-arrow-right me-1"></i>
                                <?= date('h:i A', strtotime($emp['check_out_at'])) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($emp['check_in_at']): ?>
                                <?php if ($emp['late_minutes'] > 0): ?>
                                <span class="badge bg-warning text-dark">
                                    متأخر <?= $emp['late_minutes'] ?> د
                                </span>
                                <?php else: ?>
                                <span class="badge bg-success">حاضر</span>
                                <?php endif; ?>
                            <?php else: ?>
                            <span class="badge bg-danger">غائب</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
