<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║                    🛠️ أدوات قاعدة البيانات - Database Tools                  ║
 * ║                         للمدير العام فقط                                      ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// التحقق من صلاحيات المدير
$user = get_current_user_data();
if (($user['role_level'] ?? 0) < 10) {
    die('غير مصرح لك بالوصول لهذه الصفحة');
}

$message = '';
$messageType = '';

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // التحقق من CSRF
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = 'خطأ في التحقق من الأمان';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'clean_database') {
            $result = cleanDatabaseKeepCore();
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
        }
        
        if ($action === 'generate_test_data') {
            $result = generateRealisticTestData();
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
        }
        
        if ($action === 'delete_users_keep_admins') {
            $result = deleteUsersKeepAdmins();
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
        }
        
        if ($action === 'add_deployment_data') {
            $result = addDeploymentData();
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
        }
    }
}

/**
 * إضافة بيانات النشر
 */
function addDeploymentData(): array {
    try {
        // تعريف بيانات الموظفين حسب الفروع
        // الفروع: 1=الرئيسي, 2=كورنر, 3=صرح2, 4=فضاء1, 5=فضاء2
        
        $employees = [
            // فضاء المحركات 2 (ID: 5)
            ['name_ar' => 'جهاد', 'username' => 'jihad', 'email' => 'jihad@sarh.io', 'password' => 'Aa123456', 'branch_id' => 5],
            ['name_ar' => 'قتيبة', 'username' => 'qutaiba', 'email' => 'qutaiba@sarh.io', 'password' => 'Aa123456', 'branch_id' => 5],
            
            // صرح الاتقان كورنر (ID: 2) - افتراض أنه "صرح 1"
            ['name_ar' => 'عبدالحكيم المذهول', 'username' => 'abdulhakim.almadhool', 'email' => 'abdulhakim.almadhool@sarh.io', 'password' => 'Aa123456', 'branch_id' => 2],
            ['name_ar' => 'عبدالله الكردي', 'username' => 'abdullah.alkurdi', 'email' => 'abdullah.alkurdi@sarh.io', 'password' => 'Aa123456', 'branch_id' => 2],
            ['name_ar' => 'عبدالهادي', 'username' => 'abdulhadi', 'email' => 'abdulhadi@sarh.io', 'password' => 'Aa123456', 'branch_id' => 2],
            ['name_ar' => 'ابو شادي', 'username' => 'abushadi', 'email' => 'abushadi@sarh.io', 'password' => 'Aa123456', 'branch_id' => 2],
            ['name_ar' => 'ابو عوض', 'username' => 'abuawad', 'email' => 'abuawad@sarh.io', 'password' => 'Aa123456', 'branch_id' => 2],
            ['name_ar' => 'الشيخ', 'username' => 'alshaikh', 'email' => 'alshaikh@sarh.io', 'password' => 'Aa123456', 'branch_id' => 2],
            ['name_ar' => 'شعبان', 'username' => 'shaaban', 'email' => 'shaaban@sarh.io', 'password' => 'Aa123456', 'branch_id' => 2],
            ['name_ar' => 'عنايات', 'username' => 'anayat', 'email' => 'anayat@sarh.io', 'password' => 'Aa123456', 'branch_id' => 2],
            ['name_ar' => 'محمد بلال', 'username' => 'mohammad.balal', 'email' => 'mohammad.balal@sarh.io', 'password' => 'Aa123456', 'branch_id' => 2],
            ['name_ar' => 'مصعب', 'username' => 'musab', 'email' => 'musab@sarh.io', 'password' => 'Aa123456', 'branch_id' => 2],
            ['name_ar' => 'وداعة', 'username' => 'wadaa', 'email' => 'wadaa@sarh.io', 'password' => 'Aa123456', 'branch_id' => 2],
            
            // صرح الاتقان 2 (ID: 3)
            ['name_ar' => 'أبو سليمان', 'username' => 'abusulayman', 'email' => 'abusulayman@sarh.io', 'password' => 'Aa123456', 'branch_id' => 3],
            ['name_ar' => 'أحمد كهربائي', 'username' => 'ahmad.kahrabai', 'email' => 'ahmad.kahrabai@sarh.io', 'password' => 'Aa123456', 'branch_id' => 3],
            ['name_ar' => 'إسكندر', 'username' => 'iskandar', 'email' => 'iskandar@sarh.io', 'password' => 'Aa123456', 'branch_id' => 3],
            ['name_ar' => 'بخاري', 'username' => 'bukhari', 'email' => 'bukhari@sarh.io', 'password' => 'Aa123456', 'branch_id' => 3],
            ['name_ar' => 'جوزيف', 'username' => 'joseph', 'email' => 'joseph@sarh.io', 'password' => 'Aa123456', 'branch_id' => 3],
            ['name_ar' => 'شريف', 'username' => 'shareef', 'email' => 'shareef@sarh.io', 'password' => 'Aa123456', 'branch_id' => 3],
            ['name_ar' => 'صابر', 'username' => 'saber', 'email' => 'saber@sarh.io', 'password' => 'Aa123456', 'branch_id' => 3],
            
            // صرح الاتقان الرئيسي (ID: 1)
            ['name_ar' => 'أيمن', 'username' => 'ayman', 'email' => 'ayman@sarh.io', 'password' => 'Aa123456', 'branch_id' => 1],
            ['name_ar' => 'عبد الله', 'username' => 'abdullah', 'email' => 'abdullah@sarh.io', 'password' => 'Aa123456', 'branch_id' => 1],
            ['name_ar' => 'زاهر', 'username' => 'zaher', 'email' => 'zaher@sarh.io', 'password' => 'Aa123456', 'branch_id' => 1],
            ['name_ar' => 'لطفي', 'username' => 'lotfi', 'email' => 'lotfi@sarh.io', 'password' => 'Aa123456', 'branch_id' => 1],
            ['name_ar' => 'نجيب', 'username' => 'najeeb', 'email' => 'najeeb@sarh.io', 'password' => 'Aa123456', 'branch_id' => 1],
        ];
        
        $added = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($employees as $emp) {
            try {
                // التحقق من وجود المستخدم
                $exists = Database::fetchOne(
                    "SELECT id FROM users WHERE username = ? OR email = ?",
                    [$emp['username'], $emp['email']]
                );
                
                if ($exists) {
                    $skipped++;
                    continue;
                }
                
                // توليد emp_code
                $emp_code = strtoupper(substr($emp['username'], 0, 3)) . str_pad($added + 1, 4, '0', STR_PAD_LEFT);
                
                // إضافة المستخدم
                $user_id = Database::insert('users', [
                    'emp_code' => $emp_code,
                    'username' => $emp['username'],
                    'email' => $emp['email'],
                    'password_hash' => password_hash($emp['password'], PASSWORD_DEFAULT),
                    'full_name' => $emp['name_ar'],
                    'role_id' => 1, // موظف
                    'branch_id' => $emp['branch_id'],
                    'is_active' => 1,
                    'current_points' => 1000
                ]);
                
                // إضافة جدول موظف افتراضي
                Database::insert('employee_schedules', [
                    'user_id' => $user_id,
                    'work_start_time' => '08:00:00',
                    'work_end_time' => '21:00:00',
                    'grace_period_minutes' => 15,
                    'attendance_mode' => 'unrestricted',
                    'working_days' => json_encode([0,1,2,3,4,5,6]),
                    'geofence_radius' => 150,
                    'is_flexible_hours' => 1,
                    'remote_checkin_allowed' => 1,
                    'is_active' => 1
                ]);
                
                $added++;
                
            } catch (Exception $e) {
                $errors[] = "خطأ في إضافة {$emp['name_ar']}: " . $e->getMessage();
            }
        }
        
        // تسجيل النشاط
        if (function_exists('log_activity')) {
            log_activity(
                'admin_action',
                'system',
                "إضافة بيانات النشر - تم إضافة {$added} موظف جديد",
                current_user_id(),
                'user'
            );
        }
        
        $message = "✅ تم إضافة بيانات النشر بنجاح!\n\n" .
                  "➕ عدد الموظفين المضافين: {$added}\n" .
                  "⏭️ عدد الموظفين المستبعدين (موجودين مسبقاً): {$skipped}\n" .
                  "📋 إجمالي الموظفين في القائمة: " . count($employees);
        
        if (!empty($errors)) {
            $message .= "\n\n⚠️ الأخطاء:\n" . implode("\n", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= "\n... و" . (count($errors) - 5) . " خطأ آخر";
            }
        }
        
        return [
            'success' => true,
            'message' => $message
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'خطأ: ' . $e->getMessage()
        ];
    }
}

