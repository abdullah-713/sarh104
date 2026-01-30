<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - LEADERBOARD & GAMIFICATION                           ║
 * ║           لوحة المتصدرين ونظام التحفيز                                        ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Features:                                                                   ║
 * ║  - لوحة المتصدرين (يومي/أسبوعي/شهري)                                         ║
 * ║  - الشارات والإنجازات                                                        ║
 * ║  - التحديات الأسبوعية                                                        ║
 * ║  - نظام المستويات                                                            ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/config/app.php';
require_login();

$user_id = $_SESSION['user_id'];
$period = $_GET['period'] ?? 'monthly';

// ═══════════════════════════════════════════════════════════════════════════════
// جلب بيانات لوحة المتصدرين
// ═══════════════════════════════════════════════════════════════════════════════

$periodConditions = [
    'daily' => "DATE(a.date) = CURDATE()",
    'weekly' => "YEARWEEK(a.date, 1) = YEARWEEK(CURDATE(), 1)",
    'monthly' => "YEAR(a.date) = YEAR(CURDATE()) AND MONTH(a.date) = MONTH(CURDATE())",
    'yearly' => "YEAR(a.date) = YEAR(CURDATE())"
];

$condition = $periodConditions[$period] ?? $periodConditions['monthly'];

// جلب المتصدرين بناءً على النقاط والالتزام
$leaderboard = Database::fetchAll("
    SELECT 
        u.id,
        u.full_name,
        u.avatar,
        u.current_points,
        u.job_title,
        r.name as role_name,
        r.color as role_color,
        b.name as branch_name,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.date END) as present_days,
        COUNT(DISTINCT CASE WHEN a.status = 'late' THEN a.date END) as late_days,
        COUNT(DISTINCT a.date) as total_days,
        COALESCE(SUM(a.bonus_points), 0) as total_bonus,
        COALESCE(SUM(a.penalty_points), 0) as total_penalty,
        COALESCE(SUM(a.overtime_minutes), 0) as total_overtime,
        ROUND(
            (COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.date END) * 100.0) / 
            NULLIF(COUNT(DISTINCT a.date), 0), 1
        ) as attendance_rate
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN branches b ON u.branch_id = b.id
    LEFT JOIN attendance a ON u.id = a.user_id AND {$condition}
    WHERE u.is_active = 1
    GROUP BY u.id
    ORDER BY u.current_points DESC, attendance_rate DESC
    LIMIT 50
");

