<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - POINTS STORE                                         ║
 * ║           متجر النقاط والمكافآت                                               ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Features:                                                                   ║
 * ║  - استبدال النقاط بمكافآت حقيقية                                             ║
 * ║  - تتبع الطلبات والاسترداد                                                   ║
 * ║  - مكافآت متنوعة (إجازات، قسائم، هدايا)                                      ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/config/app.php';
require_login();

$user_id = $_SESSION['user_id'];
$user_points = $_SESSION['current_points'] ?? 0;

// ═══════════════════════════════════════════════════════════════════════════════
// جلب المكافآت المتاحة
// ═══════════════════════════════════════════════════════════════════════════════

$rewards = Database::fetchAll("
    SELECT * FROM rewards 
    WHERE is_active = 1 
    ORDER BY points_required ASC
") ?: [];

// إذا لم يوجد جدول، نستخدم بيانات افتراضية
if (empty($rewards)) {
    $rewards = [
        ['id' => 1, 'name' => 'نصف يوم إجازة', 'description' => 'الحصول على نصف يوم إجازة مدفوعة', 'points_required' => 500, 'icon' => '🏖️', 'category' => 'leave', 'stock' => 99],
        ['id' => 2, 'name' => 'يوم إجازة كامل', 'description' => 'الحصول على يوم إجازة مدفوعة كامل', 'points_required' => 1000, 'icon' => '🌴', 'category' => 'leave', 'stock' => 99],
        ['id' => 3, 'name' => 'قسيمة مطعم 50 ريال', 'description' => 'قسيمة شراء من مطاعم مختارة', 'points_required' => 300, 'icon' => '🍽️', 'category' => 'voucher', 'stock' => 50],
        ['id' => 4, 'name' => 'قسيمة مطعم 100 ريال', 'description' => 'قسيمة شراء من مطاعم مختارة', 'points_required' => 550, 'icon' => '🍕', 'category' => 'voucher', 'stock' => 30],
        ['id' => 5, 'name' => 'بطاقة شحن 50 ريال', 'description' => 'بطاقة شحن رصيد للجوال', 'points_required' => 400, 'icon' => '📱', 'category' => 'gift', 'stock' => 100],
        ['id' => 6, 'name' => 'سماعات بلوتوث', 'description' => 'سماعات لاسلكية عالية الجودة', 'points_required' => 2000, 'icon' => '🎧', 'category' => 'gift', 'stock' => 10],
        ['id' => 7, 'name' => 'ساعة ذكية', 'description' => 'ساعة ذكية متعددة الاستخدامات', 'points_required' => 5000, 'icon' => '⌚', 'category' => 'gift', 'stock' => 5],
        ['id' => 8, 'name' => 'العمل من المنزل (يوم)', 'description' => 'يوم عمل من المنزل', 'points_required' => 800, 'icon' => '🏠', 'category' => 'privilege', 'stock' => 99],
        ['id' => 9, 'name' => 'مكان VIP للسيارة', 'description' => 'موقف سيارة مميز لمدة شهر', 'points_required' => 1500, 'icon' => '🚗', 'category' => 'privilege', 'stock' => 3],
        ['id' => 10, 'name' => 'شهادة تقدير', 'description' => 'شهادة تقدير موقعة من المدير العام', 'points_required' => 200, 'icon' => '🏆', 'category' => 'recognition', 'stock' => 99],
    ];
}

// جلب طلبات المستخدم السابقة
$myRedemptions = Database::fetchAll("
    SELECT rr.*, r.name as reward_name, r.icon
    FROM reward_redemptions rr
    JOIN rewards r ON rr.reward_id = r.id
    WHERE rr.user_id = ?
    ORDER BY rr.created_at DESC
    LIMIT 10
", [$user_id]) ?: [];

// تجميع المكافآت حسب الفئة
$categories = [
    'leave' => ['name' => 'الإجازات', 'icon' => '🏖️', 'color' => '#28a745'],
    'voucher' => ['name' => 'القسائم', 'icon' => '🎟️', 'color' => '#17a2b8'],
    'gift' => ['name' => 'الهدايا', 'icon' => '🎁', 'color' => '#e83e8c'],
    'privilege' => ['name' => 'الامتيازات', 'icon' => '⭐', 'color' => '#ffc107'],
    'recognition' => ['name' => 'التقدير', 'icon' => '🏆', 'color' => '#6f42c1'],
];

$rewardsByCategory = [];
foreach ($rewards as $reward) {
    $cat = $reward['category'] ?? 'gift';
    $rewardsByCategory[$cat][] = $reward;
}

$pageTitle = 'متجر النقاط';
include __DIR__ . '/includes/header.php';
?>

<style>
/* ═══════════════════════════════════════════════════════════════════════════════ */
/* أنماط متجر النقاط */
/* ═══════════════════════════════════════════════════════════════════════════════ */

.store-hero {
    background: linear-gradient(135deg, #ff6f00 0%, #ff8f00 100%);
    border-radius: 20px;
    padding: 30px;
    color: white;
    margin-bottom: 25px;
    position: relative;
    overflow: hidden;
}

.store-hero::before {
    content: '🛒';
    position: absolute;
    top: 50%;
    left: 20px;
    transform: translateY(-50%);
    font-size: 6rem;
    opacity: 0.2;
}

.points-balance {
    font-size: 3rem;
    font-weight: 900;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
}

.points-label {
    font-size: 1.2rem;
    opacity: 0.9;
}

.category-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    overflow-x: auto;
    padding-bottom: 10px;
}

.category-tab {
    padding: 12px 20px;
    border-radius: 50px;
    background: white;
    color: var(--sarh-dark);
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.3s ease;
    font-weight: 600;
    box-shadow: var(--sarh-shadow-sm);
    display: flex;
    align-items: center;
    gap: 8px;
}

.category-tab:hover, .category-tab.active {
    background: var(--sarh-primary);
    color: white;
    transform: translateY(-2px);
}

.rewards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.reward-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--sarh-shadow-sm);
    transition: all 0.3s ease;
    position: relative;
}

