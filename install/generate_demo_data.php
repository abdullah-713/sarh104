<?php
/**
 * =====================================================
 * صرح الإتقان - مولد البيانات التجريبية الواقعية
 * Sarh Al-Itqan - Realistic Demo Data Generator
 * =====================================================
 * 
 * ينشئ:
 * - 10 موظفين لكل فرع (50 موظف إجمالي)
 * - سجلات حضور لشهرين كاملين
 * - بيانات واقعية (تأخيرات، إجازات، غياب، عمل إضافي)
 * 
 * تشغيل: php generate_demo_data.php
 * أو عبر المتصفح مرة واحدة
 * =====================================================
 */

// منع timeout
set_time_limit(0);
ini_set('memory_limit', '256M');

// تحميل إعدادات التطبيق
require_once dirname(__DIR__) . '/config/app.php';

// التحقق من الأمان - قم بتعليق هذا السطر لتشغيل السكريبت
// die('⚠️ قم بتعليق هذا السطر في الكود لتشغيل مولد البيانات');

// =====================================================
// بيانات الموظفين العربية
// =====================================================

// أسماء ذكور
$maleFirstNames = [
    'محمد', 'أحمد', 'عبدالله', 'سعود', 'فهد', 'خالد', 'عمر', 'علي', 'سلطان', 'تركي',
    'ناصر', 'بندر', 'ماجد', 'فيصل', 'نايف', 'سلمان', 'راشد', 'عبدالرحمن', 'عبدالعزيز', 'إبراهيم',
    'يوسف', 'حمد', 'مشاري', 'وليد', 'هشام', 'زياد', 'طلال', 'منصور', 'صالح', 'عادل',
    'سامي', 'ياسر', 'عماد', 'أنس', 'حسام', 'بلال', 'معاذ', 'أسامة', 'حاتم', 'رامي'
];

// أسماء إناث
$femaleFirstNames = [
    'نورة', 'سارة', 'فاطمة', 'عائشة', 'مريم', 'لمى', 'هند', 'ريم', 'دانة', 'لينا',
    'أمل', 'منى', 'هدى', 'نوف', 'العنود', 'البندري', 'الجوهرة', 'مها', 'غادة', 'سمر',
    'شهد', 'رزان', 'ديما', 'لجين', 'تالا', 'جود', 'رهف', 'أروى', 'ياسمين', 'وعد'
];

// أسماء العائلات
$lastNames = [
    'العتيبي', 'القحطاني', 'الشمري', 'الدوسري', 'الحربي', 'الغامدي', 'الزهراني', 'السبيعي', 'المطيري', 'الرشيدي',
    'العنزي', 'البقمي', 'الشهري', 'الشهراني', 'السهلي', 'الحارثي', 'اليامي', 'الخالدي', 'السالم', 'المحمدي',
    'العمري', 'الأحمدي', 'الفهد', 'الناصر', 'العبدالله', 'الصالح', 'الحمد', 'الماجد', 'الراشد', 'العلي'
];

// الأقسام
$departments = [
    'المبيعات' => ['مندوب مبيعات', 'أخصائي مبيعات', 'مسؤول حسابات', 'منسق مبيعات'],
    'الموارد البشرية' => ['أخصائي موارد بشرية', 'منسق توظيف', 'أخصائي تدريب', 'مساعد إداري'],
    'تقنية المعلومات' => ['مطور برمجيات', 'فني دعم تقني', 'مدير أنظمة', 'أخصائي شبكات'],
    'المحاسبة' => ['محاسب', 'أخصائي مالي', 'مراجع حسابات', 'أمين صندوق'],
    'العمليات' => ['مشرف عمليات', 'منسق لوجستي', 'فني صيانة', 'مراقب جودة'],
    'خدمة العملاء' => ['موظف خدمة عملاء', 'أخصائي دعم', 'منسق علاقات', 'مسؤول شكاوى'],
    'التسويق' => ['أخصائي تسويق', 'مصمم جرافيك', 'منسق محتوى', 'أخصائي سوشيال ميديا'],
    'الإدارة' => ['سكرتير تنفيذي', 'مساعد إداري', 'منسق مكتب', 'موظف استقبال']
];

