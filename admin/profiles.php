<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - PSYCHOLOGICAL PROFILES DASHBOARD                     ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once '../config/app.php';
require_once '../includes/functions.php';
require_once '../includes/traps.php';

check_login();
require_role(8);

$profiles = ProfileManager::getAllProfiles();
$statistics = ProfileManager::getStatistics();

$stats = [
    'total' => count($profiles),
    'loyal' => 0,
    'risky' => 0,
    'critical' => 0
];

foreach ($profiles as $p) {
    if ($p['profile_type'] === 'loyal_sentinel') $stats['loyal']++;
    if ($p['risk_level'] === 'high') $stats['risky']++;
    if ($p['risk_level'] === 'critical') $stats['critical']++;
}

$csrf = csrf_token();
$profileTypes = [
    'loyal_sentinel' => ['label' => 'حارس مخلص', 'icon' => 'bi-shield-check', 'color' => '#27ae60'],
    'curious_observer' => ['label' => 'مراقب فضولي', 'icon' => 'bi-eye', 'color' => '#3498db'],
    'opportunist' => ['label' => 'انتهازي', 'icon' => 'bi-lightning', 'color' => '#f39c12'],
    'active_exploiter' => ['label' => 'مستغل نشط', 'icon' => 'bi-bug', 'color' => '#e74c3c'],
    'potential_insider' => ['label' => 'عميل محتمل', 'icon' => 'bi-incognito', 'color' => '#9b59b6'],
    'undetermined' => ['label' => 'غير محدد', 'icon' => 'bi-question-circle', 'color' => '#95a5a6']
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <title>الملفات النفسية - <?= e(APP_NAME ?? 'صرح') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --bg-dark: #0a0a0f;
            --bg-card: rgba(255,255,255,0.03);
            --border: rgba(255,255,255,0.08);
            --text: #e0e0e0;
            --text-muted: rgba(255,255,255,0.5);
        }
        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--bg-dark);
            color: var(--text);
            min-height: 100vh;
        }
        .navbar {
            background: rgba(255,255,255,0.02);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card h2 { font-size: 2.75rem; margin-bottom: 0.5rem; }
        .stat-card p { color: var(--text-muted); margin: 0; }
        .stat-card.success { border-color: #27ae60; }
        .stat-card.success h2 { color: #27ae60; }
        .stat-card.warning { border-color: #f39c12; }
        .stat-card.warning h2 { color: #f39c12; }
        .stat-card.danger { border-color: #e74c3c; }
        .stat-card.danger h2 { color: #e74c3c; }
        
        .profile-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }
        .profile-card:hover {
            background: rgba(255,255,255,0.05);
            transform: translateY(-2px);
        }
        .profile-card.risk-critical { border-right: 4px solid #e74c3c; }
        .profile-card.risk-high { border-right: 4px solid #f39c12; }
        .profile-card.risk-medium { border-right: 4px solid #3498db; }
        .profile-card.risk-low { border-right: 4px solid #27ae60; }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .profile-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6c5ce7, #a29bfe);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
        }
        .profile-name { font-weight: 600; font-size: 1.1rem; }
        .profile-meta { color: var(--text-muted); font-size: 0.85rem; }
        
        .profile-type {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .score-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 0.5rem;
        }
        .score-label {
            width: 60px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .score-bar {
            flex: 1;
            height: 8px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            overflow: hidden;
        }
        .score-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        .score-fill.trust { background: linear-gradient(90deg, #e74c3c, #f39c12, #27ae60); }
        .score-fill.integrity { background: #6c5ce7; }
        .score-fill.curiosity { background: #00b894; }
        .score-value {
            width: 40px;
            text-align: left;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }
        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.4rem 0.9rem;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #fff;
        }
        
        .modal-content {
            background: #1a1a2e;
            color: var(--text);
            border: 1px solid var(--border);
        }
        .modal-header { border-color: var(--border); }
        .btn-close { filter: invert(1); }
        
        .log-item {
            padding: 0.6rem 0;
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
        }
        .log-item:last-child { border: none; }
        .log-positive { color: #27ae60; }
        .log-negative { color: #e74c3c; }
        .log-neutral { color: var(--text-muted); }
        .log-critical { color: #9b59b6; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="bi bi-brain me-2"></i>الملفات النفسية
            </a>
            <a href="management.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-right me-1"></i>العودة
            </a>
        </div>
    </nav>

    <div class="container pb-5">
        <!-- Stats -->
        <div class="row g-4 mb-5">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <h2><?= $stats['total'] ?></h2>
                    <p>إجمالي الملفات</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card success">
                    <h2><?= $stats['loyal'] ?></h2>
                    <p>حراس مخلصون</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card warning">
                    <h2><?= $stats['risky'] ?></h2>
                    <p>خطر عالي</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card danger">
                    <h2><?= $stats['critical'] ?></h2>
                    <p>خطر حرج</p>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="legend">
            <?php foreach ($profileTypes as $type => $info): ?>
            <span class="legend-item" style="background:<?= $info['color'] ?>">
                <i class="bi <?= $info['icon'] ?>"></i>
                <?= $info['label'] ?>
            </span>
            <?php endforeach; ?>
        </div>

        <!-- Profiles Grid -->
        <div class="row">
            <?php foreach ($profiles as $p): ?>
            <?php
                $initials = mb_substr($p['full_name'] ?? '', 0, 2);
                $typeInfo = $profileTypes[$p['profile_type']] ?? $profileTypes['undetermined'];
            ?>
            <div class="col-md-6 col-xl-4">
                <div class="profile-card risk-<?= e($p['risk_level']) ?>">
                    <div class="profile-header">
                        <div class="profile-avatar"><?= e($initials) ?></div>
                        <div class="flex-grow-1">
                            <div class="profile-name"><?= e($p['full_name']) ?></div>
                            <div class="profile-meta"><?= e($p['emp_code']) ?> • <?= e($p['branch_name'] ?? 'بدون فرع') ?></div>
                        </div>
                        <span class="profile-type" style="background:<?= $typeInfo['color'] ?>">
                            <i class="bi <?= $typeInfo['icon'] ?>"></i>
                            <?= $typeInfo['label'] ?>
                        </span>
                    </div>
                    
                    <div class="score-row">
                        <span class="score-label">الثقة</span>
                        <div class="score-bar">
                            <div class="score-fill trust" style="width:<?= $p['trust_score'] ?>%"></div>
                        </div>
                        <span class="score-value <?= $p['trust_score'] < 50 ? 'text-danger' : '' ?>"><?= $p['trust_score'] ?>%</span>
                    </div>
                    
                    <div class="score-row">
                        <span class="score-label">النزاهة</span>
                        <div class="score-bar">
                            <div class="score-fill integrity" style="width:<?= $p['integrity_score'] ?>%"></div>
                        </div>
                        <span class="score-value"><?= $p['integrity_score'] ?>%</span>
                    </div>
                    
                    <div class="score-row">
                        <span class="score-label">الفضول</span>
                        <div class="score-bar">
                            <div class="score-fill curiosity" style="width:<?= min($p['curiosity_score'], 100) ?>%"></div>
                        </div>
                        <span class="score-value"><?= $p['curiosity_score'] ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top" style="border-color:var(--border)!important">
                        <small class="text-muted">
                            <i class="bi bi-crosshair me-1"></i><?= $p['total_traps_seen'] ?> فخ
                            <?php if ($p['total_violations'] > 0): ?>
                            <span class="text-danger">(<?= $p['total_violations'] ?> انتهاك)</span>
                            <?php endif; ?>
                        </small>
                        <button class="btn btn-sm btn-outline-light" onclick="viewLogs(<?= $p['user_id'] ?>, '<?= e(addslashes($p['full_name'])) ?>')">
                            <i class="bi bi-list-ul"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($profiles)): ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-inbox display-1 text-muted"></i>
                <p class="mt-3 text-muted">لا توجد ملفات نفسية بعد</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Logs Modal -->
    <div class="modal fade" id="logsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logsModalTitle">سجل التفاعلات</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="logsContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const CSRF = '<?= e($csrf) ?>';
    const modal = new bootstrap.Modal(document.getElementById('logsModal'));
    
    const trapLabels = {
        'data_leak': 'تسريب بيانات',
        'gps_debug': 'وضع GPS',
        'admin_override': 'زر المدير',
        'confidential_bait': 'طُعم سري',
        'recruitment': 'اختبار التجنيد'
    };
    
    const actionLabels = {
        'view_more': '👁️ شاهد المزيد',
        'close': '✅ أغلق',
        'report': '🚩 أبلغ',
        'manual_entry': '⚠️ إدخال يدوي',
        'last_known': '⚠️ موقع قديم',
        'wait_fix': '✅ انتظر',
        'report_issue': '🚩 أبلغ',
        'clicked': '⚡ نقر',
        'ignored': '😴 تجاهل',
        'view_details': '👁️ عرض',
        'dismiss': '✅ رفض',
        'accept': '💀 وافق',
        'uncomfortable': '😐 غير مرتاح',
        'illegal': '✅ غير قانوني',
        'timeout': '⏱️ انتهى الوقت'
    };
    
    async function viewLogs(userId, name) {
        document.getElementById('logsModalTitle').textContent = 'سجل: ' + name;
        document.getElementById('logsContent').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        modal.show();
        
        try {
            const res = await fetch('../api/trap_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF
                },
                body: JSON.stringify({ action: 'get_profile', user_id: userId })
            });
            const data = await res.json();
            
            if (data.success) {
                renderLogs(data.logs || []);
            } else {
                throw new Error(data.error);
            }
        } catch (e) {
            document.getElementById('logsContent').innerHTML = '<div class="alert alert-danger">خطأ: ' + e.message + '</div>';
        }
    }
    
    function renderLogs(logs) {
        if (logs.length === 0) {
            document.getElementById('logsContent').innerHTML = '<p class="text-center text-muted py-4">لا توجد سجلات</p>';
            return;
        }
        
        let html = '<div class="logs-list">';
        logs.forEach(log => {
            html += `
                <div class="log-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>
                            <strong>${trapLabels[log.trap_type] || log.trap_type}</strong>
                            <span class="log-${log.action_category}">${actionLabels[log.action_taken] || log.action_taken}</span>
                        </span>
                        <small class="text-muted">${log.created_at}</small>
                    </div>
                    <div class="mt-1">
                        <small class="text-muted">وقت الاستجابة: ${log.response_time_ms || 0}ms</small>
                        ${log.score_change ? `<small class="${log.score_change < 0 ? 'text-danger' : 'text-success'} ms-2">(${log.score_change > 0 ? '+' : ''}${log.score_change})</small>` : ''}
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        document.getElementById('logsContent').innerHTML = html;
    }
    </script>
</body>
</html>
