-- ═══════════════════════════════════════════════════════════════════════════════
-- SARH SYSTEM - GAMIFICATION & FEATURES MIGRATION
-- نظام التحفيز والميزات المتقدمة
-- ═══════════════════════════════════════════════════════════════════════════════

-- ═══════════════════════════════════════════════════════════════════════════════
-- 1. جدول الشارات (Badges)
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT '🏅',
    color VARCHAR(20) DEFAULT '#ffc107',
    points_reward INT DEFAULT 0,
    criteria_type ENUM('attendance_streak', 'points_threshold', 'early_arrival', 'overtime', 'perfect_month', 'custom') NOT NULL,
    criteria_value INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- شارات افتراضية
INSERT INTO badges (name, description, icon, points_reward, criteria_type, criteria_value) VALUES
('المبتدئ المثابر', 'أكملت أسبوعك الأول بدون غياب', '🌱', 50, 'attendance_streak', 7),
('النجم الصاعد', 'حضور مثالي لمدة شهر كامل', '⭐', 200, 'perfect_month', 1),
('البطل الخارق', 'حضور مثالي 3 أشهر متتالية', '🦸', 500, 'perfect_month', 3),
('طائر الفجر', 'وصول مبكر 20 مرة قبل الدوام بـ 15 دقيقة', '🌅', 150, 'early_arrival', 20),
('المحارب', 'تجاوز 1000 نقطة', '⚔️', 100, 'points_threshold', 1000),
('الأسطورة', 'تجاوز 5000 نقطة', '👑', 300, 'points_threshold', 5000),
('عامل الليل', '50 ساعة عمل إضافي', '🌙', 250, 'overtime', 50),
('الملتزم', 'لا تأخير لمدة شهر', '🎯', 150, 'custom', 0),
('روح الفريق', 'المشاركة في 10 تحديات جماعية', '🤝', 200, 'custom', 0),
('نجم الشهر', 'أفضل موظف في الشهر', '🏆', 500, 'custom', 0);

