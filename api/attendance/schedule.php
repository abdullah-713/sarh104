<?php
/**
 * ========================================================================
 * API: جدول دوام الموظف
 * Employee Schedule API
 * ========================================================================
 * يرجع إعدادات الدوام الخاصة بالموظف مع حساب حالة زر الحضور/الانصراف
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../config/app.php';
require_once '../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مصرح']);
    exit;
}

$userId = current_user_id();
$action = $_GET['action'] ?? 'get';

try {
    switch ($action) {
        case 'get':
            // جلب إعدادات الدوام للمستخدم الحالي
            $schedule = getEmployeeSchedule($userId);
            $status = calculateAttendanceStatus($userId, $schedule);
            
            echo json_encode([
                'success' => true,
                'schedule' => $schedule,
                'status' => $status
            ]);
            break;
            
        case 'can_checkin':
            // التحقق من إمكانية تسجيل الحضور
            $schedule = getEmployeeSchedule($userId);
            $latitude = floatval($_GET['lat'] ?? 0);
            $longitude = floatval($_GET['lng'] ?? 0);
            
            $result = canEmployeeCheckIn($userId, $schedule, $latitude, $longitude);
            echo json_encode($result);
            break;
            
        case 'can_checkout':
            // التحقق من إمكانية تسجيل الانصراف
            $schedule = getEmployeeSchedule($userId);
            $result = canEmployeeCheckOut($userId, $schedule);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'إجراء غير معروف']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * جلب إعدادات دوام الموظف
 */
