<?php
/**
 * SARH SYSTEM - HEARTBEAT API v4.0
 * نقطة نهاية النبضات الحية
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

// ═══════════════════════════════════════════════════════════════════════════════
// AUTHENTICATION
// ═══════════════════════════════════════════════════════════════════════════════

if (!is_logged_in()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'unauthorized']));
}

// ═══════════════════════════════════════════════════════════════════════════════
// CSRF
// ═══════════════════════════════════════════════════════════════════════════════

$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($csrf_token) || !verify_csrf($csrf_token)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'csrf_invalid']));
}

// ═══════════════════════════════════════════════════════════════════════════════
// PARSE INPUT
// ═══════════════════════════════════════════════════════════════════════════════

$input = json_decode(file_get_contents('php://input'), true);

// Handle offline beacon
if (isset($input['offline']) && $input['offline'] === true) {
    markUserOffline($_SESSION['user_id']);
    die(json_encode(['success' => true, 'message' => 'marked_offline']));
}

// Handle AWOL alert
if (isset($input['awol_alert']) && $input['awol_alert'] === true) {
    logAWOLAlert(
        $_SESSION['user_id'],
        $input['latitude'] ?? null,
        $input['longitude'] ?? null
    );
}

$latitude = isset($input['latitude']) ? floatval($input['latitude']) : null;
$longitude = isset($input['longitude']) ? floatval($input['longitude']) : null;
$accuracy = isset($input['accuracy']) ? floatval($input['accuracy']) : null;

// ═══════════════════════════════════════════════════════════════════════════════
// USER DATA
// ═══════════════════════════════════════════════════════════════════════════════

$user_id = intval($_SESSION['user_id'] ?? 0);
$branch_id = intval($_SESSION['branch_id'] ?? 0);
$role_level = intval($_SESSION['role_level'] ?? 1);

if ($user_id <= 0) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'invalid_user']));
}

// ═══════════════════════════════════════════════════════════════════════════════
// UPDATE LOCATION
// ═══════════════════════════════════════════════════════════════════════════════

if ($latitude !== null && $longitude !== null) {
    if ($latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180) {
        updateUserLocation($user_id, $latitude, $longitude, $accuracy);
        
        // ═══════════════════════════════════════════════════════════════════════
        // TIME BRIDGE LOGIC - Passive Attendance Tracking
        // ═══════════════════════════════════════════════════════════════════════
        processTimeBridge($user_id, $branch_id, $latitude, $longitude);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SETTINGS
// ═══════════════════════════════════════════════════════════════════════════════

$visibility_mode = get_setting('map_visibility_mode', 'branch');
$live_mode = get_setting('live_mode_enabled', 'true') === 'true';
$show_names = get_setting('show_colleague_names', 'true') === 'true';

// ═══════════════════════════════════════════════════════════════════════════════
// GET COLLEAGUES
// ═══════════════════════════════════════════════════════════════════════════════

$colleagues = [];

if ($live_mode) {
    $colleagues = getActiveColleagues($user_id, $branch_id, $role_level, $visibility_mode, $show_names);
}

// ═══════════════════════════════════════════════════════════════════════════════
// RESPONSE
// ═══════════════════════════════════════════════════════════════════════════════

echo json_encode([
    'success' => true,
    'server_time' => date('Y-m-d H:i:s'),
    'colleagues' => $colleagues,
    'colleagues_count' => count($colleagues),
    'live_mode' => $live_mode
], JSON_UNESCAPED_UNICODE);

// ═══════════════════════════════════════════════════════════════════════════════
// FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

function updateUserLocation($user_id, $lat, $lng, $accuracy = null) {
    try {
        Database::query(
            "UPDATE users SET last_latitude = ?, last_longitude = ?, last_activity_at = NOW(), is_online = 1 WHERE id = ?",
            [$lat, $lng, $user_id]
        );
    } catch (Exception $e) {
        error_log('[SARH Heartbeat] Update location failed: ' . $e->getMessage());
    }
}

function markUserOffline($user_id) {
    try {
        Database::query("UPDATE users SET is_online = 0 WHERE id = ?", [$user_id]);
    } catch (Exception $e) {}
}

function getActiveColleagues($user_id, $branch_id, $role_level, $visibility_mode, $show_names) {
    $is_admin = ($role_level >= 10);
    
    if ($visibility_mode === 'self' && !$is_admin) {
        return [];
    }
    
    $today = date('Y-m-d');
    $currentHour = (int) date('H');
    
    if ($currentHour >= 23) {
        return [];
    }
    
    $conditions = [
        'u.is_active = 1',
        'u.id != ?',
        'u.last_latitude IS NOT NULL',
        'u.last_longitude IS NOT NULL'
    ];
    $params = [$user_id];
    
    if (!$is_admin && $visibility_mode === 'branch' && $branch_id > 0) {
        $conditions[] = 'u.branch_id = ?';
        $params[] = $branch_id;
    }
    
    $where = implode(' AND ', $conditions);
    
    $sql = "
        SELECT 
            u.id AS user_id,
            u.full_name,
            u.last_latitude AS latitude,
            u.last_longitude AS longitude,
            u.last_activity_at,
            u.branch_id,
            b.name AS branch_name,
            b.latitude AS branch_lat,
            b.longitude AS branch_lng,
            COALESCE(b.geofence_radius, 100) AS geofence_radius,
            a.check_in_time,
            a.check_out_time
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
        WHERE {$where}
        AND (
            (a.check_in_time IS NOT NULL AND a.check_out_time IS NULL)
            OR u.last_activity_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        )
        AND (a.check_out_time IS NULL OR a.id IS NULL)
        ORDER BY u.last_activity_at DESC
        LIMIT 50
    ";
    
    array_unshift($params, $today);
    
    try {
        $results = Database::fetchAll($sql, $params);
        $colleagues = [];
        
        foreach ($results as $row) {
            $is_within_geofence = false;
            $distance = null;
            
            if ($row['branch_lat'] && $row['branch_lng']) {
                $distance = haversineDistance(
                    floatval($row['latitude']),
                    floatval($row['longitude']),
                    floatval($row['branch_lat']),
                    floatval($row['branch_lng'])
                );
                $is_within_geofence = ($distance <= floatval($row['geofence_radius']));
            }
            
            $colleagues[] = [
                'user_id' => intval($row['user_id']),
                'full_name' => $show_names ? $row['full_name'] : 'زميل',
                'latitude' => floatval($row['latitude']),
                'longitude' => floatval($row['longitude']),
                'branch_id' => intval($row['branch_id']),
                'branch_name' => $row['branch_name'],
                'is_within_geofence' => $is_within_geofence,
                'distance_from_branch' => $distance !== null ? round($distance) : null,
                'last_activity_at' => $row['last_activity_at']
            ];
        }
        
        return $colleagues;
        
    } catch (Exception $e) {
        error_log('[SARH Heartbeat] Get colleagues failed: ' . $e->getMessage());
        return [];
    }
}

function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000;
    $φ1 = deg2rad($lat1);
    $φ2 = deg2rad($lat2);
    $Δφ = deg2rad($lat2 - $lat1);
    $Δλ = deg2rad($lon2 - $lon1);
    
    $a = sin($Δφ / 2) * sin($Δφ / 2) +
         cos($φ1) * cos($φ2) * sin($Δλ / 2) * sin($Δλ / 2);
    
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

// ═══════════════════════════════════════════════════════════════════════════════
// TIME BRIDGE FUNCTION - Passive Attendance Tracking
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Time Bridge Logic: Tracks passive attendance when user is within 20m of branch
 * 
 * Logic:
 * - Check distance from branch (strict 20m radius)
 * - IF INSIDE:
 *   - If NO record: INSERT (check_in_time = NOW, check_out_time = NOW)
 *   - If YES: UPDATE check_out_time = NOW
 *   - Implicitly accepts user was present during gaps (updates 10min apart)
 * - IF OUTSIDE: Do nothing
 */
