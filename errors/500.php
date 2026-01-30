<?php
/**
 * صفحة خطأ 500 - خطأ في الخادم
 */
define('SARH_SYSTEM', true);
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - خطأ في الخادم</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #ff6f00 0%, #0d1642 100%);
            color: white;
        }
        .error-container {
            text-align: center;
            padding: 40px;
        }
        .error-code {
            font-size: 8rem;
            font-weight: 700;
            line-height: 1;
            opacity: 0.3;
        }
        .error-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            color: #dc3545;
        }
        .btn-home {
            background: white;
            color: #ff6f00;
            padding: 12px 32px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-home:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            color: #ff6f00;
        }
        .btn-retry {
            background: transparent;
            border: 2px solid white;
            color: white;
            padding: 12px 32px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            margin-right: 12px;
        }
        .btn-retry:hover {
            background: white;
            color: #ff6f00;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">500</div>
        <i class="bi bi-exclamation-triangle-fill error-icon"></i>
        <h1 class="h3 mb-3">خطأ في الخادم</h1>
        <p class="mb-4 opacity-75">عذراً، حدث خطأ غير متوقع. فريقنا التقني يعمل على حل المشكلة.</p>
        <div>
            <a href="javascript:location.reload()" class="btn-retry">
                <i class="bi bi-arrow-clockwise"></i>
                إعادة المحاولة
            </a>
            <a href="/app/" class="btn-home">
                <i class="bi bi-house"></i>
                العودة للرئيسية
            </a>
        </div>
    </div>
</body>
</html>