.reward-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--sarh-shadow-lg);
}

.reward-card.out-of-stock {
    opacity: 0.6;
}

.reward-icon {
    height: 120px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
}

.reward-body {
    padding: 20px;
}

.reward-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--sarh-dark);
    margin-bottom: 8px;
}

.reward-description {
    color: #888;
    font-size: 0.9rem;
    margin-bottom: 15px;
    min-height: 40px;
}

.reward-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.reward-points {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--sarh-primary);
}

.reward-points small {
    font-size: 0.7rem;
    font-weight: normal;
    color: #888;
}

.btn-redeem {
    padding: 10px 20px;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-redeem:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.stock-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 5px 12px;
    border-radius: 50px;
    font-size: 0.75rem;
}

.stock-badge.low {
    background: #dc3545;
}

.redemption-history {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: var(--sarh-shadow-sm);
}

.redemption-item {
    display: flex;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid #eee;
}

.redemption-item:last-child {
    border-bottom: none;
}

.redemption-icon {
    font-size: 2rem;
    margin-left: 15px;
}

.redemption-info {
    flex: 1;
}

.redemption-name {
    font-weight: 600;
    color: var(--sarh-dark);
}

.redemption-date {
    font-size: 0.8rem;
    color: #888;
}

.redemption-status {
    padding: 5px 12px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
}

