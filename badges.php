<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - BADGES & ACHIEVEMENTS / الشارات والإنجازات           ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  عرض جميع الشارات المتاحة وإنجازات المستخدم                                   ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

session_start();
require_once 'config/database.php';
require_once 'config/app.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title = 'الشارات والإنجازات';
$user_id = $_SESSION['user_id'];

// جلب جميع الشارات
try {
    $stmt = $pdo->query("SELECT * FROM badges ORDER BY points_required ASC");
    $all_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_badges = [];
}

// جلب شارات المستخدم
try {
    $stmt = $pdo->prepare("
        SELECT b.*, ub.earned_at
        FROM user_badges ub
        JOIN badges b ON ub.badge_id = b.id
        WHERE ub.user_id = ?
        ORDER BY ub.earned_at DESC
    ");
    $stmt->execute([$user_id]);
    $user_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $earned_ids = array_column($user_badges, 'id');
} catch (Exception $e) {
    $user_badges = [];
    $earned_ids = [];
}

// نقاط المستخدم الحالية
try {
    $stmt = $pdo->prepare("SELECT COALESCE(points, 0) as points FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_points = $stmt->fetchColumn() ?: 0;
} catch (Exception $e) {
    $user_points = 0;
}

// إحصائيات الإنجاز
$total_badges = count($all_badges);
$earned_count = count($user_badges);
$progress_percent = $total_badges > 0 ? round(($earned_count / $total_badges) * 100) : 0;

// تصنيف الشارات
$badge_categories = [
    'attendance' => ['name' => 'الحضور', 'icon' => 'bi-calendar-check', 'color' => 'success'],
    'performance' => ['name' => 'الأداء', 'icon' => 'bi-graph-up', 'color' => 'primary'],
    'social' => ['name' => 'التواصل', 'icon' => 'bi-people', 'color' => 'info'],
    'special' => ['name' => 'خاصة', 'icon' => 'bi-star', 'color' => 'warning'],
    'milestone' => ['name' => 'إنجازات', 'icon' => 'bi-trophy', 'color' => 'danger']
];

require_once 'includes/header.php';
?>

<style>
    .badge-card {
        border-radius: 20px;
        padding: 25px 20px;
        text-align: center;
        transition: all 0.4s ease;
        border: 2px solid #eee;
        background: white;
        position: relative;
        overflow: hidden;
    }

    .badge-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    }

    .badge-card.earned {
        border-color: var(--sarh-primary);
        background: linear-gradient(135deg, #fff9e6 0%, #fff 100%);
    }

    .badge-card.earned::before {
        content: '✓';
        position: absolute;
        top: 10px;
        left: 10px;
        width: 25px;
        height: 25px;
        background: #28a745;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: bold;
    }

    .badge-card.locked {
        opacity: 0.6;
        filter: grayscale(80%);
    }

    .badge-card.locked:hover {
        opacity: 0.8;
        filter: grayscale(50%);
    }

    .badge-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        font-size: 2.5rem;
        transition: all 0.3s ease;
    }

    .badge-card:hover .badge-icon {
        transform: scale(1.1) rotate(5deg);
    }

    .badge-card.earned .badge-icon {
        box-shadow: 0 5px 20px rgba(255, 111, 0, 0.3);
    }

    .badge-name {
        font-weight: 700;
        font-size: 1rem;
        margin-bottom: 5px;
    }

    .badge-desc {
        font-size: 0.8rem;
        color: #666;
        margin-bottom: 10px;
    }

    .badge-points {
        font-size: 0.85rem;
        color: var(--sarh-primary);
        font-weight: 600;
    }

    .badge-date {
        font-size: 0.75rem;
        color: #888;
        margin-top: 8px;
    }

    .progress-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 25px;
    }

    .progress-circle {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: conic-gradient(
            #fff <?php echo $progress_percent * 3.6; ?>deg,
            rgba(255,255,255,0.2) <?php echo $progress_percent * 3.6; ?>deg
        );
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
    }

    .progress-inner {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .progress-percent {
        font-size: 2rem;
        font-weight: 700;
    }

    .category-tabs {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }

    .category-tab {
        padding: 10px 20px;
        border-radius: 25px;
        border: 1px solid #ddd;
        background: white;
        white-space: nowrap;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        color: #666;
    }

    .category-tab:hover,
    .category-tab.active {
        background: var(--sarh-primary);
        color: white;
        border-color: var(--sarh-primary);
    }

    .stats-row {
        display: flex;
        justify-content: center;
        gap: 30px;
        margin-top: 20px;
    }

    .stat-item {
        text-align: center;
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
    }

    .stat-label {
        font-size: 0.85rem;
        opacity: 0.9;
    }

    .next-badge-card {
        background: linear-gradient(135deg, #ff6f00, #ff9800);
        color: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .shake-animation {
        animation: shake 0.5s ease-in-out;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px) rotate(-2deg); }
        75% { transform: translateX(5px) rotate(2deg); }
    }

    .confetti {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 9999;
    }
</style>

<div class="container py-4 mb-5">
    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-award text-warning me-2"></i>
                الشارات والإنجازات
            </h4>
            <small class="text-muted">اجمع الشارات وأثبت تميزك!</small>
        </div>
        <a href="more.php" class="btn btn-sm btn-outline-secondary rounded-pill">
            <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <!-- Progress Card -->
    <div class="progress-card">
        <div class="row align-items-center">
            <div class="col-5">
                <div class="progress-circle">
                    <div class="progress-inner">
                        <span class="progress-percent"><?php echo $progress_percent; ?>%</span>
                        <small>مكتمل</small>
                    </div>
                </div>
            </div>
            <div class="col-7">
                <h5 class="mb-2">تقدم الإنجازات</h5>
                <p class="mb-0 opacity-75">
                    حصلت على <?php echo $earned_count; ?> من <?php echo $total_badges; ?> شارة
                </p>
                <div class="stats-row mt-3">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($user_points); ?></div>
                        <div class="stat-label">نقاطك</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $earned_count; ?></div>
                        <div class="stat-label">شارة</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Next Badge to Earn -->
    <?php
    $next_badge = null;
    foreach ($all_badges as $badge) {
        if (!in_array($badge['id'], $earned_ids)) {
            $next_badge = $badge;
            break;
        }
    }
    ?>
    <?php if ($next_badge): ?>
    <div class="next-badge-card">
        <div class="d-flex align-items-center gap-3">
            <div style="font-size: 2.5rem;"><?php echo $next_badge['icon']; ?></div>
            <div class="flex-grow-1">
                <small class="opacity-75">الشارة التالية</small>
                <h6 class="mb-0"><?php echo $next_badge['name']; ?></h6>
                <small class="opacity-75"><?php echo $next_badge['description']; ?></small>
            </div>
            <div class="text-end">
                <strong><?php echo $next_badge['points_required']; ?></strong>
                <small class="d-block opacity-75">نقطة مطلوبة</small>
            </div>
        </div>
        <?php 
        $progress_to_next = $next_badge['points_required'] > 0 
            ? min(100, round(($user_points / $next_badge['points_required']) * 100)) 
            : 0;
        ?>
        <div class="progress mt-3" style="height: 8px; background: rgba(255,255,255,0.3);">
            <div class="progress-bar bg-light" style="width: <?php echo $progress_to_next; ?>%"></div>
        </div>
        <small class="d-block text-end mt-1 opacity-75"><?php echo $progress_to_next; ?>% مكتمل</small>
    </div>
    <?php endif; ?>

    <!-- Category Tabs -->
    <div class="category-tabs">
        <span class="category-tab active" data-category="all">
            <i class="bi bi-grid me-1"></i>
            الكل
        </span>
        <span class="category-tab" data-category="earned">
            <i class="bi bi-check-circle me-1"></i>
            المكتسبة
        </span>
        <span class="category-tab" data-category="locked">
            <i class="bi bi-lock me-1"></i>
            المقفلة
        </span>
    </div>

    <!-- Badges Grid -->
    <div class="row g-3" id="badgesGrid">
        <?php foreach ($all_badges as $badge): ?>
        <?php 
            $is_earned = in_array($badge['id'], $earned_ids);
            $earned_badge = $is_earned ? array_filter($user_badges, fn($b) => $b['id'] == $badge['id']) : [];
            $earned_badge = $is_earned ? array_values($earned_badge)[0] : null;
        ?>
        <div class="col-6 col-md-4 col-lg-3 badge-item" data-earned="<?php echo $is_earned ? '1' : '0'; ?>">
            <div class="badge-card <?php echo $is_earned ? 'earned' : 'locked'; ?>">
                <div class="badge-icon" style="background: <?php echo $badge['color'] ?? '#f0f0f0'; ?>;">
                    <?php echo $badge['icon'] ?? '🏆'; ?>
                </div>
                <div class="badge-name"><?php echo $badge['name']; ?></div>
                <div class="badge-desc"><?php echo $badge['description']; ?></div>
                <div class="badge-points">
                    <i class="bi bi-star-fill me-1"></i>
                    <?php echo $badge['points_required']; ?> نقطة
                </div>
                <?php if ($is_earned && $earned_badge): ?>
                <div class="badge-date">
                    <i class="bi bi-calendar-check me-1"></i>
                    <?php echo date('j M Y', strtotime($earned_badge['earned_at'])); ?>
                </div>
                <?php elseif (!$is_earned): ?>
                <div class="badge-date">
                    <i class="bi bi-lock me-1"></i>
                    مقفلة
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($all_badges)): ?>
    <div class="text-center py-5">
        <i class="bi bi-award text-muted" style="font-size: 4rem;"></i>
        <p class="text-muted mt-3">لا توجد شارات متاحة حالياً</p>
        <small class="text-muted">سيتم إضافة الشارات قريباً</small>
    </div>
    <?php endif; ?>
