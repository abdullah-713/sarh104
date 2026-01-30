<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - TRAP MANAGEMENT DASHBOARD                            ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

check_login();
require_role(8); // Only high-level admins

$csrf = csrf_token();
$page_title = 'إدارة الفخاخ';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_trap') {
        $id = intval($_POST['id'] ?? 0);
        $is_active = intval($_POST['is_active'] ?? 0);
        Database::update('trap_configurations', ['is_active' => $is_active], 'id = :id', ['id' => $id]);
        set_flash('success', $is_active ? 'تم تفعيل الفخ' : 'تم تعطيل الفخ');
    }
    
    if ($action === 'update_trap') {
        $id = intval($_POST['id'] ?? 0);
        Database::update('trap_configurations', [
            'trigger_chance' => floatval($_POST['trigger_chance'] ?? 0.1),
            'cooldown_minutes' => intval($_POST['cooldown_minutes'] ?? 10080),
            'min_role_level' => intval($_POST['min_role_level'] ?? 1),
            'max_role_level' => intval($_POST['max_role_level'] ?? 7),
        ], 'id = ?', [$id]);
        set_flash('success', 'تم تحديث إعدادات الفخ');
    }
    
    if ($action === 'reset_cooldowns') {
        Database::query("DELETE FROM user_trap_cooldowns");
        set_flash('success', 'تم إعادة تعيين جميع فترات الانتظار');
    }
    
    redirect(url('admin/traps.php'));
}

// Fetch trap configurations
$traps = Database::fetchAll("SELECT * FROM trap_configurations ORDER BY id");

// Fetch statistics
$stats = [
    'total_triggers' => Database::fetchOne("SELECT COUNT(*) as c FROM trap_logs")['c'] ?? 0,
    'positive_actions' => Database::fetchOne("SELECT COUNT(*) as c FROM trap_logs WHERE action_category = 'positive'")['c'] ?? 0,
    'negative_actions' => Database::fetchOne("SELECT COUNT(*) as c FROM trap_logs WHERE action_category = 'negative'")['c'] ?? 0,
    'critical_actions' => Database::fetchOne("SELECT COUNT(*) as c FROM trap_logs WHERE action_category = 'critical'")['c'] ?? 0,
];

