<?php
/**
 * الملف الشخصي - Profile Page
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

check_login();

$pageTitle = 'الملف الشخصي';
$currentPage = 'profile';

// جلب بيانات المستخدم
$user = get_current_user_data();

include INCLUDES_PATH . '/header.php';
?>

<div class="container py-4">
    <div class="text-center mb-4">
        <div class="avatar-lg mx-auto mb-3" style="width:100px;height:100px;background:linear-gradient(135deg,#ff6f00,#3949ab);border-radius:50%;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-person-fill text-white" style="font-size:3rem;"></i>
        </div>
        <h4 class="mb-1"><?= e($_SESSION['full_name'] ?? 'مستخدم') ?></h4>
        <span class="badge" style="background-color: <?= e($_SESSION['role_color'] ?? '#6c757d') ?>">
            <i class="<?= e($_SESSION['role_icon'] ?? 'bi-person') ?> me-1"></i>
            <?= e($_SESSION['role_name'] ?? 'موظف') ?>
        </span>
    </div>
    
    <div class="card">
        <div class="card-body">
            <h6 class="card-title border-bottom pb-2 mb-3">
                <i class="bi bi-person-vcard me-2"></i>
                البيانات الشخصية
            </h6>
            
            <div class="row g-3">
                <div class="col-6">
                    <label class="text-muted small">رقم الموظف</label>
                    <p class="fw-bold mb-0"><?= e($_SESSION['emp_code'] ?? '-') ?></p>
                </div>
                <div class="col-6">
                    <label class="text-muted small">اسم المستخدم</label>
                    <p class="fw-bold mb-0"><?= e($_SESSION['username'] ?? '-') ?></p>
                </div>
                <div class="col-12">
                    <label class="text-muted small">البريد الإلكتروني</label>
                    <p class="fw-bold mb-0"><?= e($_SESSION['email'] ?? '-') ?></p>
                </div>
                <div class="col-6">
                    <label class="text-muted small">الفرع</label>
                    <p class="fw-bold mb-0"><?= e($_SESSION['branch_name'] ?? '-') ?></p>
                </div>
                <div class="col-6">
                    <label class="text-muted small">النقاط الحالية</label>
                    <p class="fw-bold mb-0 text-warning">
                        <i class="bi bi-star-fill me-1"></i>
                        <?= number_format($_SESSION['current_points'] ?? 0) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-4">
        <a href="<?= url('settings.php') ?>" class="btn btn-outline-primary w-100 mb-2">
            <i class="bi bi-gear me-2"></i>
            الإعدادات
        </a>
        <a href="<?= url('logout.php') ?>" class="btn btn-outline-danger w-100">
            <i class="bi bi-box-arrow-right me-2"></i>
            تسجيل الخروج
        </a>
    </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
