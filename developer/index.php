<?php
/**
 * ========================================================================
 * ARCHITECT CONSOLE - MAIN DASHBOARD
 * ========================================================================
 * The central hub for all developer/architect tools.
 * This console operates INDEPENDENTLY of the main application database.
 * ========================================================================
 */

// ════════════════════════════════════════════════════════════════════════
// AUTHENTICATION GATE - MUST BE FIRST LINE
// ════════════════════════════════════════════════════════════════════════
require __DIR__ . '/auth.php';

// ════════════════════════════════════════════════════════════════════════
// SYSTEM INFORMATION GATHERING (No Database Required)
// ════════════════════════════════════════════════════════════════════════

// PHP Info
$phpVersion = phpversion();
$phpExtensions = get_loaded_extensions();
$phpMemoryLimit = ini_get('memory_limit');
$phpMaxExecTime = ini_get('max_execution_time');
$phpUploadMax = ini_get('upload_max_filesize');

// Server Info
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown';
$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';

// Directory Info
$projectRoot = dirname(__DIR__);
$projectSize = 0;
$fileCount = 0;

// Calculate project size (lightweight scan)
function getDirSize($dir) {
    $size = 0;
    $count = 0;
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
                $count++;
            }
        }
    }
    return ['size' => $size, 'count' => $count];
}

$projectStats = getDirSize($projectRoot);
$projectSize = $projectStats['size'];
$fileCount = $projectStats['count'];

// Format bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

// Database connection test
$dbStatus = 'unknown';
$dbError = '';
try {
    $configFile = $projectRoot . '/config/database.php';
    if (file_exists($configFile)) {
        // Try to connect
        $configContent = file_get_contents($configFile);
        
        // Extract connection details using regex (avoid require to prevent errors)
        preg_match("/define\s*\(\s*'DB_HOST'\s*,\s*'([^']+)'/", $configContent, $hostMatch);
        preg_match("/define\s*\(\s*'DB_NAME'\s*,\s*'([^']+)'/", $configContent, $nameMatch);
        preg_match("/define\s*\(\s*'DB_USER'\s*,\s*'([^']+)'/", $configContent, $userMatch);
        preg_match("/define\s*\(\s*'DB_PASS'\s*,\s*'([^']*)'/", $configContent, $passMatch);
        
        $dbHost = $hostMatch[1] ?? 'localhost';
        $dbName = $nameMatch[1] ?? '';
        $dbUser = $userMatch[1] ?? 'root';
        $dbPass = $passMatch[1] ?? '';
        
        if ($dbName) {
            $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $dbStatus = 'connected';
            
            // Get table count
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $tableCount = count($tables);
        } else {
            $dbStatus = 'no_config';
        }
    } else {
        $dbStatus = 'no_config';
    }
} catch (PDOException $e) {
    $dbStatus = 'error';
    $dbError = $e->getMessage();
}