-- ═══════════════════════════════════════════════════════════════════════════════
-- 2. جدول شارات المستخدمين (User Badges)
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS user_badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_badge (user_id, badge_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 3. جدول التحديات (Challenges)
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS challenges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    challenge_type ENUM('individual', 'team', 'branch', 'company') DEFAULT 'individual',
    target_type ENUM('attendance_count', 'no_late', 'early_arrival', 'overtime', 'points', 'custom') NOT NULL,
    target_value INT DEFAULT 1,
    reward_points INT DEFAULT 100,
    reward_badge_id INT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    max_participants INT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reward_badge_id) REFERENCES badges(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تحديات افتراضية
INSERT INTO challenges (name, description, challenge_type, target_type, target_value, reward_points, start_date, end_date) VALUES
('تحدي الحضور المبكر', 'سجل حضورك قبل الدوام بـ 10 دقائق لمدة 5 أيام', 'individual', 'early_arrival', 5, 100, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
('أسبوع بلا تأخير', 'لا تتأخر أبداً هذا الأسبوع', 'individual', 'no_late', 5, 150, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
('البطل الإضافي', 'اعمل 10 ساعات إضافية هذا الشهر', 'individual', 'overtime', 600, 200, CURDATE(), LAST_DAY(CURDATE()));

-- ═══════════════════════════════════════════════════════════════════════════════
-- 4. جدول تحديات المستخدمين (User Challenges)
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS user_challenges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    challenge_id INT NOT NULL,
    progress INT DEFAULT 0,
    completed TINYINT(1) DEFAULT 0,
    completed_at TIMESTAMP NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_challenge (user_id, challenge_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 5. جدول المكافآت (Rewards Store)
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS rewards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT '🎁',
    category ENUM('leave', 'voucher', 'gift', 'privilege', 'recognition') DEFAULT 'gift',
    points_required INT NOT NULL,
    stock INT DEFAULT 99,
    image_url VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- مكافآت افتراضية
INSERT INTO rewards (name, description, icon, category, points_required, stock) VALUES
('نصف يوم إجازة', 'الحصول على نصف يوم إجازة مدفوعة', '🏖️', 'leave', 500, 99),
('يوم إجازة كامل', 'الحصول على يوم إجازة مدفوعة كامل', '🌴', 'leave', 1000, 99),
('قسيمة مطعم 50 ريال', 'قسيمة شراء من مطاعم مختارة', '🍽️', 'voucher', 300, 50),
('قسيمة مطعم 100 ريال', 'قسيمة شراء من مطاعم مختارة', '🍕', 'voucher', 550, 30),
('بطاقة شحن 50 ريال', 'بطاقة شحن رصيد للجوال', '📱', 'gift', 400, 100),
('سماعات بلوتوث', 'سماعات لاسلكية عالية الجودة', '🎧', 'gift', 2000, 10),
('ساعة ذكية', 'ساعة ذكية متعددة الاستخدامات', '⌚', 'gift', 5000, 5),
('العمل من المنزل (يوم)', 'يوم عمل من المنزل', '🏠', 'privilege', 800, 99),
('مكان VIP للسيارة', 'موقف سيارة مميز لمدة شهر', '🚗', 'privilege', 1500, 3),
('شهادة تقدير', 'شهادة تقدير موقعة من المدير العام', '🏆', 'recognition', 200, 99);

-- ═══════════════════════════════════════════════════════════════════════════════
-- 6. جدول طلبات استبدال النقاط (Reward Redemptions)
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS reward_redemptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    reward_id INT NOT NULL,
    points_spent INT NOT NULL,
    status ENUM('pending', 'approved', 'delivered', 'rejected') DEFAULT 'pending',
    notes TEXT NULL,
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reward_id) REFERENCES rewards(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 7. جدول استبيان المزاج اليومي (Mood Survey)
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS mood_surveys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    mood_score TINYINT NOT NULL CHECK (mood_score BETWEEN 1 AND 5),
    mood_emoji VARCHAR(10) DEFAULT '😐',
    energy_level TINYINT NULL CHECK (energy_level BETWEEN 1 AND 5),
    stress_level TINYINT NULL CHECK (stress_level BETWEEN 1 AND 5),
    notes TEXT NULL,
    survey_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_date (user_id, survey_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 8. جدول الإعلانات (Announcements)
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS announcements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'danger') DEFAULT 'info',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    target_type ENUM('all', 'branch', 'department', 'role', 'user') DEFAULT 'all',
    target_ids JSON NULL,
    is_pinned TINYINT(1) DEFAULT 0,
    expires_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 9. جدول قراءة الإعلانات (Announcement Reads)
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS announcement_reads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    announcement_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_announcement_user (announcement_id, user_id),
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 10. جدول سجل اكتشاف التلاعب (Fraud Detection Log)
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS fraud_detection_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    detection_type ENUM('mock_gps', 'vpn', 'emulator', 'root', 'time_manipulation', 'location_jump', 'suspicious_pattern') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    details JSON NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    device_info JSON NULL,
    action_taken ENUM('none', 'warning', 'blocked', 'reported') DEFAULT 'none',
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 11. جدول تفضيلات الوضع الليلي (Theme Preferences)
-- ═══════════════════════════════════════════════════════════════════════════════

ALTER TABLE users ADD COLUMN IF NOT EXISTS theme_mode ENUM('light', 'dark', 'auto') DEFAULT 'auto';
ALTER TABLE users ADD COLUMN IF NOT EXISTS dark_mode_start TIME DEFAULT '18:00:00';
ALTER TABLE users ADD COLUMN IF NOT EXISTS dark_mode_end TIME DEFAULT '06:00:00';

-- ═══════════════════════════════════════════════════════════════════════════════
-- 12. جدول الإشعارات المجدولة (Scheduled Notifications)
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS scheduled_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    notification_type ENUM('checkin_reminder', 'checkout_reminder', 'challenge_reminder', 'custom') NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT,
    scheduled_time TIME NOT NULL,
    days_of_week JSON DEFAULT '[0,1,2,3,4]',
    is_active TINYINT(1) DEFAULT 1,
    last_sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 13. جدول طلبات الإجازات الذكية (Smart Leave Requests)
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS leave_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    leave_type ENUM('annual', 'sick', 'personal', 'emergency', 'unpaid', 'work_from_home') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days DECIMAL(3,1) NOT NULL,
    reason TEXT,
    attachment_url VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 14. أعمدة إضافية لجدول الحضور
-- ═══════════════════════════════════════════════════════════════════════════════

ALTER TABLE attendance ADD COLUMN IF NOT EXISTS mood_score TINYINT NULL;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS device_fingerprint VARCHAR(64) NULL;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS fraud_flags JSON NULL;

-- ═══════════════════════════════════════════════════════════════════════════════
-- 15. فهارس للأداء
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE INDEX IF NOT EXISTS idx_challenges_dates ON challenges(start_date, end_date);
CREATE INDEX IF NOT EXISTS idx_user_challenges_progress ON user_challenges(user_id, completed);
CREATE INDEX IF NOT EXISTS idx_mood_surveys_date ON mood_surveys(survey_date);
CREATE INDEX IF NOT EXISTS idx_fraud_logs_user ON fraud_detection_logs(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_announcements_active ON announcements(expires_at, is_pinned);
