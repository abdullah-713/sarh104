<?php
/**
 * صفحة المزيد - More Page
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

// حماية الصفحة
check_login();

$pageTitle = 'المزيد';
$currentPage = 'more';

include INCLUDES_PATH . '/header.php';
?>

<div class="container py-4">
    <h4 class="mb-4">
        <i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i>
        المزيد من الخيارات
    </h4>
    
    <div class="row g-3">
        <!-- الإعدادات -->
        <div class="col-6">
            <a href="<?= url('settings.php') ?>" class="card text-decoration-none h-100">
                <div class="card-body text-center py-4">
                    <i class="bi bi-gear fs-1 text-secondary mb-2"></i>
                    <h6 class="mb-0">الإعدادات</h6>
                </div>
            </a>
        </div>
        
        <!-- الملف الشخصي -->
        <div class="col-6">
            <a href="<?= url('profile.php') ?>" class="card text-decoration-none h-100">
                <div class="card-body text-center py-4">
                    <i class="bi bi-person-circle fs-1 text-info mb-2"></i>
                    <h6 class="mb-0">الملف الشخصي</h6>
                </div>
            </a>
        </div>
        
        <!-- سجل الحضور -->
        <div class="col-6">
            <a href="<?= url('attendance.php') ?>" class="card text-decoration-none h-100">
                <div class="card-body text-center py-4">
                    <i class="bi bi-calendar-check fs-1 text-success mb-2"></i>
                    <h6 class="mb-0">سجل الحضور</h6>
                </div>
            </a>
        </div>
        
        <!-- التقارير السرية -->
        <div class="col-6">
            <a href="<?= url('secret_report.php') ?>" class="card text-decoration-none h-100">
                <div class="card-body text-center py-4">
                    <i class="bi bi-shield-exclamation fs-1 text-warning mb-2"></i>
                    <h6 class="mb-0">بلاغ سري</h6>
                </div>
            </a>
        </div>
        
        <!-- الدردشة الجماعية -->
        <div class="col-6">
            <a href="<?= url('chat.php') ?>" class="card text-decoration-none h-100 border-primary">
                <div class="card-body text-center py-4">
                    <i class="bi bi-chat-dots fs-1 text-primary mb-2"></i>
                    <h6 class="mb-0">الدردشة</h6>
                    <small class="text-muted">جديد</small>
                </div>
            </a>
        </div>
        
        <!-- التحليلات والتنبؤ -->
        <div class="col-6">
            <a href="<?= url('analytics.php') ?>" class="card text-decoration-none h-100 border-success">
                <div class="card-body text-center py-4">
                    <i class="bi bi-graph-up-arrow fs-1 text-success mb-2"></i>
                    <h6 class="mb-0">التحليلات</h6>
                    <small class="text-muted">جديد</small>
                </div>
            </a>
        </div>
        
        <?php if (has_role(ROLE_ADMIN)): ?>
        <!-- لوحة الإدارة -->
        <div class="col-6">
            <a href="<?= url('admin/management.php') ?>" class="card text-decoration-none h-100 border-danger">
                <div class="card-body text-center py-4">
                    <i class="bi bi-sliders fs-1 text-danger mb-2"></i>
                    <h6 class="mb-0">الإدارة</h6>
                </div>
            </a>
        </div>
        
        <!-- مدير قاعدة البيانات -->
        <div class="col-6">
            <a href="<?= url('admin/universal_manager.php') ?>" class="card text-decoration-none h-100 border-danger">
                <div class="card-body text-center py-4">
                    <i class="bi bi-database-gear fs-1 text-danger mb-2"></i>
                    <h6 class="mb-0">قاعدة البيانات</h6>
                </div>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- تسجيل الخروج -->
        <div class="col-12 mt-4">
            <a href="<?= url('logout.php') ?>" class="btn btn-outline-danger w-100 py-3">
                <i class="bi bi-box-arrow-right me-2"></i>
                تسجيل الخروج
            </a>
        </div>
    </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
