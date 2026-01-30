<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - FRAUD DETECTION LOGS / سجل كشف الاحتيال              ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  صفحة عرض سجلات كشف الاحتيال للإدارة                                         ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

session_start();
require_once '../config/database.php';
require_once '../config/app.php';

// التحقق من تسجيل الدخول وصلاحيات الإدارة
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!is_super_admin($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$page_title = 'سجل كشف الاحتيال';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// جلب السجلات
try {
    $stmt = $pdo->prepare("
        SELECT fdl.*, u.name as user_name, u.email
        FROM fraud_detection_logs fdl
        JOIN users u ON fdl.user_id = u.id
        ORDER BY fdl.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$per_page, $offset]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إجمالي السجلات
    $stmt = $pdo->query("SELECT COUNT(*) FROM fraud_detection_logs");
    $total = $stmt->fetchColumn();
    $total_pages = ceil($total / $per_page);
    
    // إحصائيات
    $stmt = $pdo->query("
        SELECT 
            detection_type,
            COUNT(*) as count,
            ROUND(AVG(suspicion_score), 1) as avg_score
        FROM fraud_detection_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY detection_type
        ORDER BY count DESC
    ");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // أكثر المستخدمين مشبوهين
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.name,
            COUNT(*) as incidents,
            ROUND(AVG(fdl.suspicion_score), 1) as avg_score
        FROM fraud_detection_logs fdl
        JOIN users u ON fdl.user_id = u.id
        WHERE fdl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY u.id
        ORDER BY incidents DESC
        LIMIT 10
    ");
    $suspicious_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $logs = [];
    $total = 0;
    $total_pages = 0;
    $stats = [];
    $suspicious_users = [];
}

// ترجمة أنواع الكشف
$type_names = [
    'mock_gps' => ['name' => 'موقع GPS مزيف', 'icon' => 'bi-geo-alt', 'color' => 'danger'],
    'vpn' => ['name' => 'استخدام VPN', 'icon' => 'bi-shield-x', 'color' => 'warning'],
    'emulator' => ['name' => 'محاكي', 'icon' => 'bi-phone', 'color' => 'info'],
    'devtools' => ['name' => 'أدوات المطورين', 'icon' => 'bi-bug', 'color' => 'secondary'],
    'location_jump' => ['name' => 'قفزة في الموقع', 'icon' => 'bi-arrow-left-right', 'color' => 'warning'],
    'battery_anomaly' => ['name' => 'خلل في البطارية', 'icon' => 'bi-battery-charging', 'color' => 'info'],
    'sensor_anomaly' => ['name' => 'خلل في المستشعرات', 'icon' => 'bi-activity', 'color' => 'secondary'],
    'multiple' => ['name' => 'عدة مؤشرات', 'icon' => 'bi-exclamation-diamond', 'color' => 'danger']
];

require_once '../includes/header.php';
?>

<style>
    .fraud-card {
        border-radius: 12px;
        border: 1px solid #eee;
        margin-bottom: 12px;
        transition: all 0.3s ease;
    }

    .fraud-card:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }

    .fraud-card.high-risk {
        border-right: 4px solid #dc3545;
        background: rgba(220, 53, 69, 0.03);
    }

    .fraud-card.medium-risk {
        border-right: 4px solid #ffc107;
    }

    .fraud-card.low-risk {
        border-right: 4px solid #28a745;
    }

    .risk-score {
        font-size: 1.5rem;
        font-weight: 700;
    }

    .stat-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 20px;
        text-align: center;
    }

    .stat-box.danger {
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
    }

    .stat-box.warning {
        background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    }

    .suspicious-user {
        display: flex;
        align-items: center;
        padding: 12px;
        border-bottom: 1px solid #eee;
    }

    .suspicious-user:last-child {
        border-bottom: none;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        margin-left: 12px;
    }
</style>