/**
 * حذف جميع المستخدمين مع الإبقاء على مدراء النظام
 */
function deleteUsersKeepAdmins(): array {
    try {
        // جلب عدد المستخدمين قبل الحذف
        $totalUsers = Database::fetchValue("SELECT COUNT(*) FROM users");
        
        // جلب المستخدمين الذين سيتم الإبقاء عليهم (role_id = 5 أو 6)
        $adminUsers = Database::fetchAll(
            "SELECT id, username, full_name, role_id FROM users WHERE role_id IN (5, 6) ORDER BY id"
        );
        
        $adminCount = count($adminUsers);
        $usersToDelete = $totalUsers - $adminCount;
        
        if ($usersToDelete <= 0) {
            return [
                'success' => true,
                'message' => "✅ لا يوجد مستخدمون للحذف!\n" .
                            "👥 إجمالي المستخدمين: {$totalUsers}\n" .
                            "🔐 مدراء النظام: {$adminCount}"
            ];
        }
        
        // جلب قائمة معرفات مدراء النظام
        $adminIds = array_column($adminUsers, 'id');
        $adminIdsPlaceholder = implode(',', array_fill(0, count($adminIds), '?'));
        
        // حذف المستخدمين (جميع المستخدمين ما عدا role_id = 5 أو 6)
        $result = Database::query("DELETE FROM users WHERE role_id NOT IN (5, 6)");
        $deletedCount = $result->rowCount();
        
        // حذف السجلات المرتبطة بالمستخدمين المحذوفين
        // حذف سجلات الحضور
        if (count($adminIds) > 0) {
            Database::query("DELETE FROM attendance WHERE user_id NOT IN ({$adminIdsPlaceholder})", $adminIds);
        } else {
            Database::query("DELETE FROM attendance");
        }
        
        // حذف الإشعارات (استخدام scope_type و scope_id)
        if (count($adminIds) > 0) {
            Database::query("DELETE FROM notifications WHERE scope_type = 'user' AND scope_id NOT IN ({$adminIdsPlaceholder})", $adminIds);
        } else {
            Database::query("DELETE FROM notifications WHERE scope_type = 'user'");
        }
        
        // حذف الإجازات
        if (count($adminIds) > 0) {
            Database::query("DELETE FROM leaves WHERE user_id NOT IN ({$adminIdsPlaceholder})", $adminIds);
        } else {
            Database::query("DELETE FROM leaves");
        }
        
        // حذف سجلات النشاط (للمستخدمين المحذوفين فقط)
        if (count($adminIds) > 0) {
            Database::query("DELETE FROM activity_log WHERE user_id IS NOT NULL AND user_id NOT IN ({$adminIdsPlaceholder})", $adminIds);
        } else {
            Database::query("DELETE FROM activity_log WHERE user_id IS NOT NULL");
        }
        
        // حذف جداول الموظفين
        if (count($adminIds) > 0) {
            Database::query("DELETE FROM employee_schedules WHERE user_id NOT IN ({$adminIdsPlaceholder})", $adminIds);
        } else {
            Database::query("DELETE FROM employee_schedules");
        }
        
        // تسجيل النشاط
        if (function_exists('log_activity')) {
            log_activity(
                'admin_action',
                'system',
                "حذف جميع المستخدمين مع الإبقاء على مدراء النظام - تم حذف {$deletedCount} مستخدم",
                current_user_id(),
                'user'
            );
        }
        
        $adminList = implode("\n   • ", array_map(function($admin) {
            return "{$admin['full_name']} ({$admin['username']}) - Role ID: {$admin['role_id']}";
        }, $adminUsers));
        
        return [
            'success' => true,
            'message' => "✅ تم حذف جميع المستخدمين بنجاح!\n\n" .
                        "🗑️ عدد المستخدمين المحذوفين: {$deletedCount}\n" .
                        "🔐 عدد مدراء النظام المحفوظين: {$adminCount}\n\n" .
                        "👥 مدراء النظام المحفوظون:\n   • {$adminList}"
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'خطأ: ' . $e->getMessage()
        ];
    }
}

