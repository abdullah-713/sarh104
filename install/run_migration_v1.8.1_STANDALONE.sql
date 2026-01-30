-- ╔══════════════════════════════════════════════════════════════════════════════╗
-- ║           Migration v1.8.1 - Add check_in_method Column                      ║
-- ║           تصحيح: إضافة عمود طريقة تسجيل الحضور                               ║
-- ╠══════════════════════════════════════════════════════════════════════════════╣
-- ║  تنفيذ مباشر - يمكن نسخه ولصقه في phpMyAdmin أو MySQL Client                 ║
-- ╚══════════════════════════════════════════════════════════════════════════════╝

-- التحقق من وجود العمود أولاً (اختياري - لإعادة التشغيل الآمن)
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'attendance' 
    AND COLUMN_NAME = 'check_in_method'
);

-- إضافة العمود فقط إذا لم يكن موجوداً
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `attendance` 
     ADD COLUMN `check_in_method` ENUM(''manual'', ''auto_gps'') NULL DEFAULT ''manual'' 
     AFTER `check_out_address`',
    'SELECT ''Column check_in_method already exists'' AS result'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- التحقق من النتيجة
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'attendance' 
AND COLUMN_NAME = 'check_in_method';

-- ✅ إذا رأيت النتيجة أعلاه = Migration نجح!
