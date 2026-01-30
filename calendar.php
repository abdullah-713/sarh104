<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - CALENDAR VIEW                                       ║
 * ║           عرض التقويم                                                        ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Features:                                                                   ║
 * ║  - تقويم تفاعلي للحضور                                                       ║
 * ║  - عرض الإجازات والأحداث                                                     ║
 * ║  - ألوان حسب الحالة                                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['role'] === 'admin';

// جلب بيانات الحضور للشهر
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// التأكد من صحة الشهر والسنة
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
$end_date = date('Y-m-t', strtotime($start_date));

// جلب الحضور
$stmt = $pdo->prepare("
    SELECT 
        date,
        status,
        check_in,
        check_out
    FROM attendance 
    WHERE user_id = ? 
    AND date BETWEEN ? AND ?
    ORDER BY date
");
$stmt->execute([$user_id, $start_date, $end_date]);
$attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تحويل إلى مصفوفة بالتاريخ كمفتاح
$attendance_map = [];
foreach ($attendance as $record) {
    $attendance_map[$record['date']] = $record;
}

// جلب الإجازات
$stmt = $pdo->prepare("
    SELECT start_date, end_date, type, status
    FROM leave_requests
    WHERE user_id = ?
    AND status = 'approved'
    AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?))
");
$stmt->execute([$user_id, $start_date, $end_date, $start_date, $end_date]);
$leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تحويل الإجازات لأيام
$leave_days = [];
foreach ($leaves as $leave) {
    $current = strtotime($leave['start_date']);
    $end = strtotime($leave['end_date']);
    while ($current <= $end) {
        $leave_days[date('Y-m-d', $current)] = $leave['type'];
        $current = strtotime('+1 day', $current);
    }
}

// جلب الإعلانات المجدولة
$stmt = $pdo->prepare("
    SELECT title, publish_date, type
    FROM announcements
    WHERE is_active = 1
    AND publish_date BETWEEN ? AND ?
");
$stmt->execute([$start_date, $end_date]);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
$announcement_days = [];
foreach ($announcements as $ann) {
    $announcement_days[$ann['publish_date']] = $ann;
}

// إحصائيات الشهر
$stats = [
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'leave' => count(array_filter($leave_days, fn($d) => 
        substr($d, 0, 7) === $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT)
    ))
];

foreach ($attendance as $record) {
    if ($record['status'] === 'present') $stats['present']++;
    elseif ($record['status'] === 'absent') $stats['absent']++;
    elseif ($record['status'] === 'late') $stats['late']++;
}

// أسماء الأشهر
$month_names = [
    1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
    5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
    9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
];

$day_names = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

include 'includes/header.php';
?>