/**
 * تنظيف قاعدة البيانات مع الإبقاء على البيانات الأساسية
 */
function cleanDatabaseKeepCore(): array {
    try {
        $stats = [
            'attendance_deleted' => 0,
            'activity_log_deleted' => 0,
            'notifications_deleted' => 0,
            'leaves_deleted' => 0
        ];
        
        // حذف سجلات الحضور
        $result = Database::query("DELETE FROM attendance");
        $stats['attendance_deleted'] = $result->rowCount();
        
        // حذف سجل النشاط
        $result = Database::query("DELETE FROM activity_log");
        $stats['activity_log_deleted'] = $result->rowCount();
        
        // حذف الإشعارات
        $result = Database::query("DELETE FROM notifications");
        $stats['notifications_deleted'] = $result->rowCount();
        
        // حذف الإجازات
        $result = Database::query("DELETE FROM leaves");
        $stats['leaves_deleted'] = $result->rowCount();
        
        // إعادة تعيين عدادات المستخدمين
        Database::query("UPDATE users SET 
            streak_count = 0,
            current_points = 1000,
            total_points_earned = 0,
            total_points_deducted = 0,
            is_online = 0,
            last_latitude = NULL,
            last_longitude = NULL
        ");
        
        // إعادة تعيين AUTO_INCREMENT
        Database::query("ALTER TABLE attendance AUTO_INCREMENT = 1");
        Database::query("ALTER TABLE activity_log AUTO_INCREMENT = 1");
        Database::query("ALTER TABLE notifications AUTO_INCREMENT = 1");
        
        return [
            'success' => true,
            'message' => "✅ تم تنظيف قاعدة البيانات بنجاح!\n" .
                        "📋 سجلات الحضور المحذوفة: {$stats['attendance_deleted']}\n" .
                        "📋 سجلات النشاط المحذوفة: {$stats['activity_log_deleted']}\n" .
                        "📋 الإشعارات المحذوفة: {$stats['notifications_deleted']}\n" .
                        "✨ تم الإبقاء على: 5 فروع + جميع الموظفين"
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'خطأ: ' . $e->getMessage()
        ];
    }
}

/**
 * توليد بيانات تجريبية واقعية لشهرين
 */
function generateRealisticTestData(): array {
    try {
        // جلب جميع الموظفين
        $employees = Database::fetchAll(
            "SELECT u.id, u.full_name, u.branch_id, b.latitude as branch_lat, b.longitude as branch_lng,
                    COALESCE(b.geofence_radius, 50) as geofence_radius
             FROM users u
             LEFT JOIN branches b ON u.branch_id = b.id
             WHERE u.is_active = 1 AND u.role_id != (SELECT id FROM roles WHERE level = 99 LIMIT 1)"
        );
        
        if (empty($employees)) {
            return ['success' => false, 'message' => 'لا يوجد موظفون لتوليد بيانات لهم'];
        }
        
        // تصنيف الموظفين حسب الالتزام (عشوائي لكن ثابت لكل موظف)
        $employeeProfiles = [];
        foreach ($employees as $emp) {
            // تحديد نوع الموظف بناءً على ID (للثبات)
            $seed = crc32($emp['full_name']);
            $profileType = $seed % 100;
            
            if ($profileType < 25) {
                // 25% ممتازون - دائماً في الوقت
                $employeeProfiles[$emp['id']] = [
                    'type' => 'excellent',
                    'late_probability' => 0.05,
                    'early_leave_probability' => 0.02,
                    'absence_probability' => 0.02,
                    'max_late_minutes' => 10,
                    'typical_overtime' => rand(15, 45)
                ];
            } elseif ($profileType < 55) {
                // 30% جيدون - أحياناً متأخرون قليلاً
                $employeeProfiles[$emp['id']] = [
                    'type' => 'good',
                    'late_probability' => 0.15,
                    'early_leave_probability' => 0.08,
                    'absence_probability' => 0.05,
                    'max_late_minutes' => 20,
                    'typical_overtime' => rand(0, 20)
                ];
            } elseif ($profileType < 80) {
                // 25% متوسطون - متأخرون أحياناً
                $employeeProfiles[$emp['id']] = [
                    'type' => 'average',
                    'late_probability' => 0.30,
                    'early_leave_probability' => 0.15,
                    'absence_probability' => 0.08,
                    'max_late_minutes' => 45,
                    'typical_overtime' => 0
                ];
            } else {
                // 20% ضعيفون - كثيراً ما يتأخرون
                $employeeProfiles[$emp['id']] = [
                    'type' => 'poor',
                    'late_probability' => 0.50,
                    'early_leave_probability' => 0.25,
                    'absence_probability' => 0.12,
                    'max_late_minutes' => 90,
                    'typical_overtime' => 0
                ];
            }
        }
        
        // توليد البيانات لآخر 60 يوم
        $startDate = date('Y-m-d', strtotime('-60 days'));
        $endDate = date('Y-m-d', strtotime('-1 day'));
        
        $totalRecords = 0;
        $currentDate = $startDate;
        
        while ($currentDate <= $endDate) {
            $dayOfWeek = date('N', strtotime($currentDate)); // 1=Mon, 7=Sun
            
            // تخطي الجمعة (يوم 5) - إجازة
            if ($dayOfWeek == 5) {
                $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                continue;
            }
            
            foreach ($employees as $emp) {
                $profile = $employeeProfiles[$emp['id']];
                
                // التحقق من الغياب
                if (rand(1, 100) / 100 < $profile['absence_probability']) {
                    // غائب هذا اليوم
                    continue;
                }
                
                // وقت بداية الدوام الأساسي: 8:00
                $baseStartTime = strtotime('08:00:00');
                $baseEndTime = strtotime('17:00:00'); // 9 ساعات
                
                // حساب التأخير
                $lateMinutes = 0;
                if (rand(1, 100) / 100 < $profile['late_probability']) {
                    $lateMinutes = rand(5, $profile['max_late_minutes']);
                }
                
                // وقت الحضور
                $checkInTime = date('H:i:s', $baseStartTime + ($lateMinutes * 60) - rand(0, 10) * 60);
                if ($lateMinutes == 0) {
                    // الممتازون يأتون مبكراً
                    $earlyMinutes = rand(5, 30);
                    $checkInTime = date('H:i:s', $baseStartTime - ($earlyMinutes * 60));
                }
                
                // حساب الانصراف المبكر
                $earlyLeaveMinutes = 0;
                if (rand(1, 100) / 100 < $profile['early_leave_probability']) {
                    $earlyLeaveMinutes = rand(10, 60);
                }
                
                // وقت الانصراف مع إضافة العمل الإضافي للممتازين
                $overtimeMinutes = 0;
                if ($profile['type'] === 'excellent' || $profile['type'] === 'good') {
                    $overtimeMinutes = rand(0, $profile['typical_overtime']);
                }
                
                $checkOutTime = date('H:i:s', $baseEndTime - ($earlyLeaveMinutes * 60) + ($overtimeMinutes * 60));
                
                // حساب دقائق العمل الفعلية
                $checkInTimestamp = strtotime($checkInTime);
                $checkOutTimestamp = strtotime($checkOutTime);
                $workMinutes = max(0, ($checkOutTimestamp - $checkInTimestamp) / 60);
                
                // حساب النقاط
                $penaltyPoints = 0;
                $bonusPoints = 0;
                
                if ($lateMinutes > 0) {
                    $penaltyPoints += min(20, $lateMinutes * 0.5);
                }
                if ($earlyLeaveMinutes > 0) {
                    $penaltyPoints += min(15, $earlyLeaveMinutes * 0.3);
                }
                if ($overtimeMinutes > 0) {
                    $bonusPoints += min(30, $overtimeMinutes * 0.5);
                }
                if ($lateMinutes == 0 && $earlyLeaveMinutes == 0) {
                    $bonusPoints += 5; // مكافأة الالتزام
                }
                
                // تحديد الحالة
                $status = 'present';
                if ($lateMinutes > 30) {
                    $status = 'late';
                }
                
                // توليد موقع عشوائي قريب من الفرع
                $lat = $emp['branch_lat'];
                $lng = $emp['branch_lng'];
                
                if ($lat && $lng) {
                    // إضافة انحراف صغير (داخل نطاق الفرع)
                    $latOffset = (rand(-100, 100) / 1000000);
                    $lngOffset = (rand(-100, 100) / 1000000);
                    $checkInLat = $lat + $latOffset;
                    $checkInLng = $lng + $lngOffset;
                    $checkOutLat = $lat + (rand(-100, 100) / 1000000);
                    $checkOutLng = $lng + (rand(-100, 100) / 1000000);
                } else {
                    $checkInLat = $checkInLng = $checkOutLat = $checkOutLng = null;
                }
                
                // إدخال السجل
                try {
                    Database::insert('attendance', [
                        'user_id' => $emp['id'],
                        'branch_id' => $emp['branch_id'],
                        'recorded_branch_id' => $emp['branch_id'],
                        'date' => $currentDate,
                        'check_in_time' => $checkInTime,
                        'check_out_time' => $checkOutTime,
                        'check_in_lat' => $checkInLat,
                        'check_in_lng' => $checkInLng,
                        'check_out_lat' => $checkOutLat,
                        'check_out_lng' => $checkOutLng,
                        'check_in_distance' => rand(1, 15),
                        'check_out_distance' => rand(1, 15),
                        'work_minutes' => (int) $workMinutes,
                        'late_minutes' => $lateMinutes,
                        'early_leave_minutes' => $earlyLeaveMinutes,
                        'overtime_minutes' => $overtimeMinutes,
                        'penalty_points' => round($penaltyPoints, 2),
                        'bonus_points' => round($bonusPoints, 2),
                        'status' => $status,
                        'is_locked' => 1,
                        'notes' => 'بيانات تجريبية'
                    ]);
                    $totalRecords++;
                } catch (Exception $e) {
                    // تجاهل الأخطاء المكررة
                    continue;
                }
            }
            
            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }
        
        // تحديث نقاط المستخدمين
        Database::query("
            UPDATE users u SET 
                current_points = 1000 + COALESCE((
                    SELECT SUM(bonus_points) - SUM(penalty_points)
                    FROM attendance a 
                    WHERE a.user_id = u.id
                ), 0),
                total_points_earned = COALESCE((
                    SELECT SUM(bonus_points) FROM attendance a WHERE a.user_id = u.id
                ), 0),
                total_points_deducted = COALESCE((
                    SELECT SUM(penalty_points) FROM attendance a WHERE a.user_id = u.id
                ), 0)
        ");
        
        // حساب السلاسل
        foreach ($employees as $emp) {
            updateEmployeeStreak($emp['id']);
        }
        
        // إحصائيات حسب النوع
        $typeStats = [];
        foreach ($employeeProfiles as $id => $profile) {
            $typeStats[$profile['type']] = ($typeStats[$profile['type']] ?? 0) + 1;
        }
        
        return [
            'success' => true,
            'message' => "✅ تم توليد البيانات التجريبية بنجاح!\n\n" .
                        "📊 إجمالي السجلات: {$totalRecords}\n" .
                        "📅 الفترة: {$startDate} إلى {$endDate}\n" .
                        "👥 عدد الموظفين: " . count($employees) . "\n\n" .
                        "📈 توزيع الموظفين:\n" .
                        "   ⭐ ممتازون: " . ($typeStats['excellent'] ?? 0) . "\n" .
                        "   ✅ جيدون: " . ($typeStats['good'] ?? 0) . "\n" .
                        "   ⚡ متوسطون: " . ($typeStats['average'] ?? 0) . "\n" .
                        "   ⚠️ ضعيفون: " . ($typeStats['poor'] ?? 0)
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'خطأ: ' . $e->getMessage()
        ];
    }
}

/**
 * تحديث سلسلة الموظف
 */
function updateEmployeeStreak(int $userId): void {
    $records = Database::fetchAll(
        "SELECT date, late_minutes, early_leave_minutes, work_minutes 
         FROM attendance 
         WHERE user_id = ? 
         ORDER BY date DESC 
         LIMIT 30",
        [$userId]
    );
    
    $streak = 0;
    foreach ($records as $record) {
        $isPerfect = ($record['late_minutes'] == 0 && $record['early_leave_minutes'] == 0 && $record['work_minutes'] >= 400);
        if ($isPerfect) {
            $streak++;
        } else {
            break;
        }
    }
    
    Database::update('users', ['streak_count' => $streak], 'id = :id', ['id' => $userId]);
}

$csrf_token = csrf_token();
$page_title = "🛠️ أدوات قاعدة البيانات";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - صرح الإتقان</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --bg-dark: #0f0f1a;
            --bg-card: #1a1a2e;
            --accent-red: #ff4757;
            --accent-green: #00ff88;
            --accent-orange: #ff9f43;
            --accent-blue: #54a0ff;
            --text-primary: #ffffff;
            --text-secondary: #8b8b9a;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            padding: 40px 0;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--accent-orange), var(--accent-red));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        
        .warning-banner {
            background: linear-gradient(135deg, rgba(255, 71, 87, 0.1), rgba(255, 159, 67, 0.1));
            border: 1px solid var(--accent-red);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .warning-banner i {
            font-size: 2rem;
            color: var(--accent-red);
            margin-bottom: 10px;
        }
        
        .warning-banner h3 {
            color: var(--accent-red);
            margin-bottom: 5px;
        }
        
        .warning-banner p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .tool-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }
        
        .tool-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .tool-card.danger {
            border-color: rgba(255, 71, 87, 0.3);
        }
        
        .tool-card.success {
            border-color: rgba(0, 255, 136, 0.3);
        }
        
        .tool-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 20px;
        }
        
        .danger .tool-icon {
            background: rgba(255, 71, 87, 0.15);
            color: var(--accent-red);
        }
        
        .success .tool-icon {
            background: rgba(0, 255, 136, 0.15);
            color: var(--accent-green);
        }
        
        .tool-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .tool-desc {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.7;
            margin-bottom: 20px;
        }
        
        .tool-list {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
        }
        
        .tool-list li {
            color: var(--text-secondary);
            font-size: 0.9rem;
            padding: 5px 0;
            list-style: none;
        }
        
        .tool-list li::before {
            content: '•';
            margin-left: 10px;
            color: var(--accent-blue);
        }
        
        .tool-btn {
            width: 100%;
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-family: 'Tajawal', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--accent-red), #e84118);
            color: white;
        }
        
        .btn-danger:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 30px rgba(255, 71, 87, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--accent-green), #00b894);
            color: #0a0a0f;
        }
        
        .btn-success:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 30px rgba(0, 255, 136, 0.4);
        }
        
        .message-box {
            background: var(--bg-card);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            white-space: pre-line;
            line-height: 1.8;
        }
        
        .message-box.success {
            border: 1px solid var(--accent-green);
            color: var(--accent-green);
        }
        
        .message-box.danger {
            border: 1px solid var(--accent-red);
            color: var(--accent-red);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 10px 20px;
            background: var(--bg-card);
            border-radius: 10px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            background: var(--accent-blue);
            color: white;
        }
        
        .confirm-dialog {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .confirm-content {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 40px;
            max-width: 450px;
            text-align: center;
        }
        
        .confirm-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        .confirm-title {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .confirm-text {
            color: var(--text-secondary);
            margin-bottom: 30px;
        }
        
        .confirm-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .confirm-buttons button {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-family: 'Tajawal', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-cancel {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
        }
        
        .btn-confirm {
            background: var(--accent-red);
            color: white;
        }
        
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top-color: var(--accent-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading-text {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="<?= BASE_URL ?>/index.php" class="back-link">
            <i class="bi bi-arrow-right"></i>
            العودة للرئيسية
        </a>
        
        <header class="header">
            <h1>🛠️ أدوات قاعدة البيانات</h1>
            <p>أدوات متقدمة للمدير العام فقط</p>
        </header>
        
        <div class="warning-banner">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <h3>⚠️ تحذير مهم</h3>
            <p>هذه الأدوات تؤثر على جميع البيانات في النظام. استخدمها بحذر!</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message-box <?= $messageType ?>">
                <?= nl2br(htmlspecialchars($message)) ?>
            </div>
        <?php endif; ?>
        
        <div class="tools-grid">
            <!-- أداة تنظيف قاعدة البيانات -->
            <div class="tool-card danger">
                <div class="tool-icon">
                    <i class="bi bi-trash3-fill"></i>
                </div>
                <h2 class="tool-title">🧹 تنظيف قاعدة البيانات</h2>
                <p class="tool-desc">
                    حذف جميع السجلات مع الإبقاء على البيانات الأساسية (الفروع والموظفين)
                </p>
                <ul class="tool-list">
                    <li>حذف جميع سجلات الحضور</li>
                    <li>حذف سجل النشاط</li>
                    <li>حذف جميع الإشعارات</li>
                    <li>حذف طلبات الإجازات</li>
                    <li>إعادة تعيين نقاط الموظفين</li>
                    <li>✅ الإبقاء على: 5 فروع + الموظفين</li>
                </ul>
                <form method="POST" id="cleanForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="clean_database">
                    <button type="button" class="tool-btn btn-danger" onclick="confirmAction('clean')">
                        <i class="bi bi-trash3"></i>
                        تنظيف قاعدة البيانات
                    </button>
                </form>
            </div>
            
            <!-- أداة توليد البيانات التجريبية -->
            <div class="tool-card success">
                <div class="tool-icon">
                    <i class="bi bi-database-fill-add"></i>
                </div>
                <h2 class="tool-title">📊 توليد بيانات تجريبية</h2>
                <p class="tool-desc">
                    إنشاء سجلات حضور واقعية لمدة شهرين للاختبار والتحليل
                </p>
                <ul class="tool-list">
                    <li>60 يوم من البيانات (شهرين)</li>
                    <li>25% موظفون ممتازون ⭐</li>
                    <li>30% موظفون جيدون ✅</li>
                    <li>25% موظفون متوسطون ⚡</li>
                    <li>20% موظفون ضعيفون ⚠️</li>
                    <li>تأخيرات وغيابات واقعية</li>
                </ul>
                <form method="POST" id="generateForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="generate_test_data">
                    <button type="button" class="tool-btn btn-success" onclick="confirmAction('generate')">
                        <i class="bi bi-magic"></i>
                        توليد البيانات التجريبية
                    </button>
                </form>
            </div>
            
            <!-- أداة حذف المستخدمين -->
            <div class="tool-card danger">
                <div class="tool-icon">
                    <i class="bi bi-person-x-fill"></i>
                </div>
                <h2 class="tool-title">🗑️ حذف جميع المستخدمين</h2>
                <p class="tool-desc">
                    حذف جميع المستخدمين مع الإبقاء على مدراء النظام فقط
                </p>
                <ul class="tool-list">
                    <li>حذف جميع المستخدمين عدا مدراء النظام</li>
                    <li>حذف سجلات الحضور المرتبطة</li>
                    <li>حذف الإشعارات المرتبطة</li>
                    <li>حذف الإجازات المرتبطة</li>
                    <li>حذف سجلات النشاط المرتبطة</li>
                    <li>✅ الإبقاء على: مدير النظام (role_id=5) والمطور (role_id=6)</li>
                </ul>
                <form method="POST" id="deleteUsersForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="delete_users_keep_admins">
                    <button type="button" class="tool-btn btn-danger" onclick="confirmAction('delete_users')">
                        <i class="bi bi-person-x"></i>
                        حذف جميع المستخدمين
                    </button>
                </form>
            </div>
            
            <!-- أداة إضافة بيانات النشر -->
            <div class="tool-card success">
                <div class="tool-icon">
                    <i class="bi bi-people-fill"></i>
                </div>
                <h2 class="tool-title">📦 إضافة بيانات النشر</h2>
                <p class="tool-desc">
                    إضافة الموظفين المحددين للنشر في الفروع المختلفة
                </p>
                <ul class="tool-list">
                    <li>إضافة موظفين جدد من بيانات النشر</li>
                    <li>إنشاء جداول دوام افتراضية</li>
                    <li>توزيع الموظفين على الفروع</li>
                    <li>كلمة المرور الافتراضية: Aa123456</li>
                    <li>✅ تخطي الموظفين الموجودين مسبقاً</li>
                    <li>✅ إضافة تلقائية لجدول الدوام</li>
                </ul>
                <form method="POST" id="deploymentForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="add_deployment_data">
                    <button type="button" class="tool-btn btn-success" onclick="confirmAction('deployment')">
                        <i class="bi bi-download"></i>
                        إضافة بيانات النشر
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- نافذة التأكيد -->
    <div class="confirm-dialog" id="confirmDialog">
        <div class="confirm-content">
            <div class="confirm-icon" id="confirmIcon">⚠️</div>
            <h3 class="confirm-title" id="confirmTitle">تأكيد العملية</h3>
            <p class="confirm-text" id="confirmText">هل أنت متأكد؟</p>
            <div class="confirm-buttons">
                <button class="btn-cancel" onclick="closeConfirm()">إلغاء</button>
                <button class="btn-confirm" id="confirmBtn" onclick="executeAction()">تأكيد</button>
            </div>
        </div>
    </div>
    
    <!-- شاشة التحميل -->
    <div class="loading" id="loadingScreen">
        <div class="spinner"></div>
        <div class="loading-text" id="loadingText">جاري المعالجة...</div>
    </div>
    
    <script>
        let currentAction = '';
        
        function confirmAction(action) {
            currentAction = action;
            const dialog = document.getElementById('confirmDialog');
            const icon = document.getElementById('confirmIcon');
            const title = document.getElementById('confirmTitle');
            const text = document.getElementById('confirmText');
            const btn = document.getElementById('confirmBtn');
            
            if (action === 'clean') {
                icon.textContent = '🗑️';
                title.textContent = 'تنظيف قاعدة البيانات';
                text.textContent = 'سيتم حذف جميع السجلات! هل أنت متأكد؟';
                btn.style.background = 'var(--accent-red)';
            } else if (action === 'delete_users') {
                icon.textContent = '⚠️';
                title.textContent = 'حذف جميع المستخدمين';
                text.textContent = 'سيتم حذف جميع المستخدمين عدا مدراء النظام (role_id=5,6)! هذه عملية خطيرة ولا يمكن التراجع عنها. هل أنت متأكد تماماً؟';
                btn.style.background = 'var(--accent-red)';
            } else if (action === 'deployment') {
                icon.textContent = '📦';
                title.textContent = 'إضافة بيانات النشر';
                text.textContent = 'سيتم إضافة جميع الموظفين من بيانات النشر إلى قاعدة البيانات. الموظفون الموجودون مسبقاً سيتم تخطيهم. هل تريد المتابعة؟';
                btn.style.background = 'var(--accent-green)';
                btn.style.color = '#0a0a0f';
            } else {
                icon.textContent = '📊';
                title.textContent = 'توليد بيانات تجريبية';
                text.textContent = 'سيتم إنشاء سجلات لمدة شهرين. قد تستغرق العملية دقيقة.';
                btn.style.background = 'var(--accent-green)';
                btn.style.color = '#0a0a0f';
            }
            
            dialog.style.display = 'flex';
        }
        
        function closeConfirm() {
            document.getElementById('confirmDialog').style.display = 'none';
        }
        
        function executeAction() {
            closeConfirm();
            
            const loading = document.getElementById('loadingScreen');
            const loadingText = document.getElementById('loadingText');
            
            if (currentAction === 'clean') {
                loadingText.textContent = 'جاري تنظيف قاعدة البيانات...';
                loading.style.display = 'flex';
                document.getElementById('cleanForm').submit();
            } else if (currentAction === 'delete_users') {
                loadingText.textContent = 'جاري حذف المستخدمين...';
                loading.style.display = 'flex';
                document.getElementById('deleteUsersForm').submit();
            } else if (currentAction === 'deployment') {
                loadingText.textContent = 'جاري إضافة بيانات النشر...';
                loading.style.display = 'flex';
                document.getElementById('deploymentForm').submit();
            } else {
                loadingText.textContent = 'جاري توليد البيانات التجريبية... قد تستغرق العملية دقيقة';
                loading.style.display = 'flex';
                document.getElementById('generateForm').submit();
            }
        }
    </script>
</body>
</html>
