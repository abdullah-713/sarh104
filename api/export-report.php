<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - EXPORT REPORTS API                                  ║
 * ║           واجهة تصدير التقارير                                               ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

session_start();
require_once '../config/database.php';

// التحقق من الصلاحيات
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die('غير مصرح');
}

// التحقق من CSRF للحماية من هجمات التزوير
$csrf_token = $_GET['token'] ?? '';
if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    die('رمز أمان غير صالح');
}

// التحقق من صحة القيم المدخلة
$allowed_formats = ['csv', 'excel'];
$allowed_types = ['attendance', 'employees', 'performance'];

$format = $_GET['format'] ?? 'csv';
$type = $_GET['type'] ?? 'attendance';
$period = intval($_GET['period'] ?? 30);
$branch = $_GET['branch'] ?? 'all';

// التحقق من القيم المسموحة
if (!in_array($format, $allowed_formats)) {
    http_response_code(400);
    die('صيغة غير مدعومة');
}

if (!in_array($type, $allowed_types)) {
    http_response_code(400);
    die('نوع تقرير غير صالح');
}

// تحديد الفترة الزمنية (حد أقصى سنة)
$period = min(365, max(1, $period));

// التحقق من صحة branch
if ($branch !== 'all' && !ctype_digit($branch)) {
    http_response_code(400);
    die('معرف فرع غير صالح');
}

// إعداد الفلتر
$branch_filter = '';
$params = [$period];

if ($branch !== 'all') {
    $branch_filter = 'AND u.branch_id = ?';
    $params[] = $branch;
}

try {
    switch ($type) {
        case 'attendance':
            $stmt = $pdo->prepare("
                SELECT 
                    u.name as 'اسم الموظف',
                    b.name as 'الفرع',
                    a.date as 'التاريخ',
                    a.check_in as 'وقت الحضور',
                    a.check_out as 'وقت الانصراف',
                    CASE a.status 
                        WHEN 'present' THEN 'حاضر'
                        WHEN 'absent' THEN 'غائب'
                        WHEN 'late' THEN 'متأخر'
                        ELSE a.status
                    END as 'الحالة'
                FROM attendance a
                JOIN users u ON a.user_id = u.id
                LEFT JOIN branches b ON u.branch_id = b.id
                WHERE a.date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                $branch_filter
                ORDER BY a.date DESC, u.name
            ");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $filename = 'attendance_report_' . date('Y-m-d');
            break;
            
        case 'employees':
            $stmt = $pdo->prepare("
                SELECT 
                    u.name as 'الاسم',
                    u.employee_id as 'الرقم الوظيفي',
                    u.email as 'البريد',
                    u.phone as 'الهاتف',
                    b.name as 'الفرع',
                    d.name as 'القسم',
                    u.created_at as 'تاريخ التسجيل'
                FROM users u
                LEFT JOIN branches b ON u.branch_id = b.id
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.is_active = 1
                $branch_filter
                ORDER BY u.name
            ");
            $stmt->execute($branch !== 'all' ? [$branch] : []);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $filename = 'employees_report_' . date('Y-m-d');
            break;
            
        case 'performance':
            $stmt = $pdo->prepare("
                SELECT 
                    u.name as 'الموظف',
                    b.name as 'الفرع',
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as 'أيام الحضور',
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as 'أيام الغياب',
                    COUNT(CASE WHEN a.status = 'late' THEN 1 END) as 'أيام التأخير',
                    ROUND(COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as 'نسبة الحضور %',
                    COALESCE((SELECT points FROM user_gamification WHERE user_id = u.id), 0) as 'النقاط'
                FROM users u
                LEFT JOIN branches b ON u.branch_id = b.id
                LEFT JOIN attendance a ON u.id = a.user_id 
                    AND a.date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                WHERE u.is_active = 1 AND u.role = 'employee'
                $branch_filter
                GROUP BY u.id
                ORDER BY 'نسبة الحضور %' DESC
            ");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $filename = 'performance_report_' . date('Y-m-d');
            break;
            
        default:
            throw new Exception('نوع تقرير غير صالح');
    }
    
    if (empty($data)) {
        throw new Exception('لا توجد بيانات للتصدير');
    }
    
    // التصدير
    if ($format === 'csv') {
        exportCSV($data, $filename);
    } elseif ($format === 'excel') {
        exportExcel($data, $filename);
    } else {
        throw new Exception('صيغة غير مدعومة');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo $e->getMessage();
}

/**
 * تصدير CSV
 */
function exportCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // إضافة BOM لدعم العربية في Excel
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // العناوين
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
    }
    
    // البيانات
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * تصدير Excel (كـ HTML table)
 */
function exportExcel($data, $filename) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo '<html dir="rtl"><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1" style="font-family: Tahoma; direction: rtl;">';
    
    // العناوين
    if (!empty($data)) {
        echo '<tr style="background-color: #667eea; color: white; font-weight: bold;">';
        foreach (array_keys($data[0]) as $header) {
            echo '<th style="padding: 10px;">' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr>';
    }
    
    // البيانات
    $rowNum = 0;
    foreach ($data as $row) {
        $bgColor = $rowNum % 2 === 0 ? '#ffffff' : '#f5f5f5';
        echo '<tr style="background-color: ' . $bgColor . ';">';
        foreach ($row as $cell) {
            echo '<td style="padding: 8px;">' . htmlspecialchars($cell ?? '-') . '</td>';
        }
        echo '</tr>';
        $rowNum++;
    }
    
    echo '</table></body></html>';
    exit;
}