<div class="container py-4 mb-5">
    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-shield-exclamation text-danger me-2"></i>
                سجل كشف الاحتيال
            </h4>
            <small class="text-muted">مراقبة محاولات الغش والتلاعب</small>
        </div>
        <a href="../more.php" class="btn btn-sm btn-outline-secondary rounded-pill">
            <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-box danger">
                <div style="font-size: 2rem; font-weight: 700;"><?php echo $total; ?></div>
                <small>إجمالي السجلات</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-box warning">
                <div style="font-size: 2rem; font-weight: 700;">
                    <?php echo count(array_filter($stats, fn($s) => $s['avg_score'] >= 70)); ?>
                </div>
                <small>تهديدات عالية</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-box">
                <div style="font-size: 2rem; font-weight: 700;"><?php echo count($suspicious_users); ?></div>
                <small>مستخدمين مشبوهين</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #28a745, #20c997);">
                <div style="font-size: 2rem; font-weight: 700;">
                    <?php 
                    $avg = !empty($stats) ? round(array_sum(array_column($stats, 'avg_score')) / count($stats)) : 0;
                    echo $avg . '%';
                    ?>
                </div>
                <small>متوسط الخطورة</small>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Detection Types Stats -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0">
                        <i class="bi bi-pie-chart me-2"></i>
                        أنواع الكشف
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($stats as $stat): ?>
                    <?php $type_info = $type_names[$stat['detection_type']] ?? ['name' => $stat['detection_type'], 'icon' => 'bi-question', 'color' => 'secondary']; ?>
                    <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi <?php echo $type_info['icon']; ?> text-<?php echo $type_info['color']; ?>"></i>
                            <span><?php echo $type_info['name']; ?></span>
                        </div>
                        <span class="badge bg-<?php echo $type_info['color']; ?>"><?php echo $stat['count']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Suspicious Users -->
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0">
                        <i class="bi bi-person-exclamation me-2 text-warning"></i>
                        أكثر المشبوهين
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($suspicious_users)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                        <p class="mt-2 mb-0">لا يوجد مستخدمين مشبوهين</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($suspicious_users as $user): ?>
                        <div class="suspicious-user">
                            <div class="user-avatar">
                                <?php echo mb_substr($user['name'], 0, 1); ?>
                            </div>
                            <div class="flex-grow-1">
                                <strong><?php echo $user['name']; ?></strong>
                                <div class="small text-muted">
                                    <?php echo $user['incidents']; ?> حادثة
                                </div>
                            </div>
                            <span class="badge bg-<?php echo $user['avg_score'] >= 70 ? 'danger' : ($user['avg_score'] >= 50 ? 'warning' : 'secondary'); ?>">
                                <?php echo $user['avg_score']; ?>%
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Logs List -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>
                        سجل الأحداث
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($logs)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-shield-check text-success" style="font-size: 4rem;"></i>
                        <p class="text-muted mt-3">لا توجد محاولات احتيال مسجلة</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <?php 
                            $risk_class = $log['suspicion_score'] >= 70 ? 'high-risk' : 
                                         ($log['suspicion_score'] >= 50 ? 'medium-risk' : 'low-risk');
                            $type_info = $type_names[$log['detection_type']] ?? ['name' => $log['detection_type'], 'icon' => 'bi-question', 'color' => 'secondary'];
                        ?>
                        <div class="fraud-card <?php echo $risk_class; ?> p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?php echo $log['user_name']; ?></strong>
                                    <div class="small text-muted">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="risk-score text-<?php echo $log['suspicion_score'] >= 70 ? 'danger' : ($log['suspicion_score'] >= 50 ? 'warning' : 'success'); ?>">
                                        <?php echo $log['suspicion_score']; ?>%
                                    </div>
                                    <small class="text-muted">درجة الخطورة</small>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <span class="badge bg-<?php echo $type_info['color']; ?>">
                                    <i class="bi <?php echo $type_info['icon']; ?> me-1"></i>
                                    <?php echo $type_info['name']; ?>
                                </span>
                            </div>
                            
                            <div class="small text-muted">
                                <i class="bi bi-globe me-1"></i>
                                <?php echo $log['ip_address']; ?>
                            </div>
                            
                            <?php if ($log['details']): ?>
                            <details class="mt-2">
                                <summary class="small text-primary" style="cursor: pointer;">
                                    عرض التفاصيل
                                </summary>
                                <pre class="mt-2 p-2 bg-light rounded small" style="max-height: 150px; overflow: auto;">
<?php echo htmlspecialchars($log['details']); ?>
                                </pre>
                            </details>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">السابق</a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">التالي</a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
