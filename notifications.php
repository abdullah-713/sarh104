<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * نظام صرح الإتقان - صفحة الإشعارات المحسنة
 * Sarh Al-Itqan - Enhanced Notifications Page
 * ═══════════════════════════════════════════════════════════════════════════════
 * @version 2.0.0
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

check_login();

$pageTitle = 'الإشعارات';
$currentPage = 'notifications';

$userId = current_user_id();
$roleLevel = $_SESSION['role_level'] ?? 1;

// Handle mark all as read
if (isset($_POST['mark_all_read']) && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    try {
        Database::query("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0", [$userId]);
        $_SESSION['unread_notifications'] = 0;
        set_flash('success', 'تم تعيين جميع الإشعارات كمقروءة');
    } catch (Exception $e) {}
    redirect(url('notifications.php'));
}

// Handle single notification mark as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    try {
        Database::query("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?", [$_GET['mark_read'], $userId]);
        $unread = Database::fetch("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0", [$userId]);
        $_SESSION['unread_notifications'] = $unread['cnt'] ?? 0;
    } catch (Exception $e) {}
    redirect(url('notifications.php'));
}

// Handle delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        Database::query("DELETE FROM notifications WHERE id = ? AND user_id = ?", [$_GET['delete'], $userId]);
        set_flash('success', 'تم حذف الإشعار');
    } catch (Exception $e) {}
    redirect(url('notifications.php'));
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$whereClause = "WHERE user_id = :user_id";
$params = ['user_id' => $userId];

if ($filter === 'unread') {
    $whereClause .= " AND is_read = 0";
} elseif ($filter !== 'all' && in_array($filter, ['success', 'warning', 'danger', 'info', 'attendance', 'points', 'system'])) {
    $whereClause .= " AND type = :type";
    $params['type'] = $filter;
}

