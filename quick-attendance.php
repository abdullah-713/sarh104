<?php
/**
 * صفحة تسجيل الحضور السريع
 * Quick Attendance Page - Works with all attendance modes
 */

require_once 'config/app.php';
require_once 'includes/functions.php';

check_login();

$userId = $_SESSION['user_id'] ?? 0;
$message = '';
$messageType = '';

// جلب إعدادات الموظف
$employeeSchedule = Database::fetchOne("
    SELECT es.*, b.name as branch_name, b.latitude as branch_lat, b.longitude as branch_lng
    FROM employee_schedules es
    LEFT JOIN users u ON es.user_id = u.id
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE es.user_id = ? AND es.is_active = 1
      AND (es.effective_from IS NULL OR es.effective_from <= CURDATE())
      AND (es.effective_until IS NULL OR es.effective_until >= CURDATE())
", [$userId]);

$attendanceMode = $employeeSchedule['attendance_mode'] ?? 'time_and_location';
$remoteAllowed = !empty($employeeSchedule['remote_checkin_allowed']);
$isFlexible = !empty($employeeSchedule['is_flexible_hours']);

// جلب حضور اليوم
$todayAttendance = Database::fetchOne("
    SELECT * FROM attendance WHERE user_id = ? AND date = CURDATE()
", [$userId]);

// معالجة تسجيل الحضور
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $latitude = floatval($_POST['latitude'] ?? 0);
    $longitude = floatval($_POST['longitude'] ?? 0);
    
    try {
        if ($action === 'checkin' && !$todayAttendance) {
            // تسجيل حضور جديد
            $branchId = $_SESSION['branch_id'] ?? null;
            Database::query("
                INSERT INTO attendance (user_id, branch_id, date, check_in_time, check_in_lat, check_in_lng, status, created_at)
                VALUES (?, ?, CURDATE(), CURTIME(), ?, ?, 'present', NOW())
            ", [$userId, $branchId, $latitude, $longitude]);
            
            $message = '✅ تم تسجيل الحضور بنجاح! الساعة: ' . date('H:i');
            $messageType = 'success';
            
            // تحديث البيانات
            $todayAttendance = Database::fetchOne("SELECT * FROM attendance WHERE user_id = ? AND date = CURDATE()", [$userId]);
            
        } elseif ($action === 'checkout' && $todayAttendance && empty($todayAttendance['check_out_time'])) {
            // تسجيل انصراف
            Database::query("
                UPDATE attendance 
                SET check_out_time = CURTIME(), 
                    check_out_lat = ?, 
                    check_out_lng = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [$latitude, $longitude, $todayAttendance['id']]);
            
            $message = '✅ تم تسجيل الانصراف بنجاح! الساعة: ' . date('H:i');
            $messageType = 'success';
            
            // تحديث البيانات
            $todayAttendance = Database::fetchOne("SELECT * FROM attendance WHERE user_id = ? AND date = CURDATE()", [$userId]);
        }
    } catch (Exception $e) {
        $message = '❌ خطأ: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// تحديد الحالة
$canCheckin = !$todayAttendance;
$canCheckout = $todayAttendance && empty($todayAttendance['check_out_time']);
$isDone = $todayAttendance && !empty($todayAttendance['check_out_time']);

// ترجمة نوع الحضور
$modeLabels = [
    'unrestricted' => ['label' => 'غير مشروط', 'icon' => 'unlock', 'color' => 'success'],
    'time_only' => ['label' => 'مشروط بالوقت', 'icon' => 'clock', 'color' => 'info'],
    'location_only' => ['label' => 'مشروط بالموقع', 'icon' => 'geo-alt', 'color' => 'warning'],
    'time_and_location' => ['label' => 'مشروط بالوقت والموقع', 'icon' => 'shield-check', 'color' => 'danger'],
];
$modeInfo = $modeLabels[$attendanceMode] ?? $modeLabels['time_and_location'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الحضور السريع</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #e65100;
        }
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .attendance-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 100%;
            overflow: hidden;
        }
        .card-header {
            background: var(--primary);
            color: #fff;
            padding: 30px;
            text-align: center;
        }
        .card-header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .time-display {
            font-size: 48px;
            font-weight: 700;
        }
        .card-body {
            padding: 30px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .btn-action {
            width: 100%;
            padding: 18px;
            font-size: 18px;
            font-weight: 700;
            border-radius: 15px;
            margin-bottom: 15px;
        }
        .btn-checkin {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            color: #fff;
        }
        .btn-checkout {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            border: none;
            color: #fff;
        }
        .btn-done {
            background: #6c757d;
            border: none;
            color: #fff;
        }
        .info-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .location-status {
            text-align: center;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .spinner-border {
            width: 1.5rem;
            height: 1.5rem;
        }
        #locationError {
            display: none;
        }
    </style>
</head>
<body>
    <div class="attendance-card">
        <div class="card-header">
            <h1><i class="bi bi-clock-history me-2"></i>تسجيل الحضور</h1>
            <div class="time-display" id="currentTime"><?= date('H:i') ?></div>
            <div><?= date('Y-m-d') ?> | <?= date('l') ?></div>
        </div>
        
        <div class="card-body">
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- نوع الحضور -->
            <div class="text-center mb-3">
                <span class="status-badge bg-<?= $modeInfo['color'] ?> bg-opacity-10 text-<?= $modeInfo['color'] ?>">
                    <i class="bi bi-<?= $modeInfo['icon'] ?>"></i>
                    <?= $modeInfo['label'] ?>
                </span>
            </div>
            
            <!-- حالة الموقع -->
            <div class="location-status bg-light" id="locationStatus">
                <span class="spinner-border text-primary me-2"></span>
                جاري تحديد الموقع...
            </div>
            
            <div class="alert alert-danger" id="locationError">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <span id="locationErrorText">لم يتم تحديد الموقع</span>
                <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="requestLocation()">
                    <i class="bi bi-geo-alt"></i> طلب الموقع مرة أخرى
                </button>
            </div>
            
            <!-- نموذج التسجيل -->
            <form method="POST" id="attendanceForm">
                <input type="hidden" name="latitude" id="latitude" value="0">
                <input type="hidden" name="longitude" id="longitude" value="0">
                
                <?php if ($canCheckin): ?>
                <button type="submit" name="action" value="checkin" class="btn btn-action btn-checkin" id="checkinBtn" disabled>
                    <i class="bi bi-box-arrow-in-left me-2"></i>
                    تسجيل الحضور
                </button>
                <?php elseif ($canCheckout): ?>
                <div class="alert alert-success mb-3">
                    <i class="bi bi-check-circle me-2"></i>
                    تم تسجيل الحضور الساعة: <strong><?= substr($todayAttendance['check_in_time'], 0, 5) ?></strong>
                </div>
                <button type="submit" name="action" value="checkout" class="btn btn-action btn-checkout" id="checkoutBtn" disabled>
                    <i class="bi bi-box-arrow-right me-2"></i>
                    تسجيل الانصراف
                </button>
                <?php else: ?>
                <div class="alert alert-info mb-3">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>تم تسجيل اليوم بالكامل</strong><br>
                    الحضور: <?= substr($todayAttendance['check_in_time'], 0, 5) ?> | 
                    الانصراف: <?= substr($todayAttendance['check_out_time'], 0, 5) ?>
                </div>
                <button type="button" class="btn btn-action btn-done" disabled>
                    <i class="bi bi-check-all me-2"></i>
                    تم التسجيل ✓
                </button>
                <?php endif; ?>
            </form>
            
            <!-- معلومات إضافية -->
            <div class="info-box">
                <div class="info-item">
                    <span><i class="bi bi-person me-2"></i>الموظف</span>
                    <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'غير معروف') ?></strong>
                </div>
                <?php if ($employeeSchedule): ?>
                <div class="info-item">
                    <span><i class="bi bi-clock me-2"></i>الدوام</span>
                    <strong><?= substr($employeeSchedule['work_start_time'], 0, 5) ?> - <?= substr($employeeSchedule['work_end_time'], 0, 5) ?></strong>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <span><i class="bi bi-geo me-2"></i>الإحداثيات</span>
                    <strong id="coordsDisplay">---</strong>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-right me-1"></i>
                    العودة للرئيسية
                </a>
            </div>
        </div>
    </div>

    <script>
        // تحديث الساعة
        setInterval(() => {
            const now = new Date();
            document.getElementById('currentTime').textContent = 
                now.toLocaleTimeString('ar-SA', {hour: '2-digit', minute: '2-digit'});
        }, 1000);
        
        // طلب الموقع
        function requestLocation() {
            const statusEl = document.getElementById('locationStatus');
            const errorEl = document.getElementById('locationError');
            const checkinBtn = document.getElementById('checkinBtn');
            const checkoutBtn = document.getElementById('checkoutBtn');
            const coordsDisplay = document.getElementById('coordsDisplay');
            
            statusEl.style.display = 'block';
            errorEl.style.display = 'none';
            statusEl.innerHTML = '<span class="spinner-border text-primary me-2"></span> جاري تحديد الموقع...';
            
            if (!navigator.geolocation) {
                showError('المتصفح لا يدعم تحديد الموقع');
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                // Success
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const acc = Math.round(position.coords.accuracy);
                    
                    document.getElementById('latitude').value = lat;
                    document.getElementById('longitude').value = lng;
                    
                    statusEl.innerHTML = `
                        <i class="bi bi-check-circle text-success me-2"></i>
                        <span class="text-success">تم تحديد الموقع بدقة ${acc} متر</span>
                    `;
                    statusEl.className = 'location-status bg-success bg-opacity-10';
                    
                    coordsDisplay.textContent = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                    
                    // تفعيل الأزرار
                    if (checkinBtn) checkinBtn.disabled = false;
                    if (checkoutBtn) checkoutBtn.disabled = false;
                },
                // Error
                (error) => {
                    let msg = 'فشل تحديد الموقع';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            msg = 'تم رفض إذن الموقع. يرجى السماح بالوصول للموقع من إعدادات المتصفح.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            msg = 'معلومات الموقع غير متاحة';
                            break;
                        case error.TIMEOUT:
                            msg = 'انتهت مهلة طلب الموقع';
                            break;
                    }
                    showError(msg);
                },
                // Options
                {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0
                }
            );
        }
        
        function showError(msg) {
            const statusEl = document.getElementById('locationStatus');
            const errorEl = document.getElementById('locationError');
            const errorText = document.getElementById('locationErrorText');
            
            statusEl.style.display = 'none';
            errorEl.style.display = 'block';
            errorText.textContent = msg;
        }
        
        // طلب الموقع عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', requestLocation);
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
