-- ╔══════════════════════════════════════════════════════════════════════════════╗
-- ║           Migration v1.8.1 - Add check_in_method Column                      ║
-- ║           تصحيح: إضافة عمود طريقة تسجيل الحضور                               ║
-- ╠══════════════════════════════════════════════════════════════════════════════╣
-- ║  Issue: Column 'check_in_method' used in code but missing from schema        ║
-- ║  Fix: Add the column to attendance table                                     ║
-- ║  Priority: CRITICAL - Required for attendance INSERT to work                 ║
-- ╚══════════════════════════════════════════════════════════════════════════════╝

-- Add check_in_method column to attendance table
ALTER TABLE `attendance` 
ADD COLUMN `check_in_method` ENUM('manual', 'auto_gps') NULL DEFAULT 'manual' 
AFTER `check_out_address`;

-- Verify the column was added (run this separately to check)
-- DESCRIBE `attendance`;
