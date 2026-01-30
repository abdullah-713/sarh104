<?php
/**
 * =====================================================
 * نظام صرح الإتقان للسيطرة الميدانية
 * Sarh Al-Itqan Field Operations System
 * =====================================================
 * تذييل الصفحة
 * Page Footer
 * =====================================================
 */

// تحميل الإعدادات إذا لم تكن محملة
if (!defined('SARH_SYSTEM')) {
    require_once dirname(__DIR__) . '/config/app.php';
}

$hideBottomNav = $hideBottomNav ?? false;
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>

</main>
<!-- نهاية محتوى الصفحة الرئيسي -->

<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<!-- العلامة المائية / الختم - Watermark -->
<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<div class="watermark-overlay" aria-hidden="true">
    <div class="watermark-stamp">
        <div class="stamp-border">
            <div class="stamp-inner">
                <span class="stamp-star">★</span>
                <span class="stamp-name">عبد الحكيم المذهول</span>
                <span class="stamp-star">★</span>
            </div>
            <div class="stamp-subtitle">مطور النظام</div>
        </div>
    </div>
</div>

<style>
/* ═══════════════════════════════════════════════════════════════════════════════ */
/* أنماط العلامة المائية / الختم */
/* ═══════════════════════════════════════════════════════════════════════════════ */
.watermark-overlay {
    position: fixed;
    bottom: 100px;
    left: 20px;
    z-index: 9999;
    pointer-events: none;
    opacity: 0.25;
    transform: rotate(-15deg);
    filter: drop-shadow(0 0 2px rgba(255, 255, 255, 0.5));
}

