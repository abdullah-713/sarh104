<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - ANNOUNCEMENTS / إعلانات الإدارة                       ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  ميزات الصفحة:                                                               ║
 * ║  - عرض إعلانات الإدارة                                                       ║
 * ║  - تصنيف حسب الأولوية                                                        ║
 * ║  - قراءة/تتبع الإعلانات                                                      ║
 * ║  - إنشاء إعلانات (للمدراء)                                                   ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

session_start();
require_once 'config/database.php';
require_once 'config/app.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title = 'الإعلانات';
$user_id = $_SESSION['user_id'];
$is_admin = is_super_admin($user_id);

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// جلب الإعلانات النشطة
try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               u.name as author_name,
               (SELECT COUNT(*) FROM announcement_reads WHERE announcement_id = a.id) as read_count,
               (SELECT 1 FROM announcement_reads WHERE announcement_id = a.id AND user_id = ?) as is_read
        FROM announcements a
        LEFT JOIN users u ON a.created_by = u.id
        WHERE a.is_active = 1
        AND (a.expires_at IS NULL OR a.expires_at > NOW())
        ORDER BY a.is_pinned DESC, a.priority DESC, a.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $announcements = [];
}

// عدد غير المقروءة
$unread_count = count(array_filter($announcements, fn($a) => !$a['is_read']));

// معالجة إنشاء إعلان جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement']) && $is_admin) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $title = htmlspecialchars(trim($_POST['title']), ENT_QUOTES, 'UTF-8');
    $content = htmlspecialchars(trim($_POST['content']), ENT_QUOTES, 'UTF-8');
    $priority = intval($_POST['priority']);
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    
    if (!empty($title) && !empty($content)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO announcements (title, content, priority, is_pinned, expires_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $content, $priority, $is_pinned, $expires_at, $user_id]);
            
            header('Location: announcements.php?success=1');
            exit;
        } catch (Exception $e) {
            $error = 'حدث خطأ أثناء إنشاء الإعلان';
        }
    }
}

// تسجيل قراءة الإعلان
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO announcement_reads (announcement_id, user_id)
            VALUES (?, ?)
        ");
        $stmt->execute([intval($_GET['read']), $user_id]);
    } catch (Exception $e) {
        // Ignore errors
    }
}

// مستويات الأولوية
$priority_levels = [
    1 => ['name' => 'عادي', 'color' => 'secondary', 'icon' => 'bi-info-circle'],
    2 => ['name' => 'متوسط', 'color' => 'info', 'icon' => 'bi-bell'],
    3 => ['name' => 'مهم', 'color' => 'warning', 'icon' => 'bi-exclamation-triangle'],
    4 => ['name' => 'عاجل', 'color' => 'danger', 'icon' => 'bi-megaphone']
];

require_once 'includes/header.php';
?>

