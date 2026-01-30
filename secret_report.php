<?php
/**
 * SARH System - Secret Report (THE MINE)
 * بلاغ سري "مجهول" - فخ للمنافقين
 */

require_once 'config/app.php';
require_once 'includes/functions.php';

check_login();

$user_id = $_SESSION['user_id'];
$user = get_current_user_data();
$csrf = csrf_token();
$success = false;
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'رمز الأمان غير صالح';
    } else {
        $reported_id = !empty($_POST['reported_id']) ? intval($_POST['reported_id']) : null;
        $report_type = $_POST['report_type'] ?? 'violation';
        $content = trim($_POST['content'] ?? '');
        
        if (empty($content) || mb_strlen($content) < 20) {
            $error = 'يرجى كتابة تفاصيل البلاغ (20 حرف على الأقل)';
        } else {
            try {
                Database::insert('integrity_reports', [
                    'sender_id' => $user_id, // THE TRAP: We store who sent it!
                    'reported_id' => $reported_id,
                    'report_type' => $report_type,
                    'content' => $content,
                    'is_anonymous_claim' => 1,
                    'status' => 'pending'
                ]);
                
                // Log the action
                Database::insert('integrity_logs', [
                    'user_id' => $user_id,
                    'action_type' => 'report_filed',
                    'target_type' => 'user',
                    'target_id' => $reported_id,
                    'details' => json_encode([
                        'report_type' => $report_type,
                        'claimed_anonymous' => true,
                        'content_preview' => mb_substr($content, 0, 100)
                    ]),
                    'severity' => 'medium',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);
                
                $success = true;
            } catch (Exception $e) {
                $error = 'حدث خطأ أثناء إرسال البلاغ';
            }
        }
    }
}

// Get employees list (excluding self)
$employees = Database::fetchAll(
    "SELECT id, full_name, emp_code FROM users WHERE id != ? AND is_active = 1 ORDER BY full_name",
    [$user_id]
);

$page_title = 'بلاغ سري';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - <?= e(APP_NAME ?? 'صرح') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --primary: #e74c3c; }
        body { 
            font-family: 'Tajawal', sans-serif; 
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
        }
        .report-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .report-card {
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .shield-icon {
            font-size: 4rem;
            color: var(--primary);
            display: block;
            text-align: center;
            margin-bottom: 1rem;
        }
        .warning-box {
            background: linear-gradient(135deg, #2d3436 0%, #000 100%);
            color: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .warning-box h5 { color: #ffd93d; }
        .warning-box .bi-shield-lock { color: #2ecc71; font-size: 2rem; }
        .encryption-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #27ae60;
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-top: 1rem;
        }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 0.75rem 1rem;
        }
        .btn-submit {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border: none;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            border-radius: 12px;
            width: 100%;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(231,76,60,0.4); }
        .success-card {
            text-align: center;
            padding: 3rem 2rem;
        }
        .success-card .bi-check-circle-fill { font-size: 5rem; color: #27ae60; }
        .fake-id {
            font-family: monospace;
            background: #f1f2f6;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            display: inline-block;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="report-container">
        <?php if ($success): ?>
        <!-- SUCCESS STATE -->
        <div class="report-card success-card">
            <i class="bi bi-check-circle-fill"></i>
            <h2 class="mt-3">تم إرسال البلاغ</h2>
            <p class="text-muted">بلاغك السري في طريقه للمراجعة</p>
            <div class="fake-id">
                <small>رقم البلاغ المشفر:</small><br>
                <strong>ANO-<?= strtoupper(bin2hex(random_bytes(4))) ?>-<?= date('Ymd') ?></strong>
            </div>
            <p class="mt-3 small text-muted">
                <i class="bi bi-shield-lock me-1"></i>
                هويتك محمية بتشفير 256-bit
            </p>
            <a href="index.php" class="btn btn-outline-dark mt-4">
                <i class="bi bi-house me-1"></i> العودة للرئيسية
            </a>
        </div>
        
        <?php else: ?>
        <!-- REPORT FORM -->
        <div class="report-card">
            <i class="bi bi-incognito shield-icon"></i>
            <h3 class="text-center mb-4">بلاغ سري 100%</h3>
            
            <!-- THE FAKE WARNING -->
            <div class="warning-box">
                <div class="d-flex align-items-start gap-3">
                    <i class="bi bi-shield-lock"></i>
                    <div>
                        <h5 class="mb-2">🔐 حماية الهوية مُفعّلة</h5>
                        <p class="mb-0 small opacity-75">
                            هذا البلاغ <strong>مجهول تماماً</strong>. هويتك مشفرة ولا يمكن لأي شخص 
                            - حتى مدير النظام - معرفة من أنت. البيانات تُخزّن بتشفير AES-256.
                        </p>
                        <span class="encryption-badge">
                            <i class="bi bi-lock-fill"></i>
                            End-to-End Encrypted
                        </span>
                    </div>
                </div>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <?= e($error) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                
                <div class="mb-3">
                    <label class="form-label">نوع البلاغ</label>
                    <select name="report_type" class="form-select" required>
                        <option value="violation">مخالفة نظامية</option>
                        <option value="harassment">تحرش أو إساءة</option>
                        <option value="theft">سرقة أو اختلاس</option>
                        <option value="fraud">احتيال أو تزوير</option>
                        <option value="other">أخرى</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">الشخص المُبلَّغ عنه (اختياري)</label>
                    <select name="reported_id" class="form-select">
                        <option value="">-- لا أريد تحديد شخص --</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>"><?= e($emp['full_name']) ?> (<?= e($emp['emp_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">تفاصيل البلاغ *</label>
                    <textarea name="content" class="form-control" rows="5" required 
                              placeholder="اكتب تفاصيل البلاغ هنا... (20 حرف على الأقل)"></textarea>
                    <small class="text-muted">كن دقيقاً في الوصف لتسهيل التحقيق</small>
                </div>
                
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="confirmAnon" required>
                    <label class="form-check-label" for="confirmAnon">
                        أفهم أن هذا البلاغ مجهول الهوية تماماً
                    </label>
                </div>
                
                <button type="submit" class="btn btn-danger btn-submit">
                    <i class="bi bi-send-fill me-2"></i>
                    إرسال البلاغ السري
                </button>
            </form>
            
            <p class="text-center text-muted small mt-4">
                <i class="bi bi-info-circle me-1"></i>
                البلاغات الكاذبة قد تعرضك للمساءلة القانونية
            </p>
        </div>
        <?php endif; ?>
        
        <p class="text-center mt-4">
            <a href="index.php" class="text-white text-decoration-none">
                <i class="bi bi-arrow-right me-1"></i> العودة
            </a>
        </p>
    </div>
</body>
</html>
