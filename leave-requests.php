<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - LEAVE REQUESTS / طلبات الإجازة                       ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  ميزات الصفحة:                                                               ║
 * ║  - تقديم طلبات إجازة ذكية                                                    ║
 * ║  - عرض رصيد الإجازات                                                         ║
 * ║  - متابعة حالة الطلبات                                                       ║
 * ║  - اقتراحات ذكية لأفضل أوقات الإجازة                                          ║
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

$page_title = 'طلبات الإجازة';
$user_id = $_SESSION['user_id'];

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// جلب بيانات المستخدم
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $user = null;
}

// حساب رصيد الإجازات (افتراضي 30 يوم سنوياً)
$annual_leave_balance = 30;
$used_leaves = 0;

try {
    $stmt = $pdo->prepare("
        SELECT SUM(DATEDIFF(end_date, start_date) + 1) as total_days
        FROM leave_requests 
        WHERE user_id = ? 
        AND status = 'approved' 
        AND YEAR(start_date) = YEAR(NOW())
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $used_leaves = $result['total_days'] ?? 0;
} catch (Exception $e) {
    // Table might not exist
}

$remaining_leaves = $annual_leave_balance - $used_leaves;

// جلب طلبات الإجازة
try {
    $stmt = $pdo->prepare("
        SELECT lr.*, 
               u.name as approver_name
        FROM leave_requests lr
        LEFT JOIN users u ON lr.approved_by = u.id
        WHERE lr.user_id = ?
        ORDER BY lr.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $leave_requests = [];
}

// معالجة تقديم طلب جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leave'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $leave_type = htmlspecialchars(trim($_POST['leave_type']), ENT_QUOTES, 'UTF-8');
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = htmlspecialchars(trim($_POST['reason']), ENT_QUOTES, 'UTF-8');
    
    // Validate dates
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $days = $end->diff($start)->days + 1;
    
    if ($start > $end) {
        $error = 'تاريخ البداية يجب أن يكون قبل تاريخ النهاية';
    } elseif ($days > $remaining_leaves && $leave_type !== 'unpaid') {
        $error = 'رصيدك غير كافٍ لهذا الطلب';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$user_id, $leave_type, $start_date, $end_date, $reason]);
            
            header('Location: leave-requests.php?success=1');
            exit;
        } catch (Exception $e) {
            $error = 'حدث خطأ أثناء تقديم الطلب';
        }
    }
}

// أنواع الإجازات
$leave_types = [
    'annual' => ['name' => 'إجازة سنوية', 'icon' => 'bi-calendar-check', 'color' => 'primary'],
    'sick' => ['name' => 'إجازة مرضية', 'icon' => 'bi-heart-pulse', 'color' => 'danger'],
    'emergency' => ['name' => 'إجازة طارئة', 'icon' => 'bi-exclamation-triangle', 'color' => 'warning'],
    'unpaid' => ['name' => 'إجازة بدون راتب', 'icon' => 'bi-cash-stack', 'color' => 'secondary'],
    'maternity' => ['name' => 'إجازة أمومة', 'icon' => 'bi-heart', 'color' => 'pink'],
    'study' => ['name' => 'إجازة دراسية', 'icon' => 'bi-book', 'color' => 'info']
];

// حالات الطلب
$status_labels = [
    'pending' => ['name' => 'قيد الانتظار', 'class' => 'warning', 'icon' => 'bi-clock'],
    'approved' => ['name' => 'موافق عليه', 'class' => 'success', 'icon' => 'bi-check-circle'],
    'rejected' => ['name' => 'مرفوض', 'class' => 'danger', 'icon' => 'bi-x-circle'],
    'cancelled' => ['name' => 'ملغي', 'class' => 'secondary', 'icon' => 'bi-ban']
];

require_once 'includes/header.php';
?>

<style>
    .balance-card {
        background: linear-gradient(135deg, var(--sarh-primary) 0%, #ff9800 100%);
        color: white;
        border-radius: 20px;
        padding: 25px;
        position: relative;
        overflow: hidden;
    }

    .balance-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    }

    .balance-number {
        font-size: 3rem;
        font-weight: 700;
    }

    .leave-type-card {
        border-radius: 15px;
        padding: 15px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        background: #f8f9fa;
    }

    .leave-type-card:hover,
    .leave-type-card.selected {
        border-color: var(--sarh-primary);
        background: rgba(255, 111, 0, 0.1);
        transform: translateY(-3px);
    }

    .leave-type-card i {
        font-size: 2rem;
        margin-bottom: 8px;
    }

    .leave-request-item {
        border-radius: 12px;
        border: 1px solid #eee;
        padding: 15px;
        margin-bottom: 12px;
        transition: all 0.3s ease;
    }

    .leave-request-item:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }

    .smart-suggestion {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 20px;
    }

    .ai-badge {
        background: rgba(255,255,255,0.2);
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
    }

    .calendar-preview {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        text-align: center;
    }

    .calendar-days {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--sarh-primary);
    }
