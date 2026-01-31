<?php
/**
 * اختبار تفصيلي لعملية تسجيل الدخول
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$result = [];
$identifier = 'admin';
$password = 'Admin@2026';

try {
    // الخطوة 1: البحث عن المستخدم
    $sql = "SELECT 
                u.id,
                u.emp_code,
                u.username,
                u.email,
                u.full_name,
                u.full_name AS full_name_ar,
                u.full_name AS full_name_en,
                u.phone,
                u.avatar AS profile_image,
                u.job_title AS job_title_ar,
                u.password_hash AS password,
                u.remember_token,
                u.role_id,
                u.branch_id,
                u.department,
                u.current_points,
                1000 AS monthly_starting_points,
                u.custom_schedule,
                u.is_active,
                COALESCE(u.is_super_admin, 0) AS is_super_admin,
                u.permissions AS user_permissions,
                u.visible_modules,
                0 AS has_immunity,
                NULL AS immunity_until,
                u.login_attempts,
                u.locked_until,
                u.preferences,
                r.slug AS role_slug,
                r.name AS role_name_ar,
                r.name AS role_name_en,
                r.role_level,
                r.permissions AS role_permissions,
                0 AS role_has_immunity,
                0 AS can_access_all_branches,
                r.color AS role_color,
                r.icon AS role_icon,
                b.code AS branch_code,
                b.name AS branch_name_ar,
                b.name AS branch_name_en,
                b.city AS branch_city,
                b.latitude AS branch_latitude,
                b.longitude AS branch_longitude,
                b.geofence_radius AS branch_geofence_radius,
                b.settings AS branch_settings,
                0 AS branch_is_locked
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            LEFT JOIN branches b ON u.branch_id = b.id
            WHERE (u.username = :username 
                   OR u.emp_code = :emp_code 
                   OR u.email = :email)
            AND u.is_active = 1
            LIMIT 1";
    
    $user = Database::fetchOne($sql, [
        'username' => $identifier,
        'emp_code' => $identifier,
        'email' => $identifier
    ]);
    
    $result['step1_user_found'] = $user ? true : false;
    $result['step1_user_data'] = $user ? [
        'id' => $user['id'],
        'username' => $user['username'],
        'role_id' => $user['role_id'],
        'branch_id' => $user['branch_id'],
        'role_slug' => $user['role_slug'],
    ] : null;
    
    // الخطوة 2: التحقق من كلمة المرور
    if ($user) {
        $result['step2_password_verify'] = password_verify($password, $user['password']);
    }
    
    // الخطوة 3: فحص الأعمدة المطلوبة في جدول users
    $columns = Database::fetchAll("SHOW COLUMNS FROM users");
    $columnNames = array_column($columns, 'Field');
    $result['step3_users_columns'] = $columnNames;
    
    // فحص الأعمدة المفقودة
    $requiredColumns = ['is_super_admin', 'permissions', 'visible_modules', 'login_attempts', 'locked_until', 'preferences', 'last_activity_at'];
    $missingColumns = [];
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $columnNames)) {
            $missingColumns[] = $col;
        }
    }
    $result['step3_missing_columns'] = $missingColumns;
    
    // الخطوة 4: فحص جدول user_sessions
    try {
        $sessionTableExists = Database::fetchOne("SHOW TABLES LIKE 'user_sessions'");
        $result['step4_user_sessions_exists'] = $sessionTableExists ? true : false;
    } catch (Exception $e) {
        $result['step4_user_sessions_error'] = $e->getMessage();
    }
    
    // الخطوة 5: فحص جدول activity_log
    try {
        $activityTableExists = Database::fetchOne("SHOW TABLES LIKE 'activity_log'");
        $result['step5_activity_log_exists'] = $activityTableExists ? true : false;
    } catch (Exception $e) {
        $result['step5_activity_log_error'] = $e->getMessage();
    }
    
    $result['success'] = true;
    
} catch (PDOException $e) {
    $result['pdo_error'] = $e->getMessage();
    $result['pdo_code'] = $e->getCode();
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