// Fetch notifications
try {
    // Get total count
    $totalResult = Database::fetchOne("SELECT COUNT(*) as total FROM notifications $whereClause", $params);
    $total = $totalResult['total'] ?? 0;
    $totalPages = ceil($total / $perPage);
    
    // Get notifications
    $notifications = Database::fetchAll("
        SELECT * FROM notifications 
        $whereClause 
        ORDER BY is_read ASC, created_at DESC 
        LIMIT $perPage OFFSET $offset
    ", $params);
    
    $unreadCount = 0;
    foreach ($notifications as $n) {
        if (!$n['is_read']) $unreadCount++;
    }
    
    // Get unread total
    $unreadTotal = Database::fetchOne("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = :user_id AND is_read = 0", ['user_id' => $userId]);
    $unreadCount = $unreadTotal['cnt'] ?? 0;
    
} catch (Exception $e) {
    $notifications = [];
    $unreadCount = 0;
    $total = 0;
    $totalPages = 0;
}

// Fetch integrity logs for admins
$integrityLogs = [];
if ($roleLevel >= 8) {
    try {
        $integrityLogs = Database::fetchAll("
            SELECT 
                il.*,
                u.full_name,
                u.emp_code,
                u.avatar
            FROM integrity_logs il
            LEFT JOIN users u ON il.user_id = u.id
            ORDER BY il.created_at DESC
            LIMIT 30
        ");
    } catch (Exception $e) {
        $integrityLogs = [];
    }
}

// Notification type styles
$notificationStyles = [
    'success' => ['icon' => 'bi-check-circle-fill', 'bg' => 'success', 'color' => '#2ed573', 'label' => 'نجاح'],
    'warning' => ['icon' => 'bi-exclamation-triangle-fill', 'bg' => 'warning', 'color' => '#ffa502', 'label' => 'تحذير'],
    'danger' => ['icon' => 'bi-x-circle-fill', 'bg' => 'danger', 'color' => '#ff4757', 'label' => 'تنبيه'],
    'error' => ['icon' => 'bi-x-circle-fill', 'bg' => 'danger', 'color' => '#ff4757', 'label' => 'خطأ'],
    'info' => ['icon' => 'bi-info-circle-fill', 'bg' => 'info', 'color' => '#3742fa', 'label' => 'معلومات'],
    'attendance' => ['icon' => 'bi-calendar-check-fill', 'bg' => 'primary', 'color' => '#ff6f00', 'label' => 'حضور'],
    'points' => ['icon' => 'bi-star-fill', 'bg' => 'warning', 'color' => '#ffa502', 'label' => 'نقاط'],
    'leave' => ['icon' => 'bi-calendar-x-fill', 'bg' => 'info', 'color' => '#0288d1', 'label' => 'إجازة'],
    'system' => ['icon' => 'bi-gear-fill', 'bg' => 'secondary', 'color' => '#6c757d', 'label' => 'نظام'],
    'achievement' => ['icon' => 'bi-trophy-fill', 'bg' => 'warning', 'color' => '#ffd700', 'label' => 'إنجاز'],
    'message' => ['icon' => 'bi-chat-dots-fill', 'bg' => 'info', 'color' => '#9b59b6', 'label' => 'رسالة'],
];

function getNotificationStyle($type) {
    global $notificationStyles;
    return $notificationStyles[$type] ?? $notificationStyles['info'];
}

function getSeverityStyle($severity) {
    $styles = [
        'critical' => ['bg' => 'danger', 'color' => '#c0392b', 'label' => 'حرج', 'icon' => 'bi-exclamation-octagon-fill'],
        'high' => ['bg' => 'warning', 'color' => '#e67e22', 'label' => 'عالي', 'icon' => 'bi-exclamation-triangle-fill'],
        'medium' => ['bg' => 'info', 'color' => '#2980b9', 'label' => 'متوسط', 'icon' => 'bi-info-circle-fill'],
        'low' => ['bg' => 'secondary', 'color' => '#7f8c8d', 'label' => 'منخفض', 'icon' => 'bi-shield-check'],
    ];
    return $styles[$severity] ?? $styles['low'];
}

include INCLUDES_PATH . '/header.php';
?>

<style>
/* ═══════════════════════════════════════════════════════════════════════════════
   أنماط صفحة الإشعارات المحسنة
   ═══════════════════════════════════════════════════════════════════════════════ */

/* الترويسة */
.notifications-hero {
    background: linear-gradient(135deg, #ff6f00 0%, #ff8f00 50%, #ffa040 100%);
    color: white;
    padding: 2rem 0 3rem;
    margin: -1rem -12px 0;
    position: relative;
    overflow: hidden;
}

.notifications-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -25%;
    width: 80%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
    animation: heroGlow 8s ease-in-out infinite;
}

@keyframes heroGlow {
    0%, 100% { opacity: 0.5; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.1); }
}

.notifications-hero .hero-content {
    position: relative;
    z-index: 1;
}

.notifications-hero h1 {
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
}

.notifications-hero .hero-stats {
    display: flex;
    gap: 1.5rem;
    margin-top: 1rem;
}

.hero-stat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255,255,255,0.15);
    padding: 0.5rem 1rem;
    border-radius: 30px;
    backdrop-filter: blur(5px);
}

.hero-stat i {
    font-size: 1.1rem;
}

.hero-stat span {
    font-weight: 600;
}

/* أزرار الترويسة */
.hero-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1.5rem;
}

.hero-actions .btn {
    padding: 0.6rem 1.25rem;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s;
}

.hero-actions .btn-light {
    background: white;
    color: #ff6f00;
    border: none;
}

.hero-actions .btn-light:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.hero-actions .btn-outline-light {
    border: 2px solid rgba(255,255,255,0.5);
}

.hero-actions .btn-outline-light:hover {
    background: rgba(255,255,255,0.15);
    border-color: white;
}

/* شريط التصفية */
.filter-bar {
    background: white;
    border-radius: 20px;
    padding: 1rem 1.5rem;
    margin: -2rem auto 1.5rem;
    max-width: 800px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    position: relative;
    z-index: 10;
}

.filter-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
}

.filter-pill {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    background: #f5f5f5;
    color: #666;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.filter-pill:hover {
    background: #e8e8e8;
    color: #333;
}

