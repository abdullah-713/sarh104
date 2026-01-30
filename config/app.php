<?php
/**
 * =====================================================
 * نظام صرح الإتقان للسيطرة الميدانية
 * Sarh Al-Itqan Field Operations System
 * =====================================================
 * ملف إعدادات التطبيق الرئيسية
 * Main Application Configuration File
 * =====================================================
 */

// تعريف ثابت النظام لحماية الملفات
define('SARH_SYSTEM', true);

// =====================================================
// إعدادات البيئة - سيرفر الإنتاج
// Environment Settings - Production Server
// =====================================================
define('DEBUG_MODE', false);          // وضع الإنتاج
define('ENVIRONMENT', 'production'); // 'development' أو 'production'

// =====================================================
// إعدادات عرض الأخطاء
// Error Display Settings
// =====================================================
if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}

// =====================================================
// إعدادات المنطقة الزمنية
// Timezone Settings
// =====================================================
date_default_timezone_set('Asia/Riyadh');

// =====================================================
// إعدادات التطبيق الأساسية
// Core Application Settings
// =====================================================
define('APP_NAME', 'صرح الإتقان');
define('APP_NAME_EN', 'Sarh Al-Itqan');
define('APP_TAGLINE', 'نظام السيطرة الميدانية');
define('APP_VERSION', '1.1.0');
define('APP_LANG', 'ar');
define('APP_DIR', 'rtl');

// =====================================================
// إعدادات المسارات
// Path Settings
// =====================================================
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');

// =====================================================
// عنوان الموقع - sarh.site Production
// Site URL - Production Server
// =====================================================
define('BASE_URL', 'https://sarh.site/app');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// =====================================================
// إعدادات PWA و Push Notifications
// PWA / Push Settings
// =====================================================
// مفاتيح VAPID لإشعارات الدفع - يمكن إنشاء مفاتيح جديدة من https://vapidkeys.com/
define('PWA_VAPID_PUBLIC_KEY', 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U');
define('PWA_VAPID_PRIVATE_KEY', 'UUxI4O8-FbRouADVXc-Muhe_d-8FN-S0GYl8_Oc4gpo');
define('PWA_PUSH_SUBJECT', 'mailto:admin@sarh.site');

// =====================================================
// إعدادات الجلسات
// Session Settings
// =====================================================
define('SESSION_NAME', 'SARH_SESSION');
define('SESSION_LIFETIME', 7200);      // ساعتان
define('SESSION_SECURE', true);        // HTTPS only
define('SESSION_HTTPONLY', true);      // منع JavaScript access

// =====================================================
// إعدادات الأمان
// Security Settings
// =====================================================
define('CSRF_TOKEN_NAME', 'sarh_csrf_token');
define('PASSWORD_COST', 12);           // bcrypt cost
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900);           // 15 دقيقة

// =====================================================
// إعدادات الحضور
// Attendance Settings
// =====================================================
define('DEFAULT_WORK_START', '06:00:00');
define('DEFAULT_WORK_END', '14:00:00');
define('DEFAULT_LOCK_TIME', '10:00:00');
define('DEFAULT_GRACE_PERIOD', 15);    // دقيقة
define('DEFAULT_GEOFENCE_RADIUS', 100); // متر
define('DEFAULT_MONTHLY_POINTS', 1000);

// =====================================================
// إعدادات الملفات
// File Upload Settings
// =====================================================
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOC_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// =====================================================
// إعدادات الصفحات
// Pagination Settings
// =====================================================
define('ITEMS_PER_PAGE', 20);
define('MAX_ITEMS_PER_PAGE', 100);

// =====================================================
// ألوان حالات الحضور
// Attendance Status Colors
// =====================================================
define('STATUS_COLORS', [
    'present'     => '#28a745', // أخضر
    'absent'      => '#dc3545', // أحمر
    'late'        => '#ffc107', // أصفر
    'early_leave' => '#fd7e14', // برتقالي
    'on_leave'    => '#17a2b8', // أزرق فاتح
    'holiday'     => '#6f42c1', // بنفسجي
    'weekend'     => '#6c757d', // رمادي
    'excused'     => '#20c997', // أخضر فاتح
]);

// =====================================================
// مستويات الأدوار
// Role Levels
// =====================================================
define('ROLE_EMPLOYEE', 1);
define('ROLE_SUPERVISOR', 2);
define('ROLE_MANAGER', 3);
define('ROLE_SENIOR_MANAGER', 4);
define('ROLE_ADMIN', 5);

// =====================================================
// تحميل ملفات الإعدادات الأخرى
// Load Other Configuration Files
// =====================================================
require_once CONFIG_PATH . '/database.php';

// =====================================================
// تحميل ملف الدوال الرئيسية (إذا لم يتم تحميله)
// Load Main Functions File (if not already loaded)
// =====================================================
$functionsFile = INCLUDES_PATH . '/functions.php';
if (file_exists($functionsFile) && !function_exists('login')) {
    require_once $functionsFile;
}

