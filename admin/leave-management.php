<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - ADMIN LEAVE MANAGEMENT / إدارة الإجازات              ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  صفحة إدارة طلبات الإجازة للمدراء                                            ║
 * ║  - الموافقة/الرفض على الطلبات                                               ║
 * ║  - عرض جميع الطلبات                                                         ║
 * ║  - إحصائيات الإجازات                                                         ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

session_start();
require_once '../config/database.php';
require_once '../config/app.php';

// التحقق من تسجيل الدخول وصلاحيات الإدارة
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!is_super_admin($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$page_title = 'إدارة الإجازات';
$user_id = $_SESSION['user_id'];

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $request_id = intval($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $rejection_reason = htmlspecialchars(trim($_POST['rejection_reason'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    if ($request_id && in_array($action, ['approve', 'reject'])) {
        try {
            $new_status = $action === 'approve' ? 'approved' : 'rejected';
            
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = ?, approved_by = ?, rejection_reason = ?, updated_at = NOW()
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$new_status, $user_id, $rejection_reason, $request_id]);
            
            if ($stmt->rowCount() > 0) {
                $success_msg = $action === 'approve' ? 'تمت الموافقة على الطلب' : 'تم رفض الطلب';
            } else {
                $error_msg = 'لم يتم العثور على الطلب أو تمت معالجته مسبقاً';
            }
        } catch (Exception $e) {
            $error_msg = 'حدث خطأ أثناء معالجة الطلب';
        }
    }
}

// فلترة الحالة
$status_filter = $_GET['status'] ?? 'pending';
$valid_statuses = ['all', 'pending', 'approved', 'rejected', 'cancelled'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'pending';
}

// جلب الطلبات
try {
    $sql = "
        SELECT lr.*, 
               u.name as user_name, 
               u.email as user_email,
               b.name as branch_name,
               a.name as approver_name
        FROM leave_requests lr
        JOIN users u ON lr.user_id = u.id
        LEFT JOIN branches b ON u.branch_id = b.id
        LEFT JOIN users a ON lr.approved_by = a.id
    ";
    
    if ($status_filter !== 'all') {
        $sql .= " WHERE lr.status = ?";
        $stmt = $pdo->prepare($sql . " ORDER BY lr.created_at DESC");
        $stmt->execute([$status_filter]);
    } else {
        $stmt = $pdo->prepare($sql . " ORDER BY lr.created_at DESC");
        $stmt->execute();
    }
    
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $requests = [];
}

// إحصائيات
try {
    $stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM leave_requests
        GROUP BY status
    ");
    $stats_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $stats_raw = [];
}

$stats = [
    'pending' => $stats_raw['pending'] ?? 0,
    'approved' => $stats_raw['approved'] ?? 0,
    'rejected' => $stats_raw['rejected'] ?? 0,
    'cancelled' => $stats_raw['cancelled'] ?? 0
];
$stats['total'] = array_sum($stats);

// أنواع الإجازات
$leave_types = [
    'annual' => ['name' => 'سنوية', 'icon' => 'bi-calendar-check', 'color' => 'primary'],
    'sick' => ['name' => 'مرضية', 'icon' => 'bi-heart-pulse', 'color' => 'danger'],
    'emergency' => ['name' => 'طارئة', 'icon' => 'bi-exclamation-triangle', 'color' => 'warning'],
    'unpaid' => ['name' => 'بدون راتب', 'icon' => 'bi-cash-stack', 'color' => 'secondary'],
    'maternity' => ['name' => 'أمومة', 'icon' => 'bi-heart', 'color' => 'pink'],
    'study' => ['name' => 'دراسية', 'icon' => 'bi-book', 'color' => 'info']
];

$status_labels = [
    'pending' => ['name' => 'قيد الانتظار', 'class' => 'warning'],
    'approved' => ['name' => 'موافق عليه', 'class' => 'success'],
    'rejected' => ['name' => 'مرفوض', 'class' => 'danger'],
    'cancelled' => ['name' => 'ملغي', 'class' => 'secondary']
];

require_once '../includes/header.php';
?>

<style>
    .stats-card {
        border-radius: 15px;
        padding: 20px;
        text-align: center;
        color: white;
        transition: transform 0.3s ease;
    }

    .stats-card:hover {
        transform: translateY(-5px);
    }

    .stats-number {
        font-size: 2.5rem;
        font-weight: 700;
    }

    .request-card {
        border-radius: 12px;
        border: 1px solid #eee;
        margin-bottom: 15px;
        transition: all 0.3s ease;
    }

    .request-card:hover {
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }

    .request-card.pending {
        border-right: 4px solid #ffc107;
    }

    .request-card.approved {
        border-right: 4px solid #28a745;
    }

    .request-card.rejected {
        border-right: 4px solid #dc3545;
    }

    .filter-tabs {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .filter-tab {
        padding: 10px 20px;
        border-radius: 25px;
        border: 1px solid #ddd;
        background: white;
        color: #666;
        text-decoration: none;
        white-space: nowrap;
        transition: all 0.3s ease;
    }

    .filter-tab:hover {
        border-color: var(--sarh-primary);
        color: var(--sarh-primary);
    }

    .filter-tab.active {
        background: var(--sarh-primary);
        color: white;
        border-color: var(--sarh-primary);
    }

    .action-buttons {
        display: flex;
        gap: 10px;
    }

    .action-buttons .btn {
        flex: 1;
    }
</style>

<div class="container py-4 mb-5">
    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-calendar2-week text-primary me-2"></i>
                إدارة الإجازات
            </h4>
            <small class="text-muted">مراجعة والموافقة على طلبات الإجازة</small>
        </div>
        <a href="../more.php" class="btn btn-sm btn-outline-secondary rounded-pill">
            <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <?php if (isset($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        <?php echo $success_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle me-2"></i>
        <?php echo $error_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #ffc107, #ff9800);">
                <div class="stats-number"><?php echo $stats['pending']; ?></div>
                <small>قيد الانتظار</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #28a745, #20c997);">
                <div class="stats-number"><?php echo $stats['approved']; ?></div>
                <small>موافق عليها</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #dc3545, #fd7e14);">
                <div class="stats-number"><?php echo $stats['rejected']; ?></div>
                <small>مرفوضة</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #6c757d, #adb5bd);">
                <div class="stats-number"><?php echo $stats['total']; ?></div>
                <small>الإجمالي</small>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="?status=pending" class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
            <i class="bi bi-clock me-1"></i>
            قيد الانتظار
            <?php if ($stats['pending'] > 0): ?>
            <span class="badge bg-warning text-dark ms-1"><?php echo $stats['pending']; ?></span>
            <?php endif; ?>
        </a>
        <a href="?status=approved" class="filter-tab <?php echo $status_filter === 'approved' ? 'active' : ''; ?>">
            <i class="bi bi-check-circle me-1"></i>
            موافق عليها
        </a>
        <a href="?status=rejected" class="filter-tab <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
            <i class="bi bi-x-circle me-1"></i>
            مرفوضة
        </a>
        <a href="?status=all" class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
            <i class="bi bi-list me-1"></i>
            الكل
        </a>
    </div>

    <!-- Requests List -->
    <?php if (empty($requests)): ?>
    <div class="text-center py-5">
        <i class="bi bi-inbox text-muted" style="font-size: 4rem;"></i>
        <p class="text-muted mt-3">لا توجد طلبات</p>
    </div>
    <?php else: ?>
        <?php foreach ($requests as $request): ?>
        <?php 
            $type = $leave_types[$request['leave_type']] ?? ['name' => 'إجازة', 'icon' => 'bi-calendar', 'color' => 'secondary'];
            $status = $status_labels[$request['status']] ?? ['name' => 'غير محدد', 'class' => 'secondary'];
            $start = new DateTime($request['start_date']);
            $end = new DateTime($request['end_date']);
            $days = $end->diff($start)->days + 1;
        ?>
        <div class="card request-card <?php echo $request['status']; ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h6 class="mb-1">
                            <i class="bi <?php echo $type['icon']; ?> text-<?php echo $type['color']; ?> me-1"></i>
                            <?php echo $request['user_name']; ?>
                        </h6>
                        <small class="text-muted">
                            <?php echo $request['branch_name'] ?? 'بدون فرع'; ?>
                            &bull;
                            <?php echo $type['name']; ?>
                        </small>
                    </div>
                    <span class="badge bg-<?php echo $status['class']; ?>">
                        <?php echo $status['name']; ?>
                    </span>
                </div>

                <div class="row mb-3">
                    <div class="col-4 text-center">
                        <small class="text-muted d-block">من</small>
                        <strong><?php echo date('j M', strtotime($request['start_date'])); ?></strong>
                    </div>
                    <div class="col-4 text-center">
                        <small class="text-muted d-block">إلى</small>
                        <strong><?php echo date('j M', strtotime($request['end_date'])); ?></strong>
                    </div>
                    <div class="col-4 text-center">
                        <small class="text-muted d-block">المدة</small>
                        <strong><?php echo $days; ?> يوم</strong>
                    </div>
                </div>

                <?php if ($request['reason']): ?>
                <div class="mb-3 p-2 bg-light rounded">
                    <small class="text-muted">
                        <i class="bi bi-chat-quote me-1"></i>
                        <?php echo $request['reason']; ?>
                    </small>
                </div>
                <?php endif; ?>

                <?php if ($request['status'] === 'pending'): ?>
                <form method="POST" class="action-buttons">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                    
                    <button type="submit" name="action" value="approve" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i>
                        موافقة
                    </button>
                    
                    <button type="button" class="btn btn-danger" 
                            onclick="showRejectModal(<?php echo $request['id']; ?>, '<?php echo $request['user_name']; ?>')">
                        <i class="bi bi-x-lg me-1"></i>
                        رفض
                    </button>
                </form>
                <?php else: ?>
                <div class="text-muted small">
                    <?php if ($request['approver_name']): ?>
                    <i class="bi bi-person me-1"></i>
                    بواسطة: <?php echo $request['approver_name']; ?>
                    <?php endif; ?>
                    
                    <?php if ($request['rejection_reason']): ?>
                    <br>
                    <i class="bi bi-chat-text me-1"></i>
                    سبب الرفض: <?php echo $request['rejection_reason']; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    <i class="bi bi-x-circle text-danger me-2"></i>
                    رفض الطلب
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="rejectForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="request_id" id="rejectRequestId">
                    <input type="hidden" name="action" value="reject">
                    
                    <p class="text-muted" id="rejectUserName"></p>
                    
                    <div class="mb-3">
                        <label class="form-label">سبب الرفض (اختياري)</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" 
                                  placeholder="اكتب سبب رفض الطلب..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-lg me-1"></i>
                        تأكيد الرفض
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showRejectModal(requestId, userName) {
    document.getElementById('rejectRequestId').value = requestId;
    document.getElementById('rejectUserName').textContent = 'رفض طلب إجازة: ' + userName;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>