</div>

<script>
// Category filtering
document.querySelectorAll('.category-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const category = this.dataset.category;
        
        document.querySelectorAll('.badge-item').forEach(item => {
            const isEarned = item.dataset.earned === '1';
            
            if (category === 'all') {
                item.style.display = 'block';
            } else if (category === 'earned') {
                item.style.display = isEarned ? 'block' : 'none';
            } else if (category === 'locked') {
                item.style.display = !isEarned ? 'block' : 'none';
            }
        });
    });
});

// Click effect on locked badges
document.querySelectorAll('.badge-card.locked').forEach(card => {
    card.addEventListener('click', function() {
        this.classList.add('shake-animation');
        setTimeout(() => this.classList.remove('shake-animation'), 500);
        
        Swal.fire({
            title: 'شارة مقفلة 🔒',
            text: 'استمر في جمع النقاط للحصول على هذه الشارة!',
            icon: 'info',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#ff6f00'
        });
    });
});

// Click effect on earned badges
document.querySelectorAll('.badge-card.earned').forEach(card => {
    card.addEventListener('click', function() {
        const name = this.querySelector('.badge-name').textContent;
        const icon = this.querySelector('.badge-icon').textContent;
        
        Swal.fire({
            title: icon + ' ' + name,
            text: 'مبروك! لقد حصلت على هذه الشارة',
            confirmButtonText: '🎉 رائع',
            confirmButtonColor: '#ff6f00',
            showClass: {
                popup: 'animate__animated animate__bounceIn'
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
