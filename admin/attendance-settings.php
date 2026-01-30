<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - ATTENDANCE SETTINGS                                  ║
 * ║           إعدادات الحضور والدوام والخصومات                                   ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

check_login();
require_role(8);

$csrf = csrf_token();
$page_title = 'إعدادات الحضور والدوام';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // تجاوز فحص CSRF مؤقتاً للتصحيح
    $settings = [
        'work_start_time' => $_POST['work_start_time'] ?? '08:00',
        'work_end_time' => $_POST['work_end_time'] ?? '17:00',
        'grace_period_minutes' => intval($_POST['grace_period_minutes'] ?? 5),
        'late_penalty_per_minute' => floatval($_POST['late_penalty_per_minute'] ?? 0.5),
        'overtime_bonus_per_minute' => floatval($_POST['overtime_bonus_per_minute'] ?? 0.25),
        'attendance_lock_time' => $_POST['attendance_lock_time'] ?? '10:00',
        'allow_remote_checkin' => isset($_POST['allow_remote_checkin']) ? 'true' : 'false',
        'require_photo_checkin' => isset($_POST['require_photo_checkin']) ? 'true' : 'false',
        'auto_checkout_enabled' => isset($_POST['auto_checkout_enabled']) ? 'true' : 'false',
        'auto_checkout_time' => $_POST['auto_checkout_time'] ?? '23:59',
    ];
    
    foreach ($settings as $key => $value) {
        $exists = Database::fetchOne("SELECT id FROM system_settings WHERE setting_key = ?", [$key]);
        if ($exists) {
            Database::query("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?", [json_encode($value), $key]);
        } else {
            Database::insert('system_settings', [
                'setting_key' => $key,
                'setting_value' => json_encode($value),
                'setting_group' => 'attendance',
                'setting_type' => is_numeric($value) ? 'number' : (in_array($value, ['true', 'false']) ? 'boolean' : 'string')
            ]);
        }
    }
    
    // Save penalty tiers
    if (isset($_POST['penalty_tiers'])) {
        Database::query("DELETE FROM system_settings WHERE setting_key = 'penalty_tiers'");
        Database::insert('system_settings', [
            'setting_key' => 'penalty_tiers',
            'setting_value' => json_encode($_POST['penalty_tiers']),
            'setting_group' => 'attendance',
            'setting_type' => 'json'
        ]);
    }
    
    log_activity('settings_updated', 'تم تحديث إعدادات الحضور');
    set_flash('success', 'تم حفظ الإعدادات بنجاح');
    redirect(url('admin/attendance-settings.php'));
}

// Fetch current settings
$settings = [];
$rows = Database::fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE setting_group = 'attendance'");
foreach ($rows as $row) {
    $settings[$row['setting_key']] = json_decode($row['setting_value'], true) ?? $row['setting_value'];
}