function processTimeBridge($user_id, $branch_id, $lat, $lng) {
    try {
        // Get branch location
        if (!$branch_id || $branch_id <= 0) {
            return; // No branch assigned
        }
        
        $branch = Database::fetchOne(
            "SELECT latitude, longitude, geofence_radius FROM branches WHERE id = ? AND is_active = 1",
            [$branch_id]
        );
        
        if (!$branch || !$branch['latitude'] || !$branch['longitude']) {
            return; // Branch not found or no coordinates
        }
        
        $branch_lat = floatval($branch['latitude']);
        $branch_lng = floatval($branch['longitude']);
        
        // Calculate distance using Haversine
        $distance = haversineDistance($lat, $lng, $branch_lat, $branch_lng);
        
        // Strict 20m radius check
        $MAX_DISTANCE_METERS = 20;
        
        if ($distance > $MAX_DISTANCE_METERS) {
            // Outside range - do nothing
            return;
        }
        
        // ═══════════════════════════════════════════════════════════════════════
        // INSIDE RANGE - Process Time Bridge
        // ═══════════════════════════════════════════════════════════════════════
        
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        
        // Check if record exists for today
        $existing = Database::fetchOne(
            "SELECT id, check_in_time, check_out_time FROM attendance 
             WHERE user_id = ? AND date = ?",
            [$user_id, $today]
        );
        
        if (!$existing) {
            // NO RECORD - INSERT new record with check_in_time ONLY
            // ⚠️ FIX: Do NOT set check_out_time - this is NOT a checkout!
            // Time Bridge should only track presence, not register checkout
            Database::insert('attendance', [
                'user_id' => $user_id,
                'branch_id' => $branch_id,
                'date' => $today,
                'check_in_time' => date('H:i:s'),
                'check_out_time' => NULL, // ⚠️ FIX: NULL - not a checkout!
                'check_in_lat' => $lat,
                'check_in_lng' => $lng,
                'check_out_lat' => NULL,
                'check_out_lng' => NULL,
                'check_in_distance' => round($distance, 2),
                'check_out_distance' => NULL,
                'check_in_method' => 'auto_gps',
                'status' => 'present',
                'created_at' => $now,
                'updated_at' => $now
            ]);
            
            error_log("[SARH TimeBridge] INSERT: User {$user_id} at branch {$branch_id} (distance: {$distance}m) - Check-in only");
            
        } else {
            // RECORD EXISTS - Only update if check_out_time is NULL (not checked out yet)
            // ⚠️ FIX: Do NOT update check_out_time if it's already set (user already checked out)
            if (empty($existing['check_out_time'])) {
                // User is still present - update last known location (for tracking only)
                // But do NOT set check_out_time - this is NOT a checkout!
                // We only update location fields for tracking purposes
                Database::query(
                    "UPDATE attendance 
                     SET check_in_lat = ?,
                         check_in_lng = ?,
                         check_in_distance = ?,
                         updated_at = ?
                     WHERE id = ?",
                    [
                        $lat,
                        $lng,
                        round($distance, 2),
                        $now,
                        $existing['id']
                    ]
                );
                
                error_log("[SARH TimeBridge] UPDATE: User {$user_id} location updated (distance: {$distance}m) - Still present, NOT checked out");
            } else {
                // User already checked out - do nothing
                error_log("[SARH TimeBridge] SKIP: User {$user_id} already checked out at {$existing['check_out_time']}");
            }
        }
        
    } catch (Exception $e) {
        // Log error but don't break heartbeat
        error_log('[SARH TimeBridge] Error: ' . $e->getMessage());
    }
}