</style>

<div class="container py-4 mb-5">
    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-calendar2-week text-primary me-2"></i>
                طلبات الإجازة
            </h4>
            <small class="text-muted">أنشئ طلب إجازة جديد أو تابع طلباتك</small>
        </div>
        <a href="more.php" class="btn btn-sm btn-outline-secondary rounded-pill">
            <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        تم تقديم طلب الإجازة بنجاح! سيتم مراجعته قريباً
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle me-2"></i>
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Leave Balance -->
    <div class="balance-card mb-4">
        <div class="row align-items-center">
            <div class="col-7">
                <div class="balance-number"><?php echo $remaining_leaves; ?></div>
                <div>يوم متبقي</div>
                <div class="mt-2 opacity-75">
                    <small>استخدمت <?php echo $used_leaves; ?> من <?php echo $annual_leave_balance; ?> يوم</small>
                </div>
            </div>
            <div class="col-5 text-end">
                <div class="progress" style="height: 10px; background: rgba(255,255,255,0.3); border-radius: 10px;">
                    <?php $percentage = ($used_leaves / $annual_leave_balance) * 100; ?>
                    <div class="progress-bar bg-light" style="width: <?php echo $percentage; ?>%"></div>
                </div>
                <small class="d-block mt-2"><?php echo round($percentage); ?>% مستخدم</small>
            </div>
        </div>
    </div>

    <!-- Smart Suggestion -->
    <div class="smart-suggestion mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="ai-badge">
                <i class="bi bi-stars me-1"></i>
                اقتراح ذكي
            </span>
            <i class="bi bi-lightbulb"></i>
        </div>
        <p class="mb-0 small">
            بناءً على جدول العمل، أفضل وقت لإجازتك القادمة هو 
            <strong>الأسبوع الأخير من الشهر القادم</strong>
            حيث يكون ضغط العمل أقل 📊
        </p>
    </div>

    <!-- New Request Button -->
    <button class="btn btn-primary w-100 py-3 rounded-3 mb-4" data-bs-toggle="modal" data-bs-target="#newLeaveModal">
        <i class="bi bi-plus-circle me-2"></i>
        طلب إجازة جديدة
    </button>

    <!-- My Requests -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                <i class="bi bi-list-ul me-2"></i>
                طلباتي
            </h6>
            <span class="badge bg-primary rounded-pill"><?php echo count($leave_requests); ?></span>
        </div>
        <div class="card-body">
            <?php if (empty($leave_requests)): ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3">لا توجد طلبات إجازة</p>
            </div>
            <?php else: ?>
                <?php foreach ($leave_requests as $request): ?>
                <?php 
                    $type = $leave_types[$request['leave_type']] ?? ['name' => 'إجازة', 'icon' => 'bi-calendar', 'color' => 'secondary'];
                    $status = $status_labels[$request['status']] ?? ['name' => 'غير محدد', 'class' => 'secondary', 'icon' => 'bi-question'];
                    $start = new DateTime($request['start_date']);
                    $end = new DateTime($request['end_date']);
                    $days = $end->diff($start)->days + 1;
                ?>
                <div class="leave-request-item">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi <?php echo $type['icon']; ?> text-<?php echo $type['color']; ?>"></i>
                            <strong><?php echo $type['name']; ?></strong>
                        </div>
                        <span class="badge bg-<?php echo $status['class']; ?>">
                            <i class="bi <?php echo $status['icon']; ?> me-1"></i>
                            <?php echo $status['name']; ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between text-muted small">
                        <div>
                            <i class="bi bi-calendar3 me-1"></i>
                            <?php echo date('j M', strtotime($request['start_date'])); ?>
                            -
                            <?php echo date('j M Y', strtotime($request['end_date'])); ?>
                        </div>
                        <div>
                            <span class="badge bg-light text-dark"><?php echo $days; ?> يوم</span>
                        </div>
                    </div>
                    <?php if ($request['reason']): ?>
                    <div class="mt-2 small text-muted">
                        <i class="bi bi-chat-text me-1"></i>
                        <?php echo $request['reason']; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($request['status'] === 'pending'): ?>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-outline-danger" onclick="cancelRequest(<?php echo $request['id']; ?>)">
                            <i class="bi bi-x-circle me-1"></i>
                            إلغاء الطلب
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Leave Request Modal -->
<div class="modal fade" id="newLeaveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-plus text-primary me-2"></i>
                    طلب إجازة جديدة
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="leaveForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="submit_leave" value="1">
                    <input type="hidden" name="leave_type" id="selectedLeaveType" value="annual">
                    
                    <!-- Leave Type Selection -->
                    <label class="form-label mb-3">نوع الإجازة</label>
                    <div class="row g-2 mb-4">
                        <?php foreach ($leave_types as $key => $type): ?>
                        <div class="col-4 col-md-2">
                            <div class="leave-type-card <?php echo $key === 'annual' ? 'selected' : ''; ?>" 
                                 data-type="<?php echo $key; ?>" onclick="selectLeaveType('<?php echo $key; ?>')">
                                <i class="bi <?php echo $type['icon']; ?> text-<?php echo $type['color']; ?>"></i>
                                <div class="small"><?php echo $type['name']; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">تاريخ البداية</label>
                            <input type="date" name="start_date" id="startDate" class="form-control" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">تاريخ النهاية</label>
                            <input type="date" name="end_date" id="endDate" class="form-control" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <!-- Days Preview -->
                    <div class="calendar-preview mb-4">
                        <div class="calendar-days" id="daysPreview">0</div>
                        <div class="text-muted">يوم إجازة</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">سبب الإجازة</label>
                        <textarea name="reason" class="form-control" rows="3" 
                                  placeholder="اشرح سبب طلب الإجازة..."></textarea>
                    </div>

                    <!-- Balance Warning -->
                    <div class="alert alert-info small" id="balanceWarning" style="display: none;">
                        <i class="bi bi-info-circle me-1"></i>
                        <span id="balanceMessage"></span>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-2"></i>
                        تقديم الطلب
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const remainingLeaves = <?php echo $remaining_leaves; ?>;

