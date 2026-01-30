<?php
/**
 * =====================================================
 * نظام صرح الإتقان للسيطرة الميدانية
 * Sarh Al-Itqan Field Operations System
 * =====================================================
 * رأس الصفحة
 * Page Header
 * =====================================================
 */

// تحميل الإعدادات إذا لم تكن محملة
if (!defined('SARH_SYSTEM')) {
    require_once dirname(__DIR__) . '/config/app.php';
}

// عنوان الصفحة الافتراضي
$pageTitle = $pageTitle ?? APP_NAME;
$pageDescription = $pageDescription ?? APP_TAGLINE;
$bodyClass = $bodyClass ?? '';
$hideNavbar = $hideNavbar ?? false;
$hideBottomNav = $hideBottomNav ?? false;

// الحصول على رسالة Flash
$flashMessage = get_flash();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#ff6f00">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= APP_NAME ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="<?= APP_NAME ?>">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    
    <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
    
    <!-- PWA / Icons -->
    <link rel="manifest" href="<?= url('manifest.json') ?>" crossorigin="use-credentials">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('images/favicon.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('images/apple-touch-icon.png') ?>">
    <link rel="apple-touch-icon" sizes="152x152" href="<?= asset('images/pwa/icon-152.png') ?>">
    <link rel="apple-touch-icon" sizes="192x192" href="<?= asset('images/pwa/icon-192.png') ?>">
    
    <!-- Google Fonts - Tajawal -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    <!-- Animated Logo Styles -->
    <link href="<?= asset('css/animated-logo.css') ?>" rel="stylesheet">
    
    <!-- Custom Styles -->
    <style>
        :root {
            /* الألوان الأساسية - Primary Colors */
            --sarh-primary: #ff6f00;
            --sarh-primary-light: #ffa040;
            --sarh-primary-dark: #e65100;
            --sarh-secondary: #ff6f00;
            --sarh-secondary-light: #ffa040;
            --sarh-accent: #00bfa5;
            
            /* ألوان الحالات - Status Colors */
            --sarh-success: #2e7d32;
            --sarh-warning: #f57c00;
            --sarh-danger: #c62828;
            --sarh-info: #0288d1;
            
            /* ألوان محايدة - Neutral Colors */
            --sarh-dark: #1a1a2e;
            --sarh-gray: #6c757d;
            --sarh-light: #f8f9fa;
            --sarh-white: #ffffff;
            
            /* ألوان الخلفية - Background Colors */
            --sarh-bg-primary: #f0f2f5;
            --sarh-bg-card: #ffffff;
            --sarh-bg-dark: #16213e;
            
            /* الظلال - Shadows */
            --sarh-shadow-sm: 0 2px 4px rgba(0,0,0,0.08);
            --sarh-shadow: 0 4px 12px rgba(0,0,0,0.12);
            --sarh-shadow-lg: 0 8px 24px rgba(0,0,0,0.16);
            
            /* التنقلات - Transitions */
            --sarh-transition: all 0.3s ease;
            
            /* ارتفاع شريط التنقل السفلي */
            --bottom-nav-height: 70px;
            
            /* الحدود المستديرة */
            --sarh-radius: 12px;
            --sarh-radius-lg: 20px;
        }
        
        /* الأساسيات */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: var(--sarh-bg-primary);
            color: var(--sarh-dark);
            min-height: 100vh;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* تعديل للصفحات مع شريط التنقل السفلي */
        body.has-bottom-nav {
            padding-bottom: calc(var(--bottom-nav-height) + 20px);
        }
        
        /* =====================================================
           شريط التنقل العلوي - Top Navbar
           ===================================================== */
        .top-navbar {
            background: linear-gradient(135deg, var(--sarh-primary) 0%, var(--sarh-primary-dark) 100%);
            padding: 12px 16px;
            position: sticky;
            top: 0;
            z-index: 1030;
            box-shadow: var(--sarh-shadow);
        }
        
        .top-navbar .navbar-brand {
            color: var(--sarh-white);
            font-weight: 700;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .top-navbar .navbar-brand img,
        .top-navbar .navbar-brand .navbar-logo {
            height: 40px;
            width: auto;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .top-navbar .nav-icon {
            color: var(--sarh-white);
            font-size: 1.25rem;
            padding: 8px;
            border-radius: 50%;
            transition: var(--sarh-transition);
            position: relative;
        }
        
        .top-navbar .nav-icon:hover,
        .top-navbar .nav-icon:active {
            background: rgba(255,255,255,0.15);
        }
        
        .top-navbar .notification-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            background: var(--sarh-danger);
            color: white;
            font-size: 0.65rem;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        
        /* =====================================================
           شريط التنقل السفلي - Bottom Navigation
           ===================================================== */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: var(--bottom-nav-height);
            background: var(--sarh-white);
            box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 1040;
            padding: 8px 0;
            border-top-left-radius: var(--sarh-radius-lg);
            border-top-right-radius: var(--sarh-radius-lg);
        }
        
        .bottom-nav .nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--sarh-gray);
            padding: 6px 4px;
            transition: var(--sarh-transition);
            position: relative;
            min-height: 54px;
        }
        
        .bottom-nav .nav-item i {
            font-size: 1.35rem;
            margin-bottom: 2px;
            transition: var(--sarh-transition);
        }
        
        .bottom-nav .nav-item span {
            font-size: 0.7rem;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .bottom-nav .nav-item.active,
        .bottom-nav .nav-item:hover {
            color: var(--sarh-primary);
        }
        
        .bottom-nav .nav-item.active i {
            transform: scale(1.15);
        }
        
        .bottom-nav .nav-item.active::before {
            content: '';
            position: absolute;
            top: -8px;
            width: 40px;
            height: 4px;
            background: var(--sarh-primary);
            border-radius: 0 0 4px 4px;
        }
        
        /* زر تسجيل الحضور المميز */
        .bottom-nav .nav-item.checkin-btn {
            position: relative;
        }
        
        .bottom-nav .nav-item.checkin-btn .checkin-circle {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--sarh-secondary) 0%, var(--sarh-secondary-light) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(255,111,0,0.4);
            margin-top: -28px;
            transition: var(--sarh-transition);
        }
        
        .bottom-nav .nav-item.checkin-btn .checkin-circle i {
            color: white;
            font-size: 1.5rem;
            margin: 0;
        }
        
        .bottom-nav .nav-item.checkin-btn:hover .checkin-circle,
        .bottom-nav .nav-item.checkin-btn:active .checkin-circle {
            transform: scale(1.08);
            box-shadow: 0 6px 20px rgba(255,111,0,0.5);
        }
        
        .bottom-nav .nav-item.checkin-btn span {
            margin-top: 4px;
            color: var(--sarh-secondary);
            font-weight: 600;
        }

        /* =====================================================
           PWA Install Prompt
           ===================================================== */
        .pwa-install-banner {
            position: fixed;
            left: 16px;
            right: 16px;
            bottom: calc(var(--bottom-nav-height) + 16px + env(safe-area-inset-bottom));
            z-index: 1100;
            display: none;
            justify-content: center;
            pointer-events: none;
        }

        body:not(.has-bottom-nav) .pwa-install-banner {
            bottom: calc(16px + env(safe-area-inset-bottom));
        }

        .pwa-install-banner.show {
            display: flex;
        }

        .pwa-install-button {
            pointer-events: auto;
            background: linear-gradient(135deg, var(--sarh-primary) 0%, var(--sarh-primary-dark) 100%);
            color: var(--sarh-white);
            border: none;
            border-radius: 999px;
            padding: 12px 20px;
            min-height: 48px;
            font-weight: 700;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .pwa-install-button:active {
            transform: translateY(1px) scale(0.98);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
        }

        .pwa-install-button i {
            font-size: 1.2rem;
        }
        
        /* =====================================================
           الأزرار - Buttons
           ===================================================== */
        .btn {
            font-family: 'Tajawal', sans-serif;
            font-weight: 600;
            padding: 12px 24px;
            min-height: 48px;
            border-radius: var(--sarh-radius);
            transition: var(--sarh-transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-lg {
            padding: 14px 28px;
            min-height: 56px;
            font-size: 1.1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--sarh-primary) 0%, var(--sarh-primary-light) 100%);
            border: none;
            color: white;
        }
        
        .btn-primary:hover,
        .btn-primary:active {
            background: linear-gradient(135deg, var(--sarh-primary-dark) 0%, var(--sarh-primary) 100%);
            transform: translateY(-2px);
            box-shadow: var(--sarh-shadow);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, var(--sarh-secondary) 0%, var(--sarh-secondary-light) 100%);
            border: none;
            color: white;
        }
        
        .btn-outline-primary {
            border: 2px solid var(--sarh-primary);
            color: var(--sarh-primary);
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: var(--sarh-primary);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--sarh-success) 0%, #43a047 100%);
            border: none;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--sarh-danger) 0%, #e53935 100%);
            border: none;
        }
        
        /* زر دائري */
        .btn-circle {
            width: 48px;
            height: 48px;
            padding: 0;
            border-radius: 50%;
        }
        
        .btn-circle-lg {
            width: 64px;
            height: 64px;
            font-size: 1.5rem;
        }
        
        /* =====================================================
           البطاقات - Cards
           ===================================================== */
        .card {
            background: var(--sarh-bg-card);
            border: none;
            border-radius: var(--sarh-radius);
            box-shadow: var(--sarh-shadow-sm);
            transition: var(--sarh-transition);
            overflow: hidden;
        }
        
        .card:hover {
            box-shadow: var(--sarh-shadow);
        }
        
        .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            padding: 16px 20px;
            font-weight: 700;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* بطاقة إحصائيات */
        .stat-card {
            padding: 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1));
            transform: rotate(45deg);
        }
        
        .stat-card .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 1.5rem;
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.875rem;
            color: var(--sarh-gray);
            margin-top: 4px;
        }
        
        /* =====================================================
           حقول الإدخال - Form Inputs (Floating Labels)
           ===================================================== */
        .form-floating {
            margin-bottom: 16px;
        }
        
        .form-floating > .form-control,
        .form-floating > .form-select {
            height: 56px;
            padding: 16px;
            border: 2px solid #e0e0e0;
            border-radius: var(--sarh-radius);
            font-size: 1rem;
            transition: var(--sarh-transition);
        }
        
        .form-floating > .form-control:focus,
        .form-floating > .form-select:focus {
            border-color: var(--sarh-primary);
            box-shadow: 0 0 0 4px rgba(26,35,126,0.1);
        }
        
        .form-floating > label {
            padding: 16px;
            color: var(--sarh-gray);
        }
        
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label,
        .form-floating > .form-select ~ label {
            color: var(--sarh-primary);
            font-weight: 600;
        }
        
        /* =====================================================
           الجداول - Tables
           ===================================================== */
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: var(--sarh-primary);
            color: white;
            font-weight: 600;
            padding: 14px 16px;
            white-space: nowrap;
            border: none;
        }
        
        .table tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .table tbody tr:hover {
            background: rgba(26,35,126,0.03);
        }
        
        /* =====================================================
           الشارات - Badges
           ===================================================== */
        .badge {
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .badge-status::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }
        
        /* =====================================================
           رسائل التنبيه - Alerts
           ===================================================== */
        .alert {
            border: none;
            border-radius: var(--sarh-radius);
            padding: 16px 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .alert-dismissible .btn-close {
            padding: 20px;
        }
        
        .alert i {
            font-size: 1.25rem;
            margin-top: 2px;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .alert-danger {
            background: #ffebee;
            color: #c62828;
        }
        
        .alert-warning {
            background: #fff3e0;
            color: #e65100;
        }
        
        .alert-info {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        /* =====================================================
           صفحة التحميل - Loading Page
           ===================================================== */
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--sarh-white);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.3s ease;
        }
        
        .page-loader.fade-out {
            opacity: 0;
            pointer-events: none;
        }
        
        .loader-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e0e0e0;
            border-top-color: var(--sarh-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* =====================================================
           تأثيرات متنوعة - Misc Effects
           ===================================================== */
        .fade-in {
            animation: fadeIn 0.3s ease forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .slide-up {
            animation: slideUp 0.3s ease forwards;
        }
        
        @keyframes slideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }
        
        /* =====================================================
           تجاوب الشاشات - Responsive
           ===================================================== */
        @media (max-width: 576px) {
            .container {
                padding-left: 12px;
                padding-right: 12px;
            }
            
            .card-body {
                padding: 16px;
            }
            
            .stat-card .stat-value {
                font-size: 1.5rem;
            }
            
            .btn {
                width: 100%;
            }
            
            .btn-inline {
                width: auto;
            }
        }
        
        /* إخفاء عناصر على الجوال */
        @media (max-width: 768px) {
            .hide-mobile {
                display: none !important;
            }
        }
        
        /* إخفاء عناصر على الشاشات الكبيرة */
        @media (min-width: 769px) {
            .hide-desktop {
                display: none !important;
            }
        }
        
        /* =====================================================
           الطباعة - Print Styles
           ===================================================== */
        @media print {
            .top-navbar,
            .bottom-nav,
            .no-print {
                display: none !important;
            }
            
            body {
                padding: 0 !important;
            }
        }
    </style>
    
    <?php if (isset($additionalStyles)): ?>
    <style><?= $additionalStyles ?></style>
    <?php endif; ?>
</head>
<body class="<?= e($bodyClass) ?><?= !$hideBottomNav ? ' has-bottom-nav' : '' ?>">

<?php if (!$hideNavbar): ?>
<!-- شريط التنقل العلوي -->
<nav class="top-navbar">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <!-- الشعار والاسم -->
            <a href="<?= url('/') ?>" class="navbar-brand">
                <span class="sarh-logo logo-sm logo-float-pulse">
                    <img src="<?= asset('images/logo.png') ?>" alt="<?= APP_NAME ?>" class="navbar-logo">
                </span>
                <span class="hide-mobile"><?= APP_NAME ?></span>
            </a>
            
            <!-- أيقونات التنقل -->
            <div class="d-flex align-items-center gap-2">
                <?php if (is_logged_in()): ?>
                <!-- البحث -->
                <a href="<?= url('search.php') ?>" class="nav-icon hide-mobile" title="بحث">
                    <i class="bi bi-search"></i>
                </a>
                
                <!-- الإشعارات -->
                <a href="<?= url('notifications.php') ?>" class="nav-icon" title="الإشعارات">
                    <i class="bi bi-bell"></i>
                    <?php 
                    // عدد الإشعارات غير المقروءة
                    $unreadCount = $_SESSION['unread_notifications'] ?? 0;
                    if ($unreadCount > 0): 
                    ?>
                    <span class="notification-badge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
                    <?php endif; ?>
                </a>
                
                <!-- الملف الشخصي -->
                <a href="<?= url('profile.php') ?>" class="nav-icon" title="الملف الشخصي">
                    <i class="bi bi-person-circle"></i>
                </a>
                <?php else: ?>
                <!-- تسجيل الدخول -->
                <a href="<?= url('login.php') ?>" class="btn btn-light btn-sm">
                    <i class="bi bi-box-arrow-in-left"></i>
                    دخول
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<!-- محتوى الصفحة الرئيسي -->
<main class="main-content">
    <?php if ($flashMessage): ?>
    <!-- رسالة التنبيه -->
    <div class="container mt-3">
        <div class="alert alert-<?= e($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
            <?php 
            $alertIcons = [
                'success' => 'bi-check-circle-fill',
                'danger' => 'bi-exclamation-triangle-fill',
                'warning' => 'bi-exclamation-circle-fill',
                'info' => 'bi-info-circle-fill'
            ];
            ?>
            <i class="bi <?= $alertIcons[$flashMessage['type']] ?? 'bi-info-circle-fill' ?>"></i>
            <div><?= e($flashMessage['message']) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    </div>
    <?php endif; ?>
