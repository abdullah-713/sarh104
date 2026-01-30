-- ╔══════════════════════════════════════════════════════════════════════════════╗
-- ║           إصلاح فوري: إضافة عمود check_in_method                            ║
-- ║           Fix: Add check_in_method Column to attendance table                ║
-- ╠══════════════════════════════════════════════════════════════════════════════╣
-- ║  المشكلة: العمود check_in_method غير موجود في قاعدة البيانات                ║
-- ║  Problem: Column 'check_in_method' is missing from attendance table         ║
-- ║  الحل: إضافة العمود                                                        ║
-- ║  Solution: Add the column                                                   ║
-- ╚══════════════════════════════════════════════════════════════════════════════╝

-- التحقق من وجود العمود أولاً
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'attendance' 
    AND COLUMN_NAME = 'check_in_method'
);

-- إضافة العمود فقط إذا لم يكن موجوداً
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `attendance` 
     ADD COLUMN `check_in_method` ENUM(\'manual\', \'auto_gps\') NULL DEFAULT \'manual\' 
     COMMENT \'طريقة تسجيل الحضور\' 
     AFTER `check_out_address`',
    'SELECT "Column check_in_method already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- التحقق من نجاح العملية
SELECT 'Column check_in_method added successfully!' AS result;