.filter-pill.active {
    background: linear-gradient(135deg, #ff6f00, #ffa040);
    color: white;
}

.filter-pill .badge {
    background: rgba(0,0,0,0.15);
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 10px;
}

.filter-pill.active .badge {
    background: rgba(255,255,255,0.25);
}

/* التبويبات */
.nav-tabs-modern {
    border: none;
    background: white;
    border-radius: 16px;
    padding: 0.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 1.5rem;
}

.nav-tabs-modern .nav-link {
    border: none;
    border-radius: 12px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    color: #666;
    transition: all 0.2s;
}

.nav-tabs-modern .nav-link:hover {
    background: #f5f5f5;
}

.nav-tabs-modern .nav-link.active {
    background: linear-gradient(135deg, #ff6f00, #ffa040);
    color: white;
}

/* بطاقة الإشعار */
.notification-card {
    background: white;
    border-radius: 16px;
    margin-bottom: 0.75rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.notification-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.notification-card.unread {
    background: linear-gradient(90deg, rgba(255,111,0,0.05) 0%, white 100%);
}

.notification-card.unread::before {
    content: '';
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, #ff6f00, #ffa040);
}

.notification-card-body {
    display: flex;
    gap: 1rem;
    padding: 1.25rem;
}

.notification-icon-wrapper {
    flex-shrink: 0;
}

.notification-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    transition: transform 0.3s;
}

.notification-card:hover .notification-icon {
    transform: scale(1.1);
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.4rem;
    gap: 1rem;
}

.notification-title {
    font-weight: 700;
    color: #1a1a2e;
    font-size: 0.95rem;
    line-height: 1.4;
}

.notification-message {
    color: #6c757d;
    font-size: 0.875rem;
    line-height: 1.5;
    margin-bottom: 0.5rem;
}

.notification-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.75rem;
    color: #adb5bd;
}

.notification-time {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.notification-type-badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.6rem;
    border-radius: 10px;
}

.notification-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.notification-actions .btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f5f5;
    color: #666;
    border: none;
    transition: all 0.2s;
}

.notification-actions .btn-icon:hover {
    background: #e8e8e8;
    color: #333;
}

.notification-actions .btn-icon.text-danger:hover {
    background: #ffebee;
    color: #c62828;
}

