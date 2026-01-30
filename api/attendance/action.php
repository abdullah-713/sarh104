<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - ATTENDANCE ACTION API                                ║
 * ║           نقطة نهاية تسجيل الحضور والانصراف                                    ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Version: 3.5.0                                                              ║
 * ║  Endpoint: POST /api/attendance/action.php                                   ║
 * ║  Features:                                                                   ║
 * ║  - Server-side geofence verification (Haversine)                            ║
 * ║  - Late/Early penalty calculation                                           ║
 * ║  - Overtime bonus calculation                                               ║
 * ║  - Activity logging                                                         ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');

// Load dependencies
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

// ═══════════════════════════════════════════════════════════════════════════════
// AUTHENTICATION CHECK
// ═══════════════════════════════════════════════════════════════════════════════

if (!is_logged_in()) {
    http_response_code(401);
    die(json_encode([
        'success' => false,
        'error' => 'unauthorized',
        'message' => 'غير مصرح بالوصول'
    ], JSON_UNESCAPED_UNICODE));
}

// ═══════════════════════════════════════════════════════════════════════════════
// CSRF VERIFICATION
// ═══════════════════════════════════════════════════════════════════════════════

$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (empty($csrf_token) || !verify_csrf($csrf_token)) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'error' => 'csrf_invalid',
        'message' => 'رمز الأمان غير صالح'
    ], JSON_UNESCAPED_UNICODE));
}

// ═══════════════════════════════════════════════════════════════════════════════
// PARSE & VALIDATE INPUT
// ═══════════════════════════════════════════════════════════════════════════════

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'error' => 'invalid_input',
        'message' => 'البيانات المرسلة غير صالحة'
    ], JSON_UNESCAPED_UNICODE));
}

$action = trim($input['action'] ?? '');
$attendance_id = isset($input['attendance_id']) ? intval($input['attendance_id']) : null;
$latitude = isset($input['latitude']) ? floatval($input['latitude']) : null;
$longitude = isset($input['longitude']) ? floatval($input['longitude']) : null;
$accuracy = isset($input['accuracy']) ? floatval($input['accuracy']) : null;

// ═══════════════════════════════════════════════════════════════════════════════
// AUTO CHECK-IN SUPPORT - دعم التسجيل التلقائي
// ═══════════════════════════════════════════════════════════════════════════════
$auto_checkin = !empty($input['auto_checkin']);
$detected_branch_id = isset($input['detected_branch_id']) ? intval($input['detected_branch_id']) : null;

// ═══════════════════════════════════════════════════════════════════════════════
// SENSOR DATA - بيانات الحساسات لكشف المحاكيات
// ═══════════════════════════════════════════════════════════════════════════════
$sensors = $input['sensors'] ?? null;
$suspicion_score = 0;
$suspicion_flags = [];

if ($sensors) {
    // تحليل البطارية
    if (isset($sensors['battery'])) {
        $battery = $sensors['battery'];
        // بطارية 100% وغير متصلة = محاكي محتمل
        if ($battery['level'] === 100 && $battery['charging'] === false) {
            $suspicion_score += 25;
            $suspicion_flags[] = 'battery_always_full';
        }
    }
    
    // تحليل الحركة - جهاز ثابت تماماً
    if (isset($sensors['motion']['analysis'])) {
        $motion = $sensors['motion']['analysis'];
        if ($motion['hasMotion'] === false) {
            $suspicion_score += 30;
            $suspicion_flags[] = 'device_no_motion';
        }
    }
    
    // علامات الشك من الـ JS
    if (!empty($sensors['suspicionFlags'])) {
        $suspicion_flags = array_merge($suspicion_flags, $sensors['suspicionFlags']);
        $suspicion_score += $sensors['suspicionScore'] ?? 0;
    }
    
    // تسجيل في قاعدة البيانات إذا كان هناك شك
    if ($suspicion_score > 30) {
        try {
            Database::insert('emulator_detection_logs', [
                'user_id' => intval($_SESSION['user_id'] ?? 0),
                'suspicion_score' => $suspicion_score,
                'suspicion_flags' => json_encode($suspicion_flags, JSON_UNESCAPED_UNICODE),
                'battery_level' => $sensors['battery']['level'] ?? null,
                'has_motion' => $sensors['motion']['analysis']['hasMotion'] ?? null,
                'device_info' => json_encode($sensors['deviceInfo'] ?? [], JSON_UNESCAPED_UNICODE),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            // تجاهل أخطاء التسجيل - لا تعطل الحضور
            error_log('[SARH] Emulator log error: ' . $e->getMessage());
        }
    }
}

// Validate action type
if (!in_array($action, ['checkin', 'checkout'], true)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'error' => 'invalid_action',
        'message' => 'نوع العملية غير صالح'
    ], JSON_UNESCAPED_UNICODE));
}

