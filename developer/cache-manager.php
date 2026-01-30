<?php
/**
 * ========================================================================
 * ARCHITECT CONSOLE - CACHE MANAGER
 * مدير الكاش والملفات المؤقتة
 * ========================================================================
 */

require __DIR__ . '/auth.php';

$projectRoot = dirname(__DIR__);
$message = '';
$messageType = 'success';

// Cacheable directories
$cacheLocations = [
    'PHP Sessions' => session_save_path() ?: sys_get_temp_dir(),
    'System Temp' => sys_get_temp_dir(),
    'Rate Limit Files' => __DIR__,
];

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear PHP Sessions
    if (isset($_POST['clear_sessions'])) {
        $sessionPath = session_save_path() ?: sys_get_temp_dir();
        $count = 0;
        foreach (glob($sessionPath . '/sess_*') as $file) {
            if (unlink($file)) $count++;
        }
        $message = "تم حذف {$count} ملف جلسة";
    }
    
    // Clear Rate Limit Files
    if (isset($_POST['clear_rate_limit'])) {
        $count = 0;
        foreach (glob(__DIR__ . '/.rate_limit_*') as $file) {
            if (unlink($file)) $count++;
        }
        $message = "تم حذف {$count} ملف rate limit";
    }
    
    // Clear OPcache
    if (isset($_POST['clear_opcache'])) {
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $message = 'تم مسح OPcache';
        } else {
            $message = 'OPcache غير مفعّل';
            $messageType = 'error';
        }
    }
    
    // Clear Access Log
    if (isset($_POST['clear_access_log'])) {
        $logFile = __DIR__ . '/.architect_access.log';
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
            $message = 'تم مسح سجل الوصول';
        }
    }
}

// Get stats
function getDirSize($dir, $pattern = '*') {
    $size = 0;
    $count = 0;
    foreach (glob($dir . '/' . $pattern) as $file) {
        if (is_file($file)) {
            $size += filesize($file);
            $count++;
        }
    }
    return ['size' => $size, 'count' => $count];
}

$sessionStats = getDirSize(session_save_path() ?: sys_get_temp_dir(), 'sess_*');
$rateLimitStats = getDirSize(__DIR__, '.rate_limit_*');

$opcacheEnabled = function_exists('opcache_get_status') && opcache_get_status();
$opcacheStats = $opcacheEnabled ? opcache_get_status() : null;

$accessLogSize = file_exists(__DIR__ . '/.architect_access.log') 
    ? filesize(__DIR__ . '/.architect_access.log') 
    : 0;

