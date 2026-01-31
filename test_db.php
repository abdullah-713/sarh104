<?php
/**
 * ملف اختبار الاتصال بقاعدة البيانات
 * Database Connection Test File
 */

// تحميل الإعدادات
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$result = [
    'success' => false,
    'message' => '',
    'data' => []
];

try {
    // اختبار الاتصال
    $pdo = Database::getInstance();
    $result['message'] = '✅ اتصال قاعدة البيانات ناجح';
    
    // فحص جدول users
    $users = Database::fetchAll("SELECT id, username, full_name, role_id, branch_id FROM users LIMIT 10");
    $result['data']['users_count'] = count($users);
    $result['data']['users'] = $users;
    
    // فحص جدول roles
    $roles = Database::fetchAll("SELECT id, name, slug FROM roles");
    $result['data']['roles_count'] = count($roles);
    $result['data']['roles'] = $roles;
    
    // فحص جدول branches
    $branches = Database::fetchAll("SELECT id, name, code FROM branches");
    $result['data']['branches_count'] = count($branches);
    $result['data']['branches'] = $branches;
    
    // فحص المستخدم admin
    $admin = Database::fetchOne("SELECT id, username, email, role_id, branch_id FROM users WHERE username = 'admin'");
    $result['data']['admin_user'] = $admin;
    
    // فحص وجود الفرع المرتبط بـ admin
    if ($admin && $admin['branch_id']) {
        $adminBranch = Database::fetchOne("SELECT * FROM branches WHERE id = :id", ['id' => $admin['branch_id']]);
        $result['data']['admin_branch'] = $adminBranch;
        
        if (!$adminBranch) {
            $result['message'] .= ' ⚠️ تحذير: الفرع المرتبط بـ admin غير موجود!';
        }
    }
    
    $result['success'] = true;
    
} catch (PDOException $e) {
    $result['message'] = '❌ خطأ في الاتصال: ' . $e->getMessage();
} catch (Exception $e) {
    $result['message'] = '❌ خطأ عام: ' . $e->getMessage();
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