// Validate coordinates
if ($latitude === null || $longitude === null) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'error' => 'missing_location',
        'message' => 'الموقع الجغرافي مطلوب'
    ], JSON_UNESCAPED_UNICODE));
}

// Validate coordinate ranges
if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'error' => 'invalid_coordinates',
        'message' => 'الإحداثيات غير صالحة'
    ], JSON_UNESCAPED_UNICODE));
}

// ═══════════════════════════════════════════════════════════════════════════════
// GET USER DATA
// ═══════════════════════════════════════════════════════════════════════════════

$user_id = intval($_SESSION['user_id'] ?? 0);
$branch_id = intval($_SESSION['branch_id'] ?? 0);

// Fetch full user data
$user = Database::fetchOne(
    "SELECT u.*, r.name AS role_name, r.role_level 
     FROM users u 
     LEFT JOIN roles r ON u.role_id = r.id 
     WHERE u.id = ? AND u.is_active = 1",
    [$user_id]
);

if (!$user) {
    http_response_code(404);
    die(json_encode([
        'success' => false,
        'error' => 'user_not_found',
        'message' => 'المستخدم غير موجود أو غير نشط'
    ], JSON_UNESCAPED_UNICODE));
}

// ═══════════════════════════════════════════════════════════════════════════════
// GET BRANCH DATA - دعم التسجيل من أي فرع
// ═══════════════════════════════════════════════════════════════════════════════

$branch = null;
$branch_settings = [];
$effective_branch_id = $branch_id;

// For auto check-in, use detected branch if provided
if ($auto_checkin && $detected_branch_id > 0) {
    $effective_branch_id = $detected_branch_id;
}

if ($effective_branch_id > 0) {
    $branch = Database::fetchOne(
        "SELECT * FROM branches WHERE id = ? AND is_active = 1",
        [$effective_branch_id]
    );
    
    if ($branch) {
        $branch_settings = json_decode($branch['settings'] ?? '{}', true) ?: [];
    }
}

// If auto-checkin and no specific branch, find nearest branch
if ($auto_checkin && !$branch && $latitude && $longitude) {
    $all_branches = Database::fetchAll("SELECT * FROM branches WHERE is_active = 1");
    $nearest_distance = PHP_FLOAT_MAX;
    
    foreach ($all_branches as $b) {
        $b_lat = floatval($b['latitude'] ?? 0);
        $b_lng = floatval($b['longitude'] ?? 0);
        $b_radius = floatval($b['geofence_radius'] ?? 100);
        
        if ($b_lat == 0 || $b_lng == 0) continue;
        
        $dist = haversineDistance($latitude, $longitude, $b_lat, $b_lng);
        
        // Check if within this branch's geofence
        if ($dist <= $b_radius + 25) { // 25m tolerance
            if ($dist < $nearest_distance) {
                $nearest_distance = $dist;
                $branch = $b;
                $effective_branch_id = intval($b['id']);
                $branch_settings = json_decode($b['settings'] ?? '{}', true) ?: [];
            }
        }
    }
}

// Note: Branch check will be done after checking attendance_mode
// For unrestricted mode, branch is not required

// ═══════════════════════════════════════════════════════════════════════════════
// GET EMPLOYEE SCHEDULE (جدول دوام الموظف المخصص)
// ═══════════════════════════════════════════════════════════════════════════════