// Format size
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>🧹 مدير الكاش - Architect Console</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-card: #1a1a25;
            --accent: #ff6f00;
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --text-muted: rgba(255, 255, 255, 0.4);
            --border-color: rgba(255, 255, 255, 0.1);
            --success: #00e676;
            --danger: #ff5252;
            --warning: #ffab00;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }
        
        .header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
        }
        
        .header-title i { color: var(--accent); }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            font-family: inherit;
        }
        
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        .btn-danger {
            background: rgba(255, 82, 82, 0.2);
            color: var(--danger);
            border: 1px solid rgba(255, 82, 82, 0.3);
        }
        .btn-danger:hover {
            background: var(--danger);
            color: #fff;
        }
        
        .main {
            padding: 20px;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            background: rgba(0, 230, 118, 0.1);
            border: 1px solid rgba(0, 230, 118, 0.3);
            color: var(--success);
        }
        
        .message.error {
            background: rgba(255, 82, 82, 0.1);
            border-color: rgba(255, 82, 82, 0.3);
            color: var(--danger);
        }
        
        .cache-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .cache-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
        }
        
        .cache-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .cache-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .cache-icon.sessions {
            background: rgba(64, 196, 255, 0.2);
            color: #40c4ff;
        }
        
        .cache-icon.opcache {
            background: rgba(255, 171, 0, 0.2);
            color: var(--warning);
        }
        
        .cache-icon.rate {
            background: rgba(255, 82, 82, 0.2);
            color: var(--danger);
        }
        
        .cache-icon.logs {
            background: rgba(0, 230, 118, 0.2);
            color: var(--success);
        }
        
        .cache-title {
            font-size: 16px;
            font-weight: 700;
        }
        
        .cache-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat {
            flex: 1;
        }
        
        .stat-label {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        
        .stat-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 18px;
            color: var(--accent);
        }
        
        .cache-action {
            width: 100%;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-badge.active {
            background: rgba(0, 230, 118, 0.2);
            color: var(--success);
        }
        
        .status-badge.inactive {
            background: rgba(255, 82, 82, 0.2);
            color: var(--danger);
        }
    </style>
</head>
<body>
    <header class="header">
        <h1 class="header-title">
            <i class="bi bi-trash3"></i>
            مدير الكاش
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-right"></i>
            رجوع
        </a>
    </header>
    
    <main class="main">
        <?php if ($message): ?>
        <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="cache-grid">
            <!-- PHP Sessions -->
            <div class="cache-card">
                <div class="cache-header">
                    <div class="cache-icon sessions">
                        <i class="bi bi-people"></i>
                    </div>
                    <h3 class="cache-title">جلسات PHP</h3>
                </div>
                <div class="cache-stats">
                    <div class="stat">
                        <div class="stat-label">عدد الملفات</div>
                        <div class="stat-value"><?= number_format($sessionStats['count']) ?></div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">الحجم</div>
                        <div class="stat-value"><?= formatSize($sessionStats['size']) ?></div>
                    </div>
                </div>
                <form method="POST">
                    <button type="submit" name="clear_sessions" class="btn btn-danger cache-action" onclick="return confirm('سيتم تسجيل خروج جميع المستخدمين. متابعة؟')">
                        <i class="bi bi-trash"></i>
                        مسح الجلسات
                    </button>
                </form>
            </div>
            
            <!-- OPcache -->
            <div class="cache-card">
                <div class="cache-header">
                    <div class="cache-icon opcache">
                        <i class="bi bi-lightning"></i>
                    </div>
                    <h3 class="cache-title">OPcache</h3>
                </div>
                <div class="cache-stats">
                    <div class="stat">
                        <div class="stat-label">الحالة</div>
                        <div class="stat-value">
                            <span class="status-badge <?= $opcacheEnabled ? 'active' : 'inactive' ?>">
                                <?= $opcacheEnabled ? 'مفعّل' : 'معطّل' ?>
                            </span>
                        </div>
                    </div>
                    <?php if ($opcacheStats): ?>
                    <div class="stat">
                        <div class="stat-label">الذاكرة المستخدمة</div>
                        <div class="stat-value"><?= formatSize($opcacheStats['memory_usage']['used_memory'] ?? 0) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <form method="POST">
                    <button type="submit" name="clear_opcache" class="btn btn-danger cache-action" <?= !$opcacheEnabled ? 'disabled' : '' ?>>
                        <i class="bi bi-arrow-clockwise"></i>
                        إعادة تعيين OPcache
                    </button>
                </form>
            </div>
            
            <!-- Rate Limit Files -->
            <div class="cache-card">
                <div class="cache-header">
                    <div class="cache-icon rate">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h3 class="cache-title">ملفات Rate Limit</h3>
                </div>
                <div class="cache-stats">
                    <div class="stat">
                        <div class="stat-label">عدد الملفات</div>
                        <div class="stat-value"><?= number_format($rateLimitStats['count']) ?></div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">الحجم</div>
                        <div class="stat-value"><?= formatSize($rateLimitStats['size']) ?></div>
                    </div>
                </div>
                <form method="POST">
                    <button type="submit" name="clear_rate_limit" class="btn btn-danger cache-action">
                        <i class="bi bi-trash"></i>
                        مسح Rate Limit
                    </button>
                </form>
            </div>
            
            <!-- Access Log -->
            <div class="cache-card">
                <div class="cache-header">
                    <div class="cache-icon logs">
                        <i class="bi bi-file-text"></i>
                    </div>
                    <h3 class="cache-title">سجل الوصول</h3>
                </div>
                <div class="cache-stats">
                    <div class="stat">
                        <div class="stat-label">الحجم</div>
                        <div class="stat-value"><?= formatSize($accessLogSize) ?></div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">الملف</div>
                        <div class="stat-value" style="font-size: 11px;">.architect_access.log</div>
                    </div>
                </div>
                <form method="POST">
                    <button type="submit" name="clear_access_log" class="btn btn-danger cache-action">
                        <i class="bi bi-trash"></i>
                        مسح السجل
                    </button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