.watermark-stamp {
    width: 140px;
    height: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stamp-border {
    width: 130px;
    height: 130px;
    border: 4px double #ff6f00;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 10px;
    background: repeating-conic-gradient(
        from 0deg,
        transparent 0deg 10deg,
        rgba(255, 111, 0, 0.1) 10deg 20deg
    );
    position: relative;
}

.stamp-border::before {
    content: '';
    position: absolute;
    top: 6px;
    left: 6px;
    right: 6px;
    bottom: 6px;
    border: 2px dashed #ff6f00;
    border-radius: 50%;
}

.stamp-inner {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
    justify-content: center;
}

.stamp-star {
    color: #ff6f00;
    font-size: 12px;
}

.stamp-name {
    font-family: 'Tajawal', sans-serif;
    font-weight: 900;
    font-size: 13px;
    color: #ff6f00;
    text-align: center;
    line-height: 1.3;
    letter-spacing: 0.5px;
}

.stamp-subtitle {
    font-family: 'Tajawal', sans-serif;
    font-size: 9px;
    color: #ff6f00;
    margin-top: 2px;
    font-weight: 600;
}

/* إضافة حدود مضيئة للخلفيات الداكنة */
.stamp-border {
    box-shadow: 0 0 15px rgba(255, 111, 0, 0.3), inset 0 0 10px rgba(255, 111, 0, 0.1);
}

/* تحسين الموضع للشاشات الصغيرة */
@media (max-width: 768px) {
    .watermark-overlay {
        bottom: 90px;
        left: 10px;
        opacity: 0.15;
    }
    
    .watermark-stamp {
        width: 100px;
        height: 100px;
    }
    
    .stamp-border {
        width: 95px;
        height: 95px;
        border-width: 3px;
    }
    
    .stamp-name {
        font-size: 10px;
    }
    
    .stamp-subtitle {
        font-size: 7px;
    }
    
    .stamp-star {
        font-size: 8px;
    }
}

/* إخفاء عند الطباعة */
@media print {
    .watermark-overlay {
        display: none !important;
    }
}

/* ═══════════════════════════════════════════════════════════════════════════════ */
/* أنماط تنبيه الملكية الفكرية */
/* ═══════════════════════════════════════════════════════════════════════════════ */
.copyright-notice {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    padding: 15px 20px;
    text-align: center;
    border-top: 3px solid #ff6f00;
}

.copyright-notice .copyright-text {
    color: #b0b0b0;
    font-size: 0.8rem;
    margin: 0;
    font-family: 'Tajawal', sans-serif;
}

.copyright-notice .copyright-name {
    color: #ff6f00;
    font-weight: 700;
}

.copyright-notice .copyright-icon {
    color: #ff6f00;
    margin: 0 5px;
}

.copyright-notice .copyright-warning {
    color: #ff8a80;
    font-size: 0.7rem;
    margin-top: 5px;
    display: block;
}

@media (max-width: 768px) {
    .copyright-notice {
        padding: 12px 15px;
        margin-bottom: 70px; /* مساحة لشريط التنقل السفلي */
    }
    
    .copyright-notice .copyright-text {
        font-size: 0.75rem;
    }
    
    .copyright-notice .copyright-warning {
        font-size: 0.65rem;
    }
}
</style>

<?php if (!$hideBottomNav && is_logged_in()): ?>
<!-- شريط التنقل السفلي -->
<nav class="bottom-nav" id="bottomNav">
    <!-- الرئيسية -->
    <a href="<?= url('index.php') ?>" class="nav-item <?= $currentPage === 'index' ? 'active' : '' ?>">
        <i class="bi bi-house-door<?= $currentPage === 'index' ? '-fill' : '' ?>"></i>
        <span>الرئيسية</span>
    </a>
    
    <!-- الحضور -->
    <a href="<?= url('attendance.php') ?>" class="nav-item <?= $currentPage === 'attendance' ? 'active' : '' ?>">
        <i class="bi bi-calendar-check<?= $currentPage === 'attendance' ? '-fill' : '' ?>"></i>
        <span>الحضور</span>
    </a>
    
    <!-- زر تسجيل الحضور المميز - مع شعار متحرك -->
    <a href="<?= url('checkin.php') ?>" class="nav-item checkin-btn">
        <div class="checkin-circle" style="overflow:hidden;">
            <span class="sarh-logo logo-sm logo-bounce" style="filter:brightness(0) invert(1);">
                <img src="<?= asset('images/logo.png') ?>" alt="تسجيل" style="width:100%;height:100%;object-fit:contain;">
            </span>
        </div>
        <span>تسجيل</span>
    </a>
    
    <!-- التقارير -->
    <a href="<?= url('reports.php') ?>" class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
        <i class="bi bi-bar-chart-line<?= $currentPage === 'reports' ? '-fill' : '' ?>"></i>
        <span>التقارير</span>
    </a>
    
    <!-- المزيد -->
    <a href="<?= url('more.php') ?>" class="nav-item <?= $currentPage === 'more' ? 'active' : '' ?>">
        <i class="bi bi-grid<?= $currentPage === 'more' ? '-fill' : '' ?>"></i>
        <span>المزيد</span>
    </a>
</nav>
<?php endif; ?>

<!-- التذييل للشاشات الكبيرة (يظهر فقط على الكمبيوتر) -->
<footer class="footer mt-auto py-3 bg-light hide-mobile">
    <div class="container text-center">
        <span class="text-muted">
            <?= APP_NAME ?> &copy; <?= date('Y') ?> - الإصدار <?= APP_VERSION ?>
        </span>
    </div>
</footer>

<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<!-- تنبيه الملكية الفكرية -->
<!-- ═══════════════════════════════════════════════════════════════════════════════ -->
<div class="copyright-notice">
    <p class="copyright-text">
        <i class="bi bi-c-circle copyright-icon"></i>
        جميع الحقوق محفوظة لـ 
        <span class="copyright-name">عبد الحكيم المذهول</span>
        <i class="bi bi-shield-check copyright-icon"></i>
        <?= date('Y') ?>
    </p>
    <small class="copyright-warning">
        <i class="bi bi-exclamation-triangle-fill"></i>
        تحذير: هذا النظام محمي بموجب قوانين الملكية الفكرية. يُمنع منعاً باتاً نسخ أو توزيع أو تعديل هذا البرنامج دون إذن كتابي مسبق.
    </small>
</div>

<!-- Bootstrap 5 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- مكتبة SweetAlert2 للتنبيهات الجميلة -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- سكربت عام للتطبيق -->
<script>
/**
 * =====================================================
 * سكربتات عامة لنظام صرح الإتقان
 * =====================================================
 */

// إعدادات عامة
const SARH = {
    baseUrl: '<?= BASE_URL ?>',
    csrfToken: '<?= csrf_token() ?>',
    userId: <?= current_user_id() ?? 'null' ?>,
    isLoggedIn: <?= is_logged_in() ? 'true' : 'false' ?>,
    vapidPublicKey: '<?= PWA_VAPID_PUBLIC_KEY ?>'
};

// دالة مساعدة لعرض رسائل التنبيه
function showAlert(title, text, icon = 'info') {
    return Swal.fire({
        title: title,
        text: text,
        icon: icon,
        confirmButtonText: 'حسناً',
        confirmButtonColor: '#ff6f00',
        customClass: {
            popup: 'rtl-alert'
        }
    });
}

// دالة لعرض رسالة نجاح
function showSuccess(message) {
    return Swal.fire({
        toast: true,
        position: 'top',
        icon: 'success',
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
}

// دالة لعرض رسالة خطأ
function showError(message) {
    return Swal.fire({
        toast: true,
        position: 'top',
        icon: 'error',
        title: message,
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true
    });
}

// دالة لعرض تأكيد
async function showConfirm(title, text, confirmText = 'نعم', cancelText = 'إلغاء') {
    const result = await Swal.fire({
        title: title,
        text: text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ff6f00',
        cancelButtonColor: '#6c757d',
        confirmButtonText: confirmText,
        cancelButtonText: cancelText
    });
    return result.isConfirmed;
}

// دالة لعرض مؤشر التحميل
function showLoading(text = 'جاري التحميل...') {
    Swal.fire({
        title: text,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

// إغلاق مؤشر التحميل
function hideLoading() {
    Swal.close();
}

// دالة AJAX مساعدة
async function fetchData(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': SARH.csrfToken
        },
        credentials: 'same-origin'
    };
    
    const mergedOptions = { ...defaultOptions, ...options };
    
    try {
        const response = await fetch(url, mergedOptions);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error('Fetch error:', error);
        throw error;
    }
}

// تفعيل Tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap Tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Bootstrap Popovers
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    popoverTriggerList.forEach(function(popoverTriggerEl) {
        new bootstrap.Popover(popoverTriggerEl);
    });
    
    // إخفاء التنبيهات تلقائياً بعد 5 ثواني
    const autoHideAlerts = document.querySelectorAll('.alert-dismissible.auto-hide');
    autoHideAlerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = bootstrap.Alert.getInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 5000);
    });
});