$employeeSchedule = Database::fetchOne("
    SELECT attendance_mode, remote_checkin_allowed, geofence_radius as custom_radius,
           work_start_time, work_end_time, grace_period_minutes,
           late_penalty_per_minute, overtime_bonus_per_hour,
           early_checkin_minutes, late_checkout_allowed, min_working_hours, working_days
    FROM employee_schedules 
    WHERE user_id = ? AND is_active = 1
      AND (effective_from IS NULL OR effective_from <= CURDATE())
      AND (effective_until IS NULL OR effective_until >= CURDATE())
", [$user_id]);

$attendance_mode = $employeeSchedule['attendance_mode'] ?? 'time_and_location';
$remote_allowed = !empty($employeeSchedule['remote_checkin_allowed']);
$custom_radius = $employeeSchedule['custom_radius'] ?? null;
// Working days (0=Sunday .. 6=Saturday)
$working_days_raw = $employeeSchedule['working_days'] ?? null;
$working_days = [];
if (is_string($working_days_raw) && $working_days_raw !== '') {
    $decoded_days = json_decode($working_days_raw, true);
    if (is_array($decoded_days)) {
        $working_days = array_map('intval', $decoded_days);
    }
} elseif (is_array($working_days_raw)) {
    $working_days = array_map('intval', $working_days_raw);
}
if (empty($working_days)) {
    $working_days = [0, 1, 2, 3, 4, 5, 6];
}

// Check if branch is required (for location-based modes)
$requires_branch = in_array($attendance_mode, ['location_only', 'time_and_location']) && !$remote_allowed;

if (!$branch && $requires_branch) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'error' => 'no_branch',
        'message' => 'لم يتم تعيين فرع للمستخدم'
    ], JSON_UNESCAPED_UNICODE));
}

// ═══════════════════════════════════════════════════════════════════════════════
// SERVER-SIDE GEOFENCE VERIFICATION (HAVERSINE)
// Enforce 20-meter maximum distance from ANY branch/center
// ═══════════════════════════════════════════════════════════════════════════════

// Skip geofence check for unrestricted mode or remote-allowed
$skip_geofence = ($attendance_mode === 'unrestricted') || 
                 ($attendance_mode === 'time_only') || 
                 $remote_allowed;

$distance = 0;
$is_within_geofence = true;
$nearest_branch = null;
$nearest_distance = PHP_FLOAT_MAX;
$MAX_DISTANCE_METERS = 20; // Maximum 20 meters from any branch/center

if (!$skip_geofence) {
    // Check distance from ALL active branches/centers
    $all_branches = Database::fetchAll(
        "SELECT * FROM branches WHERE is_active = 1 AND latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0 AND longitude != 0"
    );
    
    if (empty($all_branches)) {
        // No branches configured - allow check-in
        $is_within_geofence = true;
    } else {
        // Find the nearest branch
        foreach ($all_branches as $b) {
            $b_lat = floatval($b['latitude'] ?? 0);
            $b_lng = floatval($b['longitude'] ?? 0);
            
            if ($b_lat == 0 || $b_lng == 0) continue;
            
            $dist = haversineDistance($latitude, $longitude, $b_lat, $b_lng);
            
            if ($dist < $nearest_distance) {
                $nearest_distance = $dist;
                $nearest_branch = $b;
            }
        }
        
        $distance = $nearest_distance;
        
        // Allow small tolerance for GPS accuracy (max 5m)
        $tolerance = min($accuracy ?? 0, 5);
        
        // Employee must be within 20 meters of ANY branch/center
        $is_within_geofence = ($distance <= ($MAX_DISTANCE_METERS + $tolerance));
        
        // If within range, use the nearest branch
        if ($is_within_geofence && $nearest_branch) {
            $branch = $nearest_branch;
            $effective_branch_id = intval($nearest_branch['id']);
            $branch_settings = json_decode($nearest_branch['settings'] ?? '{}', true) ?: [];
        }
    }
}

if (!$is_within_geofence && !$skip_geofence) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'error' => 'out_of_geofence',
        'message' => 'لا يمكنك تسجيل الحضور. يجب أن تكون على مسافة أقل من 20 متر من أي مركز أو فرع',
        'distance' => round($distance),
        'max_distance' => $MAX_DISTANCE_METERS,
        'nearest_branch' => $nearest_branch ? $nearest_branch['name'] : null
    ], JSON_UNESCAPED_UNICODE));
}

// ═══════════════════════════════════════════════════════════════════════════════
// GET WORK SETTINGS FROM EMPLOYEE SCHEDULE OR DEFAULTS
// ═══════════════════════════════════════════════════════════════════════════════

