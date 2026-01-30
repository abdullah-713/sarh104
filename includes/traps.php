<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - COGNITIVE TRAP ENGINE                                ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

defined('APP_ROOT') || define('APP_ROOT', dirname(__DIR__));

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * ABSTRACT BASE TRAP CLASS
 * ═══════════════════════════════════════════════════════════════════════════════
 */
abstract class BaseTrap {
    protected string $trapType;
    protected string $trapName;
    protected int $userId;
    protected array $config;
    protected array $userData;
    
    public function __construct(int $userId, array $config = []) {
        $this->userId = $userId;
        $this->config = $config;
        $this->loadUserData();
    }
    
    protected function loadUserData(): void {
        $this->userData = Database::fetchOne(
            "SELECT u.*, r.role_level FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             WHERE u.id = ?",
            [$this->userId]
        ) ?: [];
    }
    
    abstract public function render(): array;
    abstract public function process(string $action): array;
    
    public function getTrapType(): string {
        return $this->trapType;
    }
    
    public function canTrigger(): bool {
        $config = $this->getConfig();
        if (!$config || !$config['is_active']) return false;
        
        $roleLevel = $this->userData['role_level'] ?? 1;
        if ($roleLevel < $config['min_role_level'] || $roleLevel > $config['max_role_level']) {
            return false;
        }
        
        if (!$this->checkCooldown()) return false;
        
        return (mt_rand(1, 100) / 100) <= $config['trigger_chance'];
    }
    
    protected function checkCooldown(): bool {
        $cooldown = Database::fetchOne(
            "SELECT cooldown_until FROM user_trap_cooldowns 
             WHERE user_id = ? AND trap_type = ?",
            [$this->userId, $this->trapType]
        );
        
        if ($cooldown && strtotime($cooldown['cooldown_until']) > time()) {
            return false;
        }
        return true;
    }
    
    protected function setCooldown(): void {
        $config = $this->getConfig();
        $cooldownMinutes = $config['cooldown_minutes'] ?? 10080;
        $cooldownUntil = date('Y-m-d H:i:s', time() + ($cooldownMinutes * 60));
        
        Database::query(
            "INSERT INTO user_trap_cooldowns (user_id, trap_type, last_shown_at, cooldown_until)
             VALUES (?, ?, NOW(), ?)
             ON DUPLICATE KEY UPDATE last_shown_at = NOW(), cooldown_until = ?",
            [$this->userId, $this->trapType, $cooldownUntil, $cooldownUntil]
        );
    }
    
    protected function getConfig(): ?array {
        return Database::fetchOne(
            "SELECT * FROM trap_configurations WHERE trap_type = ?",
            [$this->trapType]
        );
    }
    
    protected function generateTrapId(): string {
        return bin2hex(random_bytes(16));
    }
    