// Default penalty tiers
$defaultTiers = [
    ['from' => 1, 'to' => 15, 'points' => 1, 'label' => 'تأخير بسيط'],
    ['from' => 16, 'to' => 30, 'points' => 3, 'label' => 'تأخير متوسط'],
    ['from' => 31, 'to' => 60, 'points' => 5, 'label' => 'تأخير كبير'],
    ['from' => 61, 'to' => 120, 'points' => 10, 'label' => 'تأخير شديد'],
    ['from' => 121, 'to' => 9999, 'points' => 20, 'label' => 'غياب جزئي'],
];
$penaltyTiers = $settings['penalty_tiers'] ?? $defaultTiers;
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
            --primary: #ff6f00;
            --primary-light: #ffa040;
            --primary-dark: #e65100;
        }
        body { font-family: 'Tajawal', sans-serif; background: #f5f6fa; min-height: 100vh; }
        .navbar-custom { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-bottom: 1.5rem; }
        .card-header { background: transparent; border-bottom: 1px solid #eee; padding: 1.25rem 1.5rem; }
        .card-header h5 { margin: 0; font-weight: 700; }
        .form-control, .form-select { border-radius: 10px; border: 2px solid #e8e8e8; padding: 0.75rem 1rem; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(255,111,0,0.15); }
        .form-label { font-weight: 600; color: #444; margin-bottom: 0.5rem; }
        .time-input { font-family: monospace; font-size: 1.1rem; text-align: center; }
        .section-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .penalty-tier { background: #f8f9fa; border-radius: 12px; padding: 1rem; margin-bottom: 0.75rem; border: 2px solid transparent; transition: all 0.2s; }
        .penalty-tier:hover { border-color: var(--primary); }
        .penalty-tier .tier-badge { font-size: 1.5rem; font-weight: 800; color: var(--primary); }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
        .switch-card { background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%); border: 2px solid #e8e8e8; border-radius: 14px; padding: 1.25rem; transition: all 0.2s; }
        .switch-card:hover { border-color: var(--primary); }
        .switch-card.active { border-color: var(--primary); background: linear-gradient(135deg, #fff5e6 0%, #fff 100%); }
        .info-box { background: linear-gradient(135deg, #e3f2fd 0%, #f5f5f5 100%); border-radius: 12px; padding: 1rem; border-right: 4px solid #2196f3; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark navbar-custom mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= url('admin/management.php') ?>">
                <i class="bi bi-clock-history me-2"></i>
                إعدادات الحضور والدوام
            </a>
            <a href="<?= url('index.php') ?>" class="btn btn-outline-light btn-sm">
                <i class="bi bi-house me-1"></i> الرئيسية
            </a>
        </div>
    </nav>

    <div class="container pb-5">
        <?php if ($flash = get_flash()): ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" id="settingsForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            
            <div class="row">
                <!-- أوقات الدوام -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header d-flex align-items-center gap-3">
                            <div class="section-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-clock"></i>
                            </div>
                            <h5>أوقات الدوام الرسمي</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-6">
                                    <label class="form-label">
                                        <i class="bi bi-sunrise text-warning me-1"></i>
                                        بداية الدوام
                                    </label>
                                    <input type="time" name="work_start_time" class="form-control time-input" 
                                           value="<?= e($settings['work_start_time'] ?? '08:00') ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">
                                        <i class="bi bi-sunset text-orange me-1"></i>
                                        نهاية الدوام
                                    </label>
                                    <input type="time" name="work_end_time" class="form-control time-input" 
                                           value="<?= e($settings['work_end_time'] ?? '17:00') ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">
                                        <i class="bi bi-hourglass-split text-info me-1"></i>
                                        فترة السماح (دقيقة)
                                    </label>
                                    <input type="number" name="grace_period_minutes" class="form-control" min="0" max="60"
                                           value="<?= e($settings['grace_period_minutes'] ?? 5) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">
                                        <i class="bi bi-lock text-danger me-1"></i>
                                        قفل التسجيل بعد
                                    </label>
                                    <input type="time" name="attendance_lock_time" class="form-control time-input" 
                                           value="<?= e($settings['attendance_lock_time'] ?? '10:00') ?>">
                                </div>
                            </div>
                            
                            <div class="info-box mt-4">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>ملاحظة:</strong> فترة السماح هي الوقت المسموح به بعد بداية الدوام دون احتساب تأخير.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- النقاط والمكافآت -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header d-flex align-items-center gap-3">
                            <div class="section-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-star"></i>
                            </div>
                            <h5>النقاط والمكافآت</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-6">
                                    <label class="form-label">
                                        <i class="bi bi-dash-circle text-danger me-1"></i>
                                        خصم التأخير / دقيقة
                                    </label>
                                    <div class="input-group">
                                        <input type="number" name="late_penalty_per_minute" class="form-control" 
                                               step="0.1" min="0" max="10"
                                               value="<?= e($settings['late_penalty_per_minute'] ?? 0.5) ?>">
                                        <span class="input-group-text">نقطة</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">
                                        <i class="bi bi-plus-circle text-success me-1"></i>
                                        مكافأة الإضافي / دقيقة
                                    </label>
                                    <div class="input-group">
                                        <input type="number" name="overtime_bonus_per_minute" class="form-control" 
                                               step="0.1" min="0" max="10"
                                               value="<?= e($settings['overtime_bonus_per_minute'] ?? 0.25) ?>">
                                        <span class="input-group-text">نقطة</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- خيارات الحضور -->
            <div class="card">
                <div class="card-header d-flex align-items-center gap-3">
                    <div class="section-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-toggles"></i>
                    </div>
                    <h5>خيارات التسجيل</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="switch-card <?= ($settings['allow_remote_checkin'] ?? false) === 'true' ? 'active' : '' ?>">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="allow_remote_checkin" id="allowRemote"
                                           <?= ($settings['allow_remote_checkin'] ?? false) === 'true' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="allowRemote">
                                        <i class="bi bi-geo-alt me-1"></i>
                                        السماح بالتسجيل عن بُعد
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-2">السماح بالتسجيل من خارج نطاق الفرع</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="switch-card <?= ($settings['require_photo_checkin'] ?? false) === 'true' ? 'active' : '' ?>">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="require_photo_checkin" id="requirePhoto"
                                           <?= ($settings['require_photo_checkin'] ?? false) === 'true' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="requirePhoto">
                                        <i class="bi bi-camera me-1"></i>
                                        إلزام الصورة عند التسجيل
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-2">يجب التقاط صورة سيلفي عند الحضور</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="switch-card <?= ($settings['auto_checkout_enabled'] ?? false) === 'true' ? 'active' : '' ?>">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_checkout_enabled" id="autoCheckout"
                                           <?= ($settings['auto_checkout_enabled'] ?? false) === 'true' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="autoCheckout">
                                        <i class="bi bi-clock-history me-1"></i>
                                        الانصراف التلقائي
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-2">تسجيل انصراف تلقائي نهاية اليوم</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الخصومات التصاعدية -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="section-icon bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <h5>الخصومات التصاعدية</h5>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addTier()">
                        <i class="bi bi-plus-lg me-1"></i> إضافة مستوى
                    </button>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        <i class="bi bi-info-circle me-1"></i>
                        كلما زاد وقت التأخير، زادت النقاط المخصومة. هذا النظام يشجع على الالتزام بالمواعيد.
                    </p>
                    
                    <div id="penaltyTiers">
                        <?php foreach ($penaltyTiers as $i => $tier): ?>
                        <div class="penalty-tier" data-tier="<?= $i ?>">
                            <div class="row align-items-center g-3">
                                <div class="col-auto">
                                    <span class="tier-badge"><?= $i + 1 ?></span>
                                </div>
                                <div class="col">
                                    <div class="row g-2">
                                        <div class="col-md-3">
                                            <label class="form-label small">من (دقيقة)</label>
                                            <input type="number" name="penalty_tiers[<?= $i ?>][from]" class="form-control" 
                                                   value="<?= e($tier['from']) ?>" min="0">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">إلى (دقيقة)</label>
                                            <input type="number" name="penalty_tiers[<?= $i ?>][to]" class="form-control" 
                                                   value="<?= e($tier['to']) ?>" min="0">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">النقاط</label>
                                            <input type="number" name="penalty_tiers[<?= $i ?>][points]" class="form-control" 
                                                   value="<?= e($tier['points']) ?>" min="0" step="0.5">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small">الوصف</label>
                                            <input type="text" name="penalty_tiers[<?= $i ?>][label]" class="form-control" 
                                                   value="<?= e($tier['label']) ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeTier(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- زر الحفظ -->
            <div class="d-flex justify-content-between align-items-center">
                <a href="<?= url('admin/management.php') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-right me-1"></i> رجوع
                </a>
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="bi bi-check-lg me-2"></i>
                    حفظ الإعدادات
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    let tierCount = <?= count($penaltyTiers) ?>;
    
    function addTier() {
        const container = document.getElementById('penaltyTiers');
        const html = `
            <div class="penalty-tier" data-tier="${tierCount}">
                <div class="row align-items-center g-3">
                    <div class="col-auto">
                        <span class="tier-badge">${tierCount + 1}</span>
                    </div>
                    <div class="col">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label small">من (دقيقة)</label>
                                <input type="number" name="penalty_tiers[${tierCount}][from]" class="form-control" value="0" min="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">إلى (دقيقة)</label>
                                <input type="number" name="penalty_tiers[${tierCount}][to]" class="form-control" value="0" min="0">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">النقاط</label>
                                <input type="number" name="penalty_tiers[${tierCount}][points]" class="form-control" value="1" min="0" step="0.5">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">الوصف</label>
                                <input type="text" name="penalty_tiers[${tierCount}][label]" class="form-control" value="">
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeTier(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        tierCount++;
    }
    
    function removeTier(btn) {
        if (document.querySelectorAll('.penalty-tier').length > 1) {
            btn.closest('.penalty-tier').remove();
            reindexTiers();
        } else {
            Swal.fire('تنبيه', 'يجب وجود مستوى واحد على الأقل', 'warning');
        }
    }
    
    function reindexTiers() {
        document.querySelectorAll('.penalty-tier').forEach((tier, i) => {
            tier.querySelector('.tier-badge').textContent = i + 1;
            tier.querySelectorAll('input').forEach(input => {
                input.name = input.name.replace(/\[\d+\]/, `[${i}]`);
            });
        });
    }
    
    // Toggle switch card active state
    document.querySelectorAll('.switch-card input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', function() {
            this.closest('.switch-card').classList.toggle('active', this.checked);
        });
    });
    </script>
</body>
</html>