// حساب ترتيب المستخدم الحالي
$myRank = 0;
foreach ($leaderboard as $index => $user) {
    if ($user['id'] == $user_id) {
        $myRank = $index + 1;
        break;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// جلب الشارات
// ═══════════════════════════════════════════════════════════════════════════════

$userBadges = Database::fetchAll("
    SELECT ub.*, b.name, b.description, b.icon, b.color, b.points_reward
    FROM user_badges ub
    JOIN badges b ON ub.badge_id = b.id
    WHERE ub.user_id = ?
    ORDER BY ub.earned_at DESC
", [$user_id]) ?: [];

$allBadges = Database::fetchAll("
    SELECT b.*, 
           CASE WHEN ub.id IS NOT NULL THEN 1 ELSE 0 END as earned
    FROM badges b
    LEFT JOIN user_badges ub ON b.id = ub.badge_id AND ub.user_id = ?
    WHERE b.is_active = 1
    ORDER BY b.points_reward DESC
", [$user_id]) ?: [];

// ═══════════════════════════════════════════════════════════════════════════════
// جلب التحديات النشطة
// ═══════════════════════════════════════════════════════════════════════════════

$activeChallenges = Database::fetchAll("
    SELECT c.*, 
           uc.progress,
           uc.completed,
           uc.completed_at
    FROM challenges c
    LEFT JOIN user_challenges uc ON c.id = uc.challenge_id AND uc.user_id = ?
    WHERE c.is_active = 1 
      AND c.start_date <= CURDATE() 
      AND c.end_date >= CURDATE()
    ORDER BY c.end_date ASC
", [$user_id]) ?: [];

// ═══════════════════════════════════════════════════════════════════════════════
// حساب المستوى
// ═══════════════════════════════════════════════════════════════════════════════

$totalPoints = $_SESSION['current_points'] ?? 0;
$levels = [
    ['name' => 'مبتدئ', 'min' => 0, 'max' => 500, 'icon' => '🌱', 'color' => '#95a5a6'],
    ['name' => 'نشط', 'min' => 501, 'max' => 1500, 'icon' => '⭐', 'color' => '#3498db'],
    ['name' => 'متميز', 'min' => 1501, 'max' => 3000, 'icon' => '🌟', 'color' => '#9b59b6'],
    ['name' => 'خبير', 'min' => 3001, 'max' => 5000, 'icon' => '💎', 'color' => '#e74c3c'],
    ['name' => 'أسطوري', 'min' => 5001, 'max' => 10000, 'icon' => '👑', 'color' => '#f39c12'],
    ['name' => 'أسطوري+', 'min' => 10001, 'max' => PHP_INT_MAX, 'icon' => '🏆', 'color' => '#ff6f00'],
];

$currentLevel = $levels[0];
$nextLevel = $levels[1] ?? null;
$levelProgress = 0;

foreach ($levels as $index => $level) {
    if ($totalPoints >= $level['min'] && $totalPoints <= $level['max']) {
        $currentLevel = $level;
        $nextLevel = $levels[$index + 1] ?? null;
        if ($nextLevel) {
            $levelProgress = (($totalPoints - $level['min']) / ($level['max'] - $level['min'])) * 100;
        } else {
            $levelProgress = 100;
        }
        break;
    }
}

$pageTitle = 'لوحة المتصدرين';
include __DIR__ . '/includes/header.php';
?>

<style>
/* ═══════════════════════════════════════════════════════════════════════════════ */
/* أنماط لوحة المتصدرين */
/* ═══════════════════════════════════════════════════════════════════════════════ */

.leaderboard-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 30px;
    color: white;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
}

.leaderboard-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.level-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.2);
    padding: 8px 16px;
    border-radius: 50px;
    font-weight: bold;
}

.level-progress {
    height: 10px;
    background: rgba(255,255,255,0.3);
    border-radius: 5px;
    overflow: hidden;
    margin-top: 15px;
}

.level-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #ffd700, #ffaa00);
    border-radius: 5px;
    transition: width 1s ease;
}

.rank-card {
    text-align: center;
    padding: 20px;
}

.rank-number {
    font-size: 3rem;
    font-weight: 900;
    background: linear-gradient(135deg, #ffd700, #ff6f00);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.period-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    overflow-x: auto;
    padding-bottom: 5px;
}

.period-tab {
    padding: 10px 20px;
    border-radius: 50px;
    background: var(--sarh-light);
    color: var(--sarh-dark);
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.3s ease;
    font-weight: 600;
}

.period-tab.active {
    background: linear-gradient(135deg, var(--sarh-primary), var(--sarh-primary-dark));
    color: white;
}

.leaderboard-list {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--sarh-shadow);
}

.leaderboard-item {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    transition: all 0.3s ease;
}

.leaderboard-item:hover {
    background: #f8f9fa;
}

.leaderboard-item.top-3 {
    background: linear-gradient(135deg, rgba(255,215,0,0.1), rgba(255,215,0,0.05));
}

.leaderboard-item.me {
    background: linear-gradient(135deg, rgba(255,111,0,0.15), rgba(255,111,0,0.05));
    border: 2px solid var(--sarh-primary);
}

.rank-badge {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-left: 15px;
}

.rank-1 { background: linear-gradient(135deg, #ffd700, #ffaa00); color: white; }
.rank-2 { background: linear-gradient(135deg, #c0c0c0, #a0a0a0); color: white; }
.rank-3 { background: linear-gradient(135deg, #cd7f32, #a0522d); color: white; }
.rank-default { background: #e9ecef; color: #666; }

.user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    margin-left: 15px;
    border: 3px solid #eee;
}

.user-avatar-placeholder {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--sarh-primary), var(--sarh-primary-dark));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
    margin-left: 15px;
}

.user-info {
    flex: 1;
}

.user-name {
    font-weight: 700;
    color: var(--sarh-dark);
    margin-bottom: 2px;
}

.user-meta {
    font-size: 0.8rem;
    color: #888;
}

.user-stats {
    text-align: left;
}

.points-display {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--sarh-primary);
}

.attendance-rate {
    font-size: 0.8rem;
    color: #28a745;
}

/* ═══════════════════════════════════════════════════════════════════════════════ */
/* أنماط الشارات */
/* ═══════════════════════════════════════════════════════════════════════════════ */

.badges-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 15px;
}

.badge-item {
    text-align: center;
    padding: 15px;
    border-radius: 16px;
    background: white;
    box-shadow: var(--sarh-shadow-sm);
    transition: all 0.3s ease;
}

.badge-item:hover {
    transform: translateY(-5px);
    box-shadow: var(--sarh-shadow);
}

.badge-item.locked {
    opacity: 0.5;
    filter: grayscale(1);
}

.badge-icon {
    font-size: 2.5rem;
    margin-bottom: 8px;
}

.badge-name {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--sarh-dark);
}

.badge-points {
    font-size: 0.7rem;
    color: var(--sarh-primary);
    margin-top: 4px;
}

/* ═══════════════════════════════════════════════════════════════════════════════ */
/* أنماط التحديات */
/* ═══════════════════════════════════════════════════════════════════════════════ */

.challenge-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: var(--sarh-shadow-sm);
    border-right: 4px solid var(--sarh-primary);
}

.challenge-card.completed {
    border-right-color: #28a745;
    background: linear-gradient(135deg, rgba(40,167,69,0.05), transparent);
}

.challenge-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.challenge-title {
    font-weight: 700;
    color: var(--sarh-dark);
}

.challenge-reward {
    background: linear-gradient(135deg, #ffd700, #ffaa00);
    color: white;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: bold;
}

.challenge-progress {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.challenge-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--sarh-primary), var(--sarh-primary-light));
    border-radius: 4px;
    transition: width 0.5s ease;
}