.unread-dot {
    width: 10px;
    height: 10px;
    background: linear-gradient(135deg, #ff6f00, #ffa040);
    border-radius: 50%;
    box-shadow: 0 0 0 3px rgba(255,111,0,0.2);
    animation: dotPulse 2s ease-in-out infinite;
}

@keyframes dotPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

/* حالة فارغة */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 20px;
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-state-icon i {
    font-size: 3.5rem;
    color: #ccc;
}

.empty-state h4 {
    color: #666;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #999;
    margin-bottom: 1.5rem;
}

/* سجل النزاهة */
.integrity-card {
    background: white;
    border-radius: 14px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    transition: all 0.2s;
    border-right: 4px solid #ccc;
}

.integrity-card:hover {
    transform: translateX(-4px);
}

.integrity-card.severity-critical {
    border-right-color: #c0392b;
    background: linear-gradient(90deg, rgba(192,57,43,0.05), white);
}

.integrity-card.severity-high {
    border-right-color: #e67e22;
    background: linear-gradient(90deg, rgba(230,126,34,0.05), white);
}

.integrity-card.severity-medium {
    border-right-color: #3498db;
}

.integrity-card.severity-low {
    border-right-color: #95a5a6;
}

.integrity-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.integrity-user {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.integrity-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    color: #666;
}

/* Pagination */
.pagination-modern {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.pagination-modern .page-link {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: white;
    color: #666;
    font-weight: 600;
    transition: all 0.2s;
}

.pagination-modern .page-link:hover {
    background: #f5f5f5;
}

.pagination-modern .page-item.active .page-link {
    background: linear-gradient(135deg, #ff6f00, #ffa040);
    color: white;
}

.pagination-modern .page-item.disabled .page-link {
    opacity: 0.5;
}

/* تجاوب */
@media (max-width: 768px) {
    .notifications-hero {
        padding: 1.5rem 0 2.5rem;
    }
    
    .notifications-hero h1 {
        font-size: 1.4rem;
    }
    
    .hero-stats {
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    
    .hero-stat {
        font-size: 0.85rem;
        padding: 0.4rem 0.75rem;
    }
    
    .filter-bar {
        margin: -1.5rem 0.75rem 1rem;
        padding: 0.75rem 1rem;
    }
    
    .filter-pills {
        justify-content: flex-start;
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 0.5rem;
    }
    
    .filter-pill {
        white-space: nowrap;
    }
    
    .notification-card-body {
        padding: 1rem;
    }
    
    .notification-icon {
        width: 44px;
        height: 44px;
        font-size: 1.1rem;
    }
}
</style>

<!-- Hero Section -->
<div class="notifications-hero">
    <div class="container hero-content">
        <h1>
            <i class="bi bi-bell-fill me-2"></i>
            الإشعارات
        </h1>
        <p class="opacity-75 mb-0">تابع جميع التنبيهات والتحديثات الخاصة بك</p>
        
        <div class="hero-stats">
            <div class="hero-stat">
                <i class="bi bi-envelope-fill"></i>
                <span><?= $total ?> إشعار</span>
            </div>
            <?php if ($unreadCount > 0): ?>
            <div class="hero-stat">
                <i class="bi bi-envelope-exclamation-fill"></i>
                <span><?= $unreadCount ?> غير مقروء</span>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="hero-actions">
            <?php if ($unreadCount > 0): ?>
            <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" name="mark_all_read" value="1" class="btn btn-light">
                    <i class="bi bi-check-all me-1"></i>
                    تعيين الكل كمقروء
                </button>
            </form>
            <?php endif; ?>
            <a href="<?= url('settings.php#notifications') ?>" class="btn btn-outline-light">
                <i class="bi bi-gear me-1"></i>
                الإعدادات
            </a>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="container">
    <div class="filter-bar">
        <div class="filter-pills">
            <a href="?filter=all" class="filter-pill <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="bi bi-grid"></i>
                الكل
            </a>
            <a href="?filter=unread" class="filter-pill <?= $filter === 'unread' ? 'active' : '' ?>">
                <i class="bi bi-envelope"></i>
                غير المقروء
                <?php if ($unreadCount > 0): ?>
                <span class="badge"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
            <a href="?filter=attendance" class="filter-pill <?= $filter === 'attendance' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i>
                الحضور
            </a>
            <a href="?filter=points" class="filter-pill <?= $filter === 'points' ? 'active' : '' ?>">
                <i class="bi bi-star"></i>
                النقاط
            </a>
            <a href="?filter=warning" class="filter-pill <?= $filter === 'warning' ? 'active' : '' ?>">
                <i class="bi bi-exclamation-triangle"></i>
                تحذيرات
            </a>
            <a href="?filter=system" class="filter-pill <?= $filter === 'system' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i>
                النظام
            </a>
        </div>
    </div>
</div>

<div class="container pb-4">
    
    <?php if ($roleLevel >= 8): ?>
    <!-- Tabs for Admins -->
    <ul class="nav nav-tabs-modern mb-4" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-notifications">
                <i class="bi bi-bell-fill me-2"></i>
                إشعاراتي
                <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger ms-1"><?= $unreadCount ?></span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-integrity">
                <i class="bi bi-shield-exclamation me-2"></i>
                سجل النزاهة
                <?php if (!empty($integrityLogs)): ?>
                <span class="badge bg-secondary ms-1"><?= count($integrityLogs) ?></span>
                <?php endif; ?>
            </button>
        </li>
    </ul>
    <?php endif; ?>
    
    <div class="tab-content">
        <!-- Notifications Tab -->
        <div class="tab-pane fade show active" id="tab-notifications">
            <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="bi bi-bell-slash"></i>
                </div>
                <h4>لا توجد إشعارات</h4>
                <p>
                    <?php if ($filter !== 'all'): ?>
                    لا توجد إشعارات تطابق هذا الفلتر
                    <?php else: ?>
                    ستظهر الإشعارات الجديدة هنا
                    <?php endif; ?>
                </p>
                <?php if ($filter !== 'all'): ?>
                <a href="?filter=all" class="btn btn-primary">
                    <i class="bi bi-grid me-1"></i>
                    عرض جميع الإشعارات
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $notif): ?>
                <?php $style = getNotificationStyle($notif['type'] ?? 'info'); ?>
                <div class="notification-card <?= !$notif['is_read'] ? 'unread' : '' ?> fade-in">
                    <div class="notification-card-body">
                        <div class="notification-icon-wrapper">
                            <div class="notification-icon" style="background: <?= $style['color'] ?>15; color: <?= $style['color'] ?>">
                                <i class="bi <?= $style['icon'] ?>"></i>
                            </div>
                        </div>
                        
                        <div class="notification-content">
                            <div class="notification-header">
                                <h6 class="notification-title"><?= e($notif['title']) ?></h6>
                                <div class="notification-actions">
                                    <?php if (!$notif['is_read']): ?>
                                    <span class="unread-dot" title="غير مقروء"></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <p class="notification-message"><?= e($notif['message']) ?></p>
                            
                            <div class="notification-meta">
                                <span class="notification-time">
                                    <i class="bi bi-clock"></i>
                                    <?= time_ago($notif['created_at']) ?>
                                </span>
                                <span class="notification-type-badge badge bg-<?= $style['bg'] ?> bg-opacity-10 text-<?= $style['bg'] ?>">
                                    <?= $style['label'] ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="notification-actions">
                            <?php if (!$notif['is_read']): ?>
                            <a href="?mark_read=<?= $notif['id'] ?>" class="btn-icon" title="تعيين كمقروء">
                                <i class="bi bi-check-lg"></i>
                            </a>
                            <?php endif; ?>
                            <a href="?delete=<?= $notif['id'] ?>" class="btn-icon text-danger" title="حذف" onclick="return confirm('هل تريد حذف هذا الإشعار؟')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="pagination-modern">
                <?php if ($page > 1): ?>
                <a href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>" class="page-link">
                    <i class="bi bi-chevron-right"></i>
                </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?filter=<?= $filter ?>&page=<?= $i ?>" class="page-link <?= $i === $page ? 'active' : '' ?>" style="<?= $i === $page ? 'background: linear-gradient(135deg, #ff6f00, #ffa040); color: white;' : '' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>" class="page-link">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($roleLevel >= 8): ?>
        <!-- Integrity Tab -->
        <div class="tab-pane fade" id="tab-integrity">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-shield-exclamation text-danger me-2"></i>
                    سجل النزاهة والأمان
                </h5>
                <a href="<?= url('admin/management.php?tab=integrity') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>
                    عرض الكل
                </a>
            </div>
            
            <?php if (empty($integrityLogs)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h4>لا توجد سجلات نزاهة</h4>
                <p>النظام يعمل بشكل طبيعي</p>
            </div>
            <?php else: ?>
            <?php foreach ($integrityLogs as $log): ?>
            <?php $severity = getSeverityStyle($log['severity'] ?? 'low'); ?>
            <div class="integrity-card severity-<?= e($log['severity'] ?? 'low') ?> fade-in">
                <div class="integrity-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-<?= $severity['bg'] ?>">
                            <i class="bi <?= $severity['icon'] ?> me-1"></i>
                            <?= $severity['label'] ?>
                        </span>
                        <strong><?= e($log['action_type'] ?? 'نشاط') ?></strong>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-clock me-1"></i>
                        <?= time_ago($log['created_at']) ?>
                    </small>
                </div>
                
                <?php if ($log['full_name']): ?>
                <div class="integrity-user">
                    <div class="integrity-avatar">
                        <?= mb_substr($log['full_name'], 0, 2) ?>
                    </div>
                    <div>
                        <strong><?= e($log['full_name']) ?></strong>
                        <br>
                        <small class="text-muted">
                            <code><?= e($log['emp_code']) ?></code>
                        </small>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($log['details']): ?>
                <details class="mt-2">
                    <summary class="text-muted small" style="cursor:pointer;">
                        <i class="bi bi-info-circle me-1"></i>
                        عرض التفاصيل
                    </summary>
                    <pre class="bg-light p-2 rounded mt-2 mb-0 small" style="max-height:150px;overflow:auto;direction:ltr;"><?= e(json_encode(json_decode($log['details']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                </details>
                <?php endif; ?>
                
                <?php if (!($log['is_reviewed'] ?? true)): ?>
                <div class="mt-2">
                    <span class="badge bg-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        يحتاج مراجعة
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// تحديث الإشعارات تلقائياً
document.addEventListener('DOMContentLoaded', function() {
    // إضافة تأثير التلاشي للبطاقات
    const cards = document.querySelectorAll('.notification-card, .integrity-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.05) + 's';
    });
});
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
