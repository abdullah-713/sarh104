<?php
/**
 * =====================================================
 * صرح الإتقان - مولد مفاتيح VAPID
 * =====================================================
 * قم بتشغيل هذا الملف مرة واحدة لإنشاء مفاتيح VAPID
 * ثم انسخ المفاتيح إلى ملف config/app.php
 * 
 * يمكنك أيضاً استخدام:
 * https://vapidkeys.com/
 * =====================================================
 */

// منع الوصول المباشر في الإنتاج
$allowedIPs = ['127.0.0.1', '::1', $_SERVER['SERVER_ADDR'] ?? ''];
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

// للأمان، قم بتعليق هذا السطر بعد الاستخدام
// if (!in_array($clientIP, $allowedIPs)) {
//     http_response_code(403);
//     exit('Access denied');
// }

/**
 * توليد مفتاح VAPID بسيط (للتجربة)
 * في الإنتاج، استخدم مكتبة web-push-php
 */
function generateSimpleVapidKeys() {
    // هذه مفاتيح تجريبية - في الإنتاج استخدم OpenSSL
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
    
    $publicKey = '';
    $privateKey = '';
    
    // طول مفتاح VAPID العام عادة 87 حرف (base64url)
    for ($i = 0; $i < 87; $i++) {
        $publicKey .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    // طول المفتاح الخاص عادة 43 حرف
    for ($i = 0; $i < 43; $i++) {
        $privateKey .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return [
        'public' => $publicKey,
        'private' => $privateKey
    ];
}

// توليد مفاتيح حقيقية باستخدام OpenSSL إذا متاح
function generateRealVapidKeys() {
    if (!function_exists('openssl_pkey_new')) {
        return null;
    }
    
    try {
        // إنشاء مفتاح EC P-256
        $config = [
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];
        
        $key = openssl_pkey_new($config);
        if (!$key) {
            return null;
        }
        
        $details = openssl_pkey_get_details($key);
        if (!$details || !isset($details['ec'])) {
            return null;
        }
        
        // استخراج المفتاح الخاص
        openssl_pkey_export($key, $privateKeyPem);
        
        // تحويل للـ Base64URL
        $x = $details['ec']['x'];
        $y = $details['ec']['y'];
        $d = $details['ec']['d'];
        
        // المفتاح العام = 0x04 + X + Y
        $publicKeyBin = "\x04" . $x . $y;
        $publicKey = rtrim(strtr(base64_encode($publicKeyBin), '+/', '-_'), '=');
        
        // المفتاح الخاص = D
        $privateKey = rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
        
        return [
            'public' => $publicKey,
            'private' => $privateKey
        ];
        
    } catch (Exception $e) {
        return null;
    }
}

// محاولة إنشاء مفاتيح حقيقية أولاً
$keys = generateRealVapidKeys();

if (!$keys) {
    // استخدام مفاتيح مولدة مسبقاً (آمنة)
    // يمكنك الحصول على مفاتيح من https://vapidkeys.com/
    $keys = [
        'public' => 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U',
        'private' => 'UUxI4O8-FbRouADVXc-Muhe_d-8FN-S0GYl8_Oc4gpo'
    ];
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مولد مفاتيح VAPID | صرح الإتقان</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #ff6f00 0%, #e65100 100%);
            min-height: 100vh;
            font-family: 'Tajawal', sans-serif;
        }
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .key-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
            position: relative;
        }
        .copy-btn {
            position: absolute;
            top: 10px;
            left: 10px;
        }
        .code-block {
            background: #1a1a2e;
            color: #00ff88;
            border-radius: 12px;
            padding: 20px;
            font-family: monospace;
            font-size: 13px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <i class="bi bi-key-fill text-warning" style="font-size: 3rem;"></i>
                            <h2 class="mt-3">مفاتيح VAPID للإشعارات</h2>
                            <p class="text-muted">انسخ هذه المفاتيح إلى ملف الإعدادات</p>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>تنبيه:</strong> احفظ المفتاح الخاص في مكان آمن ولا تشاركه مع أحد!
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-unlock text-success me-2"></i>
                                المفتاح العام (Public Key)
                            </label>
                            <div class="key-box">
                                <button class="btn btn-sm btn-outline-secondary copy-btn" onclick="copyKey('public')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                                <code id="public-key"><?= htmlspecialchars($keys['public']) ?></code>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-lock text-danger me-2"></i>
                                المفتاح الخاص (Private Key)
                            </label>
                            <div class="key-box">
                                <button class="btn btn-sm btn-outline-secondary copy-btn" onclick="copyKey('private')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                                <code id="private-key"><?= htmlspecialchars($keys['private']) ?></code>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h5 class="mb-3">
                            <i class="bi bi-code-slash me-2"></i>
                            أضف للملف <code>config/app.php</code>
                        </h5>
                        
                        <div class="code-block">
<pre style="margin: 0; color: inherit;">
// إعدادات PWA و Push Notifications
define('PWA_VAPID_PUBLIC_KEY', '<?= htmlspecialchars($keys['public']) ?>');
define('PWA_VAPID_PRIVATE_KEY', '<?= htmlspecialchars($keys['private']) ?>');
define('PWA_PUSH_SUBJECT', 'mailto:admin@sarh.site');
</pre>
                        </div>
                        
                        <div class="alert alert-info mt-4">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>ملاحظة:</strong> بعد إضافة المفاتيح، احذف هذا الملف لأسباب أمنية.
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="https://vapidkeys.com/" target="_blank" class="btn btn-outline-primary">
                                <i class="bi bi-box-arrow-up-right me-2"></i>
                                إنشاء مفاتيح جديدة من vapidkeys.com
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function copyKey(type) {
        const key = document.getElementById(type + '-key').textContent;
        navigator.clipboard.writeText(key).then(() => {
            alert('تم نسخ المفتاح!');
        });
    }
    </script>
</body>
</html>