function selectLeaveType(type) {
    document.querySelectorAll('.leave-type-card').forEach(card => {
        card.classList.remove('selected');
    });
    document.querySelector(`[data-type="${type}"]`).classList.add('selected');
    document.getElementById('selectedLeaveType').value = type;
}

function calculateDays() {
    const start = document.getElementById('startDate').value;
    const end = document.getElementById('endDate').value;
    
    if (start && end) {
        const startDate = new Date(start);
        const endDate = new Date(end);
        const diffTime = endDate - startDate;
        const days = Math.floor(diffTime / (1000 * 60 * 60 * 24)) + 1;
        
        if (days > 0) {
            document.getElementById('daysPreview').textContent = days;
            
            const warning = document.getElementById('balanceWarning');
            const message = document.getElementById('balanceMessage');
            
            if (days > remainingLeaves) {
                warning.style.display = 'block';
                warning.className = 'alert alert-warning small';
                message.textContent = `تطلب ${days} يوم لكن رصيدك ${remainingLeaves} يوم فقط`;
            } else {
                warning.style.display = 'block';
                warning.className = 'alert alert-info small';
                message.textContent = `سيتبقى لك ${remainingLeaves - days} يوم بعد هذا الطلب`;
            }
        } else {
            document.getElementById('daysPreview').textContent = 0;
            document.getElementById('balanceWarning').style.display = 'none';
        }
    }
}

document.getElementById('startDate').addEventListener('change', function() {
    document.getElementById('endDate').min = this.value;
    calculateDays();
});

document.getElementById('endDate').addEventListener('change', calculateDays);

function cancelRequest(id) {
    Swal.fire({
        title: 'إلغاء الطلب؟',
        text: 'هل أنت متأكد من إلغاء طلب الإجازة؟',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'نعم، إلغاء',
        cancelButtonText: 'لا'
    }).then((result) => {
        if (result.isConfirmed) {
            // Send cancel request via API
            fetch('/api/leave/cancel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    Swal.fire('خطأ', data.message || 'حدث خطأ', 'error');
                }
            });
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
