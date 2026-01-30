<?php
/**
 * ========================================================================
 * ARCHITECT CONSOLE - FILE MANAGER
 * مدير الملفات
 * ========================================================================
 */

require __DIR__ . '/auth.php';

$projectRoot = dirname(__DIR__);
$currentPath = $_GET['path'] ?? '';
$fullPath = realpath($projectRoot . '/' . $currentPath) ?: $projectRoot;

// Security: Ensure we stay within project
if (strpos($fullPath, realpath($projectRoot)) !== 0) {
    $fullPath = $projectRoot;
    $currentPath = '';
}

$files = [];
$directories = [];
$fileContent = '';
$editFile = $_GET['edit'] ?? '';
$message = '';

// Handle file operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save file
    if (isset($_POST['save_file']) && isset($_POST['file_path']) && isset($_POST['content'])) {
        $savePath = realpath($projectRoot . '/' . $_POST['file_path']);
        if ($savePath && strpos($savePath, realpath($projectRoot)) === 0) {
            file_put_contents($savePath, $_POST['content']);
            $message = 'تم حفظ الملف بنجاح';
        }
    }
    
    // Delete file
    if (isset($_POST['delete_file']) && isset($_POST['file_path'])) {
        $deletePath = realpath($projectRoot . '/' . $_POST['file_path']);
        if ($deletePath && strpos($deletePath, realpath($projectRoot)) === 0 && is_file($deletePath)) {
            unlink($deletePath);
            $message = 'تم حذف الملف';
            header('Location: ?path=' . urlencode(dirname($_POST['file_path'])));
            exit;
        }
    }
    
    // Create file/folder
    if (isset($_POST['create_name']) && isset($_POST['create_type'])) {
        $newPath = $fullPath . '/' . basename($_POST['create_name']);
        if (!file_exists($newPath)) {
            if ($_POST['create_type'] === 'folder') {
                mkdir($newPath);
                $message = 'تم إنشاء المجلد';
            } else {
                file_put_contents($newPath, '');
                $message = 'تم إنشاء الملف';
            }
        }
    }
}

// Read directory
if (is_dir($fullPath)) {
    $items = scandir($fullPath);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $itemPath = $fullPath . '/' . $item;
        if (is_dir($itemPath)) {
            $directories[] = $item;
        } else {
            $files[] = [
                'name' => $item,
                'size' => filesize($itemPath),
                'modified' => filemtime($itemPath),
                'extension' => pathinfo($item, PATHINFO_EXTENSION)
            ];
        }
    }
    sort($directories);
    usort($files, fn($a, $b) => $a['name'] <=> $b['name']);
}

