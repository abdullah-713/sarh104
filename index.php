<?php
/**
 * =====================================================
 * نظام صرح الإتقان للسيطرة الميدانية
 * Sarh Al-Itqan Field Operations System
 * =====================================================
 * الصفحة الرئيسية - Dashboard
 * =====================================================
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

// حماية الصفحة
check_login();

// ═══════════════════════════════════════════════════════════════════════════════
// جلب البيانات
// ═══════════════════════════════════════════════════════════════════════════════

$userId = current_user_id();
$roleLevel = $_SESSION['role_level'] ?? 1;
$userName = $_SESSION['full_name'] ?? 'مستخدم';

// إحصائيات الحضور للشهر الحالي
try {
    $attendanceStats = get_user_attendance_stats($userId);
} catch (Exception $e) {
    $attendanceStats = [
        'present_days' => 0,
        'absent_days' => 0,
        'late_days' => 0,
        'total_late_minutes' => 0,
        'total_penalty_points' => 0,
        'total_bonus_points' => 0
    ];
}

// سجل حضور اليوم
try {
    $todayAttendance = get_today_attendance($userId);
} catch (Exception $e) {
    $todayAttendance = null;
}

// إعدادات العمل - جلب من جدول الموظف المخصص أولاً
try {
    // محاولة جلب الجدول المخصص للموظف
    $employeeSchedule = Database::fetchOne("
        SELECT work_start_time, work_end_time, grace_period_minutes, attendance_mode,
               is_flexible_hours, remote_checkin_allowed
        FROM employee_schedules 
        WHERE user_id = ? AND is_active = 1
          AND (effective_from IS NULL OR effective_from <= CURDATE())
          AND (effective_until IS NULL OR effective_until >= CURDATE())
    ", [$userId]);
    
    if ($employeeSchedule) {
        // استخدام الجدول المخصص
        $workSettings = [
            'work_start' => substr($employeeSchedule['work_start_time'], 0, 5),
            'work_end' => substr($employeeSchedule['work_end_time'], 0, 5),
            'grace_period_minutes' => intval($employeeSchedule['grace_period_minutes']),
            'lock_time' => date('H:i', strtotime($employeeSchedule['work_start_time']) + ($employeeSchedule['grace_period_minutes'] * 60)),
            'attendance_mode' => $employeeSchedule['attendance_mode'],
            'is_flexible' => $employeeSchedule['is_flexible_hours'],
            'remote_allowed' => $employeeSchedule['remote_checkin_allowed']
        ];
    } else {
        // الرجوع للإعدادات العامة
        $workSettings = get_current_work_settings();
    }
} catch (Exception $e) {
    $workSettings = [
        'work_start' => '08:00',
        'work_end' => '17:00',
        'grace_period_minutes' => 15,
        'lock_time' => '08:15',
        'attendance_mode' => 'time_and_location'
    ];
}

// عدد الإشعارات غير المقروءة
$unreadNotifications = $_SESSION['unread_notifications'] ?? 0;

// حساب حالة اليوم
$todayStatus = 'pending';
$todayStatusText = 'لم يتم التسجيل';
$todayStatusClass = 'warning';
$todayStatusIcon = 'bi-clock';

if ($todayAttendance) {
    $todayStatus = $todayAttendance['status'] ?? 'present';
    
    switch ($todayStatus) {
        case 'checked_in':
            $todayStatusText = 'تم تسجيل الحضور';
            $todayStatusClass = 'success';
            $todayStatusIcon = 'bi-check-circle-fill';
            break;
        case 'present':
            $todayStatusText = 'يوم مكتمل';
            $todayStatusClass = 'success';
            $todayStatusIcon = 'bi-check-circle-fill';
            break;
        case 'late':
            $todayStatusText = 'متأخر ' . ($todayAttendance['late_minutes'] ?? 0) . ' دقيقة';
            $todayStatusClass = 'warning';
            $todayStatusIcon = 'bi-exclamation-triangle-fill';
            break;
        case 'absent':
            $todayStatusText = 'غياب';
            $todayStatusClass = 'danger';
            $todayStatusIcon = 'bi-x-circle-fill';
            break;
        case 'on_leave':
            $todayStatusText = 'إجازة';
            $todayStatusClass = 'info';
            $todayStatusIcon = 'bi-calendar-x';
            break;
        case 'holiday':
        case 'weekend':
            $todayStatusText = 'عطلة';
            $todayStatusClass = 'secondary';
            $todayStatusIcon = 'bi-calendar-heart';
            break;
    }
}

// التحية
$hour = (int)date('H');
if ($hour >= 5 && $hour < 12) {
    $greeting = 'صباح الخير';
    $greetingIcon = 'bi-sun-fill';
    $greetingColor = '#ffa502';
} elseif ($hour >= 12 && $hour < 17) {
    $greeting = 'مساء الخير';
    $greetingIcon = 'bi-brightness-high-fill';
    $greetingColor = '#ff7f50';
} else {
    $greeting = 'مساء النور';
    $greetingIcon = 'bi-moon-stars-fill';
    $greetingColor = '#a29bfe';
}

// إعدادات الصفحة
$pageTitle = 'الرئيسية';
$pageDescription = 'لوحة التحكم';
$currentPage = 'index';

include INCLUDES_PATH . '/header.php';
?>

<style>
.dashboard-greeting {
    background: linear-gradient(135deg, var(--sarh-primary) 0%, #ffa040 50%, var(--sarh-primary-light) 100%);
    border-radius: 24px;
    padding: 1.75rem;
    color: white;
    position: relative;
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.dashboard-greeting::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -30%;
    width: 80%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
    transform: rotate(-15deg);
}
.greeting-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}
.greeting-name {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}
.greeting-role {
    opacity: 0.85;
    font-size: 0.9rem;
}
.time-display {
    text-align: left;
}
.time-display .time {
    font-size: 2.5rem;
    font-weight: 800;
    line-height: 1;
}
.time-display .date {
    opacity: 0.75;
    font-size: 0.85rem;
}

.status-card {
    background: white;
    border-radius: 16px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}
.status-indicator {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}
@media (min-width: 768px) {
    .stat-grid { grid-template-columns: repeat(4, 1fr); }
}
.stat-box {
    background: white;
    border-radius: 16px;
    padding: 1.25rem;
    text-align: center;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    transition: transform 0.2s;
}
.stat-box:hover {
    transform: translateY(-3px);
}
.stat-box .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.75rem;
    font-size: 1.25rem;
}
.stat-box .stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1.2;
}
.stat-box .stat-label {
    font-size: 0.8rem;
    color: var(--sarh-gray);
    margin-top: 0.25rem;
}

.quick-links {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    margin-bottom: 1.5rem;
}
.quick-links h6 {
    font-weight: 700;
    margin-bottom: 1rem;
    color: var(--sarh-dark);
}
.quick-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}
@media (min-width: 768px) {
    .quick-grid { grid-template-columns: repeat(6, 1fr); }
}
.quick-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    padding: 1rem 0.5rem;
    border-radius: 16px;
    background: #f8f9fa;
    transition: all 0.2s;
    color: var(--sarh-dark);
}
.quick-link:hover {
    background: var(--sarh-primary);
    color: white;
    transform: translateY(-3px);
}
.quick-link:hover .ql-icon {
    background: rgba(255,255,255,0.2);
    color: white;
}
.quick-link .ql-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    margin-bottom: 0.5rem;
    transition: all 0.2s;
}
.quick-link .ql-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
}
.quick-link .ql-badge {
    position: absolute;
    top: 0;
    right: 0;
    transform: translate(25%, -25%);
}

.work-info {
    background: white;
    border-radius: 16px;
    padding: 1.25rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}
.work-info-item {
    text-align: center;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 12px;
}
.work-info-item i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}
.work-info-item .value {
    font-weight: 700;
    font-size: 1rem;
}
.work-info-item .label {
    font-size: 0.7rem;
    color: var(--sarh-gray);
}
</style>

<!-- شعارات عائمة في الخلفية -->
<div class="floating-logos-container">
    <div class="floating-logo sarh-logo logo-md">
        <img src="<?= asset('images/logo.png') ?>" alt="" style="width:100%;height:100%;object-fit:contain;opacity:0.3;">
    </div>
    <div class="floating-logo sarh-logo logo-sm">
        <img src="<?= asset('images/logo.png') ?>" alt="" style="width:100%;height:100%;object-fit:contain;opacity:0.2;">
    </div>
    <div class="floating-logo sarh-logo logo-lg">
        <img src="<?= asset('images/logo.png') ?>" alt="" style="width:100%;height:100%;object-fit:contain;opacity:0.15;">
    </div>
    <div class="floating-logo sarh-logo logo-sm">
        <img src="<?= asset('images/logo.png') ?>" alt="" style="width:100%;height:100%;object-fit:contain;opacity:0.25;">
    </div>
    <div class="floating-logo sarh-logo logo-md">
        <img src="<?= asset('images/logo.png') ?>" alt="" style="width:100%;height:100%;object-fit:contain;opacity:0.1;">
    </div>
</div>

<div class="container py-3">
    
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- بطاقة الترحيب -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="dashboard-greeting fade-in">
        <div class="row align-items-center position-relative">
            <div class="col">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="sarh-logo logo-sm logo-rotate-swing" style="filter: brightness(0) invert(1);">
                        <img src="<?= asset('images/logo.png') ?>" alt="" style="width:100%;height:100%;object-fit:contain;">
                    </span>
                    <div class="greeting-icon" style="color: <?= $greetingColor ?>">
                        <i class="bi <?= $greetingIcon ?>"></i>
                    </div>
                </div>
                <div class="greeting-name"><?= $greeting ?>، <?= e($userName) ?></div>
                <div class="greeting-role">
                    <span class="badge bg-white bg-opacity-25 me-1">
                        <i class="<?= e($_SESSION['role_icon'] ?? 'bi-person') ?> me-1"></i>
                        <?= e($_SESSION['role_name'] ?? 'موظف') ?>
                    </span>
                    <span class="badge bg-white bg-opacity-10">
                        <i class="bi bi-building me-1"></i>
                        <?= e($_SESSION['branch_name'] ?? 'الرئيسي') ?>
                    </span>
                </div>
            </div>
            <div class="col-auto time-display">
                <div class="time" id="liveTime"><?= date('H:i') ?></div>
                <div class="date"><?= format_arabic_date(date('Y-m-d'), false) ?></div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- حالة اليوم -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="status-card fade-in" style="animation-delay: 0.1s;">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="mb-0 fw-bold">
                <i class="bi bi-calendar-check text-primary me-2"></i>
                حالة اليوم
            </h6>
            <span class="badge bg-<?= $todayStatusClass ?>"><?= $todayStatusText ?></span>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <div class="status-indicator bg-<?= $todayStatusClass ?> bg-opacity-10 text-<?= $todayStatusClass ?>">
                <i class="bi <?= $todayStatusIcon ?>"></i>
            </div>
            <div class="flex-grow-1">
                <?php if ($todayAttendance && $todayAttendance['check_in_time']): ?>
                <div class="fw-bold text-<?= $todayStatusClass ?>">
                    <?= $todayAttendance['check_out_time'] ? 'يوم مكتمل' : 'في العمل حالياً' ?>
                </div>
                <small class="text-muted">
                    <i class="bi bi-box-arrow-in-left me-1"></i>
                    الدخول: <?= date('h:i A', strtotime($todayAttendance['check_in_time'])) ?>
                    <?php if ($todayAttendance['check_out_time']): ?>
                    <span class="mx-1">•</span>
                    <i class="bi bi-box-arrow-right me-1"></i>
                    الخروج: <?= date('h:i A', strtotime($todayAttendance['check_out_time'])) ?>
                    <?php endif; ?>
                </small>
                <?php else: ?>
                <div class="fw-bold text-warning">لم يتم تسجيل الحضور</div>
                <small class="text-muted">
                    <?php if (($workSettings['attendance_mode'] ?? '') === 'unrestricted'): ?>
                        <span class="text-success"><i class="bi bi-unlock me-1"></i>حضور غير مشروط - سجّل في أي وقت</span>
                    <?php else: ?>
                        بداية الدوام: <?= $workSettings['work_start'] ?? '08:00' ?>
                        <span class="mx-1">•</span>
                        آخر موعد: <?= $workSettings['lock_time'] ?? '08:15' ?>
                    <?php endif; ?>
                </small>
                <?php endif; ?>
            </div>
            <?php if (!$todayAttendance || !$todayAttendance['check_in_time']): ?>
            <a href="<?= url('attendance.php') ?>" class="btn btn-success px-4">
                <i class="bi bi-qr-code-scan me-1"></i>
                سجل الآن
            </a>
            <?php elseif (!$todayAttendance['check_out_time']): ?>
            <a href="<?= url('attendance.php') ?>" class="btn btn-outline-danger">
                <i class="bi bi-box-arrow-left me-1"></i>
                انصراف
            </a>
            <?php else: ?>
            <span class="badge bg-success px-3 py-2">
                <i class="bi bi-check-circle me-1"></i>
                تم تسجيل اليوم
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- إحصائيات سريعة -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="stat-grid fade-in" style="animation-delay: 0.15s;">
        <div class="stat-box">
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-star-fill"></i>
            </div>
            <div class="stat-value text-warning"><?= number_format($_SESSION['current_points'] ?? 0) ?></div>
            <div class="stat-label">نقاطي</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-calendar-check-fill"></i>
            </div>
            <div class="stat-value text-success"><?= (int)($attendanceStats['present_days'] ?? 0) ?></div>
            <div class="stat-label">أيام الحضور</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-value text-danger"><?= (int)($attendanceStats['total_late_minutes'] ?? 0) ?></div>
            <div class="stat-label">دقائق التأخير</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-graph-down-arrow"></i>
            </div>
            <div class="stat-value text-info"><?= (int)($attendanceStats['total_penalty_points'] ?? 0) ?></div>
            <div class="stat-label">نقاط الخصم</div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- الوصول السريع -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="quick-links fade-in" style="animation-delay: 0.2s;">
        <h6>
            <i class="bi bi-lightning-charge-fill text-warning me-2"></i>
            وصول سريع
        </h6>
        <div class="quick-grid">
            <!-- تسجيل الحضور - مع شعار متحرك -->
            <a href="<?= url('attendance.php') ?>" class="quick-link position-relative">
                <div class="ql-icon bg-success bg-opacity-10 text-success">
                    <span class="sarh-logo logo-sm logo-heartbeat">
                        <img src="<?= asset('images/logo.png') ?>" alt="صرح" style="width:100%;height:100%;object-fit:contain;">
                    </span>
                </div>
                <span class="ql-label">تسجيل حضور</span>
            </a>
            
            <!-- 📊 مركز تحليل الأداء -->
            <a href="<?= url('dashboard/arena.php') ?>" class="quick-link">
                <div class="ql-icon" style="background: linear-gradient(135deg, rgba(255,215,0,0.15), rgba(255,102,0,0.15)); color: #ffd700;">
                    <i class="bi bi-graph-up"></i>
                </div>
                <span class="ql-label">تحليل الأداء</span>
            </a>
            
            <?php if ($roleLevel > 1): ?>
            <!-- الفريق -->
            <a href="<?= url('employees.php') ?>" class="quick-link">
                <div class="ql-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
                <span class="ql-label">الفريق</span>
            </a>
            <?php endif; ?>
            
            <!-- البحث -->
            <a href="<?= url('search.php') ?>" class="quick-link">
                <div class="ql-icon bg-secondary bg-opacity-10 text-secondary">
                    <i class="bi bi-search"></i>
                </div>
                <span class="ql-label">البحث</span>
            </a>
            
            <!-- الإشعارات -->
            <a href="<?= url('notifications.php') ?>" class="quick-link position-relative">
                <div class="ql-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-bell-fill"></i>
                </div>
                <span class="ql-label">الإشعارات</span>
                <?php if ($unreadNotifications > 0): ?>
                <span class="ql-badge badge bg-danger rounded-pill"><?= $unreadNotifications > 99 ? '99+' : $unreadNotifications ?></span>
                <?php endif; ?>
            </a>
            
            <?php if ($roleLevel > 2): ?>
            <!-- التقارير -->
            <a href="<?= url('reports.php') ?>" class="quick-link">
                <div class="ql-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-bar-chart-line-fill"></i>
                </div>
                <span class="ql-label">التقارير</span>
            </a>
            <?php endif; ?>
            
            <!-- الإعدادات -->
            <a href="<?= url('settings.php') ?>" class="quick-link">
                <div class="ql-icon bg-dark bg-opacity-10 text-dark">
                    <i class="bi bi-gear-fill"></i>
                </div>
                <span class="ql-label">الإعدادات</span>
            </a>
        </div>
    </div>

    <?php if ($roleLevel >= 5): ?>
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- أدوات الإدارة -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="quick-links fade-in" style="animation-delay: 0.25s;">
        <h6>
            <i class="bi bi-shield-lock-fill text-danger me-2"></i>
            أدوات الإدارة
        </h6>
        <div class="quick-grid">
            <a href="<?= url('team-attendance.php') ?>" class="quick-link">
                <div class="ql-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-calendar3-week"></i>
                </div>
                <span class="ql-label">حضور الفريق</span>
            </a>
            
            <a href="<?= url('admin/management.php') ?>" class="quick-link">
                <div class="ql-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-building-gear"></i>
                </div>
                <span class="ql-label">مركز الإدارة</span>
            </a>
            
            <a href="<?= url('admin/universal_manager.php') ?>" class="quick-link">
                <div class="ql-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-database-gear"></i>
                </div>
                <span class="ql-label">المدير العام</span>
            </a>
            
            <?php if ($roleLevel >= 8): ?>
            <a href="<?= url('admin/profiles.php') ?>" class="quick-link">
                <div class="ql-icon bg-purple" style="background:rgba(155,89,182,0.1);color:#9b59b6;">
                    <i class="bi bi-incognito"></i>
                </div>
                <span class="ql-label">الملفات النفسية</span>
            </a>
            
            <a href="<?= url('admin/traps.php') ?>" class="quick-link">
                <div class="ql-icon" style="background:rgba(108,92,231,0.1);color:#6c5ce7;">
                    <i class="bi bi-joystick"></i>
                </div>
                <span class="ql-label">إدارة الفخاخ</span>
            </a>
            
            <a href="<?= url('admin/attendance-settings.php') ?>" class="quick-link">
                <div class="ql-icon" style="background:rgba(0,188,212,0.1);color:#00bcd4;">
                    <i class="bi bi-clock-history"></i>
                </div>
                <span class="ql-label">إعدادات الحضور</span>
            </a>
            
            <a href="<?= url('admin/employee-schedules.php') ?>" class="quick-link">
                <div class="ql-icon" style="background:rgba(255,111,0,0.1);color:#ff6f00;">
                    <i class="bi bi-calendar-week"></i>
                </div>
                <span class="ql-label">جداول الموظفين</span>
            </a>
            
            <a href="<?= url('admin/live-map.php') ?>" class="quick-link">
                <div class="ql-icon" style="background:rgba(76,175,80,0.1);color:#4caf50;">
                    <i class="bi bi-radar"></i>
                </div>
                <span class="ql-label">الخريطة الحية</span>
            </a>
            
            <a href="<?= url('activity-log.php') ?>" class="quick-link">
                <div class="ql-icon bg-secondary bg-opacity-10 text-secondary">
                    <i class="bi bi-clock-history"></i>
                </div>
                <span class="ql-label">سجل النشاط</span>
            </a>
            
            <a href="<?= url('secret_report.php') ?>" class="quick-link">
                <div class="ql-icon" style="background:rgba(231,76,60,0.1);color:#e74c3c;">
                    <i class="bi bi-file-earmark-lock"></i>
                </div>
                <span class="ql-label">التقرير السري</span>
            </a>
            <?php endif; ?>
            
            <?php if ($roleLevel >= 10): ?>
            <!-- 🛠️ أدوات قاعدة البيانات - للمدير العام فقط -->
            <a href="<?= url('admin/db-tools.php') ?>" class="quick-link">
                <div class="ql-icon" style="background:rgba(255,0,64,0.15);color:#ff0040;">
                    <i class="bi bi-tools"></i>
                </div>
                <span class="ql-label">أدوات DB 🛠️</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- معلومات الدوام -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="work-info fade-in" style="animation-delay: 0.3s;">
        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
            <span class="sarh-logo logo-xs logo-rotate-slow" style="opacity: 0.7;">
                <img src="<?= asset('images/logo.png') ?>" alt="" style="width:100%;height:100%;object-fit:contain;">
            </span>
            <i class="bi bi-info-circle text-info"></i>
            معلومات الدوام
        </h6>
        <div class="row g-2">
            <div class="col-6 col-md-3">
                <div class="work-info-item">
                    <i class="bi bi-sunrise text-warning d-block"></i>
                    <div class="value"><?= $workSettings['work_start'] ?? '06:00' ?></div>
                    <div class="label">بداية الدوام</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="work-info-item">
                    <i class="bi bi-sunset text-orange d-block" style="color:#fd7e14;"></i>
                    <div class="value"><?= $workSettings['work_end'] ?? '14:00' ?></div>
                    <div class="label">نهاية الدوام</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="work-info-item">
                    <i class="bi bi-hourglass-split text-primary d-block"></i>
                    <div class="value"><?= $workSettings['grace_period_minutes'] ?? 15 ?> د</div>
                    <div class="label">فترة السماح</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="work-info-item">
                    <i class="bi bi-geo-alt text-danger d-block"></i>
                    <div class="value"><?= $_SESSION['branch_geofence_radius'] ?? 100 ?> م</div>
                    <div class="label">نطاق التسجيل</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- زر الإيقاف الدائم لحساب ساعات العمل -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <button id="stop-tracking-btn" class="stop-tracking-btn" style="display:none;">
        <i class="bi bi-stop-circle-fill"></i>
        <span>إيقاف حساب ساعات العمل</span>
    </button>
    
    <!-- عنصر حالة حساب ساعات العمل (اختياري) -->
    <div id="tracking-status" style="display:none; text-align:center; padding:10px; color:#666; font-size:0.9rem;"></div>
    
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- Styles for Stop Button -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<style>
.stop-tracking-btn {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999;
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    border: none;
    border-radius: 50px;
    padding: 12px 24px;
    font-size: 1rem;
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.stop-tracking-btn:hover {
    transform: translateX(-50%) translateY(-2px);
    box-shadow: 0 6px 20px rgba(231, 76, 60, 0.5);
}

.stop-tracking-btn:active {
    transform: translateX(-50%) translateY(0);
}

.stop-tracking-btn i {
    font-size: 1.2rem;
}
</style>

<script>
// ═══════════════════════════════════════════════════════════════════════════
// Rights Protection Modal - Show on page load
// ═══════════════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function() {
    // Show rights protection modal once per session
    if (!sessionStorage.getItem('sarh_rights_modal_shown')) {
        Swal.fire({
            icon: 'shield',
            title: 'تنبيه هام: سياسة حساب ساعات العمل والخصوصية',
            html: `
                <div style="text-align: right; line-height: 1.8; padding: 10px;">
                    <p>هذا البرنامج مصمم لحفظ حقوق الموظف وصاحب العمل من خلال توثيق ساعات العمل بدقة.</p>
                    
                    <div style="margin-top: 20px;">
                        <strong>🕒 نظام الراحة التلقائي:</strong>
                        <p style="margin-top: 10px; color: #666;">
                            لضمان خصوصيتك التامة، <strong>يتوقف النظام عن حساب ساعات العمل تلقائياً من الساعة 10:00 ليلاً حتى 7:00 صباحاً</strong>. 
                            لن يتم تسجيل أي بيانات خلال هذه الفترة.
                        </p>
                    </div>
                    
                    <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 8px; border-right: 4px solid #ffc107;">
                        <strong>⚠️ عند انتهاء دوامك:</strong>
                        <p style="margin-top: 10px; color: #856404;">
                            يفضل ضغط زر <strong>'إيقاف حساب ساعات العمل'</strong> الموجود أسفل الشاشة عند مغادرتك العمل لإيقاف استهلاك البطارية فوراً.
                        </p>
                    </div>
                </div>
            `,
            confirmButtonText: 'فهمت',
            confirmButtonColor: '#ff6f00',
            customClass: {
                popup: 'rtl-alert',
                title: 'text-start'
            },
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(() => {
            sessionStorage.setItem('sarh_rights_modal_shown', 'true');
        });
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// Manual Kill Switch Button
// ═══════════════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function() {
    const stopBtn = document.getElementById('stop-tracking-btn');
    
    if (stopBtn) {
        // Check if tracking is active (will be set by SmartTracker when it starts)
        // Button visibility is managed by SmartTracker.showStopButton() / hideStopButton()
        
        stopBtn.addEventListener('click', function() {
            Swal.fire({
                icon: 'warning',
                title: 'هل أنت متأكد؟',
                html: `
                    <div style="text-align: right; line-height: 1.8;">
                        <p>سيتم إيقاف احتساب ساعات العمل فوراً.</p>
                        <p><strong>هل غادرت العمل فعلاً؟</strong></p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'نعم، أوقف حساب ساعات العمل',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#6c757d',
                customClass: {
                    popup: 'rtl-alert'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Stop tracking
                    if (window.smartTracker) {
                        window.smartTracker.stop();
                        stopBtn.style.display = 'none';
                    }
                    
                    // Send offline signal
                    if (SARH && SARH.csrfToken) {
                        fetch('/app/api/heartbeat.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': SARH.csrfToken
                            },
                            body: JSON.stringify({
                                offline: true
                            })
                        }).catch(() => {});
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'تم إيقاف حساب ساعات العمل',
                        text: 'تم إيقاف احتساب ساعات العمل بنجاح.',
                        confirmButtonText: 'حسناً',
                        confirmButtonColor: '#ff6f00'
                    });
                }
            });
        });
    }
});

// تحديث الوقت
function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    const el = document.getElementById('liveTime');
    if (el) el.textContent = h + ':' + m;
}
setInterval(updateClock, 1000);
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