function logAWOLAlert($user_id, $lat, $lng) {
    try {
        $user = Database::fetchOne("SELECT full_name, branch_id FROM users WHERE id = ?", [$user_id]);
        if (!$user) return;
        
        // Log to activity
        try {
            Database::insert('activity_log', [
                'user_id' => $user_id,
                'action' => 'awol_alert',
                'description' => "🚨 تنبيه: {$user['full_name']} خرج من نطاق العمل",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'extra_data' => json_encode(['latitude' => $lat, 'longitude' => $lng, 'time' => date('Y-m-d H:i:s')])
            ]);
        } catch (Exception $e) {}
        
        // Create notification for managers
        try {
            $check = Database::fetchOne("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'notifications' LIMIT 1");
            if ($check) {
                Database::insert('notifications', [
                    'title' => '🚨 تنبيه خروج من نطاق العمل',
                    'message' => "{$user['full_name']} خرج من نطاق الفرع",
                    'type' => 'warning',
                    'scope_type' => 'branch',
                    'scope_id' => $user['branch_id'],
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
                ]);
            }
        } catch (Exception $e) {}
        
        error_log("[SARH AWOL] Alert: user {$user_id} at {$lat}, {$lng}");
        
    } catch (Exception $e) {
        error_log('[SARH AWOL] Log failed: ' . $e->getMessage());
    }
}
