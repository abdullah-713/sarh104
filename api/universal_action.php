<?php
/**
 * =====================================================
 * نظام صرح الإتقان للسيطرة الميدانية
 * Sarh Al-Itqan Field Operations System
 * =====================================================
 * API: إجراءات قاعدة البيانات الشاملة (God Mode)
 * API: Universal Database Actions
 * =====================================================
 * ⚠️ للمسؤولين فقط - مستوى 10
 * ⚠️ Super Admin Only - Level 10
 * =====================================================
 */

// تحميل الإعدادات
require_once dirname(__DIR__) . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

// ═══════════════════════════════════════════════════════════════════════════════
// التحقق من المصادقة
// ═══════════════════════════════════════════════════════════════════════════════
if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'غير مصرح - يرجى تسجيل الدخول'], 401);
}

// ═══════════════════════════════════════════════════════════════════════════════
// التحقق من صلاحية God Mode (مستوى 10 فقط)
// ═══════════════════════════════════════════════════════════════════════════════
if ($_SESSION['role_level'] < 10) {
    log_activity('unauthorized_api', 'security', 'محاولة وصول API غير مصرح', current_user_id(), 'user');
    json_response(['success' => false, 'message' => 'ليس لديك صلاحية'], 403);
}

// ═══════════════════════════════════════════════════════════════════════════════
// التحقق من CSRF
// ═══════════════════════════════════════════════════════════════════════════════
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrfToken)) {
    json_response(['success' => false, 'message' => 'خطأ في التحقق من الأمان'], 403);
}

// ═══════════════════════════════════════════════════════════════════════════════
// قراءة البيانات
// ═══════════════════════════════════════════════════════════════════════════════
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    json_response(['success' => false, 'message' => 'JSON غير صالح'], 400);
}

$action = $input['action'] ?? '';

// ═══════════════════════════════════════════════════════════════════════════════
// قائمة الجداول المحمية (لا يمكن حذفها أو تعديل هيكلها)
// ═══════════════════════════════════════════════════════════════════════════════
$protectedTables = ['users', 'roles', 'permissions', 'settings', 'branches'];
$readOnlyTables = ['activity_logs', 'login_logs', 'fraud_detection_logs', 'audit_logs'];
$blockedTables = ['system_config', 'migrations']; // لا يمكن الوصول إليها

// ═══════════════════════════════════════════════════════════════════════════════
// التحقق من الجداول المحظورة
// ═══════════════════════════════════════════════════════════════════════════════
function isBlockedTable(string $table, array $blockedTables): bool {
    return in_array($table, $blockedTables);
}

function isReadOnlyTable(string $table, array $readOnlyTables): bool {
    return in_array($table, $readOnlyTables);
}

// ═══════════════════════════════════════════════════════════════════════════════
// التحقق من صحة اسم الجدول
// ═══════════════════════════════════════════════════════════════════════════════
function validateTableName(string $table): bool {
    // السماح فقط بالأحرف والأرقام والشرطة السفلية
    return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table);
}

// ═══════════════════════════════════════════════════════════════════════════════
// التحقق من صحة اسم العمود
// ═══════════════════════════════════════════════════════════════════════════════
function validateColumnName(string $column): bool {
    return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column);
}