// Recent trap logs
$recentLogs = Database::fetchAll("
    SELECT tl.*, u.full_name, u.emp_code, tc.trap_name_ar
    FROM trap_logs tl
    LEFT JOIN users u ON tl.user_id = u.id
    LEFT JOIN trap_configurations tc ON tl.trap_type = tc.trap_type
    ORDER BY tl.created_at DESC
    LIMIT 20
");

// Trap type info
$trapInfo = [
    'data_leak' => ['icon' => 'bi-file-earmark-lock', 'color' => '#e74c3c', 'desc' => 'يعرض بيانات راتب وهمية لموظف آخر'],
    'gps_debug' => ['icon' => 'bi-geo-alt', 'color' => '#f39c12', 'desc' => 'يعرض خيارات تجاوز GPS مزيفة'],
    'admin_override' => ['icon' => 'bi-lightning-charge', 'color' => '#9b59b6', 'desc' => 'زر تجاوز صلاحيات وهمي'],
    'confidential_bait' => ['icon' => 'bi-shield-lock', 'color' => '#3498db', 'desc' => 'إشعار سري مزيف عن موظف'],
    'recruitment' => ['icon' => 'bi-envelope-paper', 'color' => '#1abc9c', 'desc' => 'عرض تجنيد وهمي للتجسس على الزملاء'],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --bg-dark: #0f0f1a;
            --bg-card: rgba(255,255,255,0.03);
            --border: rgba(255,255,255,0.08);
            --text: #e8e8e8;
            --text-muted: rgba(255,255,255,0.5);
            --accent: #6c5ce7;
        }
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 100%);
            color: var(--text);
            min-height: 100vh;
        }
        .navbar-dark-custom {
            background: rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.2s, border-color 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            border-color: var(--accent);
        }
        .stat-card h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .stat-card p {
            color: var(--text-muted);
            margin: 0;
        }
        .trap-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }
        .trap-card:hover {
            background: rgba(255,255,255,0.05);
        }
        .trap-card.disabled {
            opacity: 0.5;
        }
        .trap-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .trap-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .trap-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .trap-type {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-family: monospace;
        }
        .trap-stats {
            display: flex;
            gap: 1.5rem;
            margin: 1rem 0;
            padding: 1rem;
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
        }
        .trap-stat {
            text-align: center;
        }
        .trap-stat .value {
            font-size: 1.25rem;
            font-weight: 700;
        }
        .trap-stat .label {
            font-size: 0.7rem;
            color: var(--text-muted);
        }
        .form-range {
            height: 8px;
        }
        .form-range::-webkit-slider-thumb {
            background: var(--accent);
        }
        .log-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
        }
        .log-item:last-child {
            border-bottom: none;
        }
        .log-positive { border-right: 3px solid #27ae60; }
        .log-negative { border-right: 3px solid #f39c12; }
        .log-critical { border-right: 3px solid #e74c3c; }
        .log-neutral { border-right: 3px solid #95a5a6; }
        .btn-glow {
            box-shadow: 0 0 20px rgba(108, 92, 231, 0.3);
        }
        .card-dark {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
        }
        .card-dark .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border);
            padding: 1rem 1.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark navbar-dark-custom mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= url('admin/management.php') ?>">
                <i class="bi bi-joystick me-2"></i>
                إدارة الفخاخ النفسية
            </a>
            <div class="d-flex align-items-center gap-3">
                <a href="<?= url('admin/profiles.php') ?>" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-brain me-1"></i> الملفات النفسية
                </a>
                <a href="<?= url('index.php') ?>" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-house me-1"></i> الرئيسية
                </a>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        <!-- Flash Messages -->
        <?php if ($flash = get_flash()): ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row g-4 mb-5">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <h2 style="color: var(--accent);"><?= number_format($stats['total_triggers']) ?></h2>
                    <p><i class="bi bi-crosshair me-1"></i>إجمالي التفعيلات</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <h2 style="color: #27ae60;"><?= number_format($stats['positive_actions']) ?></h2>
                    <p><i class="bi bi-check-circle me-1"></i>ردود إيجابية</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <h2 style="color: #f39c12;"><?= number_format($stats['negative_actions']) ?></h2>
                    <p><i class="bi bi-exclamation-triangle me-1"></i>ردود سلبية</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <h2 style="color: #e74c3c;"><?= number_format($stats['critical_actions']) ?></h2>
                    <p><i class="bi bi-x-octagon me-1"></i>ردود حرجة</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Trap Configurations -->
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">
                        <i class="bi bi-gear-wide-connected me-2" style="color: var(--accent);"></i>
                        إعدادات الفخاخ
                    </h4>
                    <form method="POST" class="d-inline" onsubmit="return confirm('هل تريد إعادة تعيين فترات الانتظار لجميع المستخدمين؟');">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="reset_cooldowns">
                        <button type="submit" class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-arrow-clockwise me-1"></i>
                            إعادة تعيين الانتظار
                        </button>
                    </form>
                </div>

                <?php foreach ($traps as $trap): ?>
                <?php $info = $trapInfo[$trap['trap_type']] ?? ['icon' => 'bi-question', 'color' => '#666', 'desc' => '']; ?>
                <div class="trap-card <?= !$trap['is_active'] ? 'disabled' : '' ?>">
                    <div class="trap-header">
                        <div class="trap-icon" style="background: <?= $info['color'] ?>20; color: <?= $info['color'] ?>;">
                            <i class="bi <?= $info['icon'] ?>"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="trap-name"><?= e($trap['trap_name_ar'] ?: $trap['trap_name']) ?></div>
                            <div class="trap-type"><?= e($trap['trap_type']) ?></div>
                        </div>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="toggle_trap">
                            <input type="hidden" name="id" value="<?= $trap['id'] ?>">
                            <input type="hidden" name="is_active" value="<?= $trap['is_active'] ? 0 : 1 ?>">
                            <button type="submit" class="btn btn-<?= $trap['is_active'] ? 'success' : 'secondary' ?> btn-sm">
                                <i class="bi bi-<?= $trap['is_active'] ? 'toggle-on' : 'toggle-off' ?> me-1"></i>
                                <?= $trap['is_active'] ? 'مُفعّل' : 'معطّل' ?>
                            </button>
                        </form>
                    </div>
                    
                    <p class="text-muted small mb-3"><?= e($info['desc']) ?></p>
                    
                    <div class="trap-stats">
                        <div class="trap-stat">
                            <div class="value" style="color: <?= $info['color'] ?>;"><?= ($trap['trigger_chance'] * 100) ?>%</div>
                            <div class="label">احتمال الظهور</div>
                        </div>
                        <div class="trap-stat">
                            <div class="value"><?= number_format($trap['cooldown_minutes'] / 60 / 24, 1) ?></div>
                            <div class="label">أيام الانتظار</div>
                        </div>
                        <div class="trap-stat">
                            <div class="value"><?= $trap['min_role_level'] ?> - <?= $trap['max_role_level'] ?></div>
                            <div class="label">مستوى الأدوار</div>
                        </div>
                    </div>
                    
                    <button class="btn btn-outline-light btn-sm" onclick="editTrap(<?= e(json_encode($trap)) ?>)">
                        <i class="bi bi-sliders me-1"></i> تعديل الإعدادات
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Recent Logs -->
            <div class="col-lg-4">
                <div class="card-dark">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="bi bi-activity me-2"></i>
                            آخر التفاعلات
                        </h6>
                    </div>
                    <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($recentLogs)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox display-4 d-block mb-2 opacity-25"></i>
                            لا توجد تفاعلات
                        </div>
                        <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                        <div class="log-item log-<?= e($log['action_category'] ?? 'neutral') ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <strong><?= e($log['full_name'] ?? 'مجهول') ?></strong>
                                <small class="text-muted"><?= time_ago($log['created_at']) ?></small>
                            </div>
                            <div class="mt-1">
                                <span class="badge bg-secondary"><?= e($log['trap_name_ar'] ?? $log['trap_type']) ?></span>
                                <span class="badge bg-<?= 
                                    $log['action_category'] === 'positive' ? 'success' : 
                                    ($log['action_category'] === 'critical' ? 'danger' : 
                                    ($log['action_category'] === 'negative' ? 'warning' : 'secondary'))
                                ?>"><?= e($log['action_taken']) ?></span>
                            </div>
                            <?php if ($log['score_change']): ?>
                            <small class="<?= $log['score_change'] > 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $log['score_change'] > 0 ? '+' : '' ?><?= $log['score_change'] ?> نقطة
                            </small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Trap Modal -->
    <div class="modal fade" id="editTrapModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">
                        <i class="bi bi-sliders me-2"></i>
                        تعديل إعدادات الفخ
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editTrapForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="update_trap">
                        <input type="hidden" name="id" id="edit_trap_id">
                        
                        <div class="mb-4">
                            <label class="form-label">احتمال الظهور: <span id="chanceValue">10</span>%</label>
                            <input type="range" class="form-range" min="1" max="50" step="1" name="trigger_chance" id="edit_trigger_chance" oninput="document.getElementById('chanceValue').textContent=this.value">
                            <small class="text-muted">نسبة ظهور الفخ للمستخدم</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">فترة الانتظار: <span id="cooldownValue">7</span> أيام</label>
                            <input type="range" class="form-range" min="1" max="60" step="1" id="cooldown_days" oninput="updateCooldown()">
                            <input type="hidden" name="cooldown_minutes" id="edit_cooldown_minutes">
                            <small class="text-muted">المدة قبل إعادة ظهور الفخ لنفس المستخدم</small>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">الحد الأدنى للدور</label>
                                <input type="number" class="form-control bg-dark text-white border-secondary" name="min_role_level" id="edit_min_role" min="1" max="10">
                            </div>
                            <div class="col-6">
                                <label class="form-label">الحد الأقصى للدور</label>
                                <input type="number" class="form-control bg-dark text-white border-secondary" name="max_role_level" id="edit_max_role" min="1" max="10">
                            </div>
                        </div>
                        <small class="text-muted">الأدوار المستهدفة (1=موظف عادي، 10=مدير عام)</small>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary btn-glow">
                            <i class="bi bi-check-lg me-1"></i> حفظ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    const editModal = new bootstrap.Modal(document.getElementById('editTrapModal'));
    
    function editTrap(trap) {
        document.getElementById('edit_trap_id').value = trap.id;
        document.getElementById('edit_trigger_chance').value = trap.trigger_chance * 100;
        document.getElementById('chanceValue').textContent = trap.trigger_chance * 100;
        
        const days = Math.round(trap.cooldown_minutes / 60 / 24);
        document.getElementById('cooldown_days').value = days;
        document.getElementById('cooldownValue').textContent = days;
        document.getElementById('edit_cooldown_minutes').value = trap.cooldown_minutes;
        
        document.getElementById('edit_min_role').value = trap.min_role_level;
        document.getElementById('edit_max_role').value = trap.max_role_level;
        
        editModal.show();
    }
    
    function updateCooldown() {
        const days = document.getElementById('cooldown_days').value;
        document.getElementById('cooldownValue').textContent = days;
        document.getElementById('edit_cooldown_minutes').value = days * 24 * 60;
    }
    
    // Convert percentage to decimal on submit
    document.getElementById('editTrapForm').addEventListener('submit', function(e) {
        const chanceInput = document.getElementById('edit_trigger_chance');
        chanceInput.value = chanceInput.value / 100;
    });
    </script>
</body>
</html>