// استخدام جدول الموظف أو الإعدادات الافتراضية
$work_start_str = $employeeSchedule['work_start_time'] ?? get_setting('work_start_time', '08:00');
$work_end_str = $employeeSchedule['work_end_time'] ?? get_setting('work_end_time', '17:00');
$grace_period = intval($employeeSchedule['grace_period_minutes'] ?? get_setting('grace_period_minutes', 15));
$late_penalty_rate = floatval($employeeSchedule['late_penalty_per_minute'] ?? get_setting('late_penalty_per_minute', 0.5));
$early_leave_penalty_rate = floatval($employeeSchedule['late_penalty_per_minute'] ?? 0.5);
$overtime_bonus_rate = floatval($employeeSchedule['overtime_bonus_per_hour'] ?? get_setting('overtime_bonus_per_minute', 0.3)) / 60;
$early_checkin_minutes = intval($employeeSchedule['early_checkin_minutes'] ?? 30);
$late_checkout_allowed = isset($employeeSchedule['late_checkout_allowed']) ? (bool)$employeeSchedule['late_checkout_allowed'] : true;
$min_working_hours = isset($employeeSchedule['min_working_hours']) ? floatval($employeeSchedule['min_working_hours']) : 0.0;
$min_overtime_for_bonus = 15;

// Parse times
$today = date('Y-m-d');
$now = new DateTime();
$work_start = new DateTime($today . ' ' . $work_start_str);
$work_end = new DateTime($today . ' ' . $work_end_str);

// ═══════════════════════════════════════════════════════════════════════════════
// STRICT TIME WINDOW ENFORCEMENT
// ═══════════════════════════════════════════════════════════════════════════════
$strict_time_windows = (get_setting('strict_time_windows', 'true') === 'true');
$enforce_time_windows = $strict_time_windows && $attendance_mode !== 'unrestricted';

if ($enforce_time_windows) {
    if ($action === 'checkin') {
        $current_day = (int) $now->format('w');
        if (!in_array($current_day, $working_days, true)) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'error' => 'not_working_day',
                'message' => 'اليوم ليس من أيام العمل'
            ], JSON_UNESCAPED_UNICODE));
        }
        
        // Check-in window: 1 hour before work_start to 1 hour after work_start
        $checkin_window_start = clone $work_start;
        $checkin_window_start->modify('-1 hour');
        
        $checkin_window_end = clone $work_start;
        $checkin_window_end->modify('+1 hour');
        
        if ($now < $checkin_window_start) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'error' => 'checkin_too_early',
                'message' => 'مبكر جداً لتسجيل الحضور',
                'earliest_checkin' => $checkin_window_start->format('H:i')
            ], JSON_UNESCAPED_UNICODE));
        }
        
        if ($now > $checkin_window_end) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'error' => 'checkin_closed',
                'message' => 'انتهى وقت تسجيل الحضور لهذا اليوم',
                'checkin_deadline' => $checkin_window_end->format('H:i')
            ], JSON_UNESCAPED_UNICODE));
        }
    } else {
        // Check-out window: 1 hour before work_end to 1 hour after work_end
        $checkout_window_start = clone $work_end;
        $checkout_window_start->modify('-1 hour');
        
        $checkout_window_end = clone $work_end;
        $checkout_window_end->modify('+1 hour');
        
        if ($now < $checkout_window_start) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'error' => 'checkout_too_early',
                'message' => 'لا يمكن تسجيل الانصراف قبل الساعة ' . $checkout_window_start->format('H:i'),
                'checkout_start' => $checkout_window_start->format('H:i')
            ], JSON_UNESCAPED_UNICODE));
        }
        
        if ($now > $checkout_window_end) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'error' => 'checkout_closed',
                'message' => 'انتهى وقت تسجيل الانصراف لهذا اليوم',
                'checkout_deadline' => $checkout_window_end->format('H:i')
            ], JSON_UNESCAPED_UNICODE));
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// PROCESS ACTION
// ═══════════════════════════════════════════════════════════════════════════════

