<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - ATTENDANCE PAGE                                      ║
 * ║           صفحة تسجيل الحضور والانصراف                                         ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Version: 4.0.0                                                              ║
 * ║  Features:                                                                   ║
 * ║  - تسجيل الحضور التلقائي بثلاثة شروط                                         ║
 * ║  - خريطة مبسطة وأقل ازدحاماً                                                 ║
 * ║  - نظام AWOL للتنبيه عند الخروج من النطاق                                    ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once 'config/app.php';
require_once 'includes/functions.php';

// ═══════════════════════════════════════════════════════════════════════════════
// AUTHENTICATION CHECK
// ═══════════════════════════════════════════════════════════════════════════════
check_login();

$user_id = $_SESSION['user_id'] ?? 0;
$user = get_current_user_data();

if (!$user) {
    redirect('login.php');
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
// FETCH BRANCH DATA
// ═══════════════════════════════════════════════════════════════════════════════
$branch_id = $user['branch_id'] ?? 0;
$branch = null;
$branch_settings = [];
$has_branch = false;

if ($branch_id) {
    $branch = Database::fetchOne(
        "SELECT * FROM branches WHERE id = ? AND is_active = 1", 
        [$branch_id]
    );
    
    if ($branch) {
        $has_branch = true;
        $branch_settings = json_decode($branch['settings'] ?? '{}', true) ?: [];
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// LOCATION SETTINGS
// ═══════════════════════════════════════════════════════════════════════════════
$target_lat = floatval($branch_settings['latitude'] ?? $branch['latitude'] ?? 24.7136);
$target_lng = floatval($branch_settings['longitude'] ?? $branch['longitude'] ?? 46.6753);
$target_radius = floatval($branch_settings['geofence_radius'] ?? 100);

// ═══════════════════════════════════════════════════════════════════════════════
// FETCH ALL BRANCHES FOR MAP DISPLAY
// ═══════════════════════════════════════════════════════════════════════════════
$all_branches = Database::fetchAll(
    "SELECT id, name, code, latitude, longitude, geofence_radius, is_ghost_branch 
     FROM branches 
     WHERE is_active = 1 AND is_ghost_branch = 0 
     ORDER BY id"
);
$branches_json = json_encode($all_branches, JSON_UNESCAPED_UNICODE);

// ═══════════════════════════════════════════════════════════════════════════════
// EMPLOYEE SCHEDULE (جدول الدوام الخاص بالموظف)
// ═══════════════════════════════════════════════════════════════════════════════
$employee_schedule = getEmployeeSchedule($user_id);

// إعدادات الدوام
$work_start = substr($employee_schedule['work_start_time'] ?? '08:00:00', 0, 5);
$work_end = substr($employee_schedule['work_end_time'] ?? '17:00:00', 0, 5);
$grace_period = intval($employee_schedule['grace_period_minutes'] ?? 15);

// نوع الحضور
$attendance_mode = $employee_schedule['attendance_mode'] ?? 'time_and_location';
$remote_checkin_allowed = !empty($employee_schedule['remote_checkin_allowed']);
$early_checkin_minutes = intval($employee_schedule['early_checkin_minutes'] ?? 60); // ساعة قبل
$late_checkin_minutes = intval($employee_schedule['late_checkin_minutes'] ?? 60); // ساعة بعد
$working_days = $employee_schedule['working_days'] ?? [0, 1, 2, 3, 4];

// تحديث نصف قطر الجيوفنس من الجدول المخصص
if (!empty($employee_schedule['geofence_radius'])) {
    $target_radius = floatval($employee_schedule['geofence_radius']);
}

/**
 * دالة جلب جدول دوام الموظف
 */
function getEmployeeSchedule(int $userId): array {
    $schedule = Database::fetchOne("
        SELECT es.*, b.name as branch_name
        FROM employee_schedules es
        LEFT JOIN users u ON es.user_id = u.id
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE es.user_id = ? 
          AND es.is_active = 1
          AND (es.effective_from IS NULL OR es.effective_from <= CURDATE())
          AND (es.effective_until IS NULL OR es.effective_until >= CURDATE())
    ", [$userId]);
    
    if ($schedule) {
        $schedule['working_days'] = json_decode($schedule['working_days'] ?? '[]', true);
        return $schedule;
    }
    
    // إرجاع الإعدادات الافتراضية
    $defaults = [];
    $settings = Database::fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE setting_group = 'attendance'");
    foreach ($settings as $s) {
        $defaults[$s['setting_key']] = json_decode($s['setting_value'], true) ?? $s['setting_value'];
    }
    
    return [
        'work_start_time' => $defaults['work_start_time'] ?? '08:00:00',
        'work_end_time' => $defaults['work_end_time'] ?? '17:00:00',
        'grace_period_minutes' => $defaults['grace_period_minutes'] ?? 15,
        'attendance_mode' => $defaults['default_attendance_mode'] ?? 'time_and_location',
        'working_days' => [0, 1, 2, 3, 4, 5, 6],
        'early_checkin_minutes' => 60, // ساعة قبل الدوام
        'late_checkin_minutes' => 60,  // ساعة بعد بداية الدوام
        'remote_checkin_allowed' => false,
        'is_default' => true
    ];
}

// ═══════════════════════════════════════════════════════════════════════════════
// SYSTEM SETTINGS
// ═══════════════════════════════════════════════════════════════════════════════
$heartbeat_interval = intval(get_setting('heartbeat_interval', 10000));
$live_mode_enabled = get_setting('live_mode_enabled', 'true') === 'true';
$show_colleague_names = get_setting('show_colleague_names', 'true') === 'true';

// ═══════════════════════════════════════════════════════════════════════════════
// CHECK TODAY'S ATTENDANCE STATUS
// ═══════════════════════════════════════════════════════════════════════════════
$today = date('Y-m-d');

$attendance = Database::fetchOne(
    "SELECT * FROM attendance WHERE user_id = ? AND date = ? ORDER BY id DESC LIMIT 1",
    [$user_id, $today]
);

// Determine available action
$action_type = 'checkin';
$attendance_id = null;
$check_in_time = null;
$check_out_time = null;

if ($attendance) {
    $attendance_id = $attendance['id'];
    $check_in_time = $attendance['check_in_time'];
    $check_out_time = $attendance['check_out_time'];
    
    if (empty($check_out_time)) {
        $action_type = 'checkout';
    } else {
        $action_type = 'done';
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECURITY TOKENS
// ═══════════════════════════════════════════════════════════════════════════════
$csrf_token = csrf_token();
$role_level = intval($_SESSION['role_level'] ?? 1);

// ═══════════════════════════════════════════════════════════════════════════════
// PAGE SETTINGS
// ═══════════════════════════════════════════════════════════════════════════════
$page_title = 'تسجيل الحضور';
$hide_header = true;
$hide_footer = true;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0a0a0a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    
    <title><?= e($page_title) ?> - <?= e(APP_NAME ?? 'صرح') ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Custom Attendance Styles -->
    <link rel="stylesheet" href="assets/css/attendance.css?v=<?= time() ?>">
</head>
<body>
    <!-- MAIN APPLICATION CONTAINER -->
    <div id="attendance-app"
         data-user-id="<?= e($user_id) ?>"
         data-user-name="<?= e($user['full_name'] ?? 'مستخدم') ?>"
         data-branch-id="<?= e($branch_id) ?>"
         data-branch-name="<?= e($branch['name'] ?? 'غير محدد') ?>"
         data-has-branch="<?= $has_branch ? 'true' : 'false' ?>"
         data-target-lat="<?= e($target_lat) ?>"
         data-target-lng="<?= e($target_lng) ?>"
         data-target-radius="<?= e($target_radius) ?>"
         data-work-start="<?= e($work_start) ?>"
         data-work-end="<?= e($work_end) ?>"
         data-early-checkin-minutes="<?= e($early_checkin_minutes) ?>"
         data-late-checkin-minutes="<?= e($late_checkin_minutes) ?>"
         data-working-days="<?= e(json_encode($working_days)) ?>"
         data-server-time="<?= date('Y-m-d H:i:s') ?>"
         data-action-type="<?= e($action_type) ?>"
         data-attendance-id="<?= e($attendance_id ?? '') ?>"
         data-check-in-time="<?= e($check_in_time ?? '') ?>"
         data-csrf-token="<?= e($csrf_token) ?>"
         data-action-url="api/attendance/action.php"
         data-heartbeat-url="api/heartbeat.php"
         data-all-branches='<?= e($branches_json) ?>'
         data-heartbeat-interval="<?= e($heartbeat_interval) ?>"
         data-live-mode="<?= $live_mode_enabled ? 'true' : 'false' ?>"
         data-role-level="<?= e($role_level) ?>"
         data-show-names="<?= $show_colleague_names ? 'true' : 'false' ?>">
        
        <!-- MAP LAYER -->
        <div id="map"></div>
        
        <!-- UI LAYER -->
        <div id="ui-layer">
            
            <!-- Top Bar - Minimal -->
            <header id="top-info">
                <div class="info-item time-info">
                    <i class="bi bi-clock"></i>
                    <span id="current-time"><?= date('H:i') ?></span>
                </div>
                <div id="connection-status" class="connected">
                    <span class="pulse-dot"></span>
                    <span class="status-text">متصل</span>
                </div>
            </header>
            
            <!-- Status Badge -->
            <div id="status-display" class="status-badge wait">
                <i class="bi bi-hourglass-split"></i>
                <span>جاري تحديد الموقع...</span>
            </div>
            
            <!-- Distance Info -->
            <div id="distance-info" class="hidden">
                <div class="distance-value">
                    <span id="dist-number">---</span>
                    <span id="dist-unit">م</span>
                </div>
                <div class="distance-label">من الفرع</div>
            </div>
            
        </div>
        
        <!-- BOTTOM PANEL - Hidden for checkin (auto), shown for checkout -->
        <footer id="btmPanel" <?php if ($action_type !== 'checkout'): ?>style="display: none;"<?php endif; ?>>
            
            <?php if ($check_in_time): ?>
            <div id="checkin-info" class="checkin-info">
                <i class="bi bi-box-arrow-in-left text-success"></i>
                <span>وقت الحضور: <strong><?= e(substr($check_in_time, 0, 5)) ?></strong></span>
            </div>
            <?php endif; ?>
            
            <!-- Action Button - Only for checkout -->
            <button id="actionBtn" 
                    class="action-btn <?= e($action_type) ?>" 
                    <?= ($action_type === 'done') ? 'disabled' : '' ?>
                    <?php if ($action_type !== 'checkout'): ?>style="display: none;"<?php endif; ?>>
                <?php if ($action_type === 'checkout'): ?>
                    <i class="bi bi-box-arrow-right"></i>
                    <span>تسجيل الانصراف</span>
                <?php else: ?>
                    <i class="bi bi-check-circle-fill"></i>
                    <span>تم تسجيل اليوم</span>
                <?php endif; ?>
            </button>
            
        </footer>
        
        <!-- Location Refresh Button -->
        <button id="locBtn" class="loc-btn" title="تحديث الموقع">
            <i class="bi bi-crosshair"></i>
        </button>
        
        <!-- Colleagues Panel -->
        <?php if ($live_mode_enabled): ?>
        <aside id="live-colleagues-panel">
            <button id="colleagues-toggle" class="colleagues-toggle">
                <i class="bi bi-people-fill"></i>
                <span id="colleagues-count" class="badge">0</span>
            </button>
            <div id="colleagues-list" class="colleagues-list hidden">
                <div class="colleagues-header">
                    <h4><i class="bi bi-broadcast"></i> الزملاء النشطون</h4>
                    <button id="close-colleagues" class="close-btn"><i class="bi bi-x-lg"></i></button>
                </div>
                <div id="colleagues-items" class="colleagues-items">
                    <p class="no-colleagues"><i class="bi bi-person-x"></i> لا يوجد زملاء نشطون</p>
                </div>
            </div>
        </aside>
        <?php endif; ?>
        
        <!-- Back Button -->
        <a href="index.php" id="backBtn" class="back-btn">
            <i class="bi bi-arrow-right"></i>
        </a>
        
    </div>
    
    <!-- SCRIPTS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/attendance_core.js?v=<?= time() ?>"></script>
    
</body>
</html>
