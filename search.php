<?php
/**
 * صفحة البحث - Search Engine
 * البحث في الموظفين والمستخدمين
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

check_login();

$pageTitle = 'البحث';
$currentPage = 'search';

$query = trim($_GET['q'] ?? '');
$results = [];
$totalResults = 0;

if ($query && mb_strlen($query) >= 2) {
    try {
        $searchTerm = "%{$query}%";
        $results = Database::fetchAll("
            SELECT 
                u.id,
                u.full_name,
                u.emp_code,
                u.email,
                u.phone,
                u.is_online,
                u.is_active,
                u.current_points,
                u.last_seen_at,
                u.avatar,
                r.name AS role_name,
                r.color AS role_color,
                r.icon AS role_icon,
                b.name AS branch_name,
                b.city AS branch_city
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN branches b ON u.branch_id = b.id
            WHERE 
                u.full_name LIKE :q1 
                OR u.emp_code LIKE :q2 
                OR u.email LIKE :q3
                OR u.phone LIKE :q4
            ORDER BY 
                CASE WHEN u.full_name LIKE :exact THEN 0 ELSE 1 END,
                u.is_online DESC,
                u.full_name ASC
            LIMIT 50
        ", [
            'q1' => $searchTerm,
            'q2' => $searchTerm,
            'q3' => $searchTerm,
            'q4' => $searchTerm,
            'exact' => $query
        ]);
        $totalResults = count($results);
    } catch (Exception $e) {
        $results = [];
    }
}

include INCLUDES_PATH . '/header.php';
?>

<style>
.search-hero {
    background: linear-gradient(135deg, var(--sarh-primary) 0%, var(--sarh-primary-light) 100%);
    padding: 2.5rem 0;
    margin: -1rem -12px 1.5rem;
    color: white;
}
.search-input-wrapper {
    position: relative;
    max-width: 600px;
    margin: 0 auto;
}
.search-input-wrapper .form-control {
    height: 56px;
    padding-right: 50px;
    padding-left: 120px;
    border-radius: 28px;
    font-size: 1.1rem;
    border: none;
    box-shadow: 0 8px 30px rgba(0,0,0,0.2);
}
.search-input-wrapper .search-icon {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--sarh-gray);
    font-size: 1.25rem;
}
.search-input-wrapper .btn {
    position: absolute;
    left: 6px;
    top: 50%;
    transform: translateY(-50%);
    border-radius: 22px;
    padding: 10px 24px;
}
.user-card {
    background: white;
    border-radius: 16px;
    padding: 1.25rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    transition: all 0.3s ease;
    height: 100%;
    text-decoration: none;
    color: inherit;
    display: block;
}
.user-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    color: inherit;
}
.user-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    margin: 0 auto 1rem;
    position: relative;
}
.user-avatar.online::after {
    content: '';
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 14px;
    height: 14px;
    background: #2ed573;
    border: 3px solid white;
    border-radius: 50%;
}
.user-card .user-name {
    font-weight: 700;
    font-size: 1.05rem;
    margin-bottom: 0.25rem;
    color: var(--sarh-dark);
}
.user-card .user-code {
    font-family: monospace;
    font-size: 0.85rem;
    color: var(--sarh-gray);
    margin-bottom: 0.5rem;
}
.user-card .user-meta {
    font-size: 0.8rem;
    color: var(--sarh-gray);
}
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}
.empty-state i {
    font-size: 5rem;
    color: #e0e0e0;
    margin-bottom: 1.5rem;
}
.empty-state h4 {
    color: var(--sarh-gray);
    margin-bottom: 0.5rem;
}
.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}
.search-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
    margin-top: 1rem;
}
.search-suggestions .badge {
    cursor: pointer;
    padding: 8px 16px;
    font-weight: 500;
    transition: all 0.2s;
}
.search-suggestions .badge:hover {
    transform: scale(1.05);
}
</style>

<!-- Hero Search Section -->
<div class="search-hero">
    <div class="container">
        <h4 class="text-center mb-4 fw-bold">
            <i class="bi bi-search me-2"></i>
            البحث في النظام
        </h4>
        <form method="GET" action="" class="search-input-wrapper">
            <i class="bi bi-search search-icon"></i>
            <input type="search" 
                   name="q" 
                   class="form-control" 
                   placeholder="ابحث بالاسم، رقم الموظف، البريد..."
                   value="<?= e($query) ?>"
                   autocomplete="off"
                   autofocus>
            <button type="submit" class="btn btn-warning fw-bold">
                <i class="bi bi-search me-1"></i>
                بحث
            </button>
        </form>
        
        <?php if (!$query): ?>
        <div class="search-suggestions">
            <span class="badge bg-white bg-opacity-25" onclick="document.querySelector('input[name=q]').value='مدير';document.querySelector('form').submit();">مدير</span>
            <span class="badge bg-white bg-opacity-25" onclick="document.querySelector('input[name=q]').value='موظف';document.querySelector('form').submit();">موظف</span>
            <span class="badge bg-white bg-opacity-25" onclick="document.querySelector('input[name=q]').value='admin';document.querySelector('form').submit();">admin</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="container pb-4">
    <?php if ($query): ?>
        <!-- Results Header -->
        <div class="results-header">
            <div>
                <h5 class="mb-1">
                    <?php if ($totalResults > 0): ?>
                    <i class="bi bi-check-circle text-success me-2"></i>
                    تم العثور على <strong class="text-primary"><?= $totalResults ?></strong> نتيجة
                    <?php else: ?>
                    <i class="bi bi-x-circle text-danger me-2"></i>
                    لا توجد نتائج
                    <?php endif; ?>
                </h5>
                <small class="text-muted">البحث عن: "<?= e($query) ?>"</small>
            </div>
            <a href="<?= url('search.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i>
                بحث جديد
            </a>
        </div>

        <?php if (!empty($results)): ?>
        <!-- Results Grid -->
        <div class="row g-3">
            <?php foreach ($results as $user): ?>
            <?php
                $initials = mb_substr($user['full_name'], 0, 2);
                $avatarColor = $user['role_color'] ?? '#6c757d';
                $lastSeen = $user['last_seen_at'] ? time_ago($user['last_seen_at']) : 'غير معروف';
            ?>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="<?= url('profile.php?id=' . $user['id']) ?>" class="user-card">
                    <div class="user-avatar <?= $user['is_online'] ? 'online' : '' ?>" style="background: <?= e($avatarColor) ?>;">
                        <?php if ($user['avatar']): ?>
                        <img src="<?= e($user['avatar']) ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                        <?= e($initials) ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-center">
                        <div class="user-name"><?= e($user['full_name']) ?></div>
                        <div class="user-code"><?= e($user['emp_code']) ?></div>
                        
                        <div class="mb-2">
                            <span class="badge" style="background: <?= e($avatarColor) ?>; font-size: 0.7rem;">
                                <?php if ($user['role_icon']): ?>
                                <i class="<?= e($user['role_icon']) ?> me-1"></i>
                                <?php endif; ?>
                                <?= e($user['role_name'] ?? 'موظف') ?>
                            </span>
                        </div>
                        
                        <?php if ($user['branch_name']): ?>
                        <div class="user-meta">
                            <i class="bi bi-building me-1"></i>
                            <?= e($user['branch_name']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="user-meta mt-1">
                            <?php if ($user['is_online']): ?>
                            <span class="text-success">
                                <i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>
                                متصل الآن
                            </span>
                            <?php else: ?>
                            <span class="text-muted">
                                <i class="bi bi-clock me-1"></i>
                                <?= $lastSeen ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!$user['is_active']): ?>
                        <span class="badge bg-danger mt-2" style="font-size:0.65rem;">معطل</span>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="bi bi-search"></i>
            <h4>لا توجد نتائج لـ "<?= e($query) ?>"</h4>
            <p class="text-muted mb-4">جرب البحث بكلمات مختلفة أو تحقق من الإملاء</p>
            <a href="<?= url('search.php') ?>" class="btn btn-primary">
                <i class="bi bi-arrow-right me-1"></i>
                بحث جديد
            </a>
        </div>
        <?php endif; ?>
        
    <?php else: ?>
    <!-- Initial State -->
    <div class="empty-state">
        <i class="bi bi-people"></i>
        <h4>ابحث عن موظف</h4>
        <p class="text-muted">يمكنك البحث بالاسم، رقم الموظف، أو البريد الإلكتروني</p>
    </div>
    <?php endif; ?>
</div>

<script>
// Auto-focus search input
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="q"]');
    if (searchInput && !searchInput.value) {
        searchInput.focus();
    }
});

// Keyboard shortcut (Ctrl/Cmd + K)
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.querySelector('input[name="q"]').focus();
    }
});
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
