<?php
/**
 * صفحة الإعدادات - Settings Page
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

check_login();

$pageTitle = 'الإعدادات';
$currentPage = 'settings';

include INCLUDES_PATH . '/header.php';
?>

<div class="container py-4">
    <h4 class="mb-4">
        <i class="bi bi-gear-fill text-primary me-2"></i>
        الإعدادات
    </h4>
    
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="card-title">
                <i class="bi bi-person-circle me-2"></i>
                معلومات الحساب
            </h6>
            <div class="list-group list-group-flush">
                <div class="list-group-item d-flex justify-content-between">
                    <span>الاسم</span>
                    <strong><?= e($_SESSION['full_name'] ?? 'غير محدد') ?></strong>
                </div>
                <div class="list-group-item d-flex justify-content-between">
                    <span>اسم المستخدم</span>
                    <strong><?= e($_SESSION['username'] ?? 'غير محدد') ?></strong>
                </div>
                <div class="list-group-item d-flex justify-content-between">
                    <span>الدور</span>
                    <span class="badge" style="background-color: <?= e($_SESSION['role_color'] ?? '#6c757d') ?>">
                        <?= e($_SESSION['role_name'] ?? 'موظف') ?>
                    </span>
                </div>
                <div class="list-group-item d-flex justify-content-between">
                    <span>الفرع</span>
                    <strong><?= e($_SESSION['branch_name'] ?? 'غير محدد') ?></strong>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="card-title">
                <i class="bi bi-shield-lock me-2"></i>
                الأمان
            </h6>
            <a href="<?= url('change-password.php') ?>" class="btn btn-outline-primary w-100 mb-2">
                <i class="bi bi-key me-2"></i>
                تغيير كلمة المرور
            </a>
        </div>
    </div>
    
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="card-title">
                <i class="bi bi-info-circle me-2"></i>
                معلومات النظام
            </h6>
            <div class="list-group list-group-flush">
                <div class="list-group-item d-flex justify-content-between">
                    <span>اسم النظام</span>
                    <strong><?= e(get_setting('app_name', APP_NAME)) ?></strong>
                </div>
                <div class="list-group-item d-flex justify-content-between">
                    <span>الإصدار</span>
                    <strong><?= APP_VERSION ?></strong>
                </div>
                <div class="list-group-item d-flex justify-content-between">
                    <span>المنطقة الزمنية</span>
                    <strong><?= e(get_setting('timezone', 'Asia/Riyadh')) ?></strong>
                </div>
            </div>
        </div>
    </div>
    
    <a href="<?= url('logout.php') ?>" class="btn btn-danger w-100 py-3 mt-3">
        <i class="bi bi-box-arrow-right me-2"></i>
        تسجيل الخروج
    </a>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
