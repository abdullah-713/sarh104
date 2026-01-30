<?php
/**
 * صفحة الإجازات - Leaves Management
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

check_login();

$pageTitle = 'طلبات الإجازة';
$currentPage = 'leaves';

$userId = current_user_id();

// جلب طلبات الإجازة
try {
    $leaves = Database::fetchAll("
        SELECT l.*, lt.name as leave_type_name, lt.color as leave_type_color
        FROM leaves l
        LEFT JOIN leave_types lt ON l.leave_type_id = lt.id
        WHERE l.user_id = :user_id
        ORDER BY l.created_at DESC
        LIMIT 20
    ", ['user_id' => $userId]);
    
    $leaveTypes = Database::fetchAll("SELECT * FROM leave_types WHERE is_active = 1 ORDER BY name");
    
    // رصيد الإجازات
    $balances = Database::fetchAll("
        SELECT lb.*, lt.name as leave_type_name, lt.color
        FROM leave_balances lb
        JOIN leave_types lt ON lb.leave_type_id = lt.id
        WHERE lb.user_id = :user_id AND lb.year = :year
    ", ['user_id' => $userId, 'year' => date('Y')]);
    
} catch (Exception $e) {
    $leaves = [];
    $leaveTypes = [];
    $balances = [];
}

include INCLUDES_PATH . '/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i class="bi bi-calendar-x-fill text-primary me-2"></i>
            طلبات الإجازة
        </h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newLeaveModal">
            <i class="bi bi-plus-lg me-1"></i>
            طلب إجازة جديد
        </button>
    </div>
    
    <!-- رصيد الإجازات -->
    <?php if (!empty($balances)): ?>
    <div class="row g-3 mb-4">
        <?php foreach ($balances as $balance): ?>
        <div class="col-6 col-md-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center"
                         style="width:50px;height:50px;background:<?= e($balance['color'] ?? '#6c757d') ?>;opacity:0.9;">
                        <i class="bi bi-calendar-check text-white"></i>
                    </div>
                    <h4 class="mb-0"><?= $balance['remaining_days'] ?></h4>
                    <small class="text-muted"><?= e($balance['leave_type_name']) ?></small>
                    <div class="progress mt-2" style="height:4px;">
                        <?php $pct = $balance['total_days'] > 0 ? ($balance['remaining_days'] / $balance['total_days']) * 100 : 0; ?>
                        <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= e($balance['color'] ?? '#6c757d') ?>"></div>
                    </div>
                    <small class="text-muted">من <?= $balance['total_days'] ?> يوم</small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- قائمة الطلبات -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-list-ul me-2"></i>
            طلباتي السابقة
        </div>
        <?php if (empty($leaves)): ?>
        <div class="card-body text-center py-5">
            <i class="bi bi-calendar-x display-1 text-muted mb-3"></i>
            <h5 class="text-muted">لا توجد طلبات إجازة</h5>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>النوع</th>
                        <th>من</th>
                        <th>إلى</th>
                        <th>الأيام</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaves as $leave): ?>
                    <tr>
                        <td>
                            <span class="badge" style="background:<?= e($leave['leave_type_color'] ?? '#6c757d') ?>">
                                <?= e($leave['leave_type_name'] ?? 'غير محدد') ?>
                            </span>
                        </td>
                        <td><?= date('Y-m-d', strtotime($leave['start_date'])) ?></td>
                        <td><?= date('Y-m-d', strtotime($leave['end_date'])) ?></td>
                        <td><?= $leave['days_count'] ?? '-' ?></td>
                        <td>
                            <?php 
                            $statusBadge = [
                                'pending' => '<span class="badge bg-warning">قيد الانتظار</span>',
                                'approved' => '<span class="badge bg-success">موافق عليها</span>',
                                'rejected' => '<span class="badge bg-danger">مرفوضة</span>',
                                'cancelled' => '<span class="badge bg-secondary">ملغاة</span>'
                            ];
                            echo $statusBadge[$leave['status']] ?? '<span class="badge bg-secondary">-</span>';
                            ?>
                        </td>
                        <td><small class="text-muted"><?= date('Y-m-d', strtotime($leave['created_at'])) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal طلب إجازة جديد -->
<div class="modal fade" id="newLeaveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-plus me-2"></i>
                    طلب إجازة جديد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="leaveForm">
                <div class="modal-body">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label class="form-label">نوع الإجازة *</label>
                        <select name="leave_type_id" class="form-select" required>
                            <option value="">-- اختر نوع الإجازة --</option>
                            <?php foreach ($leaveTypes as $type): ?>
                            <option value="<?= $type['id'] ?>"><?= e($type['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">من تاريخ *</label>
                            <input type="date" name="start_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">إلى تاريخ *</label>
                            <input type="date" name="end_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">السبب</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="اختياري..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>
                        إرسال الطلب
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('leaveForm').addEventListener('submit', function(e) {
    e.preventDefault();
    Swal.fire({
        icon: 'info',
        title: 'قيد التطوير',
        text: 'هذه الميزة قيد التطوير حالياً',
        confirmButtonText: 'حسناً'
    });
});
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
