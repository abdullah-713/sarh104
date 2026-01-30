<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - FLEET MANAGEMENT API                                 ║
 * ║           واجهة إدارة أسطول السيارات                                          ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Version: 1.0.0                                                              ║
 * ║  Endpoint: /api/fleet/vehicles.php                                           ║
 * ║  Methods: GET (list, status), POST (assign, return, report)                  ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

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

$user_id = intval($_SESSION['user_id'] ?? 0);
$branch_id = intval($_SESSION['branch_id'] ?? 0);
$role_level = intval($_SESSION['role_level'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'];

// ═══════════════════════════════════════════════════════════════════════════════
// GET - FETCH VEHICLES
// ═══════════════════════════════════════════════════════════════════════════════

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    try {
        if ($action === 'list') {
            // جلب قائمة السيارات
            $sql = "SELECT v.*, 
                           b.name as branch_name,
                           u.full_name as current_driver_name,
                           CASE 
                               WHEN va.id IS NOT NULL AND va.status = 'active' THEN 'in_use'
                               WHEN v.status = 'maintenance' THEN 'maintenance'
                               ELSE 'available'
                           END as availability_status
                    FROM fleet_vehicles v
                    LEFT JOIN branches b ON b.id = v.branch_id
                    LEFT JOIN vehicle_assignments va ON va.vehicle_id = v.id AND va.status = 'active'
                    LEFT JOIN users u ON u.id = va.user_id
                    WHERE v.is_active = 1";
            
            $params = [];
            
            // فلترة حسب الفرع للمستخدمين العاديين
            if ($role_level < 80 && $branch_id > 0) {
                $sql .= " AND v.branch_id = ?";
                $params[] = $branch_id;
            }
            
            // فلترة حسب الحالة
            if (!empty($_GET['status'])) {
                $sql .= " AND v.status = ?";
                $params[] = $_GET['status'];
            }
            
            $sql .= " ORDER BY v.plate_number ASC";
            
            $vehicles = Database::fetchAll($sql, $params);
            
            echo json_encode([
                'success' => true,
                'vehicles' => $vehicles,
                'count' => count($vehicles)
            ], JSON_UNESCAPED_UNICODE);
            
        } elseif ($action === 'my_vehicle') {
            // سيارتي الحالية
            $assignment = Database::fetchOne(
                "SELECT va.*, v.plate_number, v.make, v.model, v.year, v.color,
                        v.fuel_type, v.current_mileage, v.image_url
                 FROM vehicle_assignments va
                 JOIN fleet_vehicles v ON v.id = va.vehicle_id
                 WHERE va.user_id = ? AND va.status = 'active'
                 ORDER BY va.assigned_at DESC LIMIT 1",
                [$user_id]
            );
            
            echo json_encode([
                'success' => true,
                'has_vehicle' => !empty($assignment),
                'assignment' => $assignment
            ], JSON_UNESCAPED_UNICODE);
            
        } elseif ($action === 'available') {
            // السيارات المتاحة للاستلام
            $vehicles = Database::fetchAll(
                "SELECT v.* FROM fleet_vehicles v
                 WHERE v.is_active = 1 
                   AND v.status = 'available'
                   AND v.branch_id = ?
                   AND v.id NOT IN (
                       SELECT vehicle_id FROM vehicle_assignments WHERE status = 'active'
                   )
                 ORDER BY v.plate_number ASC",
                [$branch_id]
            );
            
            echo json_encode([
                'success' => true,
                'vehicles' => $vehicles
            ], JSON_UNESCAPED_UNICODE);
            
        } elseif ($action === 'history') {
            // سجل استخدام السيارات
            $vehicle_id = intval($_GET['vehicle_id'] ?? 0);
            $limit = min(intval($_GET['limit'] ?? 20), 100);
            
            $sql = "SELECT va.*, u.full_name as driver_name
                    FROM vehicle_assignments va
                    JOIN users u ON u.id = va.user_id
                    WHERE 1=1";
            
            $params = [];
            
            if ($vehicle_id > 0) {
                $sql .= " AND va.vehicle_id = ?";
                $params[] = $vehicle_id;
            }
            
            $sql .= " ORDER BY va.assigned_at DESC LIMIT ?";
            $params[] = $limit;
            
            $history = Database::fetchAll($sql, $params);
            
            echo json_encode([
                'success' => true,
                'history' => $history
            ], JSON_UNESCAPED_UNICODE);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'server_error',
            'message' => 'حدث خطأ في جلب بيانات الأسطول'
        ], JSON_UNESCAPED_UNICODE);
    }
    
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
// POST - VEHICLE OPERATIONS
// ═══════════════════════════════════════════════════════════════════════════════

if ($method === 'POST') {
    // CSRF verification
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($csrf_token) || !verify_csrf($csrf_token)) {
        http_response_code(403);
        die(json_encode([
            'success' => false,
            'error' => 'csrf_invalid',
            'message' => 'رمز الأمان غير صالح'
        ], JSON_UNESCAPED_UNICODE));
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    try {
        Database::beginTransaction();
        
        switch ($action) {
            case 'assign':
                // استلام سيارة
                $vehicle_id = intval($input['vehicle_id'] ?? 0);
                $mileage = intval($input['mileage'] ?? 0);
                $notes = trim($input['notes'] ?? '');
                
                if (!$vehicle_id) {
                    throw new Exception('يرجى تحديد السيارة');
                }
                
                // التحقق من السيارة
                $vehicle = Database::fetchOne(
                    "SELECT * FROM fleet_vehicles WHERE id = ? AND is_active = 1",
                    [$vehicle_id]
                );
                
                if (!$vehicle) {
                    throw new Exception('السيارة غير موجودة');
                }
                
                if ($vehicle['status'] !== 'available') {
                    throw new Exception('السيارة غير متاحة حالياً');
                }
                
                // التحقق من عدم وجود سيارة أخرى
                $existing = Database::fetchOne(
                    "SELECT id FROM vehicle_assignments WHERE user_id = ? AND status = 'active'",
                    [$user_id]
                );
                
                if ($existing) {
                    throw new Exception('لديك سيارة مُستلمة بالفعل. يرجى تسليمها أولاً');
                }
                
                // التحقق من التوفر
                $taken = Database::fetchOne(
                    "SELECT id FROM vehicle_assignments WHERE vehicle_id = ? AND status = 'active'",
                    [$vehicle_id]
                );
                
                if ($taken) {
                    throw new Exception('السيارة مُستلمة من قبل شخص آخر');
                }
                
                // تسجيل الاستلام
                $assignment_id = Database::insert('vehicle_assignments', [
                    'vehicle_id' => $vehicle_id,
                    'user_id' => $user_id,
                    'assigned_at' => date('Y-m-d H:i:s'),
                    'start_mileage' => $mileage ?: $vehicle['current_mileage'],
                    'status' => 'active',
                    'notes' => $notes
                ]);
                
                // تحديث حالة السيارة
                Database::update('fleet_vehicles', [
                    'status' => 'in_use',
                    'current_mileage' => $mileage ?: $vehicle['current_mileage']
                ], 'id = :id', ['id' => $vehicle_id]);
                
                Database::commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => "✅ تم استلام السيارة: {$vehicle['plate_number']}",
                    'assignment_id' => $assignment_id,
                    'vehicle' => [
                        'plate_number' => $vehicle['plate_number'],
                        'make' => $vehicle['make'],
                        'model' => $vehicle['model']
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'return':
                // تسليم سيارة
                $mileage = intval($input['mileage'] ?? 0);
                $fuel_level = intval($input['fuel_level'] ?? 0);
                $notes = trim($input['notes'] ?? '');
                $condition = $input['condition'] ?? 'good';
                
                // جلب السيارة المستلمة
                $assignment = Database::fetchOne(
                    "SELECT va.*, v.plate_number, v.current_mileage as old_mileage
                     FROM vehicle_assignments va
                     JOIN fleet_vehicles v ON v.id = va.vehicle_id
                     WHERE va.user_id = ? AND va.status = 'active'",
                    [$user_id]
                );
                
                if (!$assignment) {
                    throw new Exception('لا يوجد سيارة مستلمة للتسليم');
                }
                
                // حساب المسافة
                $distance = $mileage - $assignment['start_mileage'];
                
                // تحديث سجل الاستلام
                Database::update('vehicle_assignments', [
                    'returned_at' => date('Y-m-d H:i:s'),
                    'end_mileage' => $mileage,
                    'distance_traveled' => $distance > 0 ? $distance : 0,
                    'fuel_level_return' => $fuel_level,
                    'condition_on_return' => $condition,
                    'return_notes' => $notes,
                    'status' => 'completed'
                ], 'id = :id', ['id' => $assignment['id']]);
                
                // تحديث السيارة
                $new_status = ($condition === 'needs_maintenance') ? 'maintenance' : 'available';
                Database::update('fleet_vehicles', [
                    'status' => $new_status,
                    'current_mileage' => $mileage,
                    'last_used_at' => date('Y-m-d H:i:s')
                ], 'id = :id', ['id' => $assignment['vehicle_id']]);
                
                Database::commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => "✅ تم تسليم السيارة: {$assignment['plate_number']}",
                    'summary' => [
                        'distance' => $distance,
                        'duration_hours' => round((time() - strtotime($assignment['assigned_at'])) / 3600, 1)
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'report_issue':
                // الإبلاغ عن مشكلة
                $issue_type = $input['issue_type'] ?? 'other';
                $description = trim($input['description'] ?? '');
                $severity = $input['severity'] ?? 'medium';
                
                if (empty($description)) {
                    throw new Exception('يرجى وصف المشكلة');
                }
                
                // جلب السيارة المستلمة
                $assignment = Database::fetchOne(
                    "SELECT * FROM vehicle_assignments WHERE user_id = ? AND status = 'active'",
                    [$user_id]
                );
                
                if (!$assignment) {
                    throw new Exception('لا يوجد سيارة مستلمة للإبلاغ عنها');
                }
                
                // تسجيل المشكلة
                $issue_id = Database::insert('vehicle_issues', [
                    'vehicle_id' => $assignment['vehicle_id'],
                    'reported_by' => $user_id,
                    'assignment_id' => $assignment['id'],
                    'issue_type' => $issue_type,
                    'description' => $description,
                    'severity' => $severity,
                    'status' => 'open',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // إذا كانت المشكلة خطيرة، تحديث حالة السيارة
                if ($severity === 'critical') {
                    Database::update('fleet_vehicles', [
                        'status' => 'maintenance'
                    ], 'id = :id', ['id' => $assignment['vehicle_id']]);
                }
                
                // إرسال إشعار للمسؤولين
                $admins = Database::fetchAll(
                    "SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE role_level >= 80) AND is_active = 1"
                );
                
                foreach ($admins as $admin) {
                    Database::insert('notifications', [
                        'user_id' => $admin['id'],
                        'type' => 'vehicle_issue',
                        'title' => 'إبلاغ عن مشكلة في سيارة',
                        'message' => "تم الإبلاغ عن مشكلة ({$issue_type}) - الخطورة: {$severity}",
                        'data' => json_encode(['issue_id' => $issue_id, 'vehicle_id' => $assignment['vehicle_id']]),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
                
                Database::commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => '✅ تم إرسال البلاغ بنجاح',
                    'issue_id' => $issue_id
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception('العملية غير مدعومة');
        }
        
    } catch (Exception $e) {
        Database::rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'operation_failed',
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    
    exit;
}

http_response_code(405);
echo json_encode([
    'success' => false,
    'error' => 'method_not_allowed',
    'message' => 'طريقة الطلب غير مدعومة'
], JSON_UNESCAPED_UNICODE);