// التعامل مع اهتزاز الجهاز للحضور السريع (للموبايل)
if ('vibrate' in navigator) {
    const checkinBtn = document.querySelector('.checkin-btn');
    if (checkinBtn) {
        checkinBtn.addEventListener('click', function() {
            navigator.vibrate(50);
        });
    }
}

// منع zoom عند النقر المزدوج على الموبايل
document.addEventListener('touchend', function(event) {
    const now = Date.now();
    const DOUBLE_TAP_THRESHOLD = 300;
    
    if (now - (this.lastTouchEnd || 0) <= DOUBLE_TAP_THRESHOLD) {
        event.preventDefault();
    }
    
    this.lastTouchEnd = now;
}, false);

// تحديث عداد الإشعارات
function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
}

// فحص الإشعارات الجديدة كل دقيقة
if (SARH.isLoggedIn) {
    setInterval(async function() {
        try {
            const data = await fetchData(SARH.baseUrl + '/api/notifications/count.php');
            if (data.success) {
                updateNotificationBadge(data.count);
            }
        } catch (error) {
            // تجاهل الأخطاء في فحص الإشعارات
        }
    }, 60000);
}

// =====================================================
// دوال الموقع الجغرافي
// =====================================================

// الحصول على الموقع الحالي
function getCurrentLocation() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('المتصفح لا يدعم تحديد الموقع'));
            return;
        }
        
        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        };
        
        navigator.geolocation.getCurrentPosition(
            (position) => {
                resolve({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: position.coords.accuracy
                });
            },
            (error) => {
                let message = 'خطأ في تحديد الموقع';
                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        message = 'تم رفض إذن الموقع. يرجى تفعيله من الإعدادات.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message = 'الموقع غير متاح حالياً';
                        break;
                    case error.TIMEOUT:
                        message = 'انتهت مهلة تحديد الموقع';
                        break;
                }
                reject(new Error(message));
            },
            options
        );
    });
}

// حساب المسافة بين نقطتين (بالمتر)
function calculateDistance(lat1, lng1, lat2, lng2) {
    const R = 6371000; // نصف قطر الأرض بالمتر
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLng/2) * Math.sin(dLng/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// تنسيق المسافة
function formatDistance(meters) {
    if (meters < 1000) {
        return Math.round(meters) + ' متر';
    }
    return (meters / 1000).toFixed(1) + ' كم';
}

console.log('%c🏗️ ' + '<?= APP_NAME ?>' + ' v<?= APP_VERSION ?>', 'color: #ff6f00; font-size: 16px; font-weight: bold;');
</script>

<script src="<?= asset('js/pwa.js') ?>"></script>

<!-- مدير الوضع الليلي / Dark Mode -->
<script src="<?= asset('js/theme_manager.js') ?>"></script>

<?php if (is_logged_in()): ?>
<!-- نظام الإشعارات المتقدم -->
<script src="<?= asset('js/notifications.js') ?>?v=<?= filemtime(ASSETS_PATH . '/js/notifications.js') ?>"></script>

<!-- ويدجت الإشعارات الفورية -->
<script src="<?= asset('js/notifications_widget.js') ?>"></script>

<!-- كاشف الاحتيال -->
<script src="<?= asset('js/fraud_detector.js') ?>"></script>
<?php endif; ?>

<!-- زر تبديل الوضع الليلي -->
<button id="themeToggle" class="theme-toggle" title="تبديل الوضع">
    <i class="bi bi-circle-half"></i>
</button>

<?php if (isset($additionalScripts)): ?>
<?= $additionalScripts ?>
<?php endif; ?>

</body>
</html>