// Get recent error logs
$errorLogs = [];
$apacheLogPath = 'C:/xampp/apache/logs/error.log';
if (file_exists($apacheLogPath)) {
    $logContent = file($apacheLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $errorLogs = array_slice(array_reverse($logContent), 0, 10);
}

// Session info
$sessionAge = isset($_SESSION[ARCHITECT_SESSION_TIME]) 
    ? time() - $_SESSION[ARCHITECT_SESSION_TIME] 
    : 0;
$sessionAgeFormatted = gmdate('H:i:s', $sessionAge);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>🏛️ Architect Console - لوحة المعماري</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-card: #1a1a25;
            --accent: #ff6f00;
            --accent-light: #ffa040;
            --accent-dark: #e65100;
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --text-muted: rgba(255, 255, 255, 0.4);
            --border-color: rgba(255, 255, 255, 0.1);
            --success: #00e676;
            --warning: #ffab00;
            --danger: #ff5252;
            --info: #40c4ff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        /* Header */
        .header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-brand {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .header-title {
            font-size: 20px;
            font-weight: 700;
        }
        
        .header-subtitle {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--text-muted);
            letter-spacing: 1px;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .session-badge {
            background: rgba(255, 111, 0, 0.1);
            border: 1px solid rgba(255, 111, 0, 0.3);
            padding: 8px 16px;
            border-radius: 20px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: var(--accent);
        }
        
        .logout-btn {
            background: rgba(255, 82, 82, 0.1);
            border: 1px solid rgba(255, 82, 82, 0.3);
            color: var(--danger);
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: var(--danger);
            color: #fff;
        }
        
        /* Main content */
        .main {
            padding: 30px;
            max-width: 1600px;
            margin: 0 auto;
        }
        
        /* Stats row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 15px;
        }
        
        .stat-icon.php { background: rgba(119, 123, 180, 0.2); color: #777bb4; }
        .stat-icon.db { background: rgba(0, 230, 118, 0.2); color: var(--success); }
        .stat-icon.db.error { background: rgba(255, 82, 82, 0.2); color: var(--danger); }
        .stat-icon.files { background: rgba(255, 171, 0, 0.2); color: var(--warning); }
        .stat-icon.server { background: rgba(64, 196, 255, 0.2); color: var(--info); }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            font-family: 'JetBrains Mono', monospace;
        }
        
        .stat-label {
            color: var(--text-muted);
            font-size: 13px;
        }
        
        /* Tools grid */
        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .tool-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .tool-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }
        
        .tool-card:hover {
            border-color: var(--accent);
            transform: translateY(-3px);
            box-shadow: 0 10px 40px rgba(255, 111, 0, 0.1);
        }
        
        .tool-card:hover::before {
            transform: scaleY(1);
        }
        
        .tool-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 12px;
        }
        
        .tool-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .tool-title {
            font-size: 16px;
            font-weight: 700;
        }
        
        .tool-desc {
            color: var(--text-secondary);
            font-size: 13px;
            line-height: 1.5;
        }
        
        .tool-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--danger);
            color: #fff;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }
        
        /* Error logs section */
        .logs-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 30px;
        }
        
        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .log-entry {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--text-secondary);
            padding: 10px 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            margin-bottom: 8px;
            overflow-x: auto;
            white-space: nowrap;
        }
        
        .log-entry:last-child {
            margin-bottom: 0;
        }
        
        .log-entry.error {
            border-right: 3px solid var(--danger);
            color: rgba(255, 82, 82, 0.9);
        }
        
        .log-entry.warning {
            border-right: 3px solid var(--warning);
            color: rgba(255, 171, 0, 0.9);
        }
        
        /* Quick actions */
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .quick-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .quick-btn:hover {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        
        /* PHP Info table */
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
        }
        
        .info-table td:first-child {
            color: var(--text-muted);
            width: 40%;
        }
        
        .info-table td:last-child {
            color: var(--text-primary);
        }
        
        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-badge.success {
            background: rgba(0, 230, 118, 0.2);
            color: var(--success);
        }
        
        .status-badge.error {
            background: rgba(255, 82, 82, 0.2);
            color: var(--danger);
        }
        
        .status-badge.warning {
            background: rgba(255, 171, 0, 0.2);
            color: var(--warning);
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 30px;
            color: var(--text-muted);
            font-size: 12px;
            border-top: 1px solid var(--border-color);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-actions {
                width: 100%;
                justify-content: center;
            }
            
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tools-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-brand">
            <div class="header-icon">
                <img src="../assets/images/logo.png" alt="صرح الإتقان" style="width: 50px; height: 50px; object-fit: contain; border-radius: 10px;">
            </div>
            <div>
                <h1 class="header-title">لوحة المعماري</h1>
                <p class="header-subtitle">ARCHITECT CONSOLE v1.0</p>
            </div>
        </div>
        <div class="header-actions">
            <span class="session-badge">
                <i class="bi bi-clock"></i> 
                الجلسة: <?= $sessionAgeFormatted ?>
            </span>
            <a href="?logout=1" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i>
                خروج
            </a>
        </div>
    </header>
    
    <!-- Main Content -->
    <main class="main">
        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon php">
                    <i class="bi bi-filetype-php"></i>
                </div>
                <div class="stat-value"><?= $phpVersion ?></div>
                <div class="stat-label">إصدار PHP</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon db <?= $dbStatus === 'connected' ? '' : 'error' ?>">
                    <i class="bi bi-database<?= $dbStatus === 'connected' ? '-check' : '-x' ?>"></i>
                </div>
                <div class="stat-value">
                    <?php if ($dbStatus === 'connected'): ?>
                        <span class="status-badge success">متصل</span>
                    <?php elseif ($dbStatus === 'error'): ?>
                        <span class="status-badge error">خطأ</span>
                    <?php else: ?>
                        <span class="status-badge warning">غير مهيأ</span>
                    <?php endif; ?>
                </div>
                <div class="stat-label">
                    <?= $dbStatus === 'connected' ? "{$tableCount} جدول" : ($dbError ? 'تحقق من الإعدادات' : 'قاعدة البيانات') ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon files">
                    <i class="bi bi-files"></i>
                </div>
                <div class="stat-value"><?= number_format($fileCount) ?></div>
                <div class="stat-label">ملف (<?= formatBytes($projectSize) ?>)</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon server">
                    <i class="bi bi-hdd-rack"></i>
                </div>
                <div class="stat-value"><?= $phpMemoryLimit ?></div>
                <div class="stat-label">حد الذاكرة</div>
            </div>
        </div>
        
        <!-- Tools Grid -->
        <h2 class="section-title">
            <i class="bi bi-tools"></i>
            أدوات النظام
        </h2>
        <div class="tools-grid">
            <a href="db-manager.php" class="tool-card">
                <div class="tool-header">
                    <div class="tool-icon">🗄️</div>
                    <h3 class="tool-title">مدير قاعدة البيانات</h3>
                </div>
                <p class="tool-desc">
                    استعراض الجداول، تنفيذ استعلامات SQL، النسخ الاحتياطي والاستعادة
                </p>
            </a>
            
            <a href="file-manager.php" class="tool-card">
                <div class="tool-header">
                    <div class="tool-icon">📁</div>
                    <h3 class="tool-title">مدير الملفات</h3>
                </div>
                <p class="tool-desc">
                    تصفح، تعديل، حذف، ورفع الملفات مباشرة على السيرفر
                </p>
            </a>
            
            <a href="log-viewer.php" class="tool-card">
                <div class="tool-header">
                    <div class="tool-icon">📋</div>
                    <h3 class="tool-title">عارض السجلات</h3>
                </div>
                <p class="tool-desc">
                    مراقبة سجلات Apache و PHP والتطبيق في الوقت الفعلي
                </p>
            </a>
            
            <a href="phpinfo.php" class="tool-card">
                <div class="tool-header">
                    <div class="tool-icon">ℹ️</div>
                    <h3 class="tool-title">معلومات PHP</h3>
                </div>
                <p class="tool-desc">
                    عرض تفاصيل إعدادات PHP والملحقات المثبتة
                </p>
            </a>
            
            <a href="cache-manager.php" class="tool-card">
                <div class="tool-header">
                    <div class="tool-icon">🧹</div>
                    <h3 class="tool-title">مدير الكاش</h3>
                </div>
                <p class="tool-desc">
                    مسح ملفات الكاش، الجلسات المنتهية، والملفات المؤقتة
                </p>
            </a>
            
            <a href="backup-manager.php" class="tool-card">
                <span class="tool-badge">مهم</span>
                <div class="tool-header">
                    <div class="tool-icon">💾</div>
                    <h3 class="tool-title">النسخ الاحتياطي</h3>
                </div>
                <p class="tool-desc">
                    إنشاء واستعادة نسخ احتياطية كاملة للمشروع وقاعدة البيانات
                </p>
            </a>
        </div>
        
        <!-- Error Logs Section -->
        <div class="logs-section">
            <div class="logs-header">
                <h2 class="section-title" style="margin: 0;">
                    <i class="bi bi-exclamation-triangle"></i>
                    آخر الأخطاء
                </h2>
                <div class="quick-actions">
                    <a href="log-viewer.php" class="quick-btn">
                        <i class="bi bi-eye"></i>
                        عرض الكل
                    </a>
                    <a href="?clear_logs=1" class="quick-btn">
                        <i class="bi bi-trash"></i>
                        مسح السجلات
                    </a>
                </div>
            </div>
            
            <?php if (empty($errorLogs)): ?>
                <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                    <i class="bi bi-check-circle" style="font-size: 40px; color: var(--success);"></i>
                    <p style="margin-top: 10px;">لا توجد أخطاء مسجلة</p>
                </div>
            <?php else: ?>
                <?php foreach ($errorLogs as $log): ?>
                    <?php 
                    $logClass = '';
                    if (stripos($log, 'error') !== false || stripos($log, 'fatal') !== false) {
                        $logClass = 'error';
                    } elseif (stripos($log, 'warning') !== false || stripos($log, 'notice') !== false) {
                        $logClass = 'warning';
                    }
                    ?>
                    <div class="log-entry <?= $logClass ?>"><?= htmlspecialchars($log) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- System Info -->
        <div class="logs-section">
            <h2 class="section-title" style="margin-bottom: 20px;">
                <i class="bi bi-info-circle"></i>
                معلومات النظام
            </h2>
            <table class="info-table">
                <tr>
                    <td>السيرفر</td>
                    <td><?= htmlspecialchars($serverSoftware) ?></td>
                </tr>
                <tr>
                    <td>مسار المشروع</td>
                    <td><?= htmlspecialchars($projectRoot) ?></td>
                </tr>
                <tr>
                    <td>حد الرفع</td>
                    <td><?= $phpUploadMax ?></td>
                </tr>
                <tr>
                    <td>وقت التنفيذ الأقصى</td>
                    <td><?= $phpMaxExecTime ?> ثانية</td>
                </tr>
                <tr>
                    <td>عنوان IP الخاص بك</td>
                    <td><?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <td>الملحقات المحملة</td>
                    <td><?= count($phpExtensions) ?> ملحق</td>
                </tr>
            </table>
        </div>
        
        <!-- Quick Links -->
        <div class="quick-actions" style="justify-content: center; margin-top: 30px;">
            <a href="../index.php" class="quick-btn">
                <i class="bi bi-house"></i>
                الموقع الرئيسي
            </a>
            <a href="../admin/management.php" class="quick-btn">
                <i class="bi bi-gear"></i>
                لوحة الإدارة
            </a>
            <a href="../admin/universal_manager.php" class="quick-btn">
                <i class="bi bi-database"></i>
                مدير DB
            </a>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="footer">
        <p>🏛️ Architect Console • Sarh Al-Itqan System • <?= date('Y') ?></p>
        <p style="margin-top: 5px;">
            <code>Session ID: <?= session_id() ?></code>
        </p>
    </footer>
</body>
</html>