// =====================================================
// بدء الجلسة
// Start Session
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    
    $sessionOptions = [
        'cookie_lifetime' => SESSION_LIFETIME,
        'cookie_httponly' => SESSION_HTTPONLY,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
        'use_only_cookies' => true,
    ];
    
    // إضافة secure فقط في HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        $sessionOptions['cookie_secure'] = true;
    }
    
    session_start($sessionOptions);
    
    // تجديد معرف الجلسة دورياً
    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif (time() - $_SESSION['_created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }
}

// =====================================================
// دوال مساعدة أساسية
// Core Helper Functions
// =====================================================

/**
 * تحويل النص إلى HTML آمن
 * Escape HTML
 */
function e(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * إعادة التوجيه
 * Redirect
 */
function redirect(string $url, int $statusCode = 302): void {
    header("Location: {$url}", true, $statusCode);
    exit;
}

/**
 * الحصول على عنوان URL كامل
 * Get full URL
 */
function url(string $path = ''): string {
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * الحصول على مسار الأصول
 * Get asset URL
 */
function asset(string $path): string {
    return ASSETS_URL . '/' . ltrim($path, '/');
}

/**
 * توليد CSRF Token
 * Generate CSRF Token
 */
function csrf_token(): string {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * حقل CSRF مخفي
 * CSRF hidden field
 */
function csrf_field(): string {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . csrf_token() . '">';
}

/**
 * التحقق من CSRF Token
 * Verify CSRF Token
 */
function verify_csrf(string $token): bool {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && 
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * التحقق من تسجيل الدخول
 * Check if logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * الحصول على معرف المستخدم الحالي
 * Get current user ID
 */
function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * الحصول على مستوى دور المستخدم الحالي
 * Get current user role level
 */
function current_role_level(): int {
    return $_SESSION['role_level'] ?? 0;
}

/**
 * التحقق من الصلاحية
 * Check permission
 */
function has_permission(string $permission): bool {
    if (!is_logged_in()) return false;
    
    // الأدمن له جميع الصلاحيات
    if (current_role_level() >= ROLE_ADMIN) return true;
    
    // التحقق من الصلاحيات المخزنة في الجلسة
    return in_array($permission, $_SESSION['permissions'] ?? []);
}

/**
 * التحقق من مستوى الدور
 * Check role level
 */
function has_role(int $minLevel): bool {
    return is_logged_in() && current_role_level() >= $minLevel;
}

/**
 * تنسيق التاريخ بالعربية
 * Format date in Arabic
 */
function format_date(string $date, string $format = 'Y/m/d'): string {
    return date($format, strtotime($date));
}

/**
 * تنسيق الوقت
 * Format time
 */
function format_time(string $time): string {
    return date('h:i A', strtotime($time));
}

/**
 * تنسيق التاريخ والوقت
 * Format datetime
 */
function format_datetime(string $datetime): string {
    return date('Y/m/d h:i A', strtotime($datetime));
}

/**
 * الفرق بالدقائق بين وقتين
 * Get minutes difference
 */
function minutes_diff(string $start, string $end): int {
    $startTime = new DateTime($start);
    $endTime = new DateTime($end);
    $diff = $startTime->diff($endTime);
    return ($diff->h * 60) + $diff->i;
}

/**
 * تحويل الدقائق إلى ساعات ودقائق
 * Convert minutes to hours and minutes
 */
function minutes_to_time(int $minutes): string {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf('%d:%02d', $hours, $mins);
}

/**
 * رسالة Flash
 * Flash message
 */
function flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * تعيين رسالة Flash
 * Set flash message
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * الحصول على رسالة Flash وحذفها
 * Get and clear flash message
 */
function get_flash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * تسجيل النشاط
 * Log activity
 */
function log_activity(string $action, string $modelType = '', string $details = '', ?int $modelId = null, string $oldValues = ''): void {
    try {
        Database::insert('activity_log', [
            'user_id' => current_user_id(),
            'action' => $action,
            'model_type' => $modelType ?: null,
            'model_id' => $modelId,
            'old_values' => $oldValues ?: null,
            'new_values' => $details ?: null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (Exception $e) {
        error_log("Activity Log Error: " . $e->getMessage());
    }
}

/**
 * الحصول على إعداد النظام
 * Get system setting
 */
function get_setting(string $key, mixed $default = null): mixed {
    static $settings = null;
    
    if ($settings === null) {
        try {
            $results = Database::fetchAll("SELECT setting_key, setting_value FROM system_settings");
            $settings = [];
            foreach ($results as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            return $default;
        }
    }
    
    return $settings[$key] ?? $default;
}

/**
 * طلب JSON فقط
 * JSON request only
 */
function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * التحقق من طلب AJAX
 * Check if AJAX request
 */
function is_ajax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * تنظيف المدخلات
 * Sanitize input
 */
function clean_input(mixed $data): mixed {
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