    protected function logInteraction(string $action, string $category, array $deltas, int $responseTime = 0, array $context = []): void {
        $config = $this->getConfig();
        
        Database::insert('trap_logs', [
            'user_id' => $this->userId,
            'trap_type' => $this->trapType,
            'trap_config_id' => $config['id'] ?? null,
            'action_taken' => $action,
            'action_category' => $category,
            'score_change' => array_sum($deltas),
            'trust_delta' => $deltas['trust'] ?? 0,
            'curiosity_delta' => $deltas['curiosity'] ?? 0,
            'integrity_delta' => $deltas['integrity'] ?? 0,
            'response_time_ms' => $responseTime,
            'context_data' => json_encode($context, JSON_UNESCAPED_UNICODE),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        Database::query("CALL sp_update_psychological_profile(?)", [$this->userId]);
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * TRAP 1: DATA LEAK (Salary Glitch)
 * ═══════════════════════════════════════════════════════════════════════════════
 */
class TrapDataLeak extends BaseTrap {
    protected string $trapType = 'data_leak';
    protected string $trapName = 'تسريب بيانات الراتب';
    
    public function render(): array {
        $colleague = Database::fetchOne(
            "SELECT id, full_name, emp_code FROM users 
             WHERE id != ? AND is_active = 1 
             ORDER BY RAND() LIMIT 1",
            [$this->userId]
        );
        
        if (!$colleague) return ['error' => 'no_data'];
        
        $this->setCooldown();
        
        return [
            'trap_id' => $this->generateTrapId(),
            'trap_type' => $this->trapType,
            'display' => [
                'type' => 'modal',
                'theme' => 'error',
                'title' => 'خطأ في النظام',
                'message' => 'تم تحميل بيانات موظف آخر بالخطأ. يرجى إغلاق هذه النافذة فوراً.',
                'data' => [
                    'name' => $colleague['full_name'],
                    'code' => $colleague['emp_code'],
                    'salary' => number_format(rand(8000, 25000)) . ' ر.س',
                    'bonus' => number_format(rand(500, 5000)) . ' ر.س',
                    'rating' => ['ممتاز', 'جيد جداً', 'جيد'][rand(0, 2)],
                    'raise_date' => date('Y-m', strtotime('-' . rand(1, 18) . ' months'))
                ],
                'actions' => [
                    ['id' => 'view_more', 'label' => 'عرض المزيد', 'style' => 'danger'],
                    ['id' => 'close', 'label' => 'إغلاق', 'style' => 'success'],
                    ['id' => 'report', 'label' => 'إبلاغ عن خطأ', 'style' => 'warning']
                ]
            ]
        ];
    }
    
    public function process(string $action): array {
        $results = [
            'view_more' => ['category' => 'negative', 'deltas' => ['trust' => -5, 'curiosity' => 10, 'integrity' => -5], 'message' => 'تم تحميل البيانات'],
            'close' => ['category' => 'neutral', 'deltas' => ['trust' => 2, 'curiosity' => 0, 'integrity' => 2], 'message' => 'تم إغلاق النافذة'],
            'report' => ['category' => 'positive', 'deltas' => ['trust' => 5, 'curiosity' => 0, 'integrity' => 5], 'message' => 'شكراً لإبلاغك. تم إرسال البلاغ.']
        ];
        
        $result = $results[$action] ?? $results['close'];
        $this->logInteraction($action, $result['category'], $result['deltas']);
        
        return [
            'success' => true,
            'response' => ['type' => 'toast', 'message' => $result['message'], 'style' => $result['category'] === 'positive' ? 'success' : 'info']
        ];
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * TRAP 2: GPS DEBUG MODE
 * ═══════════════════════════════════════════════════════════════════════════════
 */
class TrapGPSDebug extends BaseTrap {
    protected string $trapType = 'gps_debug';
    protected string $trapName = 'وضع تصحيح GPS';
    
    public function render(): array {
        $this->setCooldown();
        
        return [
            'trap_id' => $this->generateTrapId(),
            'trap_type' => $this->trapType,
            'display' => [
                'type' => 'panel',
                'theme' => 'debug',
                'title' => 'وضع تصحيح GPS',
                'icon' => 'bi-bug-fill',
                'message' => 'تم اكتشاف مشكلة في تحديد الموقع. اختر إحدى الخيارات:',
                'actions' => [
                    ['id' => 'manual_entry', 'label' => 'إدخال الموقع يدوياً', 'icon' => 'bi-pencil-square', 'badge' => '⚡ سريع'],
                    ['id' => 'last_known', 'label' => 'استخدام آخر موقع معروف', 'icon' => 'bi-clock-history'],
                    ['id' => 'wait_fix', 'label' => 'انتظار إصلاح GPS', 'icon' => 'bi-hourglass-split'],
                    ['id' => 'report_issue', 'label' => 'الإبلاغ عن مشكلة', 'icon' => 'bi-flag']
                ]
            ]
        ];
    }
    
    public function process(string $action): array {
        $results = [
            'manual_entry' => ['category' => 'critical', 'deltas' => ['trust' => -20, 'curiosity' => 5, 'integrity' => -15], 'message' => 'هذه الميزة غير متاحة. تم تسجيل الطلب.', 'style' => 'error'],
            'last_known' => ['category' => 'negative', 'deltas' => ['trust' => -10, 'curiosity' => 3, 'integrity' => -8], 'message' => 'لا يمكن استخدام موقع قديم.', 'style' => 'warning'],
            'wait_fix' => ['category' => 'neutral', 'deltas' => ['trust' => 3, 'curiosity' => 0, 'integrity' => 2], 'message' => 'جاري محاولة إعادة الاتصال...', 'style' => 'info'],
            'report_issue' => ['category' => 'positive', 'deltas' => ['trust' => 5, 'curiosity' => 0, 'integrity' => 5], 'message' => 'تم إرسال البلاغ للدعم الفني. شكراً لك.', 'style' => 'success']
        ];
        
        $result = $results[$action] ?? $results['wait_fix'];
        $this->logInteraction($action, $result['category'], $result['deltas']);
        
        return [
            'success' => true,
            'response' => ['type' => 'modal', 'message' => $result['message'], 'style' => $result['style']]
        ];
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * TRAP 3: ADMIN OVERRIDE (Ghost Button)
 * ═══════════════════════════════════════════════════════════════════════════════
 */
class TrapAdminOverride extends BaseTrap {
    protected string $trapType = 'admin_override';
    protected string $trapName = 'زر المدير الشبح';
    
    public function render(): array {
        $this->setCooldown();
        $settings = json_decode($this->getConfig()['settings'] ?? '{}', true);
        
        return [
            'trap_id' => $this->generateTrapId(),
            'trap_type' => $this->trapType,
            'display' => [
                'type' => 'floating_button',
                'theme' => 'admin',
                'icon' => 'bi-lightning-charge-fill',
                'text' => 'تجاوز التحقق',
                'subtext' => '(صلاحية مدير)',
                'appear_delay_ms' => rand(2000, 4000),
                'disappear_delay_ms' => $settings['appear_duration_ms'] ?? 8000,
                'position' => 'above_action_button'
            ]
        ];
    }
    
    public function process(string $action): array {
        $results = [
            'clicked' => ['category' => 'critical', 'deltas' => ['trust' => -25, 'curiosity' => 5, 'integrity' => -20], 'message' => 'صلاحيات غير كافية. تم تسجيل المحاولة.'],
            'ignored' => ['category' => 'neutral', 'deltas' => ['trust' => 0, 'curiosity' => 0, 'integrity' => 0], 'message' => '']
        ];
        
        $result = $results[$action] ?? $results['ignored'];
        
        if ($action !== 'ignored') {
            $this->logInteraction($action, $result['category'], $result['deltas']);
        }
        
        return [
            'success' => true,
            'response' => $action === 'clicked' 
                ? ['type' => 'modal', 'title' => 'رفض الوصول', 'message' => $result['message'], 'style' => 'error', 'delay' => 2500]
                : ['type' => 'none']
        ];
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * TRAP 4: CONFIDENTIAL BAIT
 * ═══════════════════════════════════════════════════════════════════════════════
 */
class TrapConfidentialBait extends BaseTrap {
    protected string $trapType = 'confidential_bait';
    protected string $trapName = 'طُعم الإشعار السري';
    
    public function render(): array {
        $colleague = Database::fetchOne(
            "SELECT full_name FROM users WHERE id != ? AND is_active = 1 ORDER BY RAND() LIMIT 1",
            [$this->userId]
        );
        
        if (!$colleague) return ['error' => 'no_data'];
        
        $this->setCooldown();
        $actions = ['إجراء تأديبي', 'تحقيق داخلي', 'مراجعة أداء سرية', 'شكوى مقدمة'];
        
        return [
            'trap_id' => $this->generateTrapId(),
            'trap_type' => $this->trapType,
            'display' => [
                'type' => 'toast',
                'theme' => 'confidential',
                'icon' => 'bi-shield-lock-fill',
                'title' => '🔒 إشعار سري',
                'message' => 'تم تسجيل ' . $actions[array_rand($actions)] . ' بخصوص: ' . $colleague['full_name'],
                'actions' => [
                    ['id' => 'view_details', 'label' => 'عرض التفاصيل'],
                    ['id' => 'dismiss', 'label' => 'ليس من شأني']
                ],
                'auto_dismiss_ms' => 12000
            ]
        ];
    }
    
    public function process(string $action): array {
        $results = [
            'view_details' => ['category' => 'negative', 'deltas' => ['trust' => -8, 'curiosity' => 15, 'integrity' => -5], 'message' => 'عذراً، هذا الإشعار لم يعد متاحاً.', 'style' => 'warning'],
            'dismiss' => ['category' => 'positive', 'deltas' => ['trust' => 5, 'curiosity' => 0, 'integrity' => 5], 'message' => '', 'style' => 'none'],
            'timeout' => ['category' => 'neutral', 'deltas' => ['trust' => 0, 'curiosity' => 0, 'integrity' => 0], 'message' => '', 'style' => 'none']
        ];
        
        $result = $results[$action] ?? $results['timeout'];
        $this->logInteraction($action, $result['category'], $result['deltas']);
        
        return [
            'success' => true,
            'response' => $result['style'] !== 'none' 
                ? ['type' => 'modal', 'message' => $result['message'], 'style' => $result['style']]
                : ['type' => 'none']
        ];
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * TRAP 5: RECRUITMENT TEST
 * ═══════════════════════════════════════════════════════════════════════════════
 */
class TrapRecruitment extends BaseTrap {
    protected string $trapType = 'recruitment';
    protected string $trapName = 'اختبار التجنيد';
    
    public function render(): array {
        $this->setCooldown();
        $settings = json_decode($this->getConfig()['settings'] ?? '{}', true);
        $reward = $settings['reward_amount'] ?? 500;
        $firstName = explode(' ', $this->userData['full_name'] ?? 'الموظف')[0];
        
        $senders = [
            ['name' => 'قسم الجودة والتطوير', 'subject' => 'برنامج التقييم السري'],
            ['name' => 'إدارة الموارد البشرية', 'subject' => 'مهمة تقييم خاصة'],
            ['name' => 'مكتب المدير العام', 'subject' => 'استبيان سري للموظفين']
        ];
        $sender = $senders[array_rand($senders)];
        
        return [
            'trap_id' => $this->generateTrapId(),
            'trap_type' => $this->trapType,
            'display' => [
                'type' => 'modal',
                'theme' => 'official',
                'icon' => 'bi-envelope-paper-fill',
                'badge' => 'سري',
                'title' => 'رسالة داخلية',
                'sender' => $sender['name'],
                'subject' => $sender['subject'],
                'body' => "عزيزي/عزيزتي {$firstName}،\n\nتم اختيارك للمشاركة في برنامج تقييم سري للموظفين. مطلوب منك مراقبة أحد زملائك وتقديم تقارير أسبوعية عن سلوكه وأدائه.\n\nهذا البرنامج سري تماماً ولن يعلم به أحد.\n\nالمكافأة: {$reward} ر.س شهرياً",
                'footer' => 'هذه الرسالة سرية. يرجى عدم مشاركتها.',
                'actions' => [
                    ['id' => 'accept', 'label' => 'أوافق على المشاركة', 'icon' => 'bi-check-circle', 'style' => 'success'],
                    ['id' => 'uncomfortable', 'label' => 'لا أشعر بالراحة', 'icon' => 'bi-emoji-frown', 'style' => 'secondary'],
                    ['id' => 'illegal', 'label' => 'هذا غير قانوني', 'icon' => 'bi-x-circle', 'style' => 'warning'],
                    ['id' => 'report', 'label' => 'الإبلاغ عن هذه الرسالة', 'icon' => 'bi-flag', 'style' => 'danger']
                ]
            ]
        ];
    }
    
    public function process(string $action): array {
        $results = [
            'accept' => ['category' => 'critical', 'deltas' => ['trust' => -35, 'curiosity' => 5, 'integrity' => -30], 'message' => 'شكراً لاهتمامك. سيتم التواصل معك قريباً.', 'style' => 'info'],
            'uncomfortable' => ['category' => 'neutral', 'deltas' => ['trust' => 3, 'curiosity' => 0, 'integrity' => 2], 'message' => 'تم حفظ ردك. شكراً لصراحتك.', 'style' => 'info'],
            'illegal' => ['category' => 'positive', 'deltas' => ['trust' => 10, 'curiosity' => 0, 'integrity' => 10], 'message' => 'نقدر موقفك الأخلاقي. شكراً لتمسكك بالقيم المهنية.', 'style' => 'success'],
            'report' => ['category' => 'positive', 'deltas' => ['trust' => 20, 'curiosity' => 0, 'integrity' => 15], 'message' => 'شكراً لحرصك على النزاهة. تم إحالة البلاغ للجهة المختصة.', 'style' => 'success']
        ];
        
        $result = $results[$action] ?? $results['uncomfortable'];
        $this->logInteraction($action, $result['category'], $result['deltas']);
        
        return [
            'success' => true,
            'response' => ['type' => 'modal', 'title' => 'رد', 'message' => $result['message'], 'style' => $result['style']]
        ];
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * TRAP FACTORY
 * ═══════════════════════════════════════════════════════════════════════════════
 */
class TrapFactory {
    private static array $trapClasses = [
        'data_leak' => TrapDataLeak::class,
        'gps_debug' => TrapGPSDebug::class,
        'admin_override' => TrapAdminOverride::class,
        'confidential_bait' => TrapConfidentialBait::class,
        'recruitment' => TrapRecruitment::class
    ];
    
    public static function create(string $trapType, int $userId): ?BaseTrap {
        $class = self::$trapClasses[$trapType] ?? null;
        return $class ? new $class($userId) : null;
    }
    
    public static function getRandomTrap(int $userId): ?BaseTrap {
        $types = array_keys(self::$trapClasses);
        shuffle($types);
        
        foreach ($types as $type) {
            $trap = self::create($type, $userId);
            if ($trap && $trap->canTrigger()) {
                return $trap;
            }
        }
        
        return null;
    }
    
    public static function getAllTypes(): array {
        return array_keys(self::$trapClasses);
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * PROFILE MANAGER
 * ═══════════════════════════════════════════════════════════════════════════════
 */
class ProfileManager {
    public static function getProfile(int $userId): ?array {
        return Database::fetchOne(
            "SELECT * FROM v_psychological_profiles WHERE user_id = ?",
            [$userId]
        );
    }
    
    public static function getAllProfiles(): array {
        return Database::fetchAll(
            "SELECT * FROM v_psychological_profiles ORDER BY trust_score ASC, risk_level DESC"
        );
    }
    
    public static function getProfileLogs(int $userId, int $limit = 50): array {
        return Database::fetchAll(
            "SELECT * FROM trap_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }
    
    public static function getStatistics(): array {
        return Database::fetchAll("SELECT * FROM v_trap_statistics");
    }
}
