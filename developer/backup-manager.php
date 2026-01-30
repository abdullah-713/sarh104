<?php
/**
 * ========================================================================
 * ARCHITECT CONSOLE - BACKUP MANAGER
 * مدير النسخ الاحتياطي
 * ========================================================================
 */

require __DIR__ . '/auth.php';

$projectRoot = dirname(__DIR__);
$backupDir = $projectRoot . '/backups';
$message = '';
$messageType = 'success';

// Ensure backup directory exists
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Get existing backups
$backups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*.zip');
    foreach ($files as $file) {
        $backups[] = [
            'name' => basename($file),
            'path' => $file,
            'size' => filesize($file),
            'date' => filemtime($file)
        ];
    }
    usort($backups, fn($a, $b) => $b['date'] <=> $a['date']);
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create backup
    if (isset($_POST['create_backup'])) {
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = "sarh_backup_{$timestamp}.zip";
        $backupPath = $backupDir . '/' . $backupName;
        
        $zip = new ZipArchive();
        if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($projectRoot) + 1);
                
                // Skip backups folder and large files
                if (strpos($relativePath, 'backups') === 0) continue;
                if (strpos($relativePath, 'uploads') === 0) continue;
                if ($file->getSize() > 10 * 1024 * 1024) continue; // Skip files > 10MB
                
                if ($file->isDir()) {
                    $zip->addEmptyDir($relativePath);
                } else {
                    $zip->addFile($filePath, $relativePath);
                }
            }
            
            $zip->close();
            $message = "تم إنشاء النسخة الاحتياطية: {$backupName}";
            
            // Refresh backups list
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $message = 'فشل في إنشاء النسخة الاحتياطية';
            $messageType = 'error';
        }
    }
    
    // Delete backup
    if (isset($_POST['delete_backup']) && isset($_POST['backup_file'])) {
        $deleteFile = $backupDir . '/' . basename($_POST['backup_file']);
        if (file_exists($deleteFile) && strpos(realpath($deleteFile), realpath($backupDir)) === 0) {
            unlink($deleteFile);
            $message = 'تم حذف النسخة الاحتياطية';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
}

// Download backup
if (isset($_GET['download'])) {
    $downloadFile = $backupDir . '/' . basename($_GET['download']);
    if (file_exists($downloadFile) && strpos(realpath($downloadFile), realpath($backupDir)) === 0) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($downloadFile) . '"');
        header('Content-Length: ' . filesize($downloadFile));
        readfile($downloadFile);
        exit;
    }
}

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
    <title>💾 النسخ الاحتياطي - Architect Console</title>
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
        .btn-primary:hover { background: #e65100; }
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
        .btn-success {
            background: rgba(0, 230, 118, 0.2);
            color: var(--success);
            border: 1px solid rgba(0, 230, 118, 0.3);
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
        }
        
        .message.success {
            background: rgba(0, 230, 118, 0.1);
            border: 1px solid rgba(0, 230, 118, 0.3);
            color: var(--success);
        }
        
        .message.error {
            background: rgba(255, 82, 82, 0.1);
            border: 1px solid rgba(255, 82, 82, 0.3);
            color: var(--danger);
        }
        
        .create-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .create-section h2 {
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        .create-section p {
            color: var(--text-muted);
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .backup-list {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .backup-header {
            background: var(--bg-secondary);
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
        }
        
        .backup-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .backup-item:last-child { border-bottom: none; }
        
        .backup-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        
        .backup-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 111, 0, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--accent);
        }
        
        .backup-details h4 {
            font-size: 14px;
            margin-bottom: 4px;
            font-family: 'JetBrains Mono', monospace;
        }
        
        .backup-details span {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .backup-actions {
            display: flex;
            gap: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--accent);
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
            <i class="bi bi-safe"></i>
            النسخ الاحتياطي
        </h1>
        <div class="header-actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-right"></i>
                رجوع
            </a>
        </div>
    </header>
    
    <main class="main">
        <?php if ($message): ?>
        <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <!-- Create Backup -->
        <div class="create-section">
            <h2>💾 إنشاء نسخة احتياطية جديدة</h2>
            <p>سيتم إنشاء نسخة احتياطية كاملة من المشروع (باستثناء مجلد uploads والملفات الكبيرة)</p>
            <form method="POST">
                <button type="submit" name="create_backup" class="btn btn-primary">
                    <i class="bi bi-cloud-download"></i>
                    إنشاء نسخة احتياطية الآن
                </button>
            </form>
        </div>
        
        <!-- Backup List -->
        <div class="backup-list">
            <div class="backup-header">
                <i class="bi bi-archive"></i>
                النسخ الاحتياطية المتوفرة (<?= count($backups) ?>)
            </div>
            
            <?php if (empty($backups)): ?>
            <div class="empty-state">
                <i class="bi bi-archive"></i>
                <p>لا توجد نسخ احتياطية بعد</p>
            </div>
            <?php else: ?>
            <?php foreach ($backups as $backup): ?>
            <div class="backup-item">
                <div class="backup-info">
                    <div class="backup-icon">
                        <i class="bi bi-file-zip"></i>
                    </div>
                    <div class="backup-details">
                        <h4><?= htmlspecialchars($backup['name']) ?></h4>
                        <span>
                            <?= formatSize($backup['size']) ?> • 
                            <?= date('Y-m-d H:i', $backup['date']) ?>
                        </span>
                    </div>
                </div>
                <div class="backup-actions">
                    <a href="?download=<?= urlencode($backup['name']) ?>" class="btn btn-success">
                        <i class="bi bi-download"></i>
                        تحميل
                    </a>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذه النسخة؟')">
                        <input type="hidden" name="backup_file" value="<?= htmlspecialchars($backup['name']) ?>">
                        <button type="submit" name="delete_backup" class="btn btn-danger">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
