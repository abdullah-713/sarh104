<?php
/**
 * SARH System - Admin Command Action API
 * API إدارة الفروع والموظفين والنزاهة
 */

// Set error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

// Auth check
if (!is_logged_in()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

// CSRF check
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'رمز أمان غير صالح']));
}

// Role check
$role_level = $_SESSION['role_level'] ?? 1;
if ($role_level < 5) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'صلاحيات غير كافية']));
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        // ═══════════════════════════════════════════════════════════════════
        // BRANCH CRUD
        // ═══════════════════════════════════════════════════════════════════
        
        case 'create_branch':
            $data = [
                'name' => trim($input['name'] ?? ''),
                'code' => strtoupper(trim($input['code'] ?? '')),
                'address' => trim($input['address'] ?? ''),
                'latitude' => floatval($input['latitude'] ?? 0),
                'longitude' => floatval($input['longitude'] ?? 0),
                'geofence_radius' => intval($input['geofence_radius'] ?? 100),
                'is_active' => intval($input['is_active'] ?? 1),
                'is_ghost_branch' => ($role_level >= 10) ? intval($input['is_ghost_branch'] ?? 0) : 0,
                'timezone' => trim($input['timezone'] ?? 'Asia/Riyadh'),
                'city' => trim($input['city'] ?? 'الرياض'),
                'phone' => trim($input['phone'] ?? ''),
                'email' => trim($input['email'] ?? ''),
                'settings' => json_encode([
                    'latitude' => floatval($input['latitude'] ?? 0),
                    'longitude' => floatval($input['longitude'] ?? 0),
                    'geofence_radius' => intval($input['geofence_radius'] ?? 100)
                ])
            ];
            
            if (empty($data['name']) || empty($data['code'])) {
                throw new Exception('الاسم والكود مطلوبان');
            }
            
            // Check code uniqueness
            $existing = Database::fetchOne(
                "SELECT id FROM branches WHERE code = ?",
                [$data['code']]
            );
            if ($existing) {
                throw new Exception('كود الفرع مستخدم مسبقاً');
            }
            
            try {
            $id = Database::insert('branches', $data);
            } catch (Exception $e) {
                error_log('Database insert error for branch: ' . $e->getMessage());
                throw new Exception('فشل إضافة الفرع: ' . $e->getMessage());
            }
            
            try {
            logIntegrity($user_id, 'branch_created', 'branch', $id, $data, 'low');
            } catch (Exception $e) {
                // Don't fail if logging fails
                error_log('Failed to log integrity: ' . $e->getMessage());
            }
            
            echo json_encode(['success' => true, 'id' => $id]);
            break;
            
        case 'update_branch':
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('معرف غير صالح');
            }
            
            $data = [
                'name' => trim($input['name'] ?? ''),
                'code' => strtoupper(trim($input['code'] ?? '')),
                'address' => trim($input['address'] ?? ''),
                'latitude' => floatval($input['latitude'] ?? 0),
                'longitude' => floatval($input['longitude'] ?? 0),
                'geofence_radius' => intval($input['geofence_radius'] ?? 100),
                'is_active' => intval($input['is_active'] ?? 1),
                'timezone' => trim($input['timezone'] ?? 'Asia/Riyadh'),
                'city' => trim($input['city'] ?? 'الرياض'),
                'phone' => trim($input['phone'] ?? ''),
                'email' => trim($input['email'] ?? ''),
                'settings' => json_encode([
                    'latitude' => floatval($input['latitude'] ?? 0),
                    'longitude' => floatval($input['longitude'] ?? 0),
                    'geofence_radius' => intval($input['geofence_radius'] ?? 100)
                ])
            ];
            
            if (empty($data['name']) || empty($data['code'])) {
                throw new Exception('الاسم والكود مطلوبان');
            }
            
            // Check code uniqueness (excluding current branch)
            $existing = Database::fetchOne(
                "SELECT id FROM branches WHERE code = ? AND id != ?",
                [$data['code'], $id]
            );
            if ($existing) {
                throw new Exception('كود الفرع مستخدم مسبقاً');
            }
            
            if ($role_level >= 10) {
                $data['is_ghost_branch'] = intval($input['is_ghost_branch'] ?? 0);
            }
            
            try {
            Database::update('branches', $data, 'id = :id', ['id' => $id]);
            } catch (Exception $e) {
                error_log('Database update error for branch: ' . $e->getMessage());
                throw new Exception('فشل تحديث الفرع: ' . $e->getMessage());
            }
            
            try {
            logIntegrity($user_id, 'branch_updated', 'branch', $id, $data, 'low');
            } catch (Exception $e) {
                // Don't fail if logging fails
                error_log('Failed to log integrity: ' . $e->getMessage());
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_branch':
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) throw new Exception('معرف غير صالح');
            
            // Check if has employees
            $empCount = Database::fetchOne("SELECT COUNT(*) as c FROM users WHERE branch_id = ?", [$id]);
            if ($empCount['c'] > 0) {
                throw new Exception('لا يمكن حذف فرع به موظفين');
            }
            
            Database::delete('branches', 'id = ?', [$id]);
            
            logIntegrity($user_id, 'branch_deleted', 'branch', $id, [], 'medium');
            
            echo json_encode(['success' => true]);
            break;
            
        // ═══════════════════════════════════════════════════════════════════
        // EMPLOYEE CRUD
        // ═══════════════════════════════════════════════════════════════════
        
        case 'create_employee':
            $data = [
                'full_name' => trim($input['full_name'] ?? ''),
                'emp_code' => strtoupper(trim($input['emp_code'] ?? '')),
                'username' => trim($input['username'] ?? ''),
                'email' => trim($input['email'] ?? ''),
                'branch_id' => !empty($input['branch_id']) ? intval($input['branch_id']) : null,
                'role_id' => intval($input['role_id'] ?? 1),
                'is_active' => intval($input['is_active'] ?? 1)
            ];
            
            if (empty($data['full_name']) || empty($data['emp_code']) || empty($data['username']) || empty($data['email'])) {
                throw new Exception('جميع الحقول المطلوبة يجب تعبئتها');
            }
            
            $password = trim($input['password'] ?? '');
            if (empty($password) || strlen($password) < 6) {
                throw new Exception('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
            }
            
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            
            // Check uniqueness
            $exists = Database::fetchOne(
                "SELECT id FROM users WHERE username = ? OR email = ? OR emp_code = ?",
                [$data['username'], $data['email'], $data['emp_code']]
            );
            if ($exists) {
                throw new Exception('اسم المستخدم أو البريد أو كود الموظف موجود مسبقاً');
            }
            
            // Validate role_id exists
            $role_exists = Database::fetchOne("SELECT id FROM roles WHERE id = ?", [$data['role_id']]);
            if (!$role_exists) {
                throw new Exception('الدور المحدد غير موجود');
            }
            
            // Validate branch_id if provided
            if (!empty($data['branch_id'])) {
                $branch_exists = Database::fetchOne("SELECT id FROM branches WHERE id = ?", [$data['branch_id']]);
                if (!$branch_exists) {
                    throw new Exception('الفرع المحدد غير موجود');
                }
            }
            
            // Save photo if provided (optional)
            $photo_data = $input['photo_data'] ?? '';
            if (!empty($photo_data)) {
            $avatar_path = saveEmployeePhoto($photo_data, $data['emp_code']);
            if ($avatar_path) {
                $data['avatar'] = $avatar_path;
                }
                // Don't throw error if photo save fails, just continue without photo
            }
            
            $id = Database::insert('users', $data);
            
            logIntegrity($user_id, 'employee_created', 'user', $id, ['name' => $data['full_name']], 'low');
            
            echo json_encode(['success' => true, 'id' => $id]);
            break;
            
        case 'update_employee':
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) throw new Exception('معرف غير صالح');
            
            // Verify employee exists
            $existing = Database::fetchOne("SELECT id, emp_code, avatar FROM users WHERE id = ?", [$id]);
            if (!$existing) {
                throw new Exception('الموظف غير موجود');
            }
            
            // Process branch_id - handle empty string, null, or zero
            $branch_id_input = $input['branch_id'] ?? '';
            $branch_id = null;
            if (!empty($branch_id_input) && $branch_id_input !== '' && $branch_id_input !== '0') {
                $branch_id = intval($branch_id_input);
            }
            
            $data = [
                'full_name' => trim($input['full_name'] ?? ''),
                'emp_code' => strtoupper(trim($input['emp_code'] ?? '')),
                'username' => trim($input['username'] ?? ''),
                'email' => trim($input['email'] ?? ''),
                'branch_id' => $branch_id,
                'role_id' => intval($input['role_id'] ?? 1),
                'is_active' => intval($input['is_active'] ?? 1)
            ];
            
            // Validate required fields
            if (empty($data['full_name']) || empty($data['emp_code']) || empty($data['username']) || empty($data['email'])) {
                throw new Exception('جميع الحقول المطلوبة يجب تعبئتها');
            }
            
            // Validate role_id exists
            $role_exists = Database::fetchOne("SELECT id FROM roles WHERE id = ?", [$data['role_id']]);
            if (!$role_exists) {
                throw new Exception('الدور المحدد غير موجود');
            }
            
            // Validate branch_id if provided
            if (!empty($data['branch_id'])) {
                $branch_exists = Database::fetchOne("SELECT id FROM branches WHERE id = ?", [$data['branch_id']]);
                if (!$branch_exists) {
                    throw new Exception('الفرع المحدد غير موجود');
                }
            }
            
            // Check uniqueness (excluding self)
            $exists = Database::fetchOne(
                "SELECT id FROM users WHERE (username = ? OR email = ? OR emp_code = ?) AND id != ?",
                [$data['username'], $data['email'], $data['emp_code'], $id]
            );
            if ($exists) {
                throw new Exception('اسم المستخدم أو البريد أو كود الموظف موجود مسبقاً');
            }
            
            // Handle photo update if provided
            $photo_data = $input['photo_data'] ?? '';
            if (!empty($photo_data)) {
                $avatar_path = saveEmployeePhoto($photo_data, $data['emp_code']);
                if (!$avatar_path) {
                    throw new Exception('فشل حفظ صورة الموظف. تأكد من أن الصورة صالحة.');
                }
                        // Delete old avatar if exists
                $old_avatar = $existing['avatar'] ?? '';
                        if ($old_avatar && file_exists(UPLOADS_PATH . '/avatars/' . $old_avatar)) {
                            @unlink(UPLOADS_PATH . '/avatars/' . $old_avatar);
                        }
                        $data['avatar'] = $avatar_path;
                    }
            
            // Update employee
            try {
            Database::update('users', $data, 'id = :id', ['id' => $id]);
            } catch (Exception $e) {
                error_log('Database update error: ' . $e->getMessage());
                throw new Exception('فشل تحديث بيانات الموظف: ' . $e->getMessage());
            }
            
            // Log integrity (only log changed fields, not sensitive data)
            try {
                $log_data = [
                    'full_name' => $data['full_name'],
                    'emp_code' => $data['emp_code'],
                    'role_id' => $data['role_id'],
                    'branch_id' => $data['branch_id'],
                    'is_active' => $data['is_active']
                ];
                logIntegrity($user_id, 'employee_updated', 'user', $id, $log_data, 'low');
            } catch (Exception $e) {
                // Don't fail the update if logging fails
                error_log('Failed to log integrity: ' . $e->getMessage());
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'toggle_employee':
            $id = intval($input['id'] ?? 0);
            $is_active = intval($input['is_active'] ?? 0);
            
            Database::update('users', ['is_active' => $is_active], 'id = :id', ['id' => $id]);
            
            logIntegrity($user_id, $is_active ? 'employee_activated' : 'employee_deactivated', 'user', $id, [], 'medium');
            
            echo json_encode(['success' => true]);
            break;
            
        case 'reset_password':
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) throw new Exception('معرف غير صالح');
            
            // إنشاء كلمة مرور عشوائية آمنة
            $newPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
            
            // تحديث كلمة المرور في قاعدة البيانات
            Database::update('users', [
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'login_attempts' => 0,
                'locked_until' => null
            ], 'id = :id', ['id' => $id]);
            
            logIntegrity($user_id, 'password_reset', 'user', $id, [], 'high');
            
            echo json_encode(['success' => true, 'new_password' => $newPassword]);
            break;
            
        // ═══════════════════════════════════════════════════════════════════
        // INTEGRITY
        // ═══════════════════════════════════════════════════════════════════
        
        case 'mark_reviewed':
            if ($role_level < 8) throw new Exception('صلاحيات غير كافية');
            
            $id = intval($input['id'] ?? 0);
            
            Database::update('integrity_logs', [
                'is_reviewed' => 1,
                'reviewed_by' => $user_id,
                'reviewed_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $id]);
            
            echo json_encode(['success' => true]);
            break;
            
        // ═══════════════════════════════════════════════════════════════════
        // GHOST BRANCH PROBE (Called from attendance)
        // ═══════════════════════════════════════════════════════════════════
        
        case 'log_ghost_probe':
            $branch_id = intval($input['branch_id'] ?? 0);
            $lat = floatval($input['latitude'] ?? 0);
            $lng = floatval($input['longitude'] ?? 0);
            
            // Verify it's actually a ghost branch
            $branch = Database::fetchOne("SELECT * FROM branches WHERE id = ? AND is_ghost_branch = 1", [$branch_id]);
            
            if ($branch) {
                logIntegrity(
                    $user_id,
                    'ghost_probe',
                    'branch',
                    $branch_id,
                    [
                        'branch_name' => $branch['name'],
                        'user_location' => ['lat' => $lat, 'lng' => $lng],
                        'attempted_at' => date('Y-m-d H:i:s')
                    ],
                    'critical'
                );
            }
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            throw new Exception('إجراء غير معروف');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    $errorMsg = $e->getMessage();
    $errorTrace = $e->getTraceAsString();
    error_log('API Error in command_action.php: ' . $errorMsg . ' | Trace: ' . $errorTrace);
    
    // Return error message
    $message = $errorMsg;
    if (strpos($message, 'SQLSTATE') !== false || strpos($message, 'SQL') !== false || strpos($message, 'PDO') !== false) {
        $message = 'حدث خطأ في قاعدة البيانات. يرجى التحقق من البيانات المدخلة.';
    }
    
    // Ensure we output valid JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false, 
        'message' => $message,
        'error_code' => $e->getCode()
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Error $e) {
    http_response_code(500);
    error_log('Fatal Error in command_action.php: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
    
    // Ensure we output valid JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false, 
        'message' => 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Log integrity event
 */
function logIntegrity($user_id, $action_type, $target_type, $target_id, $details, $severity) {
    try {
        Database::insert('integrity_logs', [
            'user_id' => $user_id,
            'action_type' => $action_type,
            'target_type' => $target_type,
            'target_id' => $target_id,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'severity' => $severity,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log('Failed to log integrity: ' . $e->getMessage());
    }
}

/**
 * Save employee photo from base64 data
 */
function saveEmployeePhoto($base64_data, $emp_code) {
    try {
        // Validate base64 image data
        if (preg_match('/^data:image\/(\w+);base64,/', $base64_data, $matches)) {
            $image_type = $matches[1];
            $image_data = substr($base64_data, strpos($base64_data, ',') + 1);
            $image_data = base64_decode($image_data);
            
            // Validate image type
            if (!in_array(strtolower($image_type), ['jpeg', 'jpg', 'png', 'gif', 'webp'])) {
                throw new Exception('نوع الصورة غير مدعوم');
            }
            
            // Create uploads directory if it doesn't exist
            $uploads_dir = UPLOADS_PATH . '/avatars';
            if (!is_dir($uploads_dir)) {
                if (!mkdir($uploads_dir, 0755, true)) {
                    throw new Exception('فشل إنشاء مجلد الصور');
                }
            }
            
            // Check if directory is writable
            if (!is_writable($uploads_dir)) {
                // Try to make it writable
                @chmod($uploads_dir, 0755);
                if (!is_writable($uploads_dir)) {
                    throw new Exception('مجلد الصور غير قابل للكتابة. يرجى التحقق من الصلاحيات.');
                }
            }
            
            // Generate filename
            $filename = 'emp_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $emp_code) . '_' . time() . '.' . $image_type;
            $filepath = $uploads_dir . '/' . $filename;
            
            // Save file
            if (file_put_contents($filepath, $image_data) === false) {
                throw new Exception('فشل حفظ الصورة');
            }
            
            // Return relative path
            return 'avatars/' . $filename;
            
        } else {
            throw new Exception('بيانات الصورة غير صالحة');
        }
    } catch (Exception $e) {
        error_log('Failed to save employee photo: ' . $e->getMessage());
        return null;
    }
}
