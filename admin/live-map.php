<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - LIVE OPERATIONS MAP                                  ║
 * ║           الخريطة الحية الذكية - الفروع والموظفين والرادارات                 ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

check_login();
require_role(5);

$page_title = 'الخريطة الحية';

// Fetch branches
$branches = Database::fetchAll("SELECT * FROM branches WHERE is_active = 1 ORDER BY name");

// Fetch today's attendance with locations
$today = date('Y-m-d');
$attendanceData = Database::fetchAll("
    SELECT 
        a.*, 
        u.full_name, u.avatar, u.role_id, u.emp_code,
        b.name as branch_name, b.latitude as branch_lat, b.longitude as branch_lng, b.geofence_radius
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN branches b ON a.branch_id = b.id
    WHERE a.date = ?
    ORDER BY a.check_in_time DESC
", [$today]);

// Fetch recent locations (last 30 minutes)
$recentLocations = Database::fetchAll("
    SELECT 
        l.*, u.full_name, u.avatar, u.emp_code
    FROM user_location_history l
    JOIN users u ON l.user_id = u.id
    WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ORDER BY l.created_at DESC
");

// Stats
$statsQuery = Database::fetchOne("
    SELECT 
        COUNT(DISTINCT CASE WHEN check_in_time IS NOT NULL THEN user_id END) as present_count,
        COUNT(DISTINCT CASE WHEN check_out_time IS NOT NULL THEN user_id END) as left_count,
        COUNT(DISTINCT CASE WHEN status = 'late' THEN user_id END) as late_count,
        AVG(late_minutes) as avg_late
    FROM attendance
    WHERE date = ?
", [$today]);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        :root {
            --primary: #ff6f00;
            --primary-light: #ffa040;
            --primary-dark: #e65100;
        }
        body { font-family: 'Tajawal', sans-serif; background: #0f0f23; color: #fff; margin: 0; height: 100vh; overflow: hidden; }
        
        .top-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: rgba(15, 15, 35, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            z-index: 1000;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
        }
        
        .brand { font-weight: 700; font-size: 1.25rem; color: var(--primary); }
        
        #map {
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1;
        }
        
        .stats-panel {
            position: fixed;
            top: 80px;
            right: 20px;
            width: 320px;
            background: rgba(15, 15, 35, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.1);
            z-index: 500;
            overflow: hidden;
        }
        
        .stats-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        }
        
        .stat-card {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .stat-value { font-size: 1.5rem; font-weight: 800; }
        .stat-label { font-size: 0.85rem; opacity: 0.7; }
        
        .employees-panel {
            position: fixed;
            top: 80px;
            left: 20px;
            width: 350px;
            max-height: calc(100vh - 120px);
            background: rgba(15, 15, 35, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.1);
            z-index: 500;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .panel-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .employee-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem;
        }
        
        .employee-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 0.25rem;
        }
        
        .employee-item:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .employee-item.active {
            background: var(--primary);
        }
        
        .emp-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.2);
        }
        
        .emp-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            position: absolute;
            bottom: 0;
            right: 0;
            border: 2px solid #0f0f23;
        }
        
        .status-present { background: #00e676; }
        .status-late { background: #ff9800; }
        .status-left { background: #9e9e9e; }
        .status-absent { background: #f44336; }
        
        .legend {
            position: fixed;
            bottom: 30px;
            right: 20px;
            background: rgba(15, 15, 35, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid rgba(255,255,255,0.1);
            z-index: 500;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
        }
        
        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .branch-marker {
            background: var(--primary);
            border: 3px solid #fff;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        .emp-marker {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            border: 3px solid #00e676;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        .pulse-ring {
            position: absolute;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(0, 230, 118, 0.3);
            animation: pulse 2s ease-out infinite;
            top: -12px;
            left: -12px;
        }
        
        @keyframes pulse {
            0% { transform: scale(0.5); opacity: 1; }
            100% { transform: scale(1.5); opacity: 0; }
        }
        
        .search-box {
            background: rgba(255,255,255,0.1);
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            color: #fff;
            width: 100%;
        }
        
        .search-box::placeholder { color: rgba(255,255,255,0.5); }
        .search-box:focus { outline: none; background: rgba(255,255,255,0.15); }
        
        .refresh-btn {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(255, 111, 0, 0.4);
            z-index: 500;
            transition: all 0.2s;
        }
        
        .refresh-btn:hover {
            transform: translateX(-50%) scale(1.05);
        }
        
        .time-display {
            font-family: monospace;
            font-size: 1.1rem;
            color: var(--primary);
        }
        
        /* Leaflet custom popup */
        .leaflet-popup-content-wrapper {
            background: rgba(15, 15, 35, 0.95);
            color: #fff;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .leaflet-popup-tip {
            background: rgba(15, 15, 35, 0.95);
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="d-flex align-items-center gap-3 flex-grow-1">
            <span class="brand">
                <i class="bi bi-radar me-2"></i>
                الخريطة الحية
            </span>
            <span class="badge bg-success">
                <i class="bi bi-circle-fill me-1" style="font-size: 8px;"></i>
                متصل
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="time-display" id="currentTime"></span>
            <a href="<?= url('index.php') ?>" class="btn btn-outline-light btn-sm">
                <i class="bi bi-house me-1"></i> الرئيسية
            </a>
        </div>
    </div>
    
    <div id="map"></div>
    
    <!-- Stats Panel -->
    <div class="stats-panel">
        <div class="stats-header">
            <h6 class="mb-0 text-white">
                <i class="bi bi-bar-chart me-2"></i>
                إحصائيات اليوم
            </h6>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-25 text-success">
                <i class="bi bi-person-check"></i>
            </div>
            <div>
                <div class="stat-value text-success"><?= intval($statsQuery['present_count'] ?? 0) ?></div>
                <div class="stat-label">حاضر الآن</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-25 text-warning">
                <i class="bi bi-clock-history"></i>
            </div>
            <div>
                <div class="stat-value text-warning"><?= intval($statsQuery['late_count'] ?? 0) ?></div>
                <div class="stat-label">متأخرين</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-25 text-info">
                <i class="bi bi-box-arrow-left"></i>
            </div>
            <div>
                <div class="stat-value text-info"><?= intval($statsQuery['left_count'] ?? 0) ?></div>
                <div class="stat-label">انصرفوا</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-secondary bg-opacity-25">
                <i class="bi bi-building"></i>
            </div>
            <div>
                <div class="stat-value"><?= count($branches) ?></div>
                <div class="stat-label">الفروع النشطة</div>
            </div>
        </div>
    </div>
    
    <!-- Employees Panel -->
    <div class="employees-panel">
        <div class="panel-header">
            <h6 class="mb-0">
                <i class="bi bi-people me-2"></i>
                الموظفون (<?= count($attendanceData) ?>)
            </h6>
        </div>
        <div class="p-2">
            <input type="text" class="search-box" placeholder="بحث..." id="empSearch">
        </div>
        <div class="employee-list" id="employeeList">
            <?php if (empty($attendanceData)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    لا يوجد حضور مسجل اليوم
                </div>
            <?php else: ?>
                <?php foreach ($attendanceData as $att): ?>
                    <div class="employee-item" 
                         onclick="focusEmployee(<?= $att['check_in_lat'] ?: 0 ?>, <?= $att['check_in_lng'] ?: 0 ?>, '<?= e($att['full_name']) ?>')">
                        <div class="position-relative">
                            <img src="<?= $att['avatar'] ? url('uploads/avatars/' . $att['avatar']) : 'https://ui-avatars.com/api/?name=' . urlencode($att['full_name']) . '&background=ff6f00&color=fff' ?>" 
                                 class="emp-avatar">
                            <span class="emp-status <?= $att['check_out_time'] ? 'status-left' : ($att['status'] === 'late' ? 'status-late' : 'status-present') ?>"></span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold"><?= e($att['full_name']) ?></div>
                            <small class="opacity-75">
                                <?= e($att['branch_name'] ?? 'بدون فرع') ?>
                                • <?= $att['check_in_time'] ? date('h:i A', strtotime($att['check_in_time'])) : '--' ?>
                            </small>
                        </div>
                        <div>
                            <?php if ($att['status'] === 'late'): ?>
                                <span class="badge bg-warning">+<?= $att['late_minutes'] ?>د</span>
                            <?php elseif ($att['check_out_time']): ?>
                                <span class="badge bg-secondary">انصرف</span>
                            <?php else: ?>
                                <span class="badge bg-success">حاضر</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Legend -->
    <div class="legend">
        <div class="legend-item">
            <span class="legend-dot" style="background: var(--primary);"></span>
            فرع
        </div>
        <div class="legend-item">
            <span class="legend-dot status-present"></span>
            حاضر
        </div>
        <div class="legend-item">
            <span class="legend-dot status-late"></span>
            متأخر
        </div>
        <div class="legend-item">
            <span class="legend-dot status-left"></span>
            انصرف
        </div>
    </div>
    
    <button class="refresh-btn" onclick="refreshData()">
        <i class="bi bi-arrow-clockwise me-2"></i>
        تحديث البيانات
    </button>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    // Initialize map centered on Riyadh
    const map = L.map('map', {
        zoomControl: false
    }).setView([24.7136, 46.6753], 11);
    
    // Dark theme tiles
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19
    }).addTo(map);
    
    // Add zoom control to bottom left
    L.control.zoom({ position: 'bottomleft' }).addTo(map);
    
    // Branches data
    const branches = <?= json_encode($branches) ?>;
    const attendanceData = <?= json_encode($attendanceData) ?>;
    
    // Custom icons
    const branchIcon = L.divIcon({
        className: 'branch-marker-wrapper',
        html: '<div class="branch-marker"><i class="bi bi-building"></i></div>',
        iconSize: [30, 30],
        iconAnchor: [15, 15]
    });
    
    // Add branches to map
    branches.forEach(branch => {
        if (branch.latitude && branch.longitude) {
            // Geofence circle
            L.circle([branch.latitude, branch.longitude], {
                radius: branch.geofence_radius || 100,
                color: '#ff6f00',
                fillColor: '#ff6f00',
                fillOpacity: 0.1,
                weight: 2
            }).addTo(map);
            
            // Branch marker
            const marker = L.marker([branch.latitude, branch.longitude], {
                icon: L.divIcon({
                    className: '',
                    html: `<div class="branch-marker">${branch.name?.charAt(0) || 'ف'}</div>`,
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                })
            }).addTo(map);
            
            marker.bindPopup(`
                <div class="text-center p-2">
                    <h6 class="mb-1">${branch.name}</h6>
                    <small class="d-block mb-2">${branch.address || ''}</small>
                    <span class="badge bg-primary">نطاق: ${branch.geofence_radius || 100}م</span>
                </div>
            `);
        }
    });
    
    // Add employees to map
    const employeeMarkers = {};
    attendanceData.forEach(att => {
        if (att.check_in_lat && att.check_in_lng) {
            const statusClass = att.check_out_time ? 'status-left' : (att.status === 'late' ? 'status-late' : 'status-present');
            const borderColor = att.check_out_time ? '#9e9e9e' : (att.status === 'late' ? '#ff9800' : '#00e676');
            
            const avatarUrl = att.avatar 
                ? '<?= url('uploads/avatars/') ?>' + att.avatar 
                : `https://ui-avatars.com/api/?name=${encodeURIComponent(att.full_name)}&background=ff6f00&color=fff`;
            
            const marker = L.marker([att.check_in_lat, att.check_in_lng], {
                icon: L.divIcon({
                    className: '',
                    html: `
                        <div class="position-relative">
                            ${!att.check_out_time ? '<div class="pulse-ring"></div>' : ''}
                            <div class="emp-marker" style="background-image: url('${avatarUrl}'); border-color: ${borderColor};"></div>
                        </div>
                    `,
                    iconSize: [36, 36],
                    iconAnchor: [18, 18]
                })
            }).addTo(map);
            
            marker.bindPopup(`
                <div class="text-center p-2">
                    <img src="${avatarUrl}" class="rounded-circle mb-2" width="50" height="50">
                    <h6 class="mb-1">${att.full_name}</h6>
                    <small class="d-block text-muted mb-2">${att.branch_name || 'بدون فرع'}</small>
                    <div class="d-flex gap-2 justify-content-center">
                        <span class="badge bg-success">دخول: ${att.check_in_time || '--'}</span>
                        ${att.check_out_time ? `<span class="badge bg-secondary">خروج: ${att.check_out_time}</span>` : ''}
                    </div>
                    ${att.late_minutes > 0 ? `<span class="badge bg-warning mt-2">تأخير: ${att.late_minutes} دقيقة</span>` : ''}
                </div>
            `);
            
            employeeMarkers[att.user_id] = marker;
        }
    });
    
    // Focus on employee
    function focusEmployee(lat, lng, name) {
        if (lat && lng) {
            map.setView([lat, lng], 17);
        }
    }
    
    // Search employees
    document.getElementById('empSearch').addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase();
        document.querySelectorAll('.employee-item').forEach(item => {
            const name = item.querySelector('.fw-bold').textContent.toLowerCase();
            item.style.display = name.includes(query) ? '' : 'none';
        });
    });
    
    // Refresh data
    function refreshData() {
        location.reload();
    }
    
    // Update time
    function updateTime() {
        const now = new Date();
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('ar-SA');
    }
    updateTime();
    setInterval(updateTime, 1000);
    
    // Auto refresh every 30 seconds
    setTimeout(() => {
        refreshData();
    }, 30000);
    </script>
</body>
</html>
