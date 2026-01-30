<?php
/**
 * =====================================================
 * نظام صرح الإتقان للسيطرة الميدانية
 * Sarh Al-Itqan Field Operations System
 * =====================================================
 * صفحة تسجيل الخروج
 * Logout Page
 * =====================================================
 */

// تحميل الإعدادات
require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

// التحقق من CSRF إذا تم إرسال POST (للأمان الإضافي)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        flash('danger', 'خطأ في التحقق من الأمان');
        redirect(url('index.php'));
    }
}

// تنفيذ تسجيل الخروج
logout();

// رسالة الوداع
flash('success', 'تم تسجيل الخروج بنجاح. نراك قريباً!');

// إعادة التوجيه لصفحة تسجيل الدخول
redirect(url('login.php'));
