<?php
/**
 * API: تحديث صلاحيات المستخدم
 * Update User Permissions API
 * 
 * POST /api/admin/update_user_permissions.php
 * 
 * يتطلب: صلاحية manage_permissions أو السوبر أدمن
 */

require_once __DIR__ . '/../../config/app.php';

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'غير مصرح'], 401);
}

// التحقق من الصلاحية
if (!is_super_admin() && !has_permission('manage_permissions')) {
    json_response(['success' => false, 'message' => 'ليس لديك صلاحية الوصول'], 403);
}

// التحقق من CSRF
if (!verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    json_response(['success' => false, 'message' => 'انتهت صلاحية الجلسة'], 403);
}

// قراءة البيانات
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['user_id'])) {
    json_response(['success' => false, 'message' => 'بيانات غير صالحة'], 400);
}

$userId = (int) $input['user_id'];
$isSuperAdmin = (int) ($input['is_super_admin'] ?? 0);
$permissions = $input['permissions'] ?? [];
$visibleModules = $input['visible_modules'] ?? [];

// التحقق من وجود المستخدم
$user = Database::fetchOne("SELECT id, full_name FROM users WHERE id = ?", [$userId]);

if (!$user) {
    json_response(['success' => false, 'message' => 'المستخدم غير موجود'], 404);
}

// منع المستخدم من تعديل صلاحياته الخاصة (حماية)
if ($userId === (int) $_SESSION['user_id']) {
    json_response(['success' => false, 'message' => 'لا يمكنك تعديل صلاحياتك الخاصة'], 400);
}

// تنظيف وتحويل البيانات إلى JSON
$permissionsJson = json_encode(array_values(array_filter($permissions, 'is_string')));
$modulesJson = empty($visibleModules) ? null : json_encode(array_values(array_filter($visibleModules, 'is_string')));

try {
    // تحديث المستخدم
    $result = Database::query(
        "UPDATE users SET 
            is_super_admin = ?,
            permissions = ?,
            visible_modules = ?,
            managed_by = ?,
            updated_at = NOW()
        WHERE id = ?",
        [
            $isSuperAdmin,
            $permissionsJson,
            $modulesJson,
            $_SESSION['user_id'],
            $userId
        ]
    );

    // تسجيل النشاط
    log_activity(
        'permissions_updated',
        'admin',
        sprintf(
            "تم تحديث صلاحيات المستخدم: %s (ID: %d) - سوبر أدمن: %s, صلاحيات: %d, وحدات: %d",
            $user['full_name'],
            $userId,
            $isSuperAdmin ? 'نعم' : 'لا',
            count($permissions),
            count($visibleModules)
        ),
        $userId,
        'user'
    );

    json_response([
        'success' => true,
        'message' => 'تم تحديث صلاحيات المستخدم بنجاح'
    ]);

} catch (Exception $e) {
    error_log("Error updating user permissions: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'حدث خطأ أثناء الحفظ'], 500);
}
