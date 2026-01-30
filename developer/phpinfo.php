<?php
/**
 * ========================================================================
 * ARCHITECT CONSOLE - PHP INFO
 * معلومات PHP التفصيلية
 * ========================================================================
 */

require __DIR__ . '/auth.php';

// Show phpinfo if requested
if (isset($_GET['full'])) {
    phpinfo();
    exit;
}

// Collect PHP information
$phpInfo = [
    'إصدار PHP' => phpversion(),
    'نظام التشغيل' => PHP_OS,
    'معرف السيرفر' => php_sapi_name(),
    'حد الذاكرة' => ini_get('memory_limit'),
    'وقت التنفيذ الأقصى' => ini_get('max_execution_time') . ' ثانية',
    'حد الرفع' => ini_get('upload_max_filesize'),
    'حد POST' => ini_get('post_max_size'),
    'المنطقة الزمنية' => date_default_timezone_get(),
    'عرض الأخطاء' => ini_get('display_errors') ? 'مفعّل' : 'معطّل',
    'تسجيل الأخطاء' => ini_get('log_errors') ? 'مفعّل' : 'معطّل',
];

$extensions = get_loaded_extensions();
sort($extensions);

// Critical extensions check
$criticalExtensions = [
    'pdo' => 'PDO',
    'pdo_mysql' => 'PDO MySQL',
    'json' => 'JSON',
    'mbstring' => 'Multibyte String',
    'openssl' => 'OpenSSL',
    'curl' => 'cURL',
    'gd' => 'GD Graphics',
    'zip' => 'ZIP',
    'fileinfo' => 'FileInfo',
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>ℹ️ معلومات PHP - Architect Console</title>
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
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
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
        
        .btn-primary {
            background: var(--accent);
            color: #fff;
        }
        
        .main {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            background: var(--bg-secondary);
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header i { color: var(--accent); }
        
        .card-body { padding: 20px; }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
        }
        
        .info-table td:first-child {
            color: var(--text-muted);
            width: 40%;
        }
        
        .info-table tr:last-child td {
            border-bottom: none;
        }
        
        .extensions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }
        
        .ext-badge {
            padding: 8px 12px;
            background: rgba(0, 230, 118, 0.1);
            border: 1px solid rgba(0, 230, 118, 0.3);
            border-radius: 8px;
            font-size: 12px;
            font-family: 'JetBrains Mono', monospace;
            color: var(--success);
        }
        
        .ext-badge.missing {
            background: rgba(255, 82, 82, 0.1);
            border-color: rgba(255, 82, 82, 0.3);
            color: var(--danger);
        }
        
        .critical-check {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .critical-check:last-child { border-bottom: none; }
        
        .check-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .check-icon.ok {
            background: rgba(0, 230, 118, 0.2);
            color: var(--success);
        }
        
        .check-icon.fail {
            background: rgba(255, 82, 82, 0.2);
            color: var(--danger);
        }
        
        .php-version {
            text-align: center;
            padding: 30px;
        }
        
        .php-version .version {
            font-size: 48px;
            font-weight: 700;
            color: var(--accent);
            font-family: 'JetBrains Mono', monospace;
        }
        
        .php-version .label {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
    <header class="header">
        <h1 class="header-title">
            <i class="bi bi-filetype-php"></i>
            معلومات PHP
        </h1>
        <div class="header-actions">
            <a href="?full=1" target="_blank" class="btn btn-primary">
                <i class="bi bi-box-arrow-up-right"></i>
                phpinfo() كامل
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-right"></i>
                رجوع
            </a>
        </div>
    </header>
    
    <main class="main">
        <!-- PHP Version -->
        <div class="card">
            <div class="card-body">
                <div class="php-version">
                    <div class="version"><?= phpversion() ?></div>
                    <div class="label">PHP Version</div>
                </div>
            </div>
        </div>
        
        <!-- General Info -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-gear"></i>
                الإعدادات العامة
            </div>
            <div class="card-body">
                <table class="info-table">
                    <?php foreach ($phpInfo as $key => $value): ?>
                    <tr>
                        <td><?= $key ?></td>
                        <td><?= $value ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        
        <!-- Critical Extensions -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-shield-check"></i>
                الملحقات الأساسية
            </div>
            <div class="card-body" style="padding: 0;">
                <?php foreach ($criticalExtensions as $ext => $name): ?>
                <div class="critical-check">
                    <div class="check-icon <?= extension_loaded($ext) ? 'ok' : 'fail' ?>">
                        <i class="bi bi-<?= extension_loaded($ext) ? 'check' : 'x' ?>"></i>
                    </div>
                    <span><?= $name ?></span>
                    <span style="margin-right: auto; font-family: 'JetBrains Mono'; font-size: 11px; color: var(--text-muted);">
                        <?= $ext ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- All Extensions -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-puzzle"></i>
                جميع الملحقات (<?= count($extensions) ?>)
            </div>
            <div class="card-body">
                <div class="extensions-grid">
                    <?php foreach ($extensions as $ext): ?>
                    <div class="ext-badge"><?= $ext ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