.challenge-meta {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
    font-size: 0.8rem;
    color: #888;
}

@media (max-width: 768px) {
    .leaderboard-hero {
        padding: 20px;
    }
    
    .rank-number {
        font-size: 2rem;
    }
    
    .period-tabs {
        flex-wrap: nowrap;
        justify-content: flex-start;
    }
}
</style>

<div class="container py-4">
    <!-- Hero Section -->
    <div class="leaderboard-hero">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="level-badge">
                    <span style="font-size: 1.5rem;"><?= $currentLevel['icon'] ?></span>
                    <span>المستوى: <?= $currentLevel['name'] ?></span>
                </div>
                <h2 class="mt-3 mb-1"><?= e($_SESSION['full_name']) ?></h2>
                <p class="mb-0 opacity-75">
                    <i class="bi bi-star-fill"></i>
                    <?= number_format($totalPoints) ?> نقطة
                </p>
                <?php if ($nextLevel): ?>
                <div class="level-progress">
                    <div class="level-progress-bar" style="width: <?= $levelProgress ?>%"></div>
                </div>
                <small class="opacity-75">
                    <?= number_format($nextLevel['min'] - $totalPoints) ?> نقطة للوصول إلى <?= $nextLevel['icon'] ?> <?= $nextLevel['name'] ?>
                </small>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <div class="rank-card">
                    <div class="rank-number">#<?= $myRank ?: '-' ?></div>
                    <div>ترتيبك في لوحة المتصدرين</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Period Tabs -->
    <div class="period-tabs">
        <a href="?period=daily" class="period-tab <?= $period === 'daily' ? 'active' : '' ?>">
            <i class="bi bi-calendar-day me-1"></i> اليوم
        </a>
        <a href="?period=weekly" class="period-tab <?= $period === 'weekly' ? 'active' : '' ?>">
            <i class="bi bi-calendar-week me-1"></i> الأسبوع
        </a>
        <a href="?period=monthly" class="period-tab <?= $period === 'monthly' ? 'active' : '' ?>">
            <i class="bi bi-calendar-month me-1"></i> الشهر
        </a>
        <a href="?period=yearly" class="period-tab <?= $period === 'yearly' ? 'active' : '' ?>">
            <i class="bi bi-calendar me-1"></i> السنة
        </a>
    </div>

    <div class="row">
        <!-- Leaderboard -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-trophy-fill text-warning me-2"></i>
                        لوحة المتصدرين
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="leaderboard-list">
                        <?php foreach ($leaderboard as $index => $user): 
                            $rank = $index + 1;
                            $isMe = $user['id'] == $user_id;
                            $isTop3 = $rank <= 3;
                        ?>
                        <div class="leaderboard-item <?= $isTop3 ? 'top-3' : '' ?> <?= $isMe ? 'me' : '' ?>">
                            <div class="rank-badge <?= $rank <= 3 ? "rank-{$rank}" : 'rank-default' ?>">
                                <?php if ($rank === 1): ?>
                                    <i class="bi bi-trophy-fill"></i>
                                <?php elseif ($rank === 2): ?>
                                    <i class="bi bi-award-fill"></i>
                                <?php elseif ($rank === 3): ?>
                                    <i class="bi bi-star-fill"></i>
                                <?php else: ?>
                                    <?= $rank ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($user['avatar']): ?>
                                <img src="<?= e($user['avatar']) ?>" alt="" class="user-avatar">
                            <?php else: ?>
                                <div class="user-avatar-placeholder">
                                    <?= mb_substr($user['full_name'], 0, 1) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="user-info">
                                <div class="user-name">
                                    <?= e($user['full_name']) ?>
                                    <?php if ($isMe): ?>
                                        <span class="badge bg-primary ms-1">أنت</span>
                                    <?php endif; ?>
                                </div>
                                <div class="user-meta">
                                    <span class="badge" style="background: <?= e($user['role_color'] ?? '#6c757d') ?>">
                                        <?= e($user['role_name'] ?? 'موظف') ?>
                                    </span>
                                    <?= e($user['branch_name'] ?? '') ?>
                                </div>
                            </div>
                            
                            <div class="user-stats">
                                <div class="points-display">
                                    <?= number_format($user['current_points']) ?>
                                    <small>نقطة</small>
                                </div>
                                <div class="attendance-rate">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <?= $user['attendance_rate'] ?? 0 ?>% حضور
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($leaderboard)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-emoji-neutral fs-1 text-muted"></i>
                            <p class="text-muted mt-2">لا توجد بيانات لهذه الفترة</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Active Challenges -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning-fill text-warning me-2"></i>
                        التحديات النشطة
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($activeChallenges)): ?>
                        <?php foreach ($activeChallenges as $challenge): 
                            $progress = $challenge['progress'] ?? 0;
                            $target = $challenge['target_value'] ?? 1;
                            $progressPercent = min(100, ($progress / $target) * 100);
                            $isCompleted = $challenge['completed'] ?? false;
                        ?>
                        <div class="challenge-card <?= $isCompleted ? 'completed' : '' ?>">
                            <div class="challenge-header">
                                <span class="challenge-title">
                                    <?php if ($isCompleted): ?>
                                        <i class="bi bi-check-circle-fill text-success me-1"></i>
                                    <?php endif; ?>
                                    <?= e($challenge['name']) ?>
                                </span>
                                <span class="challenge-reward">
                                    +<?= number_format($challenge['reward_points']) ?>
                                </span>
                            </div>
                            <p class="text-muted small mb-2"><?= e($challenge['description']) ?></p>
                            <div class="challenge-progress">
                                <div class="challenge-progress-bar" style="width: <?= $progressPercent ?>%"></div>
                            </div>
                            <div class="challenge-meta">
                                <span><?= $progress ?> / <?= $target ?></span>
                                <span>
                                    <i class="bi bi-clock"></i>
                                    ينتهي <?= date('d/m', strtotime($challenge['end_date'])) ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x fs-1 text-muted"></i>
                            <p class="text-muted mt-2">لا توجد تحديات نشطة حالياً</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Badges -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-award-fill text-primary me-2"></i>
                        الشارات
                    </h5>
                    <a href="badges.php" class="btn btn-sm btn-outline-primary">عرض الكل</a>
                </div>
                <div class="card-body">
                    <div class="badges-grid">
                        <?php 
                        $displayBadges = array_slice($allBadges, 0, 8);
                        foreach ($displayBadges as $badge): 
                            $earned = $badge['earned'] ?? false;
                        ?>
                        <div class="badge-item <?= !$earned ? 'locked' : '' ?>" 
                             title="<?= e($badge['description']) ?>">
                            <div class="badge-icon"><?= $badge['icon'] ?? '🏅' ?></div>
                            <div class="badge-name"><?= e($badge['name']) ?></div>
                            <div class="badge-points">+<?= number_format($badge['points_reward']) ?></div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($allBadges)): ?>
                        <div class="col-12 text-center py-3">
                            <p class="text-muted small">لا توجد شارات متاحة</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// تحديث تلقائي كل دقيقة
setInterval(() => {
    // يمكن إضافة AJAX لتحديث البيانات بدون إعادة تحميل الصفحة
}, 60000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