// ═══════════════════════════════════════════════════════════════════════════════
// التحقق من وجود الجدول
// ═══════════════════════════════════════════════════════════════════════════════
function tableExists(string $table): bool {
    try {
        $result = Database::fetchAll("SHOW TABLES LIKE :table", ['table' => $table]);
        return !empty($result);
    } catch (Exception $e) {
        return false;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// التحقق من وجود العمود في الجدول
// ═══════════════════════════════════════════════════════════════════════════════
function columnExists(string $table, string $column): bool {
    try {
        $columns = Database::fetchAll("DESCRIBE `{$table}`");
        foreach ($columns as $col) {
            if ($col['Field'] === $column) {
                return true;
            }
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// معالجة الإجراءات
// ═══════════════════════════════════════════════════════════════════════════════

switch ($action) {
    
    // ═══════════════════════════════════════════════════════════════════════════
    // تحديث قيمة خلية
    // ═══════════════════════════════════════════════════════════════════════════
    case 'update':
        $table = $input['table'] ?? '';
        $pkColumn = $input['pk_column'] ?? 'id';
        $pkValue = $input['pk_value'] ?? '';
        $column = $input['column'] ?? '';
        $value = $input['value'] ?? null;
        
        // التحقق من صحة المدخلات
        if (!validateTableName($table)) {
            json_response(['success' => false, 'message' => 'اسم جدول غير صالح'], 400);
        }
        
        if (!validateColumnName($pkColumn) || !validateColumnName($column)) {
            json_response(['success' => false, 'message' => 'اسم عمود غير صالح'], 400);
        }
        
        if (empty($pkValue)) {
            json_response(['success' => false, 'message' => 'معرف السجل مطلوب'], 400);
        }
        
        if (!tableExists($table)) {
            json_response(['success' => false, 'message' => 'الجدول غير موجود'], 404);
        }
        
        if (!columnExists($table, $column)) {
            json_response(['success' => false, 'message' => 'العمود غير موجود'], 404);
        }
        
        try {
            // بناء الاستعلام
            $sql = "UPDATE `{$table}` SET `{$column}` = :value WHERE `{$pkColumn}` = :pk_value";
            
            $stmt = Database::query($sql, [
                'value' => $value,
                'pk_value' => $pkValue
            ]);
            
            $affectedRows = $stmt->rowCount();
            
            if ($affectedRows > 0) {
                // تسجيل النشاط
                log_activity(
                    'db_update', 
                    'database', 
                    "تحديث: {$table}.{$column} = " . substr($value ?? 'NULL', 0, 100) . " WHERE {$pkColumn} = {$pkValue}",
                    current_user_id(),
                    'user'
                );
                
                json_response([
                    'success' => true, 
                    'message' => 'تم التحديث بنجاح',
                    'affected_rows' => $affectedRows
                ]);
            } else {
                json_response([
                    'success' => true, 
                    'message' => 'لم يتم تغيير أي بيانات',
                    'affected_rows' => 0
                ]);
            }
            
        } catch (PDOException $e) {
            error_log("Universal Update Error: " . $e->getMessage());
            json_response(['success' => false, 'message' => 'خطأ في التحديث: ' . $e->getMessage()], 500);
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════════════════
    // حذف سجل
    // ═══════════════════════════════════════════════════════════════════════════
    case 'delete':
        $table = $input['table'] ?? '';
        $pkColumn = $input['pk_column'] ?? 'id';
        $pkValue = $input['pk_value'] ?? '';
        
        // التحقق من صحة المدخلات
        if (!validateTableName($table)) {
            json_response(['success' => false, 'message' => 'اسم جدول غير صالح'], 400);
        }
        
        if (!validateColumnName($pkColumn)) {
            json_response(['success' => false, 'message' => 'اسم عمود غير صالح'], 400);
        }
        
        if (empty($pkValue)) {
            json_response(['success' => false, 'message' => 'معرف السجل مطلوب'], 400);
        }
        
        if (in_array($table, $protectedTables)) {
            json_response(['success' => false, 'message' => 'هذا الجدول محمي ولا يمكن الحذف منه'], 403);
        }
        
        if (!tableExists($table)) {
            json_response(['success' => false, 'message' => 'الجدول غير موجود'], 404);
        }
        
        // منع حذف المستخدم الحالي
        if ($table === 'users' && (int)$pkValue === current_user_id()) {
            json_response(['success' => false, 'message' => 'لا يمكنك حذف حسابك الحالي'], 403);
        }
        
        try {
            // جلب السجل قبل الحذف للتسجيل
            $sql = "SELECT * FROM `{$table}` WHERE `{$pkColumn}` = :pk_value LIMIT 1";
            $record = Database::fetchOne($sql, ['pk_value' => $pkValue]);
            
            if (!$record) {
                json_response(['success' => false, 'message' => 'السجل غير موجود'], 404);
            }
            
            // حذف السجل
            $sql = "DELETE FROM `{$table}` WHERE `{$pkColumn}` = :pk_value";
            $stmt = Database::query($sql, ['pk_value' => $pkValue]);
            
            $affectedRows = $stmt->rowCount();
            
            if ($affectedRows > 0) {
                // تسجيل النشاط
                log_activity(
                    'db_delete', 
                    'database', 
                    "حذف من {$table} WHERE {$pkColumn} = {$pkValue}",
                    current_user_id(),
                    'user'
                );
                
                json_response([
                    'success' => true, 
                    'message' => 'تم الحذف بنجاح',
                    'affected_rows' => $affectedRows
                ]);
            } else {
                json_response(['success' => false, 'message' => 'لم يتم العثور على السجل'], 404);
            }
            
        } catch (PDOException $e) {
            error_log("Universal Delete Error: " . $e->getMessage());
            
            // التحقق من خطأ Foreign Key
            if ($e->getCode() == 23000) {
                json_response([
                    'success' => false, 
                    'message' => 'لا يمكن الحذف - هناك سجلات مرتبطة بهذا السجل'
                ], 400);
            }
            
            json_response(['success' => false, 'message' => 'خطأ في الحذف: ' . $e->getMessage()], 500);
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════════════════
    // إضافة سجل جديد
    // ═══════════════════════════════════════════════════════════════════════════
    case 'insert':
        $table = $input['table'] ?? '';
        $data = $input['data'] ?? [];
        
        // التحقق من صحة المدخلات
        if (!validateTableName($table)) {
            json_response(['success' => false, 'message' => 'اسم جدول غير صالح'], 400);
        }
        
        if (empty($data) || !is_array($data)) {
            json_response(['success' => false, 'message' => 'البيانات مطلوبة'], 400);
        }
        
        if (!tableExists($table)) {
            json_response(['success' => false, 'message' => 'الجدول غير موجود'], 404);
        }
        
        // التحقق من صحة أسماء الأعمدة
        foreach (array_keys($data) as $column) {
            if (!validateColumnName($column)) {
                json_response(['success' => false, 'message' => "اسم عمود غير صالح: {$column}"], 400);
            }
            if (!columnExists($table, $column)) {
                json_response(['success' => false, 'message' => "العمود غير موجود: {$column}"], 404);
            }
        }
        
        // معالجة خاصة لجدول users (تشفير كلمة المرور)
        if ($table === 'users' && isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT, ['cost' => 12]);
        }
        
        try {
            $columns = array_keys($data);
            $placeholders = array_map(fn($col) => ":{$col}", $columns);
            
            $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) 
                    VALUES (" . implode(', ', $placeholders) . ")";
            
            Database::query($sql, $data);
            $lastId = db()->lastInsertId();
            
            // تسجيل النشاط
            log_activity(
                'db_insert', 
                'database', 
                "إضافة سجل جديد في {$table}, ID: {$lastId}",
                current_user_id(),
                'user'
            );
            
            json_response([
                'success' => true, 
                'message' => 'تم إضافة السجل بنجاح',
                'id' => $lastId
            ]);
            
        } catch (PDOException $e) {
            error_log("Universal Insert Error: " . $e->getMessage());
            
            // التحقق من خطأ Duplicate
            if ($e->getCode() == 23000) {
                json_response([
                    'success' => false, 
                    'message' => 'خطأ: قيمة مكررة أو مفتاح أجنبي غير صالح'
                ], 400);
            }
            
            json_response(['success' => false, 'message' => 'خطأ في الإضافة: ' . $e->getMessage()], 500);
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════════════════
    // جلب سجل واحد
    // ═══════════════════════════════════════════════════════════════════════════
    case 'fetch':
        $table = $input['table'] ?? '';
        $pkColumn = $input['pk_column'] ?? 'id';
        $pkValue = $input['pk_value'] ?? '';
        
        if (!validateTableName($table) || !validateColumnName($pkColumn)) {
            json_response(['success' => false, 'message' => 'مدخلات غير صالحة'], 400);
        }
        
        if (!tableExists($table)) {
            json_response(['success' => false, 'message' => 'الجدول غير موجود'], 404);
        }
        
        try {
            $sql = "SELECT * FROM `{$table}` WHERE `{$pkColumn}` = :pk_value LIMIT 1";
            $record = Database::fetchOne($sql, ['pk_value' => $pkValue]);
            
            if ($record) {
                json_response(['success' => true, 'data' => $record]);
            } else {
                json_response(['success' => false, 'message' => 'السجل غير موجود'], 404);
            }
            
        } catch (PDOException $e) {
            json_response(['success' => false, 'message' => 'خطأ في جلب البيانات'], 500);
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════════════════
    // جلب هيكل الجدول
    // ═══════════════════════════════════════════════════════════════════════════
    case 'describe':
        $table = $input['table'] ?? '';
        
        if (!validateTableName($table)) {
            json_response(['success' => false, 'message' => 'اسم جدول غير صالح'], 400);
        }
        
        if (!tableExists($table)) {
            json_response(['success' => false, 'message' => 'الجدول غير موجود'], 404);
        }
        
        try {
            $columns = Database::fetchAll("DESCRIBE `{$table}`");
            json_response(['success' => true, 'columns' => $columns]);
            
        } catch (PDOException $e) {
            json_response(['success' => false, 'message' => 'خطأ في جلب الهيكل'], 500);
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════════════════
    // تنفيذ استعلام SQL خام (خطير جداً - للتطوير فقط)
    // ═══════════════════════════════════════════════════════════════════════════
    case 'raw_query':
        // تعطيل هذه الميزة في الإنتاج
        if (ENVIRONMENT === 'production') {
            json_response(['success' => false, 'message' => 'غير متاح في بيئة الإنتاج'], 403);
        }
        
        $sql = $input['sql'] ?? '';
        
        if (empty($sql)) {
            json_response(['success' => false, 'message' => 'الاستعلام مطلوب'], 400);
        }
        
        // منع الاستعلامات الخطيرة
        $forbidden = ['DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'GRANT', 'REVOKE'];
        foreach ($forbidden as $keyword) {
            if (stripos($sql, $keyword) !== false) {
                json_response(['success' => false, 'message' => "الأمر {$keyword} غير مسموح"], 403);
            }
        }
        
        try {
            $stmt = db()->query($sql);
            
            // إذا كان SELECT، أرجع النتائج
            if (stripos(trim($sql), 'SELECT') === 0) {
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                json_response(['success' => true, 'data' => $results, 'count' => count($results)]);
            } else {
                json_response(['success' => true, 'affected_rows' => $stmt->rowCount()]);
            }
            
        } catch (PDOException $e) {
            json_response(['success' => false, 'message' => 'خطأ SQL: ' . $e->getMessage()], 500);
        }
        break;
    
    // ═══════════════════════════════════════════════════════════════════════════
    // إجراء غير معروف
    // ═══════════════════════════════════════════════════════════════════════════
    default:
        json_response(['success' => false, 'message' => 'إجراء غير صالح'], 400);
}
