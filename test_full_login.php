<?php
/**
 * اختبار عملية تسجيل الدخول الكاملة
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once INCLUDES_PATH . '/functions.php';

header('Content-Type: application/json; charset=utf-8');

$result = [];

try {
    // محاولة تسجيل الدخول
    $loginResult = login('admin', 'Admin@2026', false);
    
    $result['login_result'] = $loginResult;
    
    if ($loginResult['success']) {
        $result['session'] = [
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'role_slug' => $_SESSION['role_slug'] ?? null,
        ];
    }
    
} catch (PDOException $e) {
    $result['pdo_error'] = $e->getMessage();
    $result['pdo_code'] = $e->getCode();
    $result['pdo_trace'] = $e->getTraceAsString();
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
    $result['error_trace'] = $e->getTraceAsString();
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