try {
    Database::beginTransaction();
    
    if ($action === 'checkin') {
        $result = processCheckIn(
            $user_id, $effective_branch_id, $latitude, $longitude, $accuracy,
            $now, $work_start, $grace_period, $late_penalty_rate, $branch, $auto_checkin
        );
    } else {
        $result = processCheckOut(
            $user_id, $attendance_id, $latitude, $longitude, $accuracy,
            $now, $work_end, $early_leave_penalty_rate, $overtime_bonus_rate, $min_overtime_for_bonus,
            $enforce_time_windows, $min_working_hours
        );
    }
    
    Database::commit();
    
    // Log activity
    if (function_exists('log_activity')) {
        log_activity(
            $action === 'checkin' ? 'attendance_checkin' : 'attendance_checkout',
            'attendance',
            $result['attendance_id'],
            [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => $accuracy,
                'distance' => isset($distance) ? round($distance) : 0,
                'time' => $now->format('H:i:s'),
                'penalties' => $result['penalty_points'] ?? 0,
                'bonuses' => $result['bonus_points'] ?? 0
            ]
        );
    }
    
    // Return success response
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
    // ═══════════════════════════════════════════════════════════════════════════
    // TRAP SYSTEM TRIGGER - Check for traps after successful attendance
    // ═══════════════════════════════════════════════════════════════════════════
    // Only for check-in actions, trigger random trap check
    if ($action === 'checkin' && file_exists(__DIR__ . '/../../includes/traps.php')) {
        try {
            require_once __DIR__ . '/../../includes/traps.php';
            
            // Check for random trap (TrapFactory handles probability internally)
            $trap = TrapFactory::getRandomTrap($user_id);
            
            // Note: We don't block the response if trap is triggered
            // The trap will be shown on next page load via frontend trap_engine.js
            if ($trap) {
                // Trap will be displayed by frontend on next check
                // This is intentional - we don't want to delay attendance response
            }
        } catch (Exception $e) {
            // Silently fail - trap system errors should not affect attendance
            error_log('[SARH] Trap system check failed: ' . $e->getMessage());
        }
    }
    
} catch (Exception $e) {
    Database::rollBack();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'server_error',
        'message' => 'حدث خطأ: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// ═══════════════════════════════════════════════════════════════════════════════
// PROCESS CHECK-IN
// ═══════════════════════════════════════════════════════════════════════════════

function processCheckIn($user_id, $branch_id, $lat, $lng, $accuracy, $now, $work_start, $grace_period, $late_penalty_rate, $branch, $auto_checkin = false) {
    $today = date('Y-m-d');
    
    // ═══════════════════════════════════════════════════════════════════════════
    // RACE CONDITION PROTECTION: Check for existing check-in (before transaction)
    // ═══════════════════════════════════════════════════════════════════════════
    // Note: UNIQUE constraint (uk_attendance_user_date) will catch duplicates,
    // but we check first to give a cleaner error message
    $existing = Database::fetchOne(
        "SELECT * FROM attendance WHERE user_id = ? AND date = ?",
        [$user_id, $today]
    );
    
    if ($existing && !empty($existing['check_in_time'])) {
        throw new Exception('تم تسجيل الحضور مسبقاً اليوم');
    }
    
    // Calculate late minutes
    $late_minutes = 0;
    $penalty_points = 0;
    $status = 'present';
    
    if ($now > $work_start) {
        $late_minutes = intval(($now->getTimestamp() - $work_start->getTimestamp()) / 60);
        
        // Apply penalty only after grace period
        if ($late_minutes > $grace_period) {
            $penalty_minutes = $late_minutes - $grace_period;
            $penalty_points = round($penalty_minutes * $late_penalty_rate, 2);
            $status = 'late';
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // Get address (optional) - ONLY for manual check-in to avoid Nominatim rate limiting
    // ═══════════════════════════════════════════════════════════════════════════
    // NOTE: Nominatim has strict rate limits (1 request/second max).
    // For passive tracking (auto_checkin), skip geocoding to avoid IP ban.
    // Address is optional and doesn't affect attendance record integrity.
    $address = null;
    if (!$auto_checkin) {
        // Only fetch address for manual check-in (not for passive/auto tracking)
        // This prevents Nominatim rate limiting when using continuous tracking
        $address = getAddressFromCoordinates($lat, $lng);
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // CALCULATE DISTANCE FROM BRANCH (Haversine Formula)
    // ═══════════════════════════════════════════════════════════════════════════
    $check_in_distance = null;
    if ($branch && isset($branch['latitude']) && isset($branch['longitude'])) {
        $branch_lat = floatval($branch['latitude']);
        $branch_lng = floatval($branch['longitude']);
        
        if ($branch_lat != 0 && $branch_lng != 0) {
            // Calculate distance using Haversine formula (in meters)
            $check_in_distance = haversineDistance($lat, $lng, $branch_lat, $branch_lng);
        }
    }
    
    // Insert attendance record
    $attendance_data = [
        'user_id' => $user_id,
        'branch_id' => $branch_id,
        'date' => $today,
        'check_in_time' => $now->format('H:i:s'),
        'check_in_lat' => $lat,
        'check_in_lng' => $lng,
        'check_in_address' => $address,
        'check_in_distance' => $check_in_distance, // Add calculated distance
        'late_minutes' => $late_minutes,
        'penalty_points' => $penalty_points,
        'status' => $status,
        'created_at' => $now->format('Y-m-d H:i:s')
    ];
    
    // ⚠️ إضافة check_in_method فقط إذا كان العمود موجوداً في قاعدة البيانات
    try {
        // التحقق من وجود العمود قبل إضافته
        $column_exists = Database::fetchOne(
            "SELECT COUNT(*) as cnt 
             FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'attendance' 
             AND COLUMN_NAME = 'check_in_method'"
        );
        
        if ($column_exists && $column_exists['cnt'] > 0) {
            $attendance_data['check_in_method'] = $auto_checkin ? 'auto_gps' : 'manual';
        }
    } catch (Exception $e) {
        // في حالة الخطأ، نتجاهل إضافة العمود (لن يسبب مشكلة)
        error_log('[SARH] Could not check for check_in_method column: ' . $e->getMessage());
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // RACE CONDITION PROTECTION: Handle duplicate entry from concurrent requests
    // ═══════════════════════════════════════════════════════════════════════════
    try {
        $attendance_id = Database::insert('attendance', $attendance_data);
    } catch (PDOException $e) {
        // If duplicate entry (23000 = Integrity constraint violation)
        // This happens when multiple requests try to insert simultaneously
        if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'uk_attendance_user_date') !== false) {
            throw new Exception('تم تسجيل الحضور مسبقاً اليوم');
        }
        // Re-throw if it's a different database error
        throw $e;
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // INTEGRITY LOG - Record attendance event for security monitoring
    // ═══════════════════════════════════════════════════════════════════════════
    // Determine severity based on distance from branch
    $geofence_radius = isset($branch['geofence_radius']) ? floatval($branch['geofence_radius']) : 100;
    $severity = ($check_in_distance !== null && $check_in_distance > ($geofence_radius * 2)) ? 'high' : 'low';
    
    try {
        Database::insert('integrity_logs', [
            'user_id' => $user_id,
            'action_type' => 'attendance_checkin',
            'target_type' => 'attendance',
            'target_id' => $attendance_id,
            'details' => json_encode([
                'latitude' => $lat,
                'longitude' => $lng,
                'distance_from_branch' => $check_in_distance,
                'check_in_method' => $auto_checkin ? 'auto_gps' : 'manual',
                'late_minutes' => $late_minutes,
                'penalty_points' => $penalty_points,
                'branch_id' => $branch_id
            ], JSON_UNESCAPED_UNICODE),
            'severity' => $severity,
            'location_lat' => $lat,
            'location_lng' => $lng,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => $now->format('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        // Log error but don't fail the attendance check-in
        error_log('[SARH] Integrity log insertion failed: ' . $e->getMessage());
    }
    
    // Update user points if penalty
    if ($penalty_points > 0) {
        Database::query(
            "UPDATE users SET current_points = GREATEST(0, current_points - ?) WHERE id = ?",
            [$penalty_points, $user_id]
        );
    }
    
    // Update user online status
    Database::update('users', [
        'is_online' => 1,
        'last_activity_at' => $now->format('Y-m-d H:i:s')
    ], 'id = :id', ['id' => $user_id]);
    
    // Build response
    $message = $auto_checkin ? '✅ تم تسجيل حضورك تلقائياً!' : 'تم تسجيل الحضور بنجاح';
    $details = '';
    
    // Auto check-in badge
    if ($auto_checkin) {
        $details .= "<div class='detail-item info'><i class='bi bi-radar'></i> تسجيل تلقائي عبر الرادار</div>";
    }
    
    if ($late_minutes > 0) {
        $details .= "<div class='detail-item warning'><i class='bi bi-clock-history'></i> تأخير: <strong>{$late_minutes} دقيقة</strong></div>";
        
        if ($penalty_points > 0) {
            $details .= "<div class='detail-item danger'><i class='bi bi-arrow-down-circle'></i> خصم: <strong>{$penalty_points} نقطة</strong></div>";
        } elseif ($late_minutes <= $grace_period) {
            $details .= "<div class='detail-item info'><i class='bi bi-shield-check'></i> ضمن فترة السماح</div>";
        }
    } else {
        $details .= "<div class='detail-item success'><i class='bi bi-emoji-smile'></i> حضور في الموعد! 🎉</div>";
    }
    
    // Add branch name only if branch exists
    if ($branch && !empty($branch['name'])) {
        $branch_name = htmlspecialchars($branch['name'], ENT_QUOTES, 'UTF-8');
        $details .= "<div class='detail-item'><i class='bi bi-geo-alt'></i> {$branch_name}</div>";
    }
    
    return [
        'success' => true,
        'action' => 'checkin',
        'message' => $message,
        'details' => $details,
        'attendance_id' => $attendance_id,
        'check_in_time' => $now->format('H:i'),
        'late_minutes' => $late_minutes,
        'penalty_points' => $penalty_points,
        'status' => $status,
        'auto_checkin' => $auto_checkin,
        'branch_name' => $branch['name'] ?? null
    ];
}

// ═══════════════════════════════════════════════════════════════════════════════
// PROCESS CHECK-OUT
// ═══════════════════════════════════════════════════════════════════════════════

function processCheckOut($user_id, $attendance_id, $lat, $lng, $accuracy, $now, $work_end, $early_leave_rate, $overtime_rate, $min_overtime, $enforce_time_windows, $min_working_hours) {
    $today = date('Y-m-d');
    
    // Get today's attendance record
    $attendance = Database::fetchOne(
        "SELECT * FROM attendance WHERE user_id = ? AND date = ? AND check_out_time IS NULL ORDER BY id DESC LIMIT 1",
        [$user_id, $today]
    );
    
    if (!$attendance) {
        throw new Exception('لا يوجد سجل حضور للانصراف منه');
    }
    
    // Parse check-in time
    $check_in_time = new DateTime($today . ' ' . $attendance['check_in_time']);
    
    // Calculate work duration
    $work_seconds = $now->getTimestamp() - $check_in_time->getTimestamp();
    $work_minutes = intval($work_seconds / 60);
    $work_hours = floor($work_minutes / 60);
    $work_mins_remainder = $work_minutes % 60;

    if ($enforce_time_windows && $min_working_hours > 0) {
        $worked_hours = $work_seconds / 3600;
        if ($worked_hours < $min_working_hours) {
            $remaining = round($min_working_hours - $worked_hours, 1);
            throw new Exception("لا يمكنك الانصراف قبل إكمال الحد الأدنى للساعات (متبقي {$remaining} ساعة)");
        }
    }
    
    // Calculate early leave
    $early_leave_minutes = 0;
    $early_penalty_points = 0;
    
    if ($now < $work_end) {
        $early_leave_minutes = intval(($work_end->getTimestamp() - $now->getTimestamp()) / 60);
        $early_penalty_points = round($early_leave_minutes * $early_leave_rate, 2);
    }
    
    // Calculate overtime
    $overtime_minutes = 0;
    $bonus_points = 0;
    
    if ($now > $work_end) {
        $overtime_minutes = intval(($now->getTimestamp() - $work_end->getTimestamp()) / 60);
        
        // Only award bonus if overtime exceeds minimum
        if ($overtime_minutes >= $min_overtime) {
            $bonus_points = round($overtime_minutes * $overtime_rate, 2);
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // Get address (optional) - Nominatim rate limiting consideration
    // ═══════════════════════════════════════════════════════════════════════════
    // NOTE: Nominatim has strict rate limits (1 request/second max).
    // Check-out is usually manual, so address geocoding is acceptable here.
    // If implementing passive check-out tracking, consider making this conditional.
    $address = getAddressFromCoordinates($lat, $lng);
    
    // Total penalties (check-in + check-out)
    $total_penalty = floatval($attendance['penalty_points']) + $early_penalty_points;
    
    // Update attendance record
    Database::update('attendance', [
        'check_out_time' => $now->format('H:i:s'),
        'check_out_lat' => $lat,
        'check_out_lng' => $lng,
        'check_out_address' => $address,
        'work_minutes' => $work_minutes,
        'early_leave_minutes' => $early_leave_minutes,
        'overtime_minutes' => $overtime_minutes,
        'penalty_points' => $total_penalty,
        'bonus_points' => $bonus_points,
        'updated_at' => $now->format('Y-m-d H:i:s')
    ], 'id = :id', ['id' => $attendance['id']]);
    
    // Update user points
    $net_points = $bonus_points - $early_penalty_points;
    if ($net_points != 0) {
        if ($net_points > 0) {
            Database::query(
                "UPDATE users SET current_points = current_points + ? WHERE id = ?",
                [$net_points, $user_id]
            );
        } else {
            Database::query(
                "UPDATE users SET current_points = GREATEST(0, current_points - ?) WHERE id = ?",
                [abs($net_points), $user_id]
            );
        }
    }
    
    // Build response
    $message = 'تم تسجيل الانصراف بنجاح';
    $details = '';
    
    // Work duration
    $details .= "<div class='detail-item'><i class='bi bi-hourglass-split'></i> ساعات العمل: <strong>{$work_hours} ساعة و {$work_mins_remainder} دقيقة</strong></div>";
    
    // Early leave penalty
    if ($early_leave_minutes > 0) {
        $details .= "<div class='detail-item warning'><i class='bi bi-door-open'></i> خروج مبكر: <strong>{$early_leave_minutes} دقيقة</strong></div>";
        
        if ($early_penalty_points > 0) {
            $details .= "<div class='detail-item danger'><i class='bi bi-arrow-down-circle'></i> خصم: <strong>{$early_penalty_points} نقطة</strong></div>";
        }
    }
    
    // Overtime bonus
    if ($overtime_minutes > 0) {
        $details .= "<div class='detail-item info'><i class='bi bi-lightning-charge'></i> عمل إضافي: <strong>{$overtime_minutes} دقيقة</strong></div>";
        
        if ($bonus_points > 0) {
            $details .= "<div class='detail-item success'><i class='bi bi-arrow-up-circle'></i> مكافأة: <strong>{$bonus_points} نقطة</strong> 🎉</div>";
        } elseif ($overtime_minutes < $min_overtime) {
            $details .= "<div class='detail-item muted'><i class='bi bi-info-circle'></i> الحد الأدنى للمكافأة: {$min_overtime} دقيقة</div>";
        }
    }
    
    // Net points summary
    if ($net_points != 0) {
        $pointsClass = $net_points > 0 ? 'success' : 'danger';
        $pointsIcon = $net_points > 0 ? 'bi-plus-circle' : 'bi-dash-circle';
        $pointsSign = $net_points > 0 ? '+' : '';
        $details .= "<div class='detail-item {$pointsClass}'><i class='bi {$pointsIcon}'></i> صافي النقاط: <strong>{$pointsSign}{$net_points}</strong></div>";
    }
    
    return [
        'success' => true,
        'action' => 'checkout',
        'message' => $message,
        'details' => $details,
        'attendance_id' => $attendance['id'],
        'check_out_time' => $now->format('H:i'),
        'work_minutes' => $work_minutes,
        'overtime_minutes' => $overtime_minutes,
        'early_leave_minutes' => $early_leave_minutes,
        'penalty_points' => $early_penalty_points,
        'bonus_points' => $bonus_points,
        'net_points' => $net_points
    ];
}

// ═══════════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Haversine distance calculation (server-side verification)
 */
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000; // Earth's radius in meters
    
    $φ1 = deg2rad($lat1);
    $φ2 = deg2rad($lat2);
    $Δφ = deg2rad($lat2 - $lat1);
    $Δλ = deg2rad($lon2 - $lon1);
    
    $a = sin($Δφ / 2) * sin($Δφ / 2) +
         cos($φ1) * cos($φ2) *
         sin($Δλ / 2) * sin($Δλ / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $R * $c;
}

/**
 * Get address from coordinates using Nominatim (OpenStreetMap)
 */
function getAddressFromCoordinates($lat, $lng) {
    if (!$lat || !$lng) return null;
    
    try {
        $url = sprintf(
            'https://nominatim.openstreetmap.org/reverse?format=json&lat=%f&lon=%f&accept-language=ar&zoom=18',
            $lat, $lng
        );
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'header' => "User-Agent: SARH-System/3.5\r\n"
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            
            // Try to get a short address
            if (isset($data['address'])) {
                $addr = $data['address'];
                $parts = [];
                
                if (!empty($addr['road'])) $parts[] = $addr['road'];
                if (!empty($addr['suburb'])) $parts[] = $addr['suburb'];
                if (!empty($addr['city'])) $parts[] = $addr['city'];
                
                if (!empty($parts)) {
                    return implode('، ', $parts);
                }
            }
            
            return $data['display_name'] ?? null;
        }
    } catch (Exception $e) {
        // Silently fail - address is optional
        error_log('[SARH] Geocoding failed: ' . $e->getMessage());
    }
    
    return null;
}
