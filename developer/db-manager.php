<?php
/**
 * ========================================================================
 * ARCHITECT CONSOLE - DATABASE MANAGER
 * مدير قاعدة البيانات
 * ========================================================================
 */

require __DIR__ . '/auth.php';

// Database connection (independent)
$pdo = null;
$dbError = '';
$tables = [];
$queryResult = null;
$queryError = '';

try {
    $configFile = dirname(__DIR__) . '/config/database.php';
    if (file_exists($configFile)) {
        $configContent = file_get_contents($configFile);
        
        preg_match("/define\s*\(\s*'DB_HOST'\s*,\s*'([^']+)'/", $configContent, $hostMatch);
        preg_match("/define\s*\(\s*'DB_NAME'\s*,\s*'([^']+)'/", $configContent, $nameMatch);
        preg_match("/define\s*\(\s*'DB_USER'\s*,\s*'([^']+)'/", $configContent, $userMatch);
        preg_match("/define\s*\(\s*'DB_PASS'\s*,\s*'([^']*)'/", $configContent, $passMatch);
        
        $dbHost = $hostMatch[1] ?? 'localhost';
        $dbName = $nameMatch[1] ?? '';
        $dbUser = $userMatch[1] ?? 'root';
        $dbPass = $passMatch[1] ?? '';
        
        if ($dbName) {
            $pdo = new PDO(
                "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Get tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        }
    }
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

// Execute query
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query']) && $pdo) {
    $sql = trim($_POST['query']);
    if ($sql) {
        try {
            $stmt = $pdo->query($sql);
            if (stripos($sql, 'SELECT') === 0 || stripos($sql, 'SHOW') === 0 || stripos($sql, 'DESCRIBE') === 0) {
                $queryResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $queryResult = ['affected_rows' => $stmt->rowCount()];
            }
        } catch (PDOException $e) {
            $queryError = $e->getMessage();
        }
    }
}

// View table structure
$viewTable = $_GET['table'] ?? '';
$tableStructure = [];
$tableData = [];

if ($viewTable && $pdo) {
    try {
        $tableStructure = $pdo->query("DESCRIBE `{$viewTable}`")->fetchAll(PDO::FETCH_ASSOC);
        $tableData = $pdo->query("SELECT * FROM `{$viewTable}` LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $queryError = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>🗄️ مدير قاعدة البيانات - Architect Console</title>
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
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        
        .container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: calc(100vh - 60px);
        }
        
        .sidebar {
            background: var(--bg-secondary);
            border-left: 1px solid var(--border-color);
            padding: 20px;
            overflow-y: auto;
        }
        
        .sidebar-title {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .table-list {
            list-style: none;
        }
        
        .table-item {
            display: block;
            padding: 10px 12px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 4px;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        
        .table-item:hover, .table-item.active {
            background: rgba(255, 111, 0, 0.1);
            color: var(--accent);
        }
        
        .table-item i {
            margin-left: 8px;
        }
        
        .main {
            padding: 20px;
            overflow-y: auto;
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
        }
        
        .card-body { padding: 20px; }
        
        textarea {
            width: 100%;
            min-height: 120px;
            padding: 15px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            resize: vertical;
        }
        
        textarea:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .result-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        
        .result-table th, .result-table td {
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            text-align: right;
        }
        
        .result-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--accent);
        }
        
        .result-table tr:hover td {
            background: rgba(255, 111, 0, 0.05);
        }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        .error-box {
            background: rgba(255, 82, 82, 0.1);
            border: 1px solid rgba(255, 82, 82, 0.3);
            color: var(--danger);
            padding: 15px;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
        }
        
        .success-box {
            background: rgba(0, 230, 118, 0.1);
            border: 1px solid rgba(0, 230, 118, 0.3);
            color: var(--success);
            padding: 15px;
            border-radius: 8px;
        }
        
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1 class="header-title">
            <i class="bi bi-database"></i>
            مدير قاعدة البيانات
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-right"></i>
            رجوع
        </a>
    </header>
    
    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-title">الجداول (<?= count($tables) ?>)</div>
            <ul class="table-list">
                <?php foreach ($tables as $table): ?>
                <a href="?table=<?= urlencode($table) ?>" class="table-item <?= $viewTable === $table ? 'active' : '' ?>">
                    <i class="bi bi-table"></i>
                    <?= htmlspecialchars($table) ?>
                </a>
                <?php endforeach; ?>
            </ul>
        </aside>
        
        <main class="main">
            <?php if ($dbError): ?>
            <div class="error-box" style="margin-bottom: 20px;">
                <strong>خطأ في الاتصال:</strong><br>
                <?= htmlspecialchars($dbError) ?>
            </div>
            <?php endif; ?>
            
            <!-- SQL Query -->
            <div class="card">
                <div class="card-header">تنفيذ استعلام SQL</div>
                <div class="card-body">
                    <form method="POST">
                        <textarea name="query" placeholder="SELECT * FROM users LIMIT 10;"><?= htmlspecialchars($_POST['query'] ?? '') ?></textarea>
                        <button type="submit" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="bi bi-play-fill"></i>
                            تنفيذ
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if ($queryError): ?>
            <div class="error-box"><?= htmlspecialchars($queryError) ?></div>
            <?php elseif ($queryResult !== null): ?>
            <div class="card">
                <div class="card-header">النتيجة</div>
                <div class="card-body">
                    <?php if (isset($queryResult['affected_rows'])): ?>
                        <div class="success-box">
                            تم التنفيذ بنجاح. الصفوف المتأثرة: <?= $queryResult['affected_rows'] ?>
                        </div>
                    <?php elseif (empty($queryResult)): ?>
                        <p style="color: var(--text-muted);">لا توجد نتائج</p>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table class="result-table">
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($queryResult[0]) as $col): ?>
                                        <th><?= htmlspecialchars($col) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($queryResult as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $val): ?>
                                        <td><?= htmlspecialchars($val ?? 'NULL') ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p style="margin-top: 15px; color: var(--text-muted); font-size: 12px;">
                            عدد الصفوف: <?= count($queryResult) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($viewTable && !empty($tableStructure)): ?>
            <!-- Table Structure -->
            <div class="card">
                <div class="card-header">هيكل الجدول: <?= htmlspecialchars($viewTable) ?></div>
                <div class="card-body">
                    <div class="table-wrapper">
                        <table class="result-table">
                            <thead>
                                <tr>
                                    <th>العمود</th>
                                    <th>النوع</th>
                                    <th>Null</th>
                                    <th>المفتاح</th>
                                    <th>الافتراضي</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableStructure as $col): ?>
                                <tr>
                                    <td><?= htmlspecialchars($col['Field']) ?></td>
                                    <td><?= htmlspecialchars($col['Type']) ?></td>
                                    <td><?= $col['Null'] ?></td>
                                    <td><?= $col['Key'] ?></td>
                                    <td><?= htmlspecialchars($col['Default'] ?? 'NULL') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Table Data -->
            <?php if (!empty($tableData)): ?>
            <div class="card">
                <div class="card-header">البيانات (أول 100 صف)</div>
                <div class="card-body">
                    <div class="table-wrapper">
                        <table class="result-table">
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($tableData[0]) as $col): ?>
                                    <th><?= htmlspecialchars($col) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableData as $row): ?>
                                <tr>
                                    <?php foreach ($row as $val): ?>
                                    <td><?= htmlspecialchars(mb_substr($val ?? 'NULL', 0, 100)) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