// الفروع مع إحداثياتها
$branches = [
    1 => ['name' => 'صرح الاتقان الرئيسي', 'lat' => 24.572368, 'lng' => 46.602829],
    2 => ['name' => 'صرح الاتقان كورنر', 'lat' => 24.572439, 'lng' => 46.603008],
    3 => ['name' => 'صرح الاتقان 2', 'lat' => 24.572262, 'lng' => 46.602580],
    4 => ['name' => 'فضاء المحركات 1', 'lat' => 24.56968126, 'lng' => 46.61405911],
    5 => ['name' => 'فضاء المحركات 2', 'lat' => 24.566088, 'lng' => 46.621759]
];

// أنماط سلوك الموظفين (لجعل البيانات واقعية)
$employeePatterns = [
    'excellent' => ['early_rate' => 0.7, 'ontime_rate' => 0.25, 'late_rate' => 0.03, 'absent_rate' => 0.02, 'leave_rate' => 0.05],
    'good' => ['early_rate' => 0.4, 'ontime_rate' => 0.45, 'late_rate' => 0.08, 'absent_rate' => 0.02, 'leave_rate' => 0.05],
    'average' => ['early_rate' => 0.2, 'ontime_rate' => 0.5, 'late_rate' => 0.15, 'absent_rate' => 0.08, 'leave_rate' => 0.07],
    'poor' => ['early_rate' => 0.05, 'ontime_rate' => 0.4, 'late_rate' => 0.3, 'absent_rate' => 0.15, 'leave_rate' => 0.1]
];

// =====================================================
// دوال مساعدة
// =====================================================

function generateRandomPhone(): string {
    $prefixes = ['50', '53', '54', '55', '56', '57', '58', '59'];
    return '+9665' . $prefixes[array_rand($prefixes)] . sprintf('%07d', rand(0, 9999999));
}

function generateNationalId(): string {
    // هوية سعودية (تبدأ بـ 1 للمواطنين)
    return '1' . sprintf('%09d', rand(0, 999999999));
}

