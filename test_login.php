<?php
/**
 * اختبار تسجيل الدخول المباشر
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$result = [];

try {
    // جلب بيانات admin
    $admin = Database::fetchOne("SELECT id, username, password_hash FROM users WHERE username = 'admin'");
    
    $result['admin_exists'] = $admin ? true : false;
    $result['password_hash'] = $admin['password_hash'] ?? null;
    
    // اختبار كلمة المرور Admin@2026
    $testPassword = 'Admin@2026';
    $result['password_test'] = password_verify($testPassword, $admin['password_hash']);
    
    // إذا فشلت، نجرب إنشاء هاش جديد
    if (!$result['password_test']) {
        $newHash = password_hash($testPassword, PASSWORD_BCRYPT);
        $result['new_hash'] = $newHash;
        $result['message'] = '❌ كلمة المرور الحالية لا تطابق Admin@2026';
        $result['fix_query'] = "UPDATE users SET password_hash = '$newHash' WHERE username = 'admin';";
    } else {
        $result['message'] = '✅ كلمة المرور صحيحة!';
    }
    
    // اختبار كلمة مرور المطور
    $dev = Database::fetchOne("SELECT id, username, password_hash FROM users WHERE username = 'The_Architect'");
    $devTestPassword = 'MySecretPass2026';
    $result['dev_password_test'] = password_verify($devTestPassword, $dev['password_hash']);
    
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
