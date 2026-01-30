<?php
/**
 * ========================================================================
 * ARCHITECT CONSOLE - LOG VIEWER
 * عارض السجلات في الوقت الفعلي
 * ========================================================================
 */

require __DIR__ . '/auth.php';

// Log files configuration
$logFiles = [
    'Apache Error' => 'C:/xampp/apache/logs/error.log',
    'Apache Access' => 'C:/xampp/apache/logs/access.log',
    'PHP Error' => 'C:/xampp/php/logs/php_error_log',
    'MySQL Error' => 'C:/xampp/mysql/data/mysql_error.log',
    'Architect Access' => __DIR__ . '/.architect_access.log',
];

// Get selected log
$selectedLog = $_GET['log'] ?? 'Apache Error';
$logPath = $logFiles[$selectedLog] ?? $logFiles['Apache Error'];
$lines = intval($_GET['lines'] ?? 100);

// Read log file
$logContent = [];
$fileExists = file_exists($logPath);
$fileSize = $fileExists ? filesize($logPath) : 0;

if ($fileExists && $fileSize > 0) {
    // Read last N lines efficiently
    $file = new SplFileObject($logPath, 'r');
    $file->seek(PHP_INT_MAX);
    $totalLines = $file->key();
    
    $startLine = max(0, $totalLines - $lines);
    $file->seek($startLine);
    
    while (!$file->eof()) {
        $line = $file->fgets();
        if (trim($line)) {
            $logContent[] = $line;
        }
    }
}

// Clear log action
if (isset($_POST['clear_log']) && $fileExists) {
    file_put_contents($logPath, '');
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Format file size
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Detect log type for coloring
function getLogClass($line) {
    $line = strtolower($line);
    if (strpos($line, 'fatal') !== false || strpos($line, 'error') !== false) {
        return 'log-error';
    }
    if (strpos($line, 'warning') !== false || strpos($line, 'warn') !== false) {
        return 'log-warning';
    }
    if (strpos($line, 'notice') !== false || strpos($line, 'info') !== false) {
        return 'log-info';
    }
    if (strpos($line, 'success') !== false) {
        return 'log-success';
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>📋 عارض السجلات - Architect Console</title>
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
            --warning: #ffab00;
            --danger: #ff5252;
            --info: #40c4ff;
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
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
        }
        
        .header-title i {
            color: var(--accent);
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
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
        
        .btn-primary {
            background: var(--accent);
            color: #fff;
        }
        
        .btn-primary:hover {
            background: #e65100;
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
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
        
        select {
            padding: 8px 12px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 13px;
            font-family: inherit;
            cursor: pointer;
        }
        
        select:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .main {
            padding: 20px;
        }
        
        .stats-bar {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .stat-item {
            background: var(--bg-card);
            padding: 12px 20px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }
        
        .stat-label {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        
        .stat-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            color: var(--accent);
        }
        
        .log-container {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .log-header {
            background: var(--bg-secondary);
            padding: 12px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .log-path {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .log-content {
            max-height: 70vh;
            overflow-y: auto;
            padding: 15px;
        }
        
        .log-line {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            line-height: 1.6;
            padding: 6px 12px;
            margin-bottom: 4px;
            border-radius: 6px;
            background: rgba(0, 0, 0, 0.3);
            white-space: pre-wrap;
            word-break: break-all;
            border-right: 3px solid transparent;
        }
        
        .log-error {
            border-right-color: var(--danger);
            color: rgba(255, 82, 82, 0.9);
            background: rgba(255, 82, 82, 0.1);
        }
        
        .log-warning {
            border-right-color: var(--warning);
            color: rgba(255, 171, 0, 0.9);
            background: rgba(255, 171, 0, 0.1);
        }
        
        .log-info {
            border-right-color: var(--info);
            color: rgba(64, 196, 255, 0.9);
            background: rgba(64, 196, 255, 0.1);
        }
        
        .log-success {
            border-right-color: var(--success);
            color: rgba(0, 230, 118, 0.9);
            background: rgba(0, 230, 118, 0.1);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--success);
        }
        
        /* Scrollbar */
        .log-content::-webkit-scrollbar {
            width: 8px;
        }
        
        .log-content::-webkit-scrollbar-track {
            background: var(--bg-primary);
        }
        
        .log-content::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 4px;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .stats-bar {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1 class="header-title">
            <i class="bi bi-file-text"></i>
            عارض السجلات
        </h1>
        
        <div class="header-actions">
            <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                <select name="log" onchange="this.form.submit()">
                    <?php foreach ($logFiles as $name => $path): ?>
                    <option value="<?= htmlspecialchars($name) ?>" <?= $selectedLog === $name ? 'selected' : '' ?>>
                        <?= htmlspecialchars($name) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="lines" onchange="this.form.submit()">
                    <option value="50" <?= $lines === 50 ? 'selected' : '' ?>>50 سطر</option>
                    <option value="100" <?= $lines === 100 ? 'selected' : '' ?>>100 سطر</option>
                    <option value="200" <?= $lines === 200 ? 'selected' : '' ?>>200 سطر</option>
                    <option value="500" <?= $lines === 500 ? 'selected' : '' ?>>500 سطر</option>
                </select>
            </form>
            
            <button onclick="location.reload()" class="btn btn-secondary">
                <i class="bi bi-arrow-clockwise"></i>
                تحديث
            </button>
            
            <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من مسح السجل؟')">
                <button type="submit" name="clear_log" class="btn btn-danger">
                    <i class="bi bi-trash"></i>
                    مسح
                </button>
            </form>
            
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-right"></i>
                رجوع
            </a>
        </div>
    </header>
    
    <main class="main">
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-label">الملف</div>
                <div class="stat-value"><?= htmlspecialchars($selectedLog) ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">الحجم</div>
                <div class="stat-value"><?= $fileExists ? formatSize($fileSize) : 'غير موجود' ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">الأسطر المعروضة</div>
                <div class="stat-value"><?= count($logContent) ?> / <?= $lines ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">الحالة</div>
                <div class="stat-value" style="color: <?= $fileExists ? 'var(--success)' : 'var(--danger)' ?>">
                    <?= $fileExists ? '✓ موجود' : '✗ غير موجود' ?>
                </div>
            </div>
        </div>
        
        <div class="log-container">
            <div class="log-header">
                <span class="log-path"><?= htmlspecialchars($logPath) ?></span>
                <span style="font-size: 12px; color: var(--text-muted);">
                    آخر تحديث: <?= date('H:i:s') ?>
                </span>
            </div>
            
            <div class="log-content" id="logContent">
                <?php if (empty($logContent)): ?>
                    <div class="empty-state">
                        <i class="bi bi-check-circle"></i>
                        <p>السجل فارغ أو الملف غير موجود</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($logContent as $line): ?>
                        <div class="log-line <?= getLogClass($line) ?>"><?= htmlspecialchars($line) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <script>
        // Auto-scroll to bottom
        const logContent = document.getElementById('logContent');
        logContent.scrollTop = logContent.scrollHeight;
        
        // Auto-refresh every 10 seconds
        let autoRefresh = false;
        
        function toggleAutoRefresh() {
            autoRefresh = !autoRefresh;
            if (autoRefresh) {
                setInterval(() => location.reload(), 10000);
            }
        }
    </script>
</body>
</html>