// Read file for editing
if ($editFile) {
    $editPath = realpath($projectRoot . '/' . $editFile);
    if ($editPath && strpos($editPath, realpath($projectRoot)) === 0 && is_file($editPath)) {
        $fileContent = file_get_contents($editPath);
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

// Get file icon
function getFileIcon($ext) {
    $icons = [
        'php' => 'filetype-php',
        'js' => 'filetype-js',
        'css' => 'filetype-css',
        'html' => 'filetype-html',
        'json' => 'filetype-json',
        'sql' => 'database',
        'md' => 'markdown',
        'txt' => 'file-text',
        'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image',
        'pdf' => 'file-pdf',
        'zip' => 'file-zip',
    ];
    return $icons[strtolower($ext)] ?? 'file-earmark';
}

// Build breadcrumb
$breadcrumb = [];
$pathParts = $currentPath ? explode('/', trim($currentPath, '/')) : [];
$buildPath = '';
foreach ($pathParts as $part) {
    $buildPath .= '/' . $part;
    $breadcrumb[] = ['name' => $part, 'path' => ltrim($buildPath, '/')];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>📁 مدير الملفات - Architect Console</title>
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
            flex-wrap: wrap;
            gap: 10px;
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
        }
        
        .main { padding: 20px; }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            flex-wrap: wrap;
        }
        
        .breadcrumb a {
            color: var(--accent);
            text-decoration: none;
        }
        
        .breadcrumb span { color: var(--text-muted); }
        
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .file-item {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            display: block;
        }
        
        .file-item:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
        }
        
        .file-icon {
            font-size: 32px;
            margin-bottom: 10px;
            color: var(--accent);
        }
        
        .file-icon.folder { color: #ffd54f; }
        
        .file-name {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 5px;
            word-break: break-all;
        }
        
        .file-meta {
            font-size: 11px;
            color: var(--text-muted);
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            background: rgba(0, 230, 118, 0.1);
            border: 1px solid rgba(0, 230, 118, 0.3);
            color: var(--success);
        }
        
        .editor-container {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .editor-header {
            background: var(--bg-secondary);
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .editor-textarea {
            width: 100%;
            min-height: 500px;
            padding: 20px;
            background: var(--bg-primary);
            border: none;
            color: var(--text-primary);
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            line-height: 1.6;
            resize: vertical;
        }
        
        .editor-textarea:focus { outline: none; }
        
        .create-form {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .create-form input, .create-form select {
            padding: 10px 15px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: inherit;
        }
        
        .create-form input:focus, .create-form select:focus {
            outline: none;
            border-color: var(--accent);
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
            <i class="bi bi-folder2-open"></i>
            مدير الملفات
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
        <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($editFile): ?>
        <!-- File Editor -->
        <div class="breadcrumb">
            <a href="?"><i class="bi bi-house"></i></a>
            <span>/</span>
            <span>تحرير: <?= htmlspecialchars($editFile) ?></span>
        </div>
        
        <form method="POST">
            <input type="hidden" name="file_path" value="<?= htmlspecialchars($editFile) ?>">
            <div class="editor-container">
                <div class="editor-header">
                    <span style="font-family: 'JetBrains Mono'; font-size: 12px; color: var(--text-muted);">
                        <?= htmlspecialchars($editFile) ?>
                    </span>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="save_file" class="btn btn-primary">
                            <i class="bi bi-save"></i>
                            حفظ
                        </button>
                        <a href="?path=<?= urlencode(dirname($editFile)) ?>" class="btn btn-secondary">
                            إلغاء
                        </a>
                    </div>
                </div>
                <textarea name="content" class="editor-textarea"><?= htmlspecialchars($fileContent) ?></textarea>
            </div>
        </form>
        
        <?php else: ?>
        <!-- File Browser -->
        <div class="breadcrumb">
            <a href="?"><i class="bi bi-house"></i></a>
            <?php foreach ($breadcrumb as $crumb): ?>
            <span>/</span>
            <a href="?path=<?= urlencode($crumb['path']) ?>"><?= htmlspecialchars($crumb['name']) ?></a>
            <?php endforeach; ?>
        </div>
        
        <form method="POST" class="create-form">
            <input type="text" name="create_name" placeholder="اسم الملف أو المجلد" required>
            <select name="create_type">
                <option value="file">ملف</option>
                <option value="folder">مجلد</option>
            </select>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus"></i>
                إنشاء
            </button>
        </form>
        
        <div class="file-grid">
            <?php if ($currentPath): ?>
            <a href="?path=<?= urlencode(dirname($currentPath)) ?>" class="file-item">
                <div class="file-icon folder"><i class="bi bi-arrow-up-circle"></i></div>
                <div class="file-name">..</div>
                <div class="file-meta">المجلد الأب</div>
            </a>
            <?php endif; ?>
            
            <?php foreach ($directories as $dir): ?>
            <a href="?path=<?= urlencode($currentPath ? $currentPath . '/' . $dir : $dir) ?>" class="file-item">
                <div class="file-icon folder"><i class="bi bi-folder-fill"></i></div>
                <div class="file-name"><?= htmlspecialchars($dir) ?></div>
                <div class="file-meta">مجلد</div>
            </a>
            <?php endforeach; ?>
            
            <?php foreach ($files as $file): ?>
            <a href="?edit=<?= urlencode($currentPath ? $currentPath . '/' . $file['name'] : $file['name']) ?>" class="file-item">
                <div class="file-icon"><i class="bi bi-<?= getFileIcon($file['extension']) ?>"></i></div>
                <div class="file-name"><?= htmlspecialchars($file['name']) ?></div>
                <div class="file-meta"><?= formatSize($file['size']) ?></div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
