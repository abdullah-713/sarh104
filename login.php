<?php
/**
 * =====================================================
 * نظام صرح الإتقان للسيطرة الميدانية
 * Sarh Al-Itqan Field Operations System
 * =====================================================
 * صفحة تسجيل الدخول - متوافقة مع production_lean_v1.sql
 * Login Page - Compatible with production_lean_v1.sql
 * =====================================================
 */

// تحميل الإعدادات
require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

// التحقق من توكن "تذكرني" للدخول التلقائي
if (check_remember_token() && is_logged_in()) {
    redirect(url('index.php'));
}

// التحقق إذا كان المستخدم مسجل دخول بالفعل
if (is_logged_in()) {
    redirect(url('index.php'));
}

// متغيرات
$error = '';
$identifier = '';

// معالجة طلب تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من CSRF Token
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'خطأ في التحقق من الأمان. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        // جلب البيانات
        $identifier = clean_input($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'] === 'on';
        
        // التحقق من الحقول المطلوبة
        if (empty($identifier) || empty($password)) {
            $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
        } else {
            // محاولة تسجيل الدخول
            $result = login($identifier, $password, $remember);
            
            if ($result['success']) {
                // نجاح - إعادة التوجيه
                $redirectTo = $_SESSION['redirect_after_login'] ?? url('index.php');
                unset($_SESSION['redirect_after_login']);
                
                // رسالة ترحيب
                flash('success', 'مرحباً بك، ' . $_SESSION['full_name']);
                redirect($redirectTo);
            } else {
                // فشل - عرض رسالة الخطأ
                $error = $result['message'];
            }
        }
    }
}

// جلب اسم التطبيق من الإعدادات
$appName = get_setting('app_name', APP_NAME);

// عنوان الصفحة
$pageTitle = 'تسجيل الدخول';
$hideNavbar = true;
$hideBottomNav = true;
$bodyClass = 'login-page';

// أنماط إضافية
$additionalStyles = <<<CSS
.login-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: linear-gradient(135deg, #ff6f00 0%, #0d1642 50%, #000051 100%);
    background-attachment: fixed;
    position: relative;
    overflow: hidden;
}

.login-page::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,111,0,0.1) 0%, transparent 50%);
    animation: pulse 15s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 0.3; }
}

.login-container {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    position: relative;
    z-index: 1;
}

.login-card {
    background: rgba(255, 255, 255, 0.98);
    border-radius: 24px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
    width: 100%;
    max-width: 420px;
    overflow: hidden;
    backdrop-filter: blur(10px);
}

