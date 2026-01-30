<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║                    ⚔️ THE ARENA - الحلبة ⚔️                                  ║
 * ║                 Advanced Gamification Dashboard                              ║
 * ║                    "PERFORM OR PERISH"                                       ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$user = get_current_user_data();
$period = $_GET['period'] ?? 'month';

// جلب البيانات
$warlords = get_top_performers(3, $period);
$guillotine = get_improvement_needed(5, $period);
$branch_warfare = get_branch_performance($period);

// حساب نقاط المستخدم الحالي
$dates = get_period_dates($period);
$my_score = calculate_performance_score($user['id'], $dates['start'], $dates['end']);

// العثور على ترتيب المستخدم الحالي
$all_users = get_top_performers(1000, $period);
$my_rank = 0;
foreach ($all_users as $i => $u) {
    if ($u['id'] == $user['id']) {
        $my_rank = $i + 1;
        break;
    }
}

$page_title = "📊 مركز تحليل الأداء";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - صرح الإتقان</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            /* ULTRA DARK NEON THEME */
            --bg-void: #0a0a0f;
            --bg-dark: #0d0d14;
            --bg-card: #12121a;
            --bg-elevated: #1a1a25;
            
            /* Neon Colors */
            --neon-gold: #ffd700;
            --neon-silver: #c0c0c0;
            --neon-bronze: #cd7f32;
            --neon-green: #00ff88;
            --neon-cyan: #00f5ff;
            --neon-purple: #bf00ff;
            --neon-red: #ff0040;
            --neon-orange: #ff6600;
            
            /* Glows */
            --glow-gold: 0 0 30px rgba(255, 215, 0, 0.5);
            --glow-green: 0 0 20px rgba(0, 255, 136, 0.4);
            --glow-red: 0 0 20px rgba(255, 0, 64, 0.4);
            --glow-cyan: 0 0 20px rgba(0, 245, 255, 0.4);
            
            /* Text */
            --text-primary: #ffffff;
            --text-secondary: #8888aa;
            --text-muted: #555566;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--bg-void);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Animated Background Grid */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                linear-gradient(90deg, transparent 98%, rgba(0, 255, 136, 0.03) 100%),
                linear-gradient(0deg, transparent 98%, rgba(0, 255, 136, 0.03) 100%);
            background-size: 50px 50px;
            pointer-events: none;
            z-index: 0;
        }
        
        /* Scanline Effect */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(
                0deg,
                transparent,
                transparent 2px,
                rgba(0, 0, 0, 0.1) 2px,
                rgba(0, 0, 0, 0.1) 4px
            );
            pointer-events: none;
            z-index: 9999;
            opacity: 0.3;
        }
        
        .arena-container {
            position: relative;
            z-index: 1;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* ═══════════════════════════════════════════════════════════════
           HEADER
           ═══════════════════════════════════════════════════════════════ */
        .arena-header {
            text-align: center;
            padding: 40px 20px;
            position: relative;
        }
        
        .arena-title {
            font-family: 'Orbitron', monospace;
            font-size: clamp(2rem, 6vw, 4rem);
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 8px;
            background: linear-gradient(135deg, var(--neon-gold), var(--neon-orange), var(--neon-red));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: none;
            animation: titleGlow 3s ease-in-out infinite;
            margin-bottom: 10px;
        }
        
        @keyframes titleGlow {
            0%, 100% { filter: brightness(1) drop-shadow(0 0 10px rgba(255, 215, 0, 0.5)); }
            50% { filter: brightness(1.2) drop-shadow(0 0 30px rgba(255, 215, 0, 0.8)); }
        }
        
        .arena-subtitle {
            font-family: 'Orbitron', monospace;
            font-size: 0.9rem;
            color: var(--neon-red);
            letter-spacing: 6px;
            text-transform: uppercase;
            animation: subtitlePulse 2s ease-in-out infinite;
        }
        
        @keyframes subtitlePulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }
        
        /* Period Selector */
        .period-selector {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .period-btn {
            font-family: 'Orbitron', monospace;
            padding: 10px 25px;
            background: var(--bg-card);
            border: 1px solid var(--text-muted);
            color: var(--text-secondary);
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .period-btn:hover, .period-btn.active {
            background: var(--neon-cyan);
            color: var(--bg-void);
            border-color: var(--neon-cyan);
            box-shadow: var(--glow-cyan);
        }
        
        /* ═══════════════════════════════════════════════════════════════
           MY STATS BAR
           ═══════════════════════════════════════════════════════════════ */
        .my-stats {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-elevated));
            border: 1px solid rgba(0, 245, 255, 0.2);
            border-radius: 15px;
            padding: 20px 30px;
            margin: 30px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .my-stats-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .my-rank-badge {
            width: 70px;
            height: 70px;
            background: var(--bg-void);
            border: 3px solid var(--neon-cyan);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Orbitron', monospace;
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--neon-cyan);
            box-shadow: var(--glow-cyan);
        }
        
        .my-info h3 {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }
        
        .my-info p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .my-score {
            text-align: center;
        }
        
        .my-score-value {
            font-family: 'Orbitron', monospace;
            font-size: 3rem;
            font-weight: 900;
            color: var(--neon-green);
            text-shadow: var(--glow-green);
        }
        
        .my-score-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        /* ═══════════════════════════════════════════════════════════════
           THE WARLORDS - TOP 3
           ═══════════════════════════════════════════════════════════════ */
        .section-title {
            font-family: 'Orbitron', monospace;
            font-size: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 4px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .section-title::before,
        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--neon-gold), transparent);
        }
        
        .warlords-grid {
            display: grid;
            grid-template-columns: 1fr 1.3fr 1fr;
            gap: 20px;
            margin-bottom: 50px;
            align-items: end;
        }
        
        .warlord-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 30px 20px;
            text-align: center;
            position: relative;
            transition: all 0.4s ease;
        }
        
        .warlord-card:hover {
            transform: translateY(-10px);
        }
        
        /* Gold - First Place */
        .warlord-card.gold {
            order: 2;
            border: 2px solid var(--neon-gold);
            box-shadow: var(--glow-gold);
            padding-top: 50px;
        }
        
        .warlord-card.gold::before {
            content: '👑';
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 3rem;
            animation: crownFloat 2s ease-in-out infinite;
        }
        
        @keyframes crownFloat {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(-10px); }
        }
        
        /* Silver - Second Place */
        .warlord-card.silver {
            order: 1;
            border: 2px solid var(--neon-silver);
            box-shadow: 0 0 20px rgba(192, 192, 192, 0.3);
        }
        
        /* Bronze - Third Place */
        .warlord-card.bronze {
            order: 3;
            border: 2px solid var(--neon-bronze);
            box-shadow: 0 0 20px rgba(205, 127, 50, 0.3);
        }
        
        .warlord-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        .gold .warlord-avatar {
            background: linear-gradient(135deg, #ffd700, #ff8c00);
            border: 4px solid var(--neon-gold);
            box-shadow: var(--glow-gold);
        }
        
        .silver .warlord-avatar {
            background: linear-gradient(135deg, #c0c0c0, #808080);
            border: 4px solid var(--neon-silver);
        }
        
        .bronze .warlord-avatar {
            background: linear-gradient(135deg, #cd7f32, #8b4513);
            border: 4px solid var(--neon-bronze);
        }
        
        .warlord-name {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .warlord-branch {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 15px;
        }
        
        .warlord-score {
            font-family: 'Orbitron', monospace;
            font-size: 2rem;
            font-weight: 900;
        }
        
        .gold .warlord-score { color: var(--neon-gold); }
        .silver .warlord-score { color: var(--neon-silver); }
        .bronze .warlord-score { color: var(--neon-bronze); }
        
        .streak-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            animation: flamePulse 0.5s ease-in-out infinite;
        }
        
        @keyframes flamePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        /* ═══════════════════════════════════════════════════════════════
           BRANCH WARFARE
           ═══════════════════════════════════════════════════════════════ */
        .warfare-section {
            margin: 50px 0;
        }
        
        .warfare-section .section-title::before,
        .warfare-section .section-title::after {
            background: linear-gradient(90deg, transparent, var(--neon-cyan), transparent);
        }
        
        .warfare-bars {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .warfare-bar {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .warfare-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background: linear-gradient(90deg, var(--neon-cyan), var(--neon-purple));
            opacity: 0.15;
            transition: width 1.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .warfare-rank {
            font-family: 'Orbitron', monospace;
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--neon-cyan);
            min-width: 50px;
            z-index: 1;
        }
        
        .warfare-info {
            flex: 1;
            z-index: 1;
        }
        
        .warfare-name {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 3px;
        }
        
        .warfare-soldiers {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .warfare-score {
            font-family: 'Orbitron', monospace;
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--neon-green);
            z-index: 1;
        }
        
        /* ═══════════════════════════════════════════════════════════════
           THE GUILLOTINE - BOTTOM 5
           ═══════════════════════════════════════════════════════════════ */
        .guillotine-section {
            margin: 50px 0;
        }
        
        .guillotine-section .section-title {
            color: var(--neon-red);
        }
        
        .guillotine-section .section-title::before,
        .guillotine-section .section-title::after {
            background: linear-gradient(90deg, transparent, var(--neon-red), transparent);
        }
        
        .guillotine-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .guillotine-item {
            background: var(--bg-card);
            border: 1px solid rgba(255, 0, 64, 0.2);
            border-radius: 10px;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            overflow: hidden;
            animation: deathPulse 3s ease-in-out infinite;
        }
        
        @keyframes deathPulse {
            0%, 100% { 
                background: var(--bg-card);
                border-color: rgba(255, 0, 64, 0.2);
            }
            50% { 
                background: rgba(255, 0, 64, 0.05);
                border-color: rgba(255, 0, 64, 0.4);
            }
        }
        
        .guillotine-skull {
            font-size: 2rem;
            opacity: 0.8;
        }
        
        .guillotine-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--bg-elevated);
            border: 2px solid var(--neon-red);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        
        .guillotine-info {
            flex: 1;
        }
        
        .guillotine-name {
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .guillotine-branch {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .guillotine-score {
            font-family: 'Orbitron', monospace;
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--neon-red);
        }
        
        .guillotine-warning {
            font-size: 0.75rem;
            color: var(--neon-red);
            opacity: 0.7;
        }
        
        /* ═══════════════════════════════════════════════════════════════
           RANK ARROWS
           ═══════════════════════════════════════════════════════════════ */
        .rank-up {
            color: var(--neon-green);
            font-size: 1.2rem;
        }
        
        .rank-down {
            color: var(--neon-red);
            font-size: 1.2rem;
        }
        
        .rank-same {
            color: var(--text-muted);
        }
        
        /* ═══════════════════════════════════════════════════════════════
           BACK BUTTON
           ═══════════════════════════════════════════════════════════════ */
        .back-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
            padding: 12px 25px;
            background: var(--bg-card);
            border: 1px solid var(--text-muted);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-btn:hover {
            background: var(--neon-cyan);
            color: var(--bg-void);
            border-color: var(--neon-cyan);
        }
        
        /* ═══════════════════════════════════════════════════════════════
           RESPONSIVE
           ═══════════════════════════════════════════════════════════════ */
        @media (max-width: 900px) {
            .warlords-grid {
                grid-template-columns: 1fr;
            }
            
            .warlord-card {
                order: unset !important;
            }
            
            .warlord-card.gold {
                padding-top: 30px;
            }
            
            .my-stats {
                flex-direction: column;
                text-align: center;
            }
            
            .my-stats-left {
                flex-direction: column;
            }
        }
        
        @media (max-width: 600px) {
            .arena-title {
                letter-spacing: 3px;
            }
            
            .period-selector {
                flex-wrap: wrap;
            }
            
            .warfare-bar {
                flex-wrap: wrap;
            }
            
            .back-btn {
                top: 10px;
                right: 10px;
                padding: 8px 15px;
                font-size: 0.8rem;
            }
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <a href="<?= BASE_URL ?>/index.php" class="back-btn">
        <i class="bi bi-arrow-right"></i>
        العودة
    </a>
    
    <div class="arena-container">
        <!-- Header -->
        <header class="arena-header">
            <h1 class="arena-title">📊 مركز تحليل الأداء 📊</h1>
            <p class="arena-subtitle">مراقبة وتقييم الأداء المؤسسي</p>
            
            <!-- Period Selector -->
            <div class="period-selector">
                <a href="?period=week" class="period-btn <?= $period === 'week' ? 'active' : '' ?>">
                    أسبوع
                </a>
                <a href="?period=month" class="period-btn <?= $period === 'month' ? 'active' : '' ?>">
                    شهر
                </a>
                <a href="?period=year" class="period-btn <?= $period === 'year' ? 'active' : '' ?>">
                    سنة
                </a>
            </div>
        </header>
        
        <!-- My Stats -->
        <div class="my-stats">
            <div class="my-stats-left">
                <div class="my-rank-badge">#<?= $my_rank ?></div>
                <div class="my-info">
                    <h3><?= htmlspecialchars($user['full_name']) ?></h3>
                    <p>ترتيبك الحالي في المنافسة</p>
                </div>
            </div>
            <div class="my-score">
                <div class="my-score-value"><?= $my_score['score'] ?></div>
                <div class="my-score-label">مؤشر الأداء</div>
            </div>
        </div>
        
        <!-- المتفوقون - Top 3 -->
        <section class="warlords-section">
            <h2 class="section-title">🏆 أفضل الأداء 🏆</h2>
            
            <?php if (empty($warlords)): ?>
                <div class="empty-state">
                    <i class="bi bi-trophy"></i>
                    <p>لا توجد بيانات كافية لعرض المتصدرين</p>
                </div>
            <?php else: ?>
                <div class="warlords-grid">
                    <?php 
                    $medals = ['gold', 'silver', 'bronze'];
                    foreach ($warlords as $i => $warlord): 
                        $medal = $medals[$i] ?? 'bronze';
                        $initials = get_initials($warlord['name']);
                    ?>
                        <div class="warlord-card <?= $medal ?>">
                            <?php if ($warlord['streak'] > 3): ?>
                                <span class="streak-badge">🔥</span>
                            <?php endif; ?>
                            
                            <div class="warlord-avatar">
                                <?= htmlspecialchars($initials) ?>
                            </div>
                            <div class="warlord-name"><?= htmlspecialchars($warlord['name']) ?></div>
                            <div class="warlord-branch"><?= htmlspecialchars($warlord['branch'] ?? 'غير محدد') ?></div>
                            <div class="warlord-score"><?= $warlord['score'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        
        <!-- مقارنة الفروع -->
        <section class="warfare-section">
            <h2 class="section-title">📈 أداء الفروع 📈</h2>
            
            <?php if (empty($branch_warfare)): ?>
                <div class="empty-state">
                    <i class="bi bi-building"></i>
                    <p>لا توجد بيانات فروع للمقارنة</p>
                </div>
            <?php else: ?>
                <div class="warfare-bars">
                    <?php 
                    $max_score = max(array_column($branch_warfare, 'avg_score'));
                    foreach ($branch_warfare as $branch): 
                        $bar_width = $max_score > 0 ? ($branch['avg_score'] / $max_score) * 100 : 0;
                    ?>
                        <div class="warfare-bar" style="--bar-width: <?= $bar_width ?>%">
                            <style>
                                .warfare-bar[style*="--bar-width: <?= $bar_width ?>%"]::before {
                                    width: <?= $bar_width ?>%;
                                }
                            </style>
                            <div class="warfare-rank">#<?= $branch['rank'] ?></div>
                            <div class="warfare-info">
                                <div class="warfare-name"><?= htmlspecialchars($branch['name']) ?></div>
                                <div class="warfare-soldiers">
                                    <i class="bi bi-people-fill"></i>
                                    <?= $branch['soldiers'] ?> مقاتل
                                </div>
                            </div>
                            <div class="warfare-score"><?= $branch['avg_score'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        
        <!-- الأداء المنخفض - Bottom 5 -->
        <section class="guillotine-section">
            <h2 class="section-title">📉 يحتاج تحسين 📉</h2>
            
            <?php if (empty($guillotine)): ?>
                <div class="empty-state">
                    <i class="bi bi-emoji-smile"></i>
                    <p>لا يوجد متأخرون! الجميع يؤدون بشكل ممتاز</p>
                </div>
            <?php else: ?>
                <div class="guillotine-list">
                    <?php foreach ($guillotine as $victim): 
                        $initials = get_initials($victim['name']);
                    ?>
                        <div class="guillotine-item">
                            <span class="guillotine-skull">💀</span>
                            <div class="guillotine-avatar">
                                <?= htmlspecialchars($initials) ?>
                            </div>
                            <div class="guillotine-info">
                                <div class="guillotine-name"><?= htmlspecialchars($victim['name']) ?></div>
                                <div class="guillotine-branch"><?= htmlspecialchars($victim['branch'] ?? 'غير محدد') ?></div>
                            </div>
                            <div>
                                <div class="guillotine-score"><?= $victim['score'] ?></div>
                                <div class="guillotine-warning">⬇️ تحتاج لتحسين</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
    
    <script>
        // Animate warfare bars on load
        document.addEventListener('DOMContentLoaded', () => {
            const bars = document.querySelectorAll('.warfare-bar');
            bars.forEach((bar, i) => {
                bar.style.opacity = '0';
                bar.style.transform = 'translateX(50px)';
                
                setTimeout(() => {
                    bar.style.transition = 'all 0.6s ease-out';
                    bar.style.opacity = '1';
                    bar.style.transform = 'translateX(0)';
                }, i * 150);
            });
        });
    </script>
</body>
</html>
