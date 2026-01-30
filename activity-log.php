<?php
/**
 * سجل النشاطات - Activity Log
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

check_login();
require_role(ROLE_ADMIN);

$pageTitle = 'سجل النشاطات';
$currentPage = 'activity-log';

// الصفحة الحالية
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// جلب السجلات
try {
    $total = Database::fetchValue("SELECT COUNT(*) FROM activity_log");
    $logs = Database::fetchAll("
        SELECT al.*, u.full_name, u.username
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    ");
    $totalPages = ceil($total / $perPage);
} catch (Exception $e) {
    $logs = [];
    $total = 0;
    $totalPages = 1;
}

include INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i class="bi bi-clock-history text-primary me-2"></i>
            سجل النشاطات
        </h4>
        <span class="badge bg-secondary"><?= number_format($total) ?> سجل</span>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>المستخدم</th>
                            <th>الإجراء</th>
                            <th>التفاصيل</th>
                            <th>IP</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                لا توجد سجلات
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= e($log['id']) ?></td>
                            <td>
                                <?php if ($log['full_name']): ?>
                                <strong><?= e($log['full_name']) ?></strong>
                                <br><small class="text-muted"><?= e($log['username']) ?></small>
                                <?php else: ?>
                                <span class="text-muted">النظام</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= e(getActionColor($log['action'])) ?>">
                                    <?= e($log['action']) ?>
                                </span>
                            </td>
                            <td style="max-width:300px;">
                                <?php if ($log['model_type']): ?>
                                <small class="text-muted"><?= e($log['model_type']) ?> #<?= e($log['model_id']) ?></small><br>
                                <?php endif; ?>
                                <?php if ($log['new_values']): ?>
                                <small class="text-truncate d-block"><?= e(substr(json_encode(json_decode($log['new_values']), JSON_UNESCAPED_UNICODE), 0, 100)) ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td><small><?= e($log['ip_address'] ?? '-') ?></small></td>
                            <td>
                                <small><?= e(date('Y/m/d', strtotime($log['created_at']))) ?></small>
                                <br><small class="text-muted"><?= e(date('H:i:s', strtotime($log['created_at']))) ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>">السابق</a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="page-item disabled">
                        <span class="page-link"><?= $page ?> / <?= $totalPages ?></span>
                    </li>
                    
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>">التالي</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
function getActionColor($action) {
    $colors = [
        'login_success' => 'success',
        'login_failed' => 'danger',
        'logout' => 'secondary',
        'create' => 'primary',
        'update' => 'warning',
        'delete' => 'danger',
        'checkin' => 'success',
        'checkout' => 'info',
    ];
    return $colors[$action] ?? 'secondary';
}

include INCLUDES_PATH . '/footer.php';
?>