.login-header {
    background: linear-gradient(135deg, #ff6f00 0%, #ffa040 100%);
    padding: 40px 30px;
    text-align: center;
    color: white;
    position: relative;
}

.login-header::after {
    content: '';
    position: absolute;
    bottom: -20px;
    left: 50%;
    transform: translateX(-50%);
    width: 40px;
    height: 40px;
    background: white;
    border-radius: 50%;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.login-logo {
    width: 100px;
    height: 100px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 2.5rem;
    transition: transform 0.3s ease;
    padding: 10px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}

.login-logo:hover {
    transform: scale(1.05) rotate(5deg);
}

.login-logo-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 12px;
}

.login-title {
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 8px;
}

.login-subtitle {
    opacity: 0.8;
    font-size: 0.95rem;
}

.login-body {
    padding: 40px 32px 32px;
}

.login-footer {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    font-size: 0.85rem;
    color: #6c757d;
    border-top: 1px solid #eee;
}

.form-floating {
    margin-bottom: 20px;
}

.form-floating > .form-control {
    height: 58px;
    padding: 18px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 14px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-floating > .form-control:focus {
    border-color: #ff6f00;
    box-shadow: 0 0 0 4px rgba(26,35,126,0.1);
}

.form-floating > label {
    padding: 18px 16px;
    color: #6c757d;
}

.password-wrapper {
    position: relative;
}

.password-wrapper .form-control {
    padding-left: 50px !important;
}

.password-toggle {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 8px;
    z-index: 10;
    border-radius: 8px;
    transition: all 0.2s;
}

.password-toggle:hover {
    color: #ff6f00;
    background: rgba(26,35,126,0.1);
}

.form-check {
    padding-right: 1.75rem;
    padding-left: 0;
}

.form-check-input {
    float: right;
    margin-right: -1.75rem;
    margin-left: 0;
    width: 20px;
    height: 20px;
    border: 2px solid #ddd;
    border-radius: 6px;
}

.form-check-input:checked {
    background-color: #ff6f00;
    border-color: #ff6f00;
}

.form-check-label {
    cursor: pointer;
    user-select: none;
}

.btn-login {
    height: 56px;
    font-size: 1.1rem;
    font-weight: 700;
    border-radius: 14px;
    background: linear-gradient(135deg, #ff6f00 0%, #ffa040 100%);
    border: none;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.btn-login::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s ease;
}

.btn-login:hover::before {
    left: 100%;
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(26,35,126,0.4);
}

.btn-login:active {
    transform: translateY(0);
}

.alert {
    border-radius: 12px;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: shake 0.5s ease;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.forgot-link {
    color: #6c757d;
    text-decoration: none;
    transition: color 0.2s;
}

.forgot-link:hover {
    color: #ff6f00;
}

.input-hint {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: -12px;
    margin-bottom: 16px;
    padding-right: 4px;
}

/* التجاوب */
@media (max-width: 576px) {
    .login-card {
        border-radius: 0;
        min-height: 100vh;
        max-width: none;
    }
    
    .login-container {
        padding: 0;
        align-items: stretch;
    }
    
    .login-header {
        padding: 50px 24px 40px;
    }
    
    .login-body {
        padding: 40px 24px 24px;
    }
}
CSS;

// تحميل رأس الصفحة
include INCLUDES_PATH . '/header.php';
?>

<div class="login-container">
    <div class="login-card fade-in">
        <!-- رأس تسجيل الدخول -->
        <div class="login-header">
            <div class="login-logo">
                <span class="sarh-logo logo-2xl logo-bounce-elastic">
                    <img src="<?= asset('images/logo.png') ?>" alt="<?= e($appName) ?>" class="login-logo-img">
                </span>
            </div>
            <h1 class="login-title"><?= e($appName) ?></h1>
            <p class="login-subtitle"><?= APP_TAGLINE ?></p>
        </div>
        
        <!-- نموذج تسجيل الدخول -->
        <div class="login-body">
            <?php if ($error): ?>
            <div class="alert alert-danger mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <span><?= e($error) ?></span>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" autocomplete="off" id="loginForm">
                <?= csrf_field() ?>
                
                <!-- معرّف المستخدم (اسم المستخدم / رقم الموظف / البريد) -->
                <div class="form-floating">
                    <input type="text" 
                           class="form-control" 
                           id="identifier" 
                           name="identifier" 
                           placeholder="اسم المستخدم"
                           value="<?= e($identifier) ?>"
                           autocomplete="username"
                           required
                           autofocus>
                    <label for="identifier">
                        <i class="bi bi-person me-2"></i>
                        اسم المستخدم
                    </label>
                </div>
                <p class="input-hint">
                    <i class="bi bi-info-circle me-1"></i>
                    يمكنك استخدام رقم الموظف أو البريد الإلكتروني
                </p>
                
                <!-- كلمة المرور -->
                <div class="form-floating password-wrapper">
                    <input type="password" 
                           class="form-control" 
                           id="password" 
                           name="password" 
                           placeholder="كلمة المرور"
                           autocomplete="current-password"
                           required>
                    <label for="password">
                        <i class="bi bi-lock me-2"></i>
                        كلمة المرور
                    </label>
                    <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="إظهار كلمة المرور">
                        <i class="bi bi-eye fs-5" id="toggleIcon"></i>
                    </button>
                </div>
                
                <!-- تذكرني -->
                <div class="form-check mb-4">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">
                        <i class="bi bi-heart me-1 text-danger"></i>
                        تذكرني لمدة <?= get_setting('remember_me_days', 30) ?> يوم
                    </label>
                </div>
                
                <!-- زر الدخول -->
                <button type="submit" class="btn btn-primary btn-login w-100" id="btnLogin">
                    <i class="bi bi-box-arrow-in-left me-2"></i>
                    تسجيل الدخول
                </button>
            </form>
            
            <!-- رابط استعادة كلمة المرور -->
            <div class="text-center mt-4">
                <a href="forgot-password.php" class="forgot-link">
                    <i class="bi bi-question-circle me-1"></i>
                    نسيت كلمة المرور؟
                </a>
            </div>
        </div>
        
        <!-- تذييل -->
        <div class="login-footer">
            <div class="d-flex justify-content-center align-items-center gap-2">
                <i class="bi bi-shield-check text-success"></i>
                <span>اتصال آمن ومشفر</span>
            </div>
            <div class="mt-2">
                <span>&copy; <?= date('Y') ?> <?= e($appName) ?></span>
                <span class="mx-1">•</span>
                <span>الإصدار <?= get_setting('app_version', APP_VERSION) ?></span>
            </div>
        </div>
    </div>
</div>

<script>
// إظهار/إخفاء كلمة المرور
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('bi-eye');
        toggleIcon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('bi-eye-slash');
        toggleIcon.classList.add('bi-eye');
    }
}

// منع إرسال النموذج مرتين
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('btnLogin');
    
    // التحقق من صحة الحقول
    const identifier = document.getElementById('identifier').value.trim();
    const password = document.getElementById('password').value;
    
    if (!identifier || !password) {
        e.preventDefault();
        return;
    }
    
    // تعطيل الزر وإظهار التحميل
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>جاري الدخول...';
});

// اختصار Enter للدخول
document.getElementById('password').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('loginForm').submit();
    }
});

// تركيز على حقل كلمة المرور إذا كان اسم المستخدم مملوء
document.addEventListener('DOMContentLoaded', function() {
    const identifier = document.getElementById('identifier');
    const password = document.getElementById('password');
    
    if (identifier.value.trim() !== '') {
        password.focus();
    }
});
</script>

<?php
// تحميل تذييل الصفحة
include INCLUDES_PATH . '/footer.php';
?>