.redemption-status.pending { background: #fff3cd; color: #856404; }
.redemption-status.approved { background: #d4edda; color: #155724; }
.redemption-status.delivered { background: #cce5ff; color: #004085; }
.redemption-status.rejected { background: #f8d7da; color: #721c24; }

@media (max-width: 768px) {
    .store-hero {
        padding: 20px;
    }
    
    .points-balance {
        font-size: 2rem;
    }
    
    .rewards-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container py-4">
    <!-- Hero Section -->
    <div class="store-hero">
        <div class="row align-items-center">
            <div class="col">
                <div class="points-label">رصيد نقاطك</div>
                <div class="points-balance">
                    <i class="bi bi-star-fill"></i>
                    <?= number_format($user_points) ?>
                </div>
                <p class="mb-0 mt-2 opacity-75">
                    <i class="bi bi-info-circle"></i>
                    استبدل نقاطك بمكافآت حقيقية!
                </p>
            </div>
            <div class="col-auto">
                <a href="leaderboard.php" class="btn btn-light btn-lg">
                    <i class="bi bi-trophy me-2"></i>
                    لوحة المتصدرين
                </a>
            </div>
        </div>
    </div>

    <!-- Category Tabs -->
    <div class="category-tabs">
        <a href="#all" class="category-tab active" data-category="all">
            <span>🛒</span> الكل
        </a>
        <?php foreach ($categories as $key => $cat): ?>
        <a href="#<?= $key ?>" class="category-tab" data-category="<?= $key ?>">
            <span><?= $cat['icon'] ?></span> <?= $cat['name'] ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="row">
        <!-- Rewards Grid -->
        <div class="col-lg-8 mb-4">
            <div class="rewards-grid" id="rewardsGrid">
                <?php foreach ($rewards as $reward): 
                    $canAfford = $user_points >= $reward['points_required'];
                    $inStock = ($reward['stock'] ?? 99) > 0;
                    $lowStock = ($reward['stock'] ?? 99) <= 5;
                ?>
                <div class="reward-card <?= !$inStock ? 'out-of-stock' : '' ?>" 
                     data-category="<?= $reward['category'] ?? 'gift' ?>">
                    <?php if ($lowStock && $inStock): ?>
                        <span class="stock-badge low">
                            <i class="bi bi-exclamation-circle"></i>
                            باقي <?= $reward['stock'] ?> فقط
                        </span>
                    <?php elseif (!$inStock): ?>
                        <span class="stock-badge">نفذت الكمية</span>
                    <?php endif; ?>
                    
                    <div class="reward-icon"><?= $reward['icon'] ?? '🎁' ?></div>
                    <div class="reward-body">
                        <div class="reward-name"><?= e($reward['name']) ?></div>
                        <div class="reward-description"><?= e($reward['description']) ?></div>
                        <div class="reward-footer">
                            <div class="reward-points">
                                <?= number_format($reward['points_required']) ?>
                                <small>نقطة</small>
                            </div>
                            <button class="btn btn-redeem <?= $canAfford && $inStock ? 'btn-primary' : 'btn-secondary' ?>"
                                    onclick="redeemReward(<?= $reward['id'] ?>, '<?= e($reward['name']) ?>', <?= $reward['points_required'] ?>)"
                                    <?= !$canAfford || !$inStock ? 'disabled' : '' ?>>
                                <?php if (!$inStock): ?>
                                    نفذت
                                <?php elseif (!$canAfford): ?>
                                    تحتاج <?= number_format($reward['points_required'] - $user_points) ?>
                                <?php else: ?>
                                    <i class="bi bi-cart-plus"></i> استبدال
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- My Redemptions -->
            <div class="redemption-history">
                <h5 class="mb-4">
                    <i class="bi bi-clock-history text-primary me-2"></i>
                    طلباتي السابقة
                </h5>
                
                <?php if (!empty($myRedemptions)): ?>
                    <?php foreach ($myRedemptions as $redemption): ?>
                    <div class="redemption-item">
                        <div class="redemption-icon"><?= $redemption['icon'] ?? '🎁' ?></div>
                        <div class="redemption-info">
                            <div class="redemption-name"><?= e($redemption['reward_name']) ?></div>
                            <div class="redemption-date">
                                <?= date('d/m/Y', strtotime($redemption['created_at'])) ?>
                            </div>
                        </div>
                        <span class="redemption-status <?= $redemption['status'] ?? 'pending' ?>">
                            <?php
                            $statuses = [
                                'pending' => 'قيد المراجعة',
                                'approved' => 'تمت الموافقة',
                                'delivered' => 'تم التسليم',
                                'rejected' => 'مرفوض'
                            ];
                            echo $statuses[$redemption['status'] ?? 'pending'];
                            ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-bag fs-1"></i>
                        <p class="mt-2">لا توجد طلبات سابقة</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- How to earn points -->
            <div class="card mt-4 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3">
                        <i class="bi bi-lightbulb text-warning me-2"></i>
                        كيف تكسب النقاط؟
                    </h5>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            الحضور في الموعد: <strong>+10 نقاط</strong>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-clock text-info me-2"></i>
                            العمل الإضافي: <strong>+5 نقاط/ساعة</strong>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-calendar-check text-primary me-2"></i>
                            حضور مثالي أسبوعي: <strong>+50 نقطة</strong>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-award text-warning me-2"></i>
                            إنجاز التحديات: <strong>حتى +200 نقطة</strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Filter by category
document.querySelectorAll('.category-tab').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        
        document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const category = this.dataset.category;
        
        document.querySelectorAll('.reward-card').forEach(card => {
            if (category === 'all' || card.dataset.category === category) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
});

// Redeem reward
function redeemReward(rewardId, rewardName, pointsRequired) {
    Swal.fire({
        title: 'تأكيد الاستبدال',
        html: `
            <div class="text-center">
                <p>هل تريد استبدال <strong>${pointsRequired.toLocaleString()}</strong> نقطة بـ</p>
                <h4 class="text-primary">${rewardName}</h4>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-lg"></i> تأكيد',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#ff6f00',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Send redemption request
            fetch('api/store/redeem.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?= csrf_token() ?>'
                },
                body: JSON.stringify({ reward_id: rewardId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'تم بنجاح!',
                        text: 'تم تقديم طلب الاستبدال وسيتم مراجعته',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: err.message || 'فشل في تقديم الطلب'
                });
            });
        }
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