function getEmployeeSchedule(int $userId): array {
    // محاولة جلب الجدول المخصص
    $schedule = Database::fetchOne("
        SELECT es.*, b.name as branch_name, b.latitude as branch_lat, b.longitude as branch_lng
        FROM employee_schedules es
        LEFT JOIN users u ON es.user_id = u.id
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE es.user_id = ? 
          AND es.is_active = 1
          AND (es.effective_from IS NULL OR es.effective_from <= CURDATE())
          AND (es.effective_until IS NULL OR es.effective_until >= CURDATE())
    ", [$userId]);
    
    if ($schedule) {
        // تحويل JSON fields
        $schedule['working_days'] = json_decode($schedule['working_days'] ?? '[]', true);
        $schedule['allowed_branches'] = json_decode($schedule['allowed_branches'] ?? 'null', true);
        return $schedule;
    }
    
    // إرجاع الإعدادات الافتراضية من system_settings
    $defaults = [];
    $settings = Database::fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE setting_group = 'attendance'");
    foreach ($settings as $s) {
        $defaults[$s['setting_key']] = json_decode($s['setting_value'], true) ?? $s['setting_value'];
    }
    
    // جلب بيانات الفرع الافتراضي للمستخدم
    $userBranch = Database::fetchOne("
        SELECT b.id, b.name, b.latitude, b.longitude, b.geofence_radius
        FROM users u
        JOIN branches b ON u.branch_id = b.id
        WHERE u.id = ?
    ", [$userId]);
    
    return [
        'user_id' => $userId,
        'work_start_time' => $defaults['work_start_time'] ?? '08:00:00',
        'work_end_time' => $defaults['work_end_time'] ?? '17:00:00',
        'grace_period_minutes' => $defaults['grace_period_minutes'] ?? 15,
        'attendance_mode' => 'time_and_location', // الافتراضي: مشروط بالوقت والموقع
        'working_days' => [0, 1, 2, 3, 4], // الأحد - الخميس
        'allowed_branches' => $userBranch ? [$userBranch['id']] : null,
        'geofence_radius' => $userBranch['geofence_radius'] ?? 100,
        'branch_lat' => $userBranch['latitude'] ?? null,
        'branch_lng' => $userBranch['longitude'] ?? null,
        'branch_name' => $userBranch['name'] ?? null,
        'is_flexible_hours' => false,
        'early_checkin_minutes' => 30,
        'late_checkout_allowed' => true,
        'remote_checkin_allowed' => false,
        'overtime_allowed' => false,
        'late_penalty_per_minute' => $defaults['late_penalty_per_minute'] ?? 0.5,
        'is_default' => true
    ];
}

/**
 * حساب حالة الحضور الحالية
 */
function calculateAttendanceStatus(int $userId, array $schedule): array {
    $now = new DateTime();
    $today = $now->format('Y-m-d');
    $currentTime = $now->format('H:i:s');
    $currentDay = (int) $now->format('w'); // 0 = Sunday
    
    // التحقق من الحضور اليوم
    $todayRecord = Database::fetchOne("
        SELECT * FROM attendance 
        WHERE user_id = ? AND date = ?
    ", [$userId, $today]);
    
    $status = [
        'date' => $today,
        'current_time' => $currentTime,
        'current_day' => $currentDay,
        'is_working_day' => in_array($currentDay, $schedule['working_days'] ?? []),
        'has_checked_in' => !empty($todayRecord['check_in_time']),
        'has_checked_out' => !empty($todayRecord['check_out_time']),
        'check_in_time' => $todayRecord['check_in_time'] ?? null,
        'check_out_time' => $todayRecord['check_out_time'] ?? null,
        'attendance_mode' => $schedule['attendance_mode'] ?? 'time_and_location',
        'schedule_start' => $schedule['work_start_time'],
        'schedule_end' => $schedule['work_end_time'],
    ];
    
    // حساب أوقات السماح
    $workStart = new DateTime($today . ' ' . $schedule['work_start_time']);
    $workEnd = new DateTime($today . ' ' . $schedule['work_end_time']);
    $graceEnd = clone $workStart;
    $graceEnd->modify('+' . ($schedule['grace_period_minutes'] ?? 15) . ' minutes');
    
    // Check-in window: 1 hour before work_start to 1 hour after work_start
    $checkinWindowStart = clone $workStart;
    $checkinWindowStart->modify('-1 hour');
    $checkinWindowEnd = clone $workStart;
    $checkinWindowEnd->modify('+1 hour');
    
    // Check-out window: 1 hour before work_end to 1 hour after work_end
    $checkoutWindowStart = clone $workEnd;
    $checkoutWindowStart->modify('-1 hour');
    $checkoutWindowEnd = clone $workEnd;
    $checkoutWindowEnd->modify('+1 hour');
    
    $status['early_checkin_from'] = $checkinWindowStart->format('H:i');
    $status['checkin_window_end'] = $checkinWindowEnd->format('H:i');
    $status['checkout_window_start'] = $checkoutWindowStart->format('H:i');
    $status['checkout_window_end'] = $checkoutWindowEnd->format('H:i');
    $status['grace_period_until'] = $graceEnd->format('H:i');
    
    // حالة الوقت الحالي للحضور
    if ($now < $checkinWindowStart) {
        $status['time_status'] = 'too_early';
        $status['time_message'] = 'مبكر جداً للحضور';
    } elseif ($now >= $checkinWindowStart && $now < $workStart) {
        $status['time_status'] = 'early';
        $status['time_message'] = 'وقت الحضور المبكر';
    } elseif ($now >= $workStart && $now <= $graceEnd) {
        $status['time_status'] = 'on_time';
        $status['time_message'] = 'في الوقت المحدد';
    } elseif ($now > $graceEnd && $now <= $checkinWindowEnd) {
        $status['time_status'] = 'late';
        $diff = $now->diff($graceEnd);
        $lateMinutes = ($diff->h * 60) + $diff->i;
        $status['late_minutes'] = $lateMinutes;
        $status['time_message'] = "متأخر {$lateMinutes} دقيقة";
    } elseif ($now > $checkinWindowEnd && $now < $checkoutWindowStart) {
        $status['time_status'] = 'after_checkin_window';
        $status['time_message'] = 'انتهى وقت تسجيل الحضور';
    } else {
        $status['time_status'] = 'after_work';
        $status['time_message'] = 'بعد نهاية الدوام';
    }
    
    return $status;
}

/**
 * التحقق من إمكانية تسجيل الحضور
 */
function canEmployeeCheckIn(int $userId, array $schedule, float $latitude, float $longitude): array {
    $result = [
        'can_checkin' => false,
        'reasons' => [],
        'warnings' => []
    ];
    
    $status = calculateAttendanceStatus($userId, $schedule);
    
    // التحقق من عدم تسجيل الحضور مسبقاً
    if ($status['has_checked_in']) {
        $result['reasons'][] = 'تم تسجيل الحضور مسبقاً';
        return $result;
    }
    
    $mode = $schedule['attendance_mode'] ?? 'time_and_location';
    $strict_time_windows = (get_setting('strict_time_windows', 'true') === 'true');
    $enforce_time_windows = $strict_time_windows && $mode !== 'unrestricted';
    
    // نوع الحضور: غير مشروط
    if ($mode === 'unrestricted') {
        $result['can_checkin'] = true;
        return $result;
    }
    
    // التحقق من الوقت
    $timeOk = true;
    if ($mode === 'time_only' || $mode === 'time_and_location') {
        if (!$status['is_working_day']) {
            $result['reasons'][] = 'اليوم ليس من أيام العمل';
            $timeOk = false;
        } elseif ($status['time_status'] === 'too_early') {
            $result['reasons'][] = 'مبكر جداً - يبدأ الحضور الساعة ' . $status['early_checkin_from'];
            $timeOk = false;
        } elseif ($status['time_status'] === 'late' && !empty($status['late_minutes'])) {
            $result['warnings'][] = 'ستُسجل متأخراً ' . $status['late_minutes'] . ' دقيقة';
        } elseif ($enforce_time_windows && ($status['time_status'] === 'after_checkin_window' || $status['time_status'] === 'after_work')) {
            $result['reasons'][] = 'انتهى وقت تسجيل الحضور (ينتهي الساعة ' . $status['checkin_window_end'] . ')';
            $timeOk = false;
        }
    }
    
    // التحقق من الموقع
    $locationOk = true;
    if ($mode === 'location_only' || $mode === 'time_and_location') {
        // السماح بالحضور عن بُعد
        if (!empty($schedule['remote_checkin_allowed'])) {
            $locationOk = true;
        } else {
            // حساب المسافة
            $branchLat = floatval($schedule['branch_lat'] ?? 0);
            $branchLng = floatval($schedule['branch_lng'] ?? 0);
            $radius = intval($schedule['geofence_radius'] ?? 100);
            
            if ($branchLat && $branchLng && $latitude && $longitude) {
                $distance = haversineDistance($latitude, $longitude, $branchLat, $branchLng);
                
                if ($distance > $radius) {
                    $result['reasons'][] = "أنت بعيد عن الفرع ({$schedule['branch_name']}) بمسافة " . round($distance) . " متر";
                    $result['distance'] = round($distance);
                    $result['required_radius'] = $radius;
                    $locationOk = false;
                }
            } elseif (!$latitude || !$longitude) {
                $result['reasons'][] = 'لم يتم تحديد موقعك';
                $locationOk = false;
            }
        }
    }
    
    // تحديد النتيجة النهائية
    switch ($mode) {
        case 'time_only':
            $result['can_checkin'] = $timeOk;
            break;
        case 'location_only':
            $result['can_checkin'] = $locationOk;
            break;
        case 'time_and_location':
            $result['can_checkin'] = $timeOk && $locationOk;
            break;
    }
    
    return $result;
}

/**
 * التحقق من إمكانية تسجيل الانصراف
 */
function canEmployeeCheckOut(int $userId, array $schedule): array {
    $result = [
        'can_checkout' => false,
        'reasons' => [],
        'warnings' => []
    ];
    
    $status = calculateAttendanceStatus($userId, $schedule);
    
    // التحقق من تسجيل الحضور
    if (!$status['has_checked_in']) {
        $result['reasons'][] = 'لم يتم تسجيل الحضور بعد';
        return $result;
    }
    
    // التحقق من عدم تسجيل الانصراف مسبقاً
    if ($status['has_checked_out']) {
        $result['reasons'][] = 'تم تسجيل الانصراف مسبقاً';
        return $result;
    }
    
    $mode = $schedule['attendance_mode'] ?? 'time_and_location';
    $strict_time_windows = (get_setting('strict_time_windows', 'true') === 'true');
    $enforce_time_windows = $strict_time_windows && $mode !== 'unrestricted';
    
    $today = $status['date'] ?? date('Y-m-d');
    $workEnd = new DateTime($today . ' ' . ($schedule['work_end_time'] ?? '17:00:00'));
    $now = new DateTime();
    
    // Check-out window: 1 hour before work_end to 1 hour after work_end
    $checkoutWindowStart = clone $workEnd;
    $checkoutWindowStart->modify('-1 hour');
    $checkoutWindowEnd = clone $workEnd;
    $checkoutWindowEnd->modify('+1 hour');
    
    if ($enforce_time_windows) {
        if ($now < $checkoutWindowStart) {
            $result['reasons'][] = 'لا يمكن تسجيل الانصراف قبل الساعة ' . $checkoutWindowStart->format('H:i');
            return $result;
        }
        
        if ($now > $checkoutWindowEnd) {
            $result['reasons'][] = 'انتهى وقت تسجيل الانصراف (ينتهي الساعة ' . $checkoutWindowEnd->format('H:i') . ')';
            return $result;
        }
    }
    
    // التحقق من الحد الأدنى لساعات العمل
    if (!empty($schedule['min_working_hours'])) {
        $checkInTime = new DateTime($today . ' ' . $status['check_in_time']);
        $workedHours = ($now->getTimestamp() - $checkInTime->getTimestamp()) / 3600;
        
        if ($workedHours < floatval($schedule['min_working_hours'])) {
            $remaining = round(floatval($schedule['min_working_hours']) - $workedHours, 1);
            if ($enforce_time_windows) {
                $result['reasons'][] = "لا يمكنك الانصراف قبل إكمال الحد الأدنى ({$schedule['min_working_hours']} ساعة)";
                $result['warnings'][] = "متبقي {$remaining} ساعة";
                return $result;
            }
            
            $result['warnings'][] = "عملت " . round($workedHours, 1) . " ساعة فقط. الحد الأدنى: {$schedule['min_working_hours']} ساعة";
        }
    }
    
    // التحقق من نهاية الدوام
    if ($status['time_status'] !== 'after_work') {
        $result['warnings'][] = 'لم ينتهِ وقت الدوام بعد';
    }
    
    $result['can_checkout'] = true;
    return $result;
}

/**
 * حساب المسافة بين نقطتين (Haversine)
 */
function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $earthRadius = 6371000; // بالأمتار
    
    $lat1Rad = deg2rad($lat1);
    $lat2Rad = deg2rad($lat2);
    $deltaLat = deg2rad($lat2 - $lat1);
    $deltaLng = deg2rad($lng2 - $lng1);
    
    $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
         cos($lat1Rad) * cos($lat2Rad) *
         sin($deltaLng / 2) * sin($deltaLng / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earthRadius * $c;
}