<style>
    .announcement-card {
        border-radius: 15px;
        border: 1px solid #eee;
        overflow: hidden;
        margin-bottom: 15px;
        transition: all 0.3s ease;
    }

    .announcement-card:hover {
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    .announcement-card.unread {
        border-right: 4px solid var(--sarh-primary);
        background: rgba(255, 111, 0, 0.03);
    }

    .announcement-card.pinned {
        background: linear-gradient(135deg, #fff9e6 0%, #fff 100%);
        border-color: #ffc107;
    }

    .announcement-header {
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
    }

    .announcement-content {
        padding: 0 20px 20px;
    }

    .announcement-meta {
        display: flex;
        align-items: center;
        gap: 15px;
        font-size: 0.85rem;
        color: #888;
    }

    .priority-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
    }

    .pin-icon {
        color: #ffc107;
        font-size: 1.2rem;
    }

    .unread-dot {
        width: 10px;
        height: 10px;
        background: var(--sarh-primary);
        border-radius: 50%;
        display: inline-block;
    }

    .stats-row {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }

    .stat-item {
        background: linear-gradient(135deg, var(--sarh-primary), #ff9800);
        color: white;
        padding: 20px;
        border-radius: 15px;
        flex: 1;
        text-align: center;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
    }

    .fab-button {
        position: fixed;
        bottom: 100px;
        left: 20px;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: var(--sarh-primary);
        color: white;
        border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        font-size: 1.5rem;
        z-index: 1000;
    }

    .filter-pills {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .filter-pill {
        white-space: nowrap;
        padding: 8px 16px;
        border-radius: 20px;
        border: 1px solid #ddd;
        background: white;
        color: #666;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .filter-pill.active {
        background: var(--sarh-primary);
        color: white;
        border-color: var(--sarh-primary);
    }
</style>

<div class="container py-4 mb-5">
    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-megaphone text-primary me-2"></i>
                الإعلانات
            </h4>
            <small class="text-muted">
                <?php if ($unread_count > 0): ?>
                    <span class="text-warning"><?php echo $unread_count; ?> إعلان جديد</span>
                <?php else: ?>
                    جميع الإعلانات مقروءة
                <?php endif; ?>
            </small>
        </div>
        <a href="more.php" class="btn btn-sm btn-outline-secondary rounded-pill">
            <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        تم نشر الإعلان بنجاح!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Stats Row (Admin only) -->
    <?php if ($is_admin): ?>
    <div class="stats-row">
        <div class="stat-item">
            <div class="stat-number"><?php echo count($announcements); ?></div>
            <small>إعلان نشط</small>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo $unread_count; ?></div>
            <small>غير مقروء</small>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Pills -->
    <div class="filter-pills">
        <div class="filter-pill active" onclick="filterAnnouncements('all')">
            <i class="bi bi-grid me-1"></i>
            الكل
        </div>
        <div class="filter-pill" onclick="filterAnnouncements('unread')">
            <i class="bi bi-circle-fill text-warning me-1" style="font-size: 0.6rem;"></i>
            غير المقروءة
        </div>
        <div class="filter-pill" onclick="filterAnnouncements('pinned')">
            <i class="bi bi-pin-angle me-1"></i>
            المثبتة
        </div>
        <div class="filter-pill" onclick="filterAnnouncements('urgent')">
            <i class="bi bi-exclamation-triangle me-1"></i>
            العاجلة
        </div>
    </div>

    <!-- Announcements List -->
    <div id="announcementsList">
        <?php if (empty($announcements)): ?>
        <div class="text-center py-5">
            <i class="bi bi-megaphone text-muted" style="font-size: 4rem;"></i>
            <p class="text-muted mt-3">لا توجد إعلانات حالياً</p>
        </div>
        <?php else: ?>
            <?php foreach ($announcements as $ann): ?>
            <?php 
                $priority = $priority_levels[$ann['priority']] ?? $priority_levels[1];
                $time_ago = time_ago($ann['created_at']);
            ?>
            <div class="announcement-card <?php echo $ann['is_read'] ? '' : 'unread'; ?> <?php echo $ann['is_pinned'] ? 'pinned' : ''; ?>" 
                 data-priority="<?php echo $ann['priority']; ?>"
                 data-read="<?php echo $ann['is_read'] ? '1' : '0'; ?>"
                 data-pinned="<?php echo $ann['is_pinned']; ?>"
                 onclick="markAsRead(<?php echo $ann['id']; ?>)">
                
                <div class="announcement-header">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <?php if ($ann['is_pinned']): ?>
                            <i class="bi bi-pin-angle-fill pin-icon"></i>
                            <?php endif; ?>
                            
                            <?php if (!$ann['is_read']): ?>
                            <span class="unread-dot"></span>
                            <?php endif; ?>
                            
                            <h6 class="mb-0"><?php echo $ann['title']; ?></h6>
                        </div>
                        
                        <div class="announcement-meta">
                            <span>
                                <i class="bi bi-person me-1"></i>
                                <?php echo $ann['author_name'] ?? 'الإدارة'; ?>
                            </span>
                            <span>
                                <i class="bi bi-clock me-1"></i>
                                <?php echo $time_ago; ?>
                            </span>
                            <span>
                                <i class="bi bi-eye me-1"></i>
                                <?php echo $ann['read_count']; ?> قراءة
                            </span>
                        </div>
                    </div>
                    
                    <span class="badge bg-<?php echo $priority['color']; ?> priority-badge">
                        <i class="bi <?php echo $priority['icon']; ?> me-1"></i>
                        <?php echo $priority['name']; ?>
                    </span>
                </div>
                
                <div class="announcement-content">
                    <p class="mb-0 text-muted"><?php echo nl2br($ann['content']); ?></p>
                    
                    <?php if ($ann['expires_at']): ?>
                    <div class="mt-2">
                        <small class="text-danger">
                            <i class="bi bi-hourglass-split me-1"></i>
                            ينتهي: <?php echo date('j M Y', strtotime($ann['expires_at'])); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- FAB for Admin -->
<?php if ($is_admin): ?>
<button class="fab-button" data-bs-toggle="modal" data-bs-target="#newAnnouncementModal">
    <i class="bi bi-plus"></i>
</button>

<!-- New Announcement Modal -->
<div class="modal fade" id="newAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    <i class="bi bi-megaphone text-primary me-2"></i>
                    إعلان جديد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="create_announcement" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">عنوان الإعلان</label>
                        <input type="text" name="title" class="form-control" required 
                               placeholder="عنوان واضح ومختصر">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">محتوى الإعلان</label>
                        <textarea name="content" class="form-control" rows="5" required
                                  placeholder="تفاصيل الإعلان..."></textarea>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">الأولوية</label>
                            <select name="priority" class="form-select">
                                <?php foreach ($priority_levels as $level => $info): ?>
                                <option value="<?php echo $level; ?>"><?php echo $info['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">تاريخ الانتهاء (اختياري)</label>
                            <input type="date" name="expires_at" class="form-control" 
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-check form-switch">
                        <input type="checkbox" name="is_pinned" class="form-check-input" id="isPinned">
                        <label class="form-check-label" for="isPinned">
                            <i class="bi bi-pin-angle me-1"></i>
                            تثبيت الإعلان في الأعلى
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-2"></i>
                        نشر الإعلان
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function filterAnnouncements(filter) {
    document.querySelectorAll('.filter-pill').forEach(pill => pill.classList.remove('active'));
    event.target.closest('.filter-pill').classList.add('active');
    
    document.querySelectorAll('.announcement-card').forEach(card => {
        let show = false;
        
        switch(filter) {
            case 'all':
                show = true;
                break;
            case 'unread':
                show = card.dataset.read === '0';
                break;
            case 'pinned':
                show = card.dataset.pinned === '1';
                break;
            case 'urgent':
                show = parseInt(card.dataset.priority) >= 3;
                break;
        }
        
        card.style.display = show ? 'block' : 'none';
    });
}

function markAsRead(id) {
    fetch(`announcements.php?read=${id}`, { method: 'GET' });
    
    const card = event.target.closest('.announcement-card');
    if (card) {
        card.classList.remove('unread');
        card.dataset.read = '1';
        const dot = card.querySelector('.unread-dot');
        if (dot) dot.remove();
    }
}

// Time ago helper function (defined in PHP)
<?php
function time_ago($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'الآن';
    if ($diff < 3600) return floor($diff / 60) . ' دقيقة';
    if ($diff < 86400) return floor($diff / 3600) . ' ساعة';
    if ($diff < 604800) return floor($diff / 86400) . ' يوم';
    if ($diff < 2592000) return floor($diff / 604800) . ' أسبوع';
    return date('j M Y', $time);
}
?>
</script>

<?php require_once 'includes/footer.php'; ?>