function generateRandomTime($baseHour, $baseMinute, $variationMinutes, $direction = 'both'): string {
    $totalMinutes = ($baseHour * 60) + $baseMinute;
    
    if ($direction === 'early') {
        $variation = -rand(1, $variationMinutes);
    } elseif ($direction === 'late') {
        $variation = rand(1, $variationMinutes);
    } else {
        $variation = rand(-$variationMinutes, $variationMinutes);
    }
    
    $totalMinutes += $variation;
    $hours = floor($totalMinutes / 60);
    $minutes = $totalMinutes % 60;
    $seconds = rand(0, 59);
    
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

function generateLocationNear($lat, $lng, $radiusMeters = 15): array {
    // توليد موقع عشوائي ضمن دائرة نصف قطرها المحدد
    $radiusDegrees = $radiusMeters / 111000; // تحويل تقريبي من متر إلى درجات
    $angle = rand(0, 360) * M_PI / 180;
    $distance = sqrt(rand(0, 100) / 100) * $radiusDegrees;
    
    return [
        'lat' => round($lat + ($distance * cos($angle)), 7),
        'lng' => round($lng + ($distance * sin($angle)), 7),
        'distance' => round($distance * 111000, 2) // المسافة بالمتر
    ];
}

function isWeekend($date): bool {
    // في السعودية: الجمعة والسبت إجازة
    $dayOfWeek = date('w', strtotime($date));
    return $dayOfWeek == 5 || $dayOfWeek == 6; // 5=Friday, 6=Saturday
}

function getRandomAddress($branchName): string {
    $streets = ['طريق الملك فهد', 'شارع العليا', 'طريق الملك عبدالعزيز', 'شارع التحلية', 'طريق الملك سلمان'];
    $districts = ['العليا', 'الورود', 'السليمانية', 'الملز', 'النخيل'];
    return $streets[array_rand($streets)] . '، حي ' . $districts[array_rand($districts)] . '، قرب ' . $branchName;
}

// =====================================================
// الكود الرئيسي
// =====================================================

try {
    echo "<pre style='direction:rtl; font-family: Tahoma, Arial; font-size: 14px; line-height: 1.8;'>\n";
    echo "╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║     🏗️ صرح الإتقان - مولد البيانات التجريبية الواقعية           ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
    
    // التحقق من آخر رقم موظف موجود
    $maxEmpCode = Database::fetchValue("SELECT MAX(CAST(SUBSTRING(emp_code, 4) AS UNSIGNED)) FROM users WHERE emp_code LIKE 'EMP%'");
    $empCounter = max(100, intval($maxEmpCode) + 1);
    echo "📊 آخر رقم موظف موجود: EMP" . ($maxEmpCode ?: 'لا يوجد') . "\n";
    echo "🆕 سيبدأ الترقيم من: EMP{$empCounter}\n\n";
    
    // التحقق من عدد الموظفين الحاليين لكل فرع
    $existingCounts = Database::fetchAll("SELECT branch_id, COUNT(*) as count FROM users WHERE branch_id IS NOT NULL GROUP BY branch_id");
    $branchCounts = [];
    foreach ($existingCounts as $row) {
        $branchCounts[$row['branch_id']] = $row['count'];
    }
    
    Database::beginTransaction();
    
    $passwordHash = password_hash('Employee@2026', PASSWORD_DEFAULT);
    $createdEmployees = [];
    $totalAttendanceRecords = 0;
    
    // =====================================================
    // الخطوة 1: إنشاء الموظفين
    // =====================================================
    echo "📝 الخطوة 1: إنشاء الموظفين...\n";
    echo str_repeat('─', 60) . "\n";
    
    foreach ($branches as $branchId => $branch) {
        $existingInBranch = $branchCounts[$branchId] ?? 0;
        $neededEmployees = max(0, 10 - $existingInBranch);
        
        echo "\n🏢 الفرع: {$branch['name']} (ID: {$branchId})\n";
        echo "   📊 موظفون موجودون: {$existingInBranch}، سيتم إضافة: {$neededEmployees}\n";
        
        if ($neededEmployees <= 0) {
            echo "   ✅ الفرع مكتمل بالفعل\n";
            continue;
        }
        
        for ($i = 1; $i <= $neededEmployees; $i++) {
            $empCounter++;
            
            // تحديد الجنس (60% ذكور، 40% إناث)
            $isMale = rand(1, 100) <= 60;
            
            // توليد الاسم
            if ($isMale) {
                $firstName = $maleFirstNames[array_rand($maleFirstNames)];
            } else {
                $firstName = $femaleFirstNames[array_rand($femaleFirstNames)];
            }
            $lastName = $lastNames[array_rand($lastNames)];
            $fullName = $firstName . ' ' . $lastName;
            
            // توليد اسم المستخدم
            $username = strtolower(str_replace(' ', '', $firstName)) . $empCounter;
            
            // اختيار القسم والوظيفة
            $deptName = array_rand($departments);
            $jobTitle = $departments[$deptName][array_rand($departments[$deptName])];
            
            // تحديد المستوى (1=موظف، 2=مشرف، 3=مدير فرع)
            $roleId = 1;
            if ($i === 1) $roleId = 3; // أول موظف مدير فرع
            elseif ($i <= 3) $roleId = 2; // الثاني والثالث مشرفين
            
            // تحديد نمط السلوك
            $patternKeys = array_keys($employeePatterns);
            if ($roleId >= 3) $patternKey = 'excellent';
            elseif ($roleId >= 2) $patternKey = 'good';
            else $patternKey = $patternKeys[array_rand($patternKeys)];
            
            // تاريخ التعيين (بين 6 أشهر وسنتين)
            $hireDate = date('Y-m-d', strtotime('-' . rand(180, 730) . ' days'));
            
            // إدراج الموظف
            $empCode = 'EMP' . $empCounter;
            $email = strtolower($username) . '@sarh.io';
            
            $userId = Database::insert('users', [
                'emp_code' => $empCode,
                'username' => $username,
                'email' => $email,
                'password_hash' => $passwordHash,
                'full_name' => $fullName,
                'phone' => generateRandomPhone(),
                'role_id' => $roleId,
                'branch_id' => $branchId,
                'department' => $deptName,
                'job_title' => $jobTitle,
                'hire_date' => $hireDate,
                'national_id' => generateNationalId(),
                'is_active' => 1,
                'current_points' => rand(100, 800)
            ]);
            
            // إنشاء جدول الدوام
            Database::insert('employee_schedules', [
                'user_id' => $userId,
                'work_start_time' => '08:00:00',
                'work_end_time' => '17:00:00',
                'grace_period_minutes' => 15,
                'attendance_mode' => 'time_and_location',
                'working_days' => '[0,1,2,3,4]', // الأحد للخميس
                'geofence_radius' => 100,
                'is_flexible_hours' => 0,
                'min_working_hours' => 8.00,
                'max_working_hours' => 12.00,
                'is_active' => 1
            ]);
            
            $createdEmployees[] = [
                'id' => $userId,
                'name' => $fullName,
                'branch_id' => $branchId,
                'pattern' => $patternKey,
                'hire_date' => $hireDate
            ];
            
            $roleNames = [1 => 'موظف', 2 => 'مشرف', 3 => 'مدير فرع'];
            echo "   ✅ {$empCode}: {$fullName} ({$roleNames[$roleId]} - {$deptName})\n";
        }
    }
    
    echo "\n✨ تم إنشاء " . count($createdEmployees) . " موظف جديد!\n";
    
    // جلب جميع الموظفين للفروع لإنشاء سجلات الحضور
    $allEmployees = Database::fetchAll("
        SELECT u.id, u.full_name, u.branch_id, u.hire_date, u.role_id
        FROM users u
        WHERE u.branch_id IS NOT NULL AND u.is_active = 1
        ORDER BY u.branch_id, u.id
    ");
    
    // تحديد نمط السلوك لكل موظف
    foreach ($allEmployees as &$emp) {
        $patternKeys = array_keys($employeePatterns);
        if ($emp['role_id'] >= 5) $emp['pattern'] = 'excellent';
        elseif ($emp['role_id'] >= 3) $emp['pattern'] = 'good';
        elseif ($emp['role_id'] >= 2) $emp['pattern'] = 'good';
        else $emp['pattern'] = $patternKeys[array_rand($patternKeys)];
    }
    unset($emp);
    
    echo "📊 إجمالي الموظفين للحضور: " . count($allEmployees) . "\n";
    
    // =====================================================
    // الخطوة 2: إنشاء سجلات الحضور
    // =====================================================
    echo "\n\n📅 الخطوة 2: إنشاء سجلات الحضور لشهرين...\n";
    echo str_repeat('─', 60) . "\n";
    
    // تحديد الفترة (شهرين سابقين)
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-60 days'));
    
    echo "📆 الفترة: من {$startDate} إلى {$endDate}\n\n";
    
    // العطل الرسمية (يمكن إضافة المزيد)
    $holidays = [
        '2025-11-23', // يوم العلم
        '2025-12-18', // اليوم الوطني للإمارات (عطلة اختيارية)
    ];
    
    $currentDate = $startDate;
    while ($currentDate <= $endDate) {
        // تخطي عطلة نهاية الأسبوع
        if (isWeekend($currentDate)) {
            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
            continue;
        }
        
        // التحقق من العطل الرسمية
        $isHoliday = in_array($currentDate, $holidays);
        
        foreach ($allEmployees as $emp) {
            // تخطي إذا كان تاريخ التعيين بعد هذا اليوم
            if ($emp['hire_date'] > $currentDate) continue;
            
            $branch = $branches[$emp['branch_id']];
            $pattern = $employeePatterns[$emp['pattern']];
            
            // تحديد حالة اليوم بناءً على نمط الموظف
            $rand = rand(1, 100) / 100;
            
            if ($isHoliday) {
                $dayStatus = 'holiday';
            } elseif ($rand < $pattern['absent_rate']) {
                $dayStatus = 'absent';
            } elseif ($rand < ($pattern['absent_rate'] + $pattern['leave_rate'])) {
                $dayStatus = 'leave';
            } elseif ($rand < ($pattern['absent_rate'] + $pattern['leave_rate'] + $pattern['late_rate'])) {
                $dayStatus = 'late';
            } elseif ($rand < ($pattern['absent_rate'] + $pattern['leave_rate'] + $pattern['late_rate'] + $pattern['early_rate'])) {
                $dayStatus = 'early';
            } else {
                $dayStatus = 'ontime';
            }
            
            // بناء سجل الحضور
            $attendance = [
                'user_id' => $emp['id'],
                'branch_id' => $emp['branch_id'],
                'recorded_branch_id' => $emp['branch_id'],
                'date' => $currentDate,
                'status' => 'present',
                'late_minutes' => 0,
                'early_leave_minutes' => 0,
                'overtime_minutes' => 0,
                'penalty_points' => 0,
                'bonus_points' => 0,
                'notes' => null
            ];
            
            switch ($dayStatus) {
                case 'holiday':
                    $attendance['status'] = 'holiday';
                    $attendance['notes'] = 'عطلة رسمية';
                    break;
                    
                case 'absent':
                    $attendance['status'] = 'absent';
                    $attendance['penalty_points'] = 10;
                    $reasons = ['غياب بدون عذر', 'مرض مفاجئ', 'ظرف طارئ', 'تأخر التبليغ'];
                    $attendance['notes'] = $reasons[array_rand($reasons)];
                    break;
                    
                case 'leave':
                    $attendance['status'] = 'leave';
                    $leaveTypes = ['إجازة سنوية', 'إجازة مرضية', 'إجازة طارئة', 'مأمورية عمل'];
                    $attendance['notes'] = $leaveTypes[array_rand($leaveTypes)];
                    break;
                    
                case 'early':
                    // حضور مبكر
                    $checkInTime = generateRandomTime(7, 30, 25, 'early'); // 7:05 - 7:55
                    $checkOutTime = generateRandomTime(17, 0, 60, 'both'); // 16:00 - 18:00
                    
                    $checkInLoc = generateLocationNear($branch['lat'], $branch['lng']);
                    $checkOutLoc = generateLocationNear($branch['lat'], $branch['lng']);
                    
                    $attendance['check_in_time'] = $checkInTime;
                    $attendance['check_out_time'] = $checkOutTime;
                    $attendance['check_in_lat'] = $checkInLoc['lat'];
                    $attendance['check_in_lng'] = $checkInLoc['lng'];
                    $attendance['check_in_distance'] = $checkInLoc['distance'];
                    $attendance['check_out_lat'] = $checkOutLoc['lat'];
                    $attendance['check_out_lng'] = $checkOutLoc['lng'];
                    $attendance['check_out_distance'] = $checkOutLoc['distance'];
                    $attendance['check_in_address'] = getRandomAddress($branch['name']);
                    $attendance['check_out_address'] = getRandomAddress($branch['name']);
                    
                    // حساب ساعات العمل
                    $workMinutes = (strtotime($checkOutTime) - strtotime($checkInTime)) / 60;
                    $attendance['work_minutes'] = max(0, $workMinutes);
                    
                    // مكافأة الحضور المبكر
                    $earlyMinutes = max(0, (strtotime('08:00:00') - strtotime($checkInTime)) / 60);
                    if ($earlyMinutes > 5) {
                        $attendance['bonus_points'] = min(5, $earlyMinutes * 0.5);
                    }
                    
                    // عمل إضافي
                    if (strtotime($checkOutTime) > strtotime('17:00:00')) {
                        $overtime = (strtotime($checkOutTime) - strtotime('17:00:00')) / 60;
                        $attendance['overtime_minutes'] = min(180, $overtime);
                        $attendance['bonus_points'] += min(10, $overtime * 0.1);
                    }
                    
                    $attendance['status'] = 'present';
                    break;
                    
                case 'late':
                    // حضور متأخر
                    $lateMinutes = rand(5, 45);
                    $checkInTime = generateRandomTime(8, $lateMinutes, 10, 'late');
                    $checkOutTime = generateRandomTime(17, 30, 60, 'both');
                    
                    $checkInLoc = generateLocationNear($branch['lat'], $branch['lng']);
                    $checkOutLoc = generateLocationNear($branch['lat'], $branch['lng']);
                    
                    $attendance['check_in_time'] = $checkInTime;
                    $attendance['check_out_time'] = $checkOutTime;
                    $attendance['check_in_lat'] = $checkInLoc['lat'];
                    $attendance['check_in_lng'] = $checkInLoc['lng'];
                    $attendance['check_in_distance'] = $checkInLoc['distance'];
                    $attendance['check_out_lat'] = $checkOutLoc['lat'];
                    $attendance['check_out_lng'] = $checkOutLoc['lng'];
                    $attendance['check_out_distance'] = $checkOutLoc['distance'];
                    $attendance['check_in_address'] = getRandomAddress($branch['name']);
                    $attendance['check_out_address'] = getRandomAddress($branch['name']);
                    
                    // حساب التأخير
                    $actualLate = (strtotime($checkInTime) - strtotime('08:15:00')) / 60; // بعد فترة السماح
                    $attendance['late_minutes'] = max(0, $actualLate);
                    $attendance['penalty_points'] = min(5, $actualLate * 0.5);
                    
                    // حساب ساعات العمل
                    $workMinutes = (strtotime($checkOutTime) - strtotime($checkInTime)) / 60;
                    $attendance['work_minutes'] = max(0, $workMinutes);
                    
                    $attendance['status'] = $actualLate > 0 ? 'late' : 'present';
                    break;
                    
                case 'ontime':
                default:
                    // حضور في الوقت
                    $checkInTime = generateRandomTime(7, 55, 20, 'both'); // 7:35 - 8:15
                    $checkOutTime = generateRandomTime(17, 0, 30, 'both'); // 16:30 - 17:30
                    
                    $checkInLoc = generateLocationNear($branch['lat'], $branch['lng']);
                    $checkOutLoc = generateLocationNear($branch['lat'], $branch['lng']);
                    
                    $attendance['check_in_time'] = $checkInTime;
                    $attendance['check_out_time'] = $checkOutTime;
                    $attendance['check_in_lat'] = $checkInLoc['lat'];
                    $attendance['check_in_lng'] = $checkInLoc['lng'];
                    $attendance['check_in_distance'] = $checkInLoc['distance'];
                    $attendance['check_out_lat'] = $checkOutLoc['lat'];
                    $attendance['check_out_lng'] = $checkOutLoc['lng'];
                    $attendance['check_out_distance'] = $checkOutLoc['distance'];
                    $attendance['check_in_address'] = getRandomAddress($branch['name']);
                    $attendance['check_out_address'] = getRandomAddress($branch['name']);
                    
                    // حساب ساعات العمل
                    $workMinutes = (strtotime($checkOutTime) - strtotime($checkInTime)) / 60;
                    $attendance['work_minutes'] = max(0, $workMinutes);
                    
                    // التحقق من التأخير البسيط
                    if (strtotime($checkInTime) > strtotime('08:15:00')) {
                        $lateMin = (strtotime($checkInTime) - strtotime('08:15:00')) / 60;
                        $attendance['late_minutes'] = $lateMin;
                        $attendance['status'] = 'late';
                    }
                    break;
            }
            
            // إدراج السجل
            try {
                Database::insert('attendance', $attendance);
                $totalAttendanceRecords++;
            } catch (Exception $e) {
                // تجاهل الأخطاء المكررة
            }
        }
        
        // عرض التقدم
        if (rand(1, 10) === 1) {
            echo "   📅 معالجة: {$currentDate}...\n";
            flush();
        }
        
        $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
    }
    
    Database::commit();
    
    echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║                    ✅ تم بنجاح!                                  ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
    
    echo "📊 ملخص البيانات المُنشأة:\n";
    echo str_repeat('─', 40) . "\n";
    echo "👥 عدد الموظفين الجدد: " . count($createdEmployees) . "\n";
    echo "👥 إجمالي الموظفين: " . count($allEmployees) . "\n";
    echo "📋 سجلات الحضور: {$totalAttendanceRecords}\n";
    echo "📅 الفترة: {$startDate} إلى {$endDate}\n\n";
    
    echo "🔐 كلمة مرور جميع الموظفين الجدد: Employee@2026\n\n";
    
    echo "🏢 توزيع الموظفين على الفروع:\n";
    foreach ($branches as $branchId => $branch) {
        $count = count(array_filter($allEmployees, fn($e) => $e['branch_id'] == $branchId));
        echo "   • {$branch['name']}: {$count} موظف\n";
    }
    
    echo "\n</pre>";
    
} catch (Exception $e) {
    Database::rollback();
    echo "<pre style='color:red; direction:rtl;'>\n";
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "📍 الموقع: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "</pre>";
}