<style>
    .calendar-container {
        background: var(--bs-body-bg);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    }
    
    .calendar-header {
        background: var(--gradient-primary);
        color: white;
        padding: 1.5rem;
    }
    
    .calendar-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .calendar-nav-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    
    .calendar-nav-btn:hover {
        background: rgba(255,255,255,0.3);
        transform: scale(1.1);
    }
    
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
    }
    
    .calendar-day-header {
        padding: 0.75rem;
        text-align: center;
        font-weight: 600;
        font-size: 0.8rem;
        color: var(--bs-secondary-color);
        background: var(--bs-secondary-bg);
    }
    
    .calendar-day {
        aspect-ratio: 1;
        padding: 0.5rem;
        border: 1px solid var(--bs-border-color);
        position: relative;
        cursor: pointer;
        transition: all 0.2s;
        min-height: 80px;
    }
    
    .calendar-day:hover {
        background: var(--bs-tertiary-bg);
    }
    
    .calendar-day.empty {
        background: var(--bs-secondary-bg);
        cursor: default;
    }
    
    .calendar-day.today {
        background: rgba(102, 126, 234, 0.1);
        border-color: #667eea;
    }
    
    .day-number {
        font-weight: bold;
        font-size: 1.1rem;
        color: var(--bs-body-color);
    }
    
    .today .day-number {
        background: #667eea;
        color: white;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .day-status {
        position: absolute;
        bottom: 5px;
        left: 50%;
        transform: translateX(-50%);
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
    
    .status-present { background: #28a745; }
    .status-absent { background: #dc3545; }
    .status-late { background: #ffc107; }
    .status-leave { background: #17a2b8; }
    .status-holiday { background: #6f42c1; }
    
    .day-badge {
        position: absolute;
        top: 5px;
        left: 5px;
        font-size: 0.6rem;
        padding: 2px 6px;
        border-radius: 10px;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        padding: 1rem;
        background: var(--bs-secondary-bg);
    }
    
    .stat-box {
        text-align: center;
        padding: 0.75rem;
        background: var(--bs-body-bg);
        border-radius: 12px;
    }
    
    .stat-number {
        font-size: 1.5rem;
        font-weight: bold;
    }
    
    .stat-label {
        font-size: 0.75rem;
        color: var(--bs-secondary-color);
    }
    
    .legend {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        padding: 1rem;
        justify-content: center;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8rem;
    }
    
    .legend-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }
    
    @media (max-width: 768px) {
        .calendar-day {
            min-height: 50px;
            padding: 0.25rem;
        }
        
        .day-number {
            font-size: 0.9rem;
        }
        
        .calendar-day-header {
            font-size: 0.65rem;
            padding: 0.5rem;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="container py-4">
    <div class="calendar-container">
        <!-- Header -->
        <div class="calendar-header">
            <div class="calendar-nav">
                <a href="?month=<?php echo $month - 1; ?>&year=<?php echo $year; ?>" class="calendar-nav-btn">
                    <i class="bi bi-chevron-right"></i>
                </a>
                <div class="text-center">
                    <h4 class="mb-0"><?php echo $month_names[$month]; ?> <?php echo $year; ?></h4>
                    <small class="opacity-75">تقويم الحضور الشخصي</small>
                </div>
                <a href="?month=<?php echo $month + 1; ?>&year=<?php echo $year; ?>" class="calendar-nav-btn">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-number text-success"><?php echo $stats['present']; ?></div>
                <div class="stat-label">حضور</div>
            </div>
            <div class="stat-box">
                <div class="stat-number text-danger"><?php echo $stats['absent']; ?></div>
                <div class="stat-label">غياب</div>
            </div>
            <div class="stat-box">
                <div class="stat-number text-warning"><?php echo $stats['late']; ?></div>
                <div class="stat-label">تأخير</div>
            </div>
            <div class="stat-box">
                <div class="stat-number text-info"><?php echo $stats['leave']; ?></div>
                <div class="stat-label">إجازة</div>
            </div>
        </div>
        
        <!-- Calendar Grid -->
        <div class="calendar-grid">
            <!-- Day Headers -->
            <?php foreach ($day_names as $day): ?>
                <div class="calendar-day-header"><?php echo $day; ?></div>
            <?php endforeach; ?>
            
            <!-- Days -->
            <?php
            $first_day = date('w', strtotime($start_date)); // يوم الأسبوع للأول من الشهر
            $days_in_month = date('t', strtotime($start_date));
            $today = date('Y-m-d');
            
            // خلايا فارغة قبل أول يوم
            for ($i = 0; $i < $first_day; $i++): ?>
                <div class="calendar-day empty"></div>
            <?php endfor; ?>
            
            <?php for ($day = 1; $day <= $days_in_month; $day++):
                $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $is_today = $current_date === $today;
                $record = $attendance_map[$current_date] ?? null;
                $leave_type = $leave_days[$current_date] ?? null;
                $announcement = $announcement_days[$current_date] ?? null;
                
                // تحديد الحالة
                $status_class = '';
                if ($leave_type) {
                    $status_class = 'status-leave';
                } elseif ($record) {
                    $status_class = 'status-' . $record['status'];
                }
                
                // يوم الجمعة = إجازة رسمية
                $day_of_week = date('w', strtotime($current_date));
                $is_friday = $day_of_week == 5;
            ?>
                <div class="calendar-day <?php echo $is_today ? 'today' : ''; ?>" 
                     onclick="showDayDetails('<?php echo $current_date; ?>')"
                     data-date="<?php echo $current_date; ?>">
                    <div class="day-number <?php echo $is_today ? 'd-flex' : ''; ?>">
                        <?php echo $day; ?>
                    </div>
                    
                    <?php if ($announcement): ?>
                        <span class="day-badge bg-primary text-white">
                            <i class="bi bi-megaphone"></i>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($is_friday): ?>
                        <div class="day-status status-holiday" title="إجازة أسبوعية"></div>
                    <?php elseif ($status_class): ?>
                        <div class="day-status <?php echo $status_class; ?>"></div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
            
            <?php
            // خلايا فارغة بعد آخر يوم
            $remaining = (7 - (($first_day + $days_in_month) % 7)) % 7;
            for ($i = 0; $i < $remaining; $i++): ?>
                <div class="calendar-day empty"></div>
            <?php endfor; ?>
        </div>
        
        <!-- Legend -->
        <div class="legend">
            <div class="legend-item">
                <div class="legend-dot status-present"></div>
                <span>حاضر</span>
            </div>
            <div class="legend-item">
                <div class="legend-dot status-absent"></div>
                <span>غائب</span>
            </div>
            <div class="legend-item">
                <div class="legend-dot status-late"></div>
                <span>متأخر</span>
            </div>
            <div class="legend-item">
                <div class="legend-dot status-leave"></div>
                <span>إجازة</span>
            </div>
            <div class="legend-item">
                <div class="legend-dot status-holiday"></div>
                <span>إجازة رسمية</span>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="mt-4 text-center">
        <a href="attendance.php" class="btn btn-outline-primary me-2">
            <i class="bi bi-list-ul me-1"></i> سجل الحضور
        </a>
        <a href="leave-requests.php" class="btn btn-outline-info">
            <i class="bi bi-calendar-plus me-1"></i> طلب إجازة
        </a>
    </div>
</div>

<script>
const attendanceData = <?php echo json_encode($attendance_map); ?>;
const leaveData = <?php echo json_encode($leave_days); ?>;

function showDayDetails(date) {
    const record = attendanceData[date];
    const leave = leaveData[date];
    
    let html = `<div class="text-start">`;
    html += `<p><strong>التاريخ:</strong> ${date}</p>`;
    
    if (leave) {
        const leaveTypes = {
            'annual': 'سنوية',
            'sick': 'مرضية',
            'emergency': 'طارئة',
            'unpaid': 'بدون راتب'
        };
        html += `<p><strong>الحالة:</strong> <span class="badge bg-info">إجازة ${leaveTypes[leave] || leave}</span></p>`;
    } else if (record) {
        const statusLabels = {
            'present': '<span class="badge bg-success">حاضر</span>',
            'absent': '<span class="badge bg-danger">غائب</span>',
            'late': '<span class="badge bg-warning">متأخر</span>'
        };
        html += `<p><strong>الحالة:</strong> ${statusLabels[record.status] || record.status}</p>`;
        
        if (record.check_in) {
            html += `<p><strong>وقت الحضور:</strong> ${record.check_in}</p>`;
        }
        if (record.check_out) {
            html += `<p><strong>وقت الانصراف:</strong> ${record.check_out}</p>`;
        }
    } else {
        const dayOfWeek = new Date(date).getDay();
        if (dayOfWeek === 5) {
            html += `<p><strong>الحالة:</strong> <span class="badge bg-secondary">إجازة أسبوعية</span></p>`;
        } else if (new Date(date) > new Date()) {
            html += `<p class="text-muted">يوم مستقبلي</p>`;
        } else {
            html += `<p class="text-muted">لا توجد بيانات</p>`;
        }
    }
    
    html += `</div>`;
    
    Swal.fire({
        title: 'تفاصيل اليوم',
        html: html,
        icon: record ? (record.status === 'present' ? 'success' : 'info') : 'info',
        confirmButtonText: 'حسناً'
    });
}
</script>

<?php include 'includes/footer.php'; ?>
