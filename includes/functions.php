<?php
/**
 * =====================================================
 * نظام صرح الإتقان للسيطرة الميدانية
 * Sarh Al-Itqan Field Operations System
 * =====================================================
 * ملف الدوال الرئيسية - متوافق مع production_lean_v1.sql
 * Core Functions File - Compatible with production_lean_v1.sql
 * =====================================================
 */

// منع الوصول المباشر للملف
if (!defined('SARH_SYSTEM')) {
    die('الوصول المباشر غير مسموح');
}

// =====================================================
// دوال المصادقة - Authentication Functions
// =====================================================

/**
 * تسجيل دخول المستخدم
 * User Login Function
 * 
 * @param string $identifier اسم المستخدم أو رقم الموظف أو البريد الإلكتروني
 * @param string $password كلمة المرور
 * @param bool $remember تذكرني
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function login(string $identifier, string $password, bool $remember = false): array {
    try {
        // البحث عن المستخدم مع بيانات الدور والفرع
        // Schema: master.sql
        $sql = "SELECT 
                    u.id,
                    u.emp_code,
                    u.username,
                    u.email,
                    u.full_name,
                    u.full_name AS full_name_ar,
                    u.full_name AS full_name_en,
                    u.phone,
                    u.avatar AS profile_image,
                    u.job_title AS job_title_ar,
                    u.password_hash AS password,
                    u.remember_token,
                    u.role_id,
                    u.branch_id,
                    u.department,
                    u.current_points,
                    1000 AS monthly_starting_points,
                    u.custom_schedule,
                    u.is_active,
                    0 AS has_immunity,
                    NULL AS immunity_until,
                    u.login_attempts,
                    u.locked_until,
                    u.preferences,
                    r.slug AS role_slug,
                    r.name AS role_name_ar,
                    r.name AS role_name_en,
                    r.role_level,
                    r.permissions AS role_permissions,
                    0 AS role_has_immunity,
                    0 AS can_access_all_branches,
                    r.color AS role_color,
                    r.icon AS role_icon,
                    b.code AS branch_code,
                    b.name AS branch_name_ar,
                    b.name AS branch_name_en,
                    b.city AS branch_city,
                    b.latitude AS branch_latitude,
                    b.longitude AS branch_longitude,
                    b.geofence_radius AS branch_geofence_radius,
                    b.settings AS branch_settings,
                    0 AS branch_is_locked
                FROM users u
                INNER JOIN roles r ON u.role_id = r.id
                LEFT JOIN branches b ON u.branch_id = b.id
                WHERE (u.username = :username 
                       OR u.emp_code = :emp_code 
                       OR u.email = :email)
                AND u.is_active = 1
                LIMIT 1";
        
        $user = Database::fetchOne($sql, [
            'username' => $identifier,
            'emp_code' => $identifier,
            'email' => $identifier
        ]);
        
        // المستخدم غير موجود
        if (!$user) {
            log_activity('login_failed', 'auth', "محاولة دخول فاشلة - المستخدم غير موجود: {$identifier}");
            return [
                'success' => false,
                'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة',
                'user' => null
            ];
        }
        
        // التحقق من قفل الحساب
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $remainingMinutes = ceil((strtotime($user['locked_until']) - time()) / 60);
            log_activity('login_blocked', 'auth', "محاولة دخول لحساب مقفل: {$identifier}", $user['id'], 'user');
            return [
                'success' => false,
                'message' => "الحساب مقفل مؤقتاً. يرجى المحاولة بعد {$remainingMinutes} دقيقة.",
                'user' => null
            ];
        }
        
        // التحقق من قفل الفرع
        if ($user['branch_is_locked'] && $user['role_level'] < 5) {
            return [
                'success' => false,
                'message' => 'الفرع مغلق حالياً. يرجى التواصل مع الإدارة.',
                'user' => null
            ];
        }
        
        // التحقق من كلمة المرور
        if (!password_verify($password, $user['password'])) {
            // زيادة عداد محاولات الدخول الفاشلة
            $newAttempts = $user['login_attempts'] + 1;
            $maxAttempts = (int) get_setting('max_login_attempts', 5);
            $lockoutMinutes = (int) get_setting('lockout_duration_minutes', 15);
            
            $updateData = ['login_attempts' => $newAttempts];
            
            // قفل الحساب إذا تجاوز الحد المسموح
            if ($newAttempts >= $maxAttempts) {
                $updateData['locked_until'] = date('Y-m-d H:i:s', time() + ($lockoutMinutes * 60));
                $message = "تم قفل الحساب بسبب كثرة محاولات الدخول الفاشلة. يرجى المحاولة بعد {$lockoutMinutes} دقيقة.";
                log_activity('account_locked', 'auth', "تم قفل الحساب: {$identifier}", $user['id'], 'user');
            } else {
                $remaining = $maxAttempts - $newAttempts;
                $message = "اسم المستخدم أو كلمة المرور غير صحيحة. ({$remaining} محاولات متبقية)";
            }
            
            Database::update('users', $updateData, 'id = :id', ['id' => $user['id']]);
            log_activity('login_failed', 'auth', "كلمة مرور خاطئة للمستخدم: {$identifier}", $user['id'], 'user');
            
            return [
                'success' => false,
                'message' => $message,
                'user' => null
            ];
        }
        
        // نجاح تسجيل الدخول - إنشاء الجلسة
        create_user_session($user);
        
        // إنشاء سجل في جدول user_sessions
        $sessionToken = create_persistent_session($user['id'], $remember);
        
        // معالجة "تذكرني"
        if ($remember) {
            set_remember_cookie($user['id'], $sessionToken);
        }
        
        // تحديث بيانات آخر تسجيل دخول
        Database::update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_activity_at' => date('Y-m-d H:i:s'),
            'login_attempts' => 0,
            'locked_until' => null,
            'is_online' => 1
        ], 'id = :id', ['id' => $user['id']]);
        
        // تسجيل النشاط
        log_activity('login_success', 'auth', 'تسجيل دخول ناجح', $user['id'], 'user');
        
        return [
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'user' => $user
        ];
        
    } catch (PDOException $e) {
        error_log("Login Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'حدث خطأ أثناء تسجيل الدخول. يرجى المحاولة لاحقاً.',
            'user' => null
        ];
    }
}

/**
 * إنشاء جلسة المستخدم في $_SESSION
 * Create User Session in $_SESSION
 * 
 * @param array $user بيانات المستخدم من قاعدة البيانات
 */
function create_user_session(array $user): void {
    // تجديد معرف الجلسة لمنع Session Fixation
    session_regenerate_id(true);
    
    // ═══════════════════════════════════════════════════════════════════════
    // بيانات المستخدم الأساسية
    // ═══════════════════════════════════════════════════════════════════════
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['emp_code'] = $user['emp_code'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name_ar'] ?: $user['full_name_en'];
    $_SESSION['full_name_ar'] = $user['full_name_ar'];
    $_SESSION['full_name_en'] = $user['full_name_en'];
    $_SESSION['phone'] = $user['phone'];
    $_SESSION['profile_image'] = $user['profile_image'];
    $_SESSION['job_title'] = $user['job_title_ar'];
    
    // ═══════════════════════════════════════════════════════════════════════
    // بيانات الدور والصلاحيات
    // ═══════════════════════════════════════════════════════════════════════
    $_SESSION['role_id'] = (int) $user['role_id'];
    $_SESSION['role_slug'] = $user['role_slug'];
    $_SESSION['role_name'] = $user['role_name_ar'];
    $_SESSION['role_name_ar'] = $user['role_name_ar'];
    $_SESSION['role_name_en'] = $user['role_name_en'];
    $_SESSION['role_level'] = (int) $user['role_level'];
    $_SESSION['role_color'] = $user['role_color'];
    $_SESSION['role_icon'] = $user['role_icon'];
    $_SESSION['can_access_all_branches'] = (bool) $user['can_access_all_branches'];
    
    // الصلاحيات من JSON column
    $_SESSION['permissions'] = parse_permissions_json($user['role_permissions']);
    
    // الحصانة (من المستخدم أو الدور)
    $hasImmunity = (bool) $user['has_immunity'] || (bool) $user['role_has_immunity'];
    if ($user['immunity_until'] && strtotime($user['immunity_until']) < time()) {
        $hasImmunity = false; // انتهت صلاحية الحصانة
    }
    $_SESSION['has_immunity'] = $hasImmunity;
    
    // ═══════════════════════════════════════════════════════════════════════
    // بيانات الفرع
    // ═══════════════════════════════════════════════════════════════════════
    $_SESSION['branch_id'] = (int) $user['branch_id'];
    $_SESSION['branch_code'] = $user['branch_code'];
    $_SESSION['branch_name'] = $user['branch_name_ar'] ?: $user['branch_name_en'];
    $_SESSION['branch_city'] = $user['branch_city'];
    $_SESSION['branch_latitude'] = $user['branch_latitude'] ? (float) $user['branch_latitude'] : null;
    $_SESSION['branch_longitude'] = $user['branch_longitude'] ? (float) $user['branch_longitude'] : null;
    $_SESSION['branch_geofence_radius'] = (int) $user['branch_geofence_radius'];
    
    // إعدادات الفرع من JSON
    $_SESSION['branch_settings'] = parse_json_column($user['branch_settings']);
    
    // ═══════════════════════════════════════════════════════════════════════
    // بيانات النقاط
    // ═══════════════════════════════════════════════════════════════════════
    $_SESSION['current_points'] = (int) $user['current_points'];
    $_SESSION['monthly_starting_points'] = (int) $user['monthly_starting_points'];
    
    // ═══════════════════════════════════════════════════════════════════════
    // جدول العمل المخصص (إن وجد)
    // ═══════════════════════════════════════════════════════════════════════
    $_SESSION['custom_schedule'] = parse_json_column($user['custom_schedule']);
    
    // ═══════════════════════════════════════════════════════════════════════
    // تفضيلات المستخدم
    // ═══════════════════════════════════════════════════════════════════════
    $_SESSION['preferences'] = parse_json_column($user['preferences']);
    
    // ═══════════════════════════════════════════════════════════════════════
    // بيانات الجلسة
    // ═══════════════════════════════════════════════════════════════════════
    $_SESSION['_login_time'] = time();
    $_SESSION['_last_activity'] = time();
    $_SESSION['_ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
    
    // ═══════════════════════════════════════════════════════════════════════
    // الإشعارات
    // ═══════════════════════════════════════════════════════════════════════
    $_SESSION['unread_notifications'] = 0; // سيتم تحديثها لاحقاً
}

/**
 * تحليل عمود JSON من قاعدة البيانات
 * Parse JSON Column from Database
 * 
 * @param string|null $json
 * @return array
 */
function parse_json_column(?string $json): array {
    if (empty($json)) {
        return [];
    }
    
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * تحليل صلاحيات JSON من جدول roles
 * Parse Permissions JSON from roles table
 * 
 * @param string|null $permissionsJson
 * @return array
 */
function parse_permissions_json(?string $permissionsJson): array {
    if (empty($permissionsJson)) {
        return [];
    }
    
    $permissions = json_decode($permissionsJson, true);
    
    if (!is_array($permissions)) {
        return [];
    }
    
    // إذا كانت تحتوي على "*" يعني كل الصلاحيات
    if (in_array('*', $permissions, true)) {
        return ['*']; // سيتم معالجتها في has_permission()
    }
    
    return $permissions;
}

/**
 * إنشاء سجل جلسة دائمة في قاعدة البيانات
 * Create Persistent Session Record in Database
 * 
 * @param int $userId
 * @param bool $isRememberMe
 * @return string Session Token
 */
function create_persistent_session(int $userId, bool $isRememberMe = false): string {
    try {
        // إنشاء توكن عشوائي
        $token = bin2hex(random_bytes(64));
        $hashedToken = hash('sha256', $token);
        
        // تحديد وقت انتهاء الصلاحية
        $sessionLifetimeHours = (int) get_setting('session_lifetime_hours', 2);
        $rememberMeDays = (int) get_setting('remember_me_days', 30);
        
        if ($isRememberMe) {
            $expiresAt = date('Y-m-d H:i:s', time() + ($rememberMeDays * 24 * 60 * 60));
        } else {
            $expiresAt = date('Y-m-d H:i:s', time() + ($sessionLifetimeHours * 60 * 60));
        }
        
        // تحليل User Agent
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $deviceType = detect_device_type($userAgent);
        
        // إدراج السجل
        Database::insert('user_sessions', [
            'user_id' => $userId,
            'session_token' => $hashedToken,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($userAgent, 0, 500),
            'device_type' => $deviceType,
            'is_active' => 1,
            'expires_at' => $expiresAt
        ]);
        
        // تخزين التوكن في الجلسة
        $_SESSION['_session_token'] = $hashedToken;
        
        return $token; // إرجاع التوكن غير المشفر للكوكي
        
    } catch (PDOException $e) {
        error_log("Create Session Error: " . $e->getMessage());
        return '';
    }
}

/**
 * كشف نوع الجهاز من User Agent
 * Detect Device Type from User Agent
 * 
 * @param string $userAgent
 * @return string
 */
function detect_device_type(string $userAgent): string {
    $userAgent = strtolower($userAgent);
    
    if (preg_match('/mobile|android|iphone|ipod|blackberry|windows phone/i', $userAgent)) {
        return 'mobile';
    }
    
    if (preg_match('/tablet|ipad|kindle|playbook/i', $userAgent)) {
        return 'tablet';
    }
    
    if (preg_match('/mozilla|chrome|safari|firefox|edge|opera/i', $userAgent)) {
        return 'desktop';
    }
    
    return 'unknown';
}

/**
 * تعيين كوكي "تذكرني"
 * Set Remember Me Cookie
 * 
 * @param int $userId
 * @param string $token
 */
function set_remember_cookie(int $userId, string $token): void {
    $rememberMeDays = (int) get_setting('remember_me_days', 30);
    $expires = time() + ($rememberMeDays * 24 * 60 * 60);
    
    $cookieValue = base64_encode($userId . ':' . $token);
    
    setcookie(
        'sarh_remember',
        $cookieValue,
        [
            'expires' => $expires,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

/**
 * التحقق من كوكي "تذكرني" وتسجيل الدخول التلقائي
 * Check Remember Cookie and Auto-Login
 * 
 * @return bool
 */
function check_remember_token(): bool {
    if (is_logged_in()) {
        return true;
    }
    
    if (!isset($_COOKIE['sarh_remember'])) {
        return false;
    }
    
    try {
        $decoded = base64_decode($_COOKIE['sarh_remember']);
        $parts = explode(':', $decoded, 2);
        
        if (count($parts) !== 2) {
            clear_remember_token();
            return false;
        }
        
        [$userId, $token] = $parts;
        $hashedToken = hash('sha256', $token);
        
        // التحقق من صلاحية الجلسة في قاعدة البيانات
        $sql = "SELECT us.*, u.is_active 
                FROM user_sessions us
                INNER JOIN users u ON us.user_id = u.id
                WHERE us.user_id = :user_id 
                AND us.session_token = :token
                AND us.is_valid = 1
                AND us.is_remember_me = 1
                AND us.expires_at > NOW()
                AND u.is_active = 1
                AND u.deleted_at IS NULL
                LIMIT 1";
        
        $session = Database::fetchOne($sql, [
            'user_id' => $userId,
            'token' => $hashedToken
        ]);
        
        if (!$session) {
            clear_remember_token();
            return false;
        }
        
        // جلب بيانات المستخدم الكاملة
        $user = get_user_with_relations((int) $userId);
        
        if (!$user) {
            clear_remember_token();
            return false;
        }
        
        // إنشاء جلسة جديدة
        create_user_session($user);
        
        // تحديث آخر نشاط
        Database::update('user_sessions', [
            'last_activity_at' => date('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ], 'id = :id', ['id' => $session['id']]);
        
        Database::update('users', [
            'last_activity_at' => date('Y-m-d H:i:s'),
            'is_online' => 1
        ], 'id = :id', ['id' => $userId]);
        
        log_activity('auto_login', 'auth', 'تسجيل دخول تلقائي', (int) $userId, 'user');
        
        return true;
        
    } catch (Exception $e) {
        error_log("Check Remember Token Error: " . $e->getMessage());
        clear_remember_token();
        return false;
    }
}

/**
 * مسح كوكي "تذكرني"
 * Clear Remember Token Cookie
 * 
 * @param int|null $userId
 */
function clear_remember_token(?int $userId = null): void {
    // حذف الكوكي
    setcookie('sarh_remember', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // إبطال الجلسات في قاعدة البيانات
    if ($userId) {
        try {
            Database::update('user_sessions', [
                'is_valid' => 0,
                'logged_out_at' => date('Y-m-d H:i:s')
            ], 'user_id = :user_id AND is_remember_me = 1', ['user_id' => $userId]);
        } catch (Exception $e) {
            error_log("Clear Remember Token Error: " . $e->getMessage());
        }
    }
}

/**
 * التحقق من تسجيل الدخول وحماية الصفحة
 * Check Login and Protect Page
 * 
 * @param bool $redirect
 * @return bool
 */
function check_login(bool $redirect = true): bool {
    // محاولة تسجيل الدخول التلقائي
    check_remember_token();
    
    if (is_logged_in()) {
        // تحديث وقت آخر نشاط
        $_SESSION['_last_activity'] = time();
        
        // التحقق من انتهاء صلاحية الجلسة
        $sessionLifetimeHours = (int) get_setting('session_lifetime_hours', 2);
        $maxIdleTime = $sessionLifetimeHours * 60 * 60;
        
        if (isset($_SESSION['_login_time']) && (time() - $_SESSION['_last_activity']) > $maxIdleTime) {
            logout();
            if ($redirect) {
                flash('warning', 'انتهت صلاحية جلستك. يرجى تسجيل الدخول مرة أخرى.');
                redirect(url('login.php'));
            }
            return false;
        }
        
        return true;
    }
    
    if ($redirect) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect(url('login.php'));
    }
    
    return false;
}

/**
 * التحقق من أي صلاحية من قائمة
 * Check Any Permission from List
 * 
 * @param array $permissions
 * @return bool
 */
function has_any_permission(array $permissions): bool {
    foreach ($permissions as $permission) {
        if (has_permission($permission)) {
            return true;
        }
    }
    return false;
}

/**
 * التحقق من جميع الصلاحيات
 * Check All Permissions
 * 
 * @param array $permissions
 * @return bool
 */
function has_all_permissions(array $permissions): bool {
    foreach ($permissions as $permission) {
        if (!has_permission($permission)) {
            return false;
        }
    }
    return true;
}

/**
 * طلب صلاحية (إعادة توجيه إذا غير متوفرة)
 * Require Permission
 * 
 * @param string $permission
 * @param string|null $redirectUrl
 */
function require_permission(string $permission, ?string $redirectUrl = null): void {
    if (!has_permission($permission)) {
        log_activity('permission_denied', 'auth', "محاولة وصول غير مصرح: {$permission}", current_user_id(), 'user');
        
        if (is_ajax()) {
            json_response(['success' => false, 'message' => 'ليس لديك صلاحية للقيام بهذا الإجراء'], 403);
        }
        
        flash('danger', 'ليس لديك صلاحية للوصول إلى هذه الصفحة');
        redirect($redirectUrl ?? url('index.php'));
    }
}

/**
 * طلب مستوى دور معين
 * Require Role Level
 * 
 * @param int $minLevel
 * @param string|null $redirectUrl
 */
function require_role(int $minLevel, ?string $redirectUrl = null): void {
    if (!has_role($minLevel)) {
        log_activity('role_denied', 'auth', "محاولة وصول - المستوى المطلوب: {$minLevel}", current_user_id(), 'user');
        
        if (is_ajax()) {
            json_response(['success' => false, 'message' => 'ليس لديك صلاحية'], 403);
        }
        
        flash('danger', 'ليس لديك صلاحية للوصول إلى هذه الصفحة');
        redirect($redirectUrl ?? url('index.php'));
    }
}

/**
 * تسجيل الخروج
 * Logout
 */
function logout(): void {
    $userId = current_user_id();
    $sessionToken = $_SESSION['_session_token'] ?? null;
    
    if ($userId) {
        try {
            // تحديث حالة المستخدم
            Database::update('users', [
                'is_online' => 0,
                'last_activity_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $userId]);
            
            // إبطال الجلسة في قاعدة البيانات
            if ($sessionToken) {
                Database::update('user_sessions', [
                    'is_valid' => 0,
                    'logged_out_at' => date('Y-m-d H:i:s')
                ], 'session_token = :token', ['token' => $sessionToken]);
            }
            
            // مسح كوكي "تذكرني"
            clear_remember_token($userId);
            
            log_activity('logout', 'auth', 'تسجيل خروج', $userId, 'user');
            
        } catch (Exception $e) {
            error_log("Logout Error: " . $e->getMessage());
        }
    }
    
    // تدمير الجلسة
    $_SESSION = [];
    
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax'
            ]
        );
    }
    
    session_destroy();
}

// =====================================================
// دوال المستخدم - User Functions
// =====================================================

/**
 * جلب بيانات المستخدم مع العلاقات
 * Get User with Relations
 * 
 * @param int $userId
 * @return array|null
 */
function get_user_with_relations(int $userId): ?array {
    try {
        $sql = "SELECT 
                    u.*,
                    r.slug AS role_slug,
                    r.name AS role_name_ar,
                    r.name AS role_name_en,
                    r.role_level,
                    r.permissions AS role_permissions,
                    0 AS role_has_immunity,
                    0 AS can_access_all_branches,
                    r.color AS role_color,
                    r.icon AS role_icon,
                    b.code AS branch_code,
                    b.name AS branch_name_ar,
                    b.name AS branch_name_en,
                    b.city AS branch_city,
                    b.latitude AS branch_latitude,
                    b.longitude AS branch_longitude,
                    b.geofence_radius AS branch_geofence_radius,
                    b.settings AS branch_settings,
                    0 AS branch_is_locked
                FROM users u
                INNER JOIN roles r ON u.role_id = r.id
                LEFT JOIN branches b ON u.branch_id = b.id
                WHERE u.id = :user_id
                LIMIT 1";
        
        return Database::fetchOne($sql, ['user_id' => $userId]) ?: null;
        
    } catch (PDOException $e) {
        error_log("Get User Error: " . $e->getMessage());
        return null;
    }
}

/**
 * الحصول على بيانات المستخدم الحالي من قاعدة البيانات
 * Get Current User from Database
 * 
 * @return array|null
 */
function get_current_user_data(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return get_user_with_relations(current_user_id());
}

// =====================================================
// دوال الإعدادات - Settings Functions
// =====================================================

/**
 * الحصول على إعدادات مجموعة كاملة
 * Get All Settings in Group
 * 
 * @param string $group
 * @return array
 */
function get_settings_group(string $group): array {
    try {
        $sql = "SELECT setting_key, setting_value, value_text, setting_type 
                FROM system_settings 
                WHERE setting_group = :group";
        $results = Database::fetchAll($sql, ['group' => $group]);
        
        $settings = [];
        foreach ($results as $row) {
            if (!empty($row['setting_value'])) {
                $settings[$row['setting_key']] = json_decode($row['setting_value'], true);
            } else {
                $settings[$row['setting_key']] = $row['value_text'];
            }
        }
        
        return $settings;
        
    } catch (Exception $e) {
        error_log("Get Settings Group Error: " . $e->getMessage());
        return [];
    }
}

// =====================================================
// دوال الفروع - Branch Functions
// =====================================================

/**
 * الحصول على قائمة الفروع
 * Get All Branches
 * 
 * @param bool $activeOnly
 * @return array
 */
function get_branches(bool $activeOnly = true): array {
    try {
        $sql = "SELECT id, branch_code, name_ar, name_en, city, region, 
                       latitude, longitude, geofence_radius, settings, is_active, is_locked
                FROM branches";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY name_ar";
        
        return Database::fetchAll($sql);
        
    } catch (PDOException $e) {
        error_log("Get Branches Error: " . $e->getMessage());
        return [];
    }
}

/**
 * الحصول على إعدادات عمل الفرع
 * Get Branch Work Settings
 * 
 * @param int $branchId
 * @return array
 */
function get_branch_work_settings(int $branchId): array {
    // أولاً من الجلسة إذا كان نفس الفرع
    if (isset($_SESSION['branch_id']) && $_SESSION['branch_id'] === $branchId && isset($_SESSION['branch_settings'])) {
        return $_SESSION['branch_settings'];
    }
    
    try {
        $sql = "SELECT settings FROM branches WHERE id = :id";
        $result = Database::fetchOne($sql, ['id' => $branchId]);
        
        if ($result && !empty($result['settings'])) {
            return json_decode($result['settings'], true) ?: [];
        }
        
        return [];
        
    } catch (Exception $e) {
        return [];
    }
}

// =====================================================
// دوال الحضور - Attendance Functions
// =====================================================

/**
 * الحصول على سجل حضور اليوم
 * Get Today's Attendance
 * 
 * @param int $userId
 * @return array|null
 */
function get_today_attendance(int $userId): ?array {
    try {
        $sql = "SELECT * FROM attendance 
                WHERE user_id = :user_id 
                AND date = CURDATE()
                ORDER BY id DESC
                LIMIT 1";
        
        return Database::fetchOne($sql, ['user_id' => $userId]) ?: null;
        
    } catch (PDOException $e) {
        error_log("Get Today Attendance Error: " . $e->getMessage());
        return null;
    }
}

/**
 * الحصول على إحصائيات الحضور للمستخدم
 * Get User Attendance Stats
 * 
 * @param int $userId
 * @param int|null $month
 * @param int|null $year
 * @return array
 */
function get_user_attendance_stats(int $userId, ?int $month = null, ?int $year = null): array {
    $month = $month ?? (int) date('m');
    $year = $year ?? (int) date('Y');
    
    try {
        $sql = "SELECT 
                    COUNT(*) as total_records,
                    SUM(CASE WHEN status IN ('present', 'late', 'early_leave', 'checked_in') THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
                    SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_days,
                    COALESCE(SUM(late_minutes), 0) as total_late_minutes,
                    COALESCE(SUM(early_leave_minutes), 0) as total_early_leave_minutes,
                    COALESCE(SUM(overtime_minutes), 0) as total_overtime_minutes,
                    COALESCE(SUM(work_minutes), 0) as total_work_minutes,
                    COALESCE(SUM(penalty_points), 0) as total_penalty_points,
                    COALESCE(SUM(bonus_points), 0) as total_bonus_points
                FROM attendance 
                WHERE user_id = :user_id 
                AND MONTH(date) = :month 
                AND YEAR(date) = :year";
        
        $stats = Database::fetchOne($sql, [
            'user_id' => $userId,
            'month' => $month,
            'year' => $year
        ]);
        
        return $stats ?: [
            'total_records' => 0,
            'present_days' => 0,
            'absent_days' => 0,
            'late_days' => 0,
            'leave_days' => 0,
            'total_late_minutes' => 0,
            'total_early_leave_minutes' => 0,
            'total_overtime_minutes' => 0,
            'total_work_minutes' => 0,
            'total_penalty_points' => 0,
            'total_bonus_points' => 0
        ];
        
    } catch (PDOException $e) {
        error_log("Get Attendance Stats Error: " . $e->getMessage());
        return [];
    }
}

// =====================================================
// دوال مساعدة - Helper Functions
// =====================================================

/**
 * الحصول على التحية حسب الوقت
 * Get Greeting Based on Time
 * 
 * @return string
 */
function get_greeting(): string {
    $hour = (int) date('H');
    
    if ($hour >= 5 && $hour < 12) {
        return 'صباح الخير';
    } elseif ($hour >= 12 && $hour < 17) {
        return 'مساء الخير';
    } elseif ($hour >= 17 && $hour < 21) {
        return 'مساء النور';
    } else {
        return 'مساء الخير';
    }
}

/**
 * تنسيق التاريخ بالعربية
 * Format Date in Arabic
 * 
 * @param string $date
 * @param bool $includeDay
 * @return string
 */
function format_arabic_date(string $date, bool $includeDay = true): string {
    $timestamp = strtotime($date);
    
    $days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    $months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 
               'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    
    $dayName = $days[date('w', $timestamp)];
    $day = date('j', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    
    if ($includeDay) {
        return "{$dayName}، {$day} {$month} {$year}";
    }
    
    return "{$day} {$month} {$year}";
}

/**
 * الفرق الزمني بصيغة مقروءة
 * Human Readable Time Ago
 * 
 * @param string $datetime
 * @return string
 */
function time_ago(string $datetime): string {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'الآن';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return "منذ {$mins} دقيقة";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "منذ {$hours} ساعة";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return "منذ {$days} يوم";
    } else {
        return format_arabic_date($datetime, false);
    }
}

/**
 * الحصول على إعدادات العمل للمستخدم الحالي
 * Get Work Settings for Current User
 * (يأخذ من custom_schedule أو branch_settings أو الافتراضي)
 * 
 * @return array
 */
function get_current_work_settings(): array {
    // 1. من جدول المستخدم المخصص
    $customSchedule = $_SESSION['custom_schedule'] ?? [];
    if (!empty($customSchedule)) {
        return array_merge(get_default_work_settings(), $customSchedule);
    }
    
    // 2. من إعدادات الفرع
    $branchSettings = $_SESSION['branch_settings'] ?? [];
    if (!empty($branchSettings)) {
        return array_merge(get_default_work_settings(), $branchSettings);
    }
    
    // 3. الافتراضي من system_settings
    return get_default_work_settings();
}

/**
 * الحصول على إعدادات العمل الافتراضية
 * Get Default Work Settings
 * 
 * @return array
 */
function get_default_work_settings(): array {
    return [
        'work_start' => get_setting('default_work_start', '06:00'),
        'work_end' => get_setting('default_work_end', '14:00'),
        'lock_time' => get_setting('default_lock_time', '10:00'),
        'grace_period_minutes' => (int) get_setting('default_grace_period', 15),
        'weekly_off_days' => get_setting('weekly_off_days', [5, 6])
    ];
}

// =====================================================
// دوال قاعدة البيانات - Database Functions
// =====================================================

/**
 * الحصول على عنوان IP الحقيقي
 * Get Real IP Address
 * 
 * @return string
 */
if (!function_exists('get_real_ip')) {
    function get_real_ip(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/**
 * عرض رسائل الفلاش كـ HTML
 * Display Flash Messages as HTML
 * 
 * @return string
 */
if (!function_exists('display_flash_messages')) {
    function display_flash_messages(): string {
        $flash = get_flash();
        if (!$flash) return '';
        
        $type = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
        $icons = [
            'success' => 'bi-check-circle-fill',
            'danger' => 'bi-exclamation-triangle-fill',
            'warning' => 'bi-exclamation-circle-fill',
            'info' => 'bi-info-circle-fill'
        ];
        $icon = $icons[$type] ?? 'bi-info-circle-fill';
        
        return <<<HTML
        <div class="alert alert-{$type} alert-dismissible fade show" role="alert">
            <i class="bi {$icon} me-2"></i>
            {$message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        HTML;
    }
}

// =====================================================
// فئة قاعدة البيانات - Database Class
// =====================================================

if (!class_exists('Database')) {
    /**
     * فئة قاعدة البيانات
     * Database Class
     */
    class Database {
        private static ?PDO $connection = null;
        
        /**
         * الحصول على الاتصال
         * Get Connection
         * 
         * @return PDO
         */
        public static function getConnection(): PDO {
            if (self::$connection === null) {
                try {
                    $dsn = sprintf(
                        'mysql:host=%s;dbname=%s;charset=utf8mb4',
                        DB_HOST,
                        DB_NAME
                    );
                    
                    self::$connection = new PDO($dsn, DB_USER, DB_PASS, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_520_ci"
                    ]);
                } catch (PDOException $e) {
                    error_log("Database Connection Error: " . $e->getMessage());
                    throw new Exception('خطأ في الاتصال بقاعدة البيانات');
                }
            }
            
            return self::$connection;
        }
        
        /**
         * تنفيذ استعلام
         * Execute Query
         * 
         * @param string $sql
         * @param array $params
         * @return PDOStatement
         */
        public static function query(string $sql, array $params = []): PDOStatement {
            $stmt = self::getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        }
        
        /**
         * جلب جميع النتائج
         * Fetch All Results
         * 
         * @param string $sql
         * @param array $params
         * @return array
         */
        public static function fetchAll(string $sql, array $params = []): array {
            return self::query($sql, $params)->fetchAll();
        }
        
        /**
         * جلب صف واحد
         * Fetch One Row
         * 
         * @param string $sql
         * @param array $params
         * @return array|false
         */
        public static function fetchOne(string $sql, array $params = []): array|false {
            return self::query($sql, $params)->fetch();
        }
        
        /**
         * جلب قيمة واحدة
         * Fetch Single Value
         * 
         * @param string $sql
         * @param array $params
         * @return mixed
         */
        public static function fetchValue(string $sql, array $params = []): mixed {
            return self::query($sql, $params)->fetchColumn();
        }
        
        /**
         * إدراج سجل
         * Insert Record
         * 
         * @param string $table
         * @param array $data
         * @return int Last Insert ID
         */
        public static function insert(string $table, array $data): int {
            $columns = implode('`, `', array_keys($data));
            $placeholders = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
            
            $sql = "INSERT INTO `{$table}` (`{$columns}`) VALUES ({$placeholders})";
            
            self::query($sql, $data);
            return (int) self::getConnection()->lastInsertId();
        }
        
        /**
         * تحديث سجل
         * Update Record
         * 
         * @param string $table
         * @param array $data
         * @param string $where
         * @param array $whereParams
         * @return int Affected Rows
         */
        public static function update(string $table, array $data, string $where, array $whereParams = []): int {
            $set = implode(', ', array_map(fn($k) => "`{$k}` = :set_{$k}", array_keys($data)));
            
            $sql = "UPDATE `{$table}` SET {$set} WHERE {$where}";
            
            $params = [];
            foreach ($data as $key => $value) {
                $params["set_{$key}"] = $value;
            }
            $params = array_merge($params, $whereParams);
            
            $stmt = self::query($sql, $params);
            return $stmt->rowCount();
        }
        
        /**
         * حذف سجل
         * Delete Record
         * 
         * @param string $table
         * @param string $where
         * @param array $params
         * @return int Affected Rows
         */
        public static function delete(string $table, string $where, array $params = []): int {
            $sql = "DELETE FROM `{$table}` WHERE {$where}";
            $stmt = self::query($sql, $params);
            return $stmt->rowCount();
        }
        
        /**
         * التحقق من وجود سجل
         * Check if Record Exists
         * 
         * @param string $table
         * @param string $where
         * @param array $params
         * @return bool
         */
        public static function exists(string $table, string $where, array $params = []): bool {
            $sql = "SELECT 1 FROM `{$table}` WHERE {$where} LIMIT 1";
            return self::fetchOne($sql, $params) !== false;
        }
        
        /**
         * عد السجلات
         * Count Records
         * 
         * @param string $table
         * @param string $where
         * @param array $params
         * @return int
         */
        public static function count(string $table, string $where = '1', array $params = []): int {
            $sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where}";
            return (int) self::fetchValue($sql, $params);
        }
        
        /**
         * بدء معاملة
         * Begin Transaction
         */
        public static function beginTransaction(): void {
            self::getConnection()->beginTransaction();
        }
        
        /**
         * تأكيد المعاملة
         * Commit Transaction
         */
        public static function commit(): void {
            self::getConnection()->commit();
        }
        
        /**
         * التراجع عن المعاملة
         * Rollback Transaction
         */
        public static function rollback(): void {
            self::getConnection()->rollBack();
        }
    }
}

/**
 * الحصول على اتصال PDO
 * Get PDO Connection
 * 
 * @return PDO
 */
if (!function_exists('db')) {
    function db(): PDO {
        return Database::getConnection();
    }
}

/**
 * الحصول على عدد الإشعارات غير المقروءة
 * Get Unread Notifications Count
 * 
 * @param int $userId
 * @return int
 */
function get_unread_notifications_count(int $userId): int {
    try {
        // التحقق من وجود جدول الإشعارات
        $result = Database::fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0",
            ['user_id' => $userId]
        );
        return (int) ($result ?? 0);
    } catch (Exception $e) {
        // الجدول قد لا يكون موجوداً
        return 0;
    }
}

// =====================================================
// دوال ترحيل الحضور اليومي
// Daily Attendance Finalization Functions
// =====================================================

/**
 * ترحيل سجلات الحضور اليومية (تُنفذ الساعة 11 مساءً)
 * Finalize Daily Attendance Records (runs at 11 PM)
 * 
 * - قفل السجلات وجعلها غير قابلة للتعديل
 * - تسجيل انصراف تلقائي لمن لم يسجلوا (كغياب/تأخر)
 * - مسح مواقع الموظفين
 * 
 * @return array ['success' => bool, 'message' => string, 'stats' => array]
 */
function finalizeDailyAttendance(): array {
    $today = date('Y-m-d');
    $stats = [
        'locked_records' => 0,
        'auto_checkouts' => 0,
        'locations_cleared' => 0
    ];
    
    try {
        // 1. قفل جميع سجلات الحضور لهذا اليوم
        $lockResult = Database::query(
            "UPDATE attendance SET is_locked = 1 WHERE date = ? AND is_locked = 0",
            [$today]
        );
        $stats['locked_records'] = $lockResult->rowCount();
        
        // 2. تسجيل انصراف تلقائي لمن سجلوا حضورهم ولم يسجلوا انصرافهم
        $autoCheckoutResult = Database::query(
            "UPDATE attendance 
             SET check_out_time = '23:00:00',
                 notes = CONCAT(COALESCE(notes, ''), ' [انصراف تلقائي - نهاية اليوم]'),
                 is_locked = 1
             WHERE date = ? 
             AND check_in_time IS NOT NULL 
             AND check_out_time IS NULL",
            [$today]
        );
        $stats['auto_checkouts'] = $autoCheckoutResult->rowCount();
        
        // 3. مسح مواقع جميع الموظفين
        $clearLocationsResult = Database::query(
            "UPDATE users SET 
                last_latitude = NULL, 
                last_longitude = NULL, 
                is_online = 0"
        );
        $stats['locations_cleared'] = $clearLocationsResult->rowCount();
        
        // 4. تسجيل في سجل النشاط
        log_activity(
            'daily_attendance_finalized',
            'system',
            "تم ترحيل سجلات الحضور لليوم {$today}: " . json_encode($stats, JSON_UNESCAPED_UNICODE),
            0,
            'system'
        );
        
        return [
            'success' => true,
            'message' => 'تم ترحيل سجلات الحضور بنجاح',
            'stats' => $stats
        ];
        
    } catch (Exception $e) {
        error_log("Daily Attendance Finalization Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'خطأ في ترحيل سجلات الحضور: ' . $e->getMessage(),
            'stats' => $stats
        ];
    }
}

/**
 * التحقق وتنفيذ الترحيل التلقائي إذا حان الوقت
 * Check and Execute Auto-Finalization if Time
 * 
 * يمكن استدعاء هذه الدالة من أي صفحة للتحقق
 */
function checkAndRunDailyFinalization(): void {
    $currentHour = (int) date('H');
    $currentMinute = (int) date('i');
    
    // تنفيذ الترحيل بين 23:00 و 23:05
    if ($currentHour === 23 && $currentMinute <= 5) {
        $lastRunKey = 'last_daily_finalization_' . date('Y-m-d');
        $lastRun = get_setting($lastRunKey, '');
        
        // إذا لم يتم التنفيذ اليوم
        if (empty($lastRun)) {
            $result = finalizeDailyAttendance();
            
            if ($result['success']) {
                // تسجيل وقت التنفيذ
                set_setting($lastRunKey, date('Y-m-d H:i:s'));
            }
        }
    }
}

// =====================================================
// 📊 مركز تحليل الأداء - نظام تقييم الموظفين
// PERFORMANCE ANALYSIS CENTER - EMPLOYEE SCORING SYSTEM
// =====================================================

/**
 * حساب مؤشر أداء الموظف
 * Calculate Employee Performance Score
 * 
 * @param int $user_id معرف المستخدم
 * @param string $start_date تاريخ البداية
 * @param string $end_date تاريخ النهاية
 * @return array ['score' => float, 'breakdown' => array, 'rank_change' => int]
 */
function calculate_performance_score(int $user_id, string $start_date, string $end_date): array {
    $breakdown = [
        'time_score' => 0,
        'efficiency_score' => 0,
        'streak_bonus' => 0,
        'penalty_deduction' => 0,
        'total_hours' => 0,
        'scheduled_hours' => 0,
        'streak_days' => 0
    ];
    
    try {
        // 1. جلب بيانات الحضور للفترة (مجمعة حسب الفرع المسجل)
        $attendance_sql = "
            SELECT 
                a.date,
                a.check_in_time,
                a.check_out_time,
                a.work_minutes,
                a.late_minutes,
                a.penalty_points,
                a.bonus_points,
                COALESCE(a.recorded_branch_id, a.branch_id) as effective_branch_id,
                es.work_start_time,
                es.work_end_time
            FROM attendance a
            LEFT JOIN employee_schedules es ON a.user_id = es.user_id
            WHERE a.user_id = :user_id
            AND a.date BETWEEN :start_date AND :end_date
            AND a.check_in_time IS NOT NULL
            ORDER BY a.date ASC
        ";
        
        $records = Database::fetchAll($attendance_sql, [
            'user_id' => $user_id,
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);
        
        if (empty($records)) {
            return [
                'score' => 0,
                'breakdown' => $breakdown,
                'rank_change' => 0
            ];
        }
        
        // 2. حساب الساعات الفعلية والمجدولة
        $total_work_minutes = 0;
        $total_scheduled_minutes = 0;
        $total_penalties = 0;
        $total_bonuses = 0;
        $consecutive_perfect_days = 0;
        $current_streak = 0;
        
        foreach ($records as $record) {
            // الساعات الفعلية
            $work_minutes = (int) ($record['work_minutes'] ?? 0);
            $total_work_minutes += $work_minutes;
            
            // الساعات المجدولة (افتراضي 8 ساعات = 480 دقيقة)
            $scheduled = 480;
            if (!empty($record['work_start_time']) && !empty($record['work_end_time'])) {
                $start = strtotime($record['work_start_time']);
                $end = strtotime($record['work_end_time']);
                $scheduled = ($end - $start) / 60;
            }
            $total_scheduled_minutes += $scheduled;
            
            // النقاط والعقوبات
            $total_penalties += (float) ($record['penalty_points'] ?? 0);
            $total_bonuses += (float) ($record['bonus_points'] ?? 0);
            
            // حساب السلسلة (الأيام المثالية المتتالية)
            $late = (int) ($record['late_minutes'] ?? 0);
            $is_perfect = ($late == 0 && $work_minutes >= ($scheduled * 0.9));
            
            if ($is_perfect) {
                $current_streak++;
            } else {
                $current_streak = 0;
            }
            
            $consecutive_perfect_days = max($consecutive_perfect_days, $current_streak);
        }
        
        $breakdown['total_hours'] = round($total_work_minutes / 60, 1);
        $breakdown['scheduled_hours'] = round($total_scheduled_minutes / 60, 1);
        $breakdown['streak_days'] = $consecutive_perfect_days;
        
        // 3. TIME NORMALIZATION: (Actual / Scheduled) * 50, capped at 100%
        $time_ratio = $total_scheduled_minutes > 0 
            ? min(1, $total_work_minutes / $total_scheduled_minutes) 
            : 0;
        $breakdown['time_score'] = round($time_ratio * 50, 1);
        
        // 4. EFFICIENCY SCORE: Based on consistency and bonuses
        $efficiency_base = 30;
        $efficiency_modifier = ($total_bonuses - $total_penalties) * 2;
        $breakdown['efficiency_score'] = round(max(0, min(50, $efficiency_base + $efficiency_modifier)), 1);
        
        // 5. STREAK BONUS: 1.2x multiplier if > 3 consecutive perfect days
        $streak_multiplier = 1.0;
        if ($consecutive_perfect_days > 3) {
            $streak_multiplier = 1.2;
            $breakdown['streak_bonus'] = 20; // Bonus points for streak
        }
        
        // 6. PENALTY DEDUCTION
        $breakdown['penalty_deduction'] = round(min(20, $total_penalties), 1);
        
        // 7. FINAL SCORE CALCULATION
        $base_score = $breakdown['time_score'] + $breakdown['efficiency_score'] + $breakdown['streak_bonus'];
        $final_score = ($base_score - $breakdown['penalty_deduction']) * $streak_multiplier;
        $final_score = max(0, min(100, $final_score));
        
        // 8. تحديث عداد السلسلة للمستخدم
        Database::update('users', [
            'streak_count' => $current_streak
        ], 'id = :id', ['id' => $user_id]);
        
        return [
            'score' => round($final_score, 1),
            'breakdown' => $breakdown,
            'rank_change' => 0 // سيتم حسابها لاحقاً عند المقارنة
        ];
        
    } catch (Exception $e) {
        error_log("Gladiator Score Calculation Error: " . $e->getMessage());
        return [
            'score' => 0,
            'breakdown' => $breakdown,
            'rank_change' => 0
        ];
    }
}

/**
 * جلب المصارعين الأقوى (Top Warlords)
 * Get Top Gladiators / Warlords
 * 
 * @param int $limit عدد النتائج
 * @param string $period الفترة (week, month, year)
 * @return array
 */
function get_top_performers(int $limit = 3, string $period = 'month'): array {
    $dates = get_period_dates($period);
    $warlords = [];
    
    try {
        // جلب جميع الموظفين النشطين
        $users = Database::fetchAll(
            "SELECT u.id, u.full_name, u.avatar, u.streak_count, u.branch_id,
                    b.name as branch_name, r.name as role_name, r.color as role_color
             FROM users u
             LEFT JOIN branches b ON u.branch_id = b.id
             LEFT JOIN roles r ON u.role_id = r.id
             WHERE u.is_active = 1 AND u.role_id != (SELECT id FROM roles WHERE level = 99 LIMIT 1)
             ORDER BY u.full_name"
        );
        
        foreach ($users as $user) {
            $score_data = calculate_performance_score($user['id'], $dates['start'], $dates['end']);
            $warlords[] = [
                'id' => $user['id'],
                'name' => $user['full_name'],
                'avatar' => $user['avatar'],
                'branch' => $user['branch_name'],
                'role' => $user['role_name'],
                'role_color' => $user['role_color'],
                'score' => $score_data['score'],
                'streak' => (int) $user['streak_count'],
                'breakdown' => $score_data['breakdown']
            ];
        }
        
        // ترتيب حسب النقاط
        usort($warlords, fn($a, $b) => $b['score'] <=> $a['score']);
        
        // إضافة الرتب وتغيير الترتيب
        foreach ($warlords as $i => &$w) {
            $w['rank'] = $i + 1;
            $w['rank_change'] = 0; // يمكن حسابها بالمقارنة مع الفترة السابقة
        }
        
        return array_slice($warlords, 0, $limit);
        
    } catch (Exception $e) {
        error_log("Arena Warlords Error: " . $e->getMessage());
        return [];
    }
}

/**
 * جلب المصارعين الضعفاء (The Guillotine)
 * Get Bottom Performers
 * 
 * @param int $limit عدد النتائج
 * @param string $period الفترة
 * @return array
 */
function get_improvement_needed(int $limit = 5, string $period = 'month'): array {
    $dates = get_period_dates($period);
    $performers = [];
    
    try {
        $users = Database::fetchAll(
            "SELECT u.id, u.full_name, u.avatar, u.streak_count, u.branch_id,
                    b.name as branch_name
             FROM users u
             LEFT JOIN branches b ON u.branch_id = b.id
             WHERE u.is_active = 1 AND u.role_id != (SELECT id FROM roles WHERE level = 99 LIMIT 1)"
        );
        
        foreach ($users as $user) {
            $score_data = calculate_performance_score($user['id'], $dates['start'], $dates['end']);
            $performers[] = [
                'id' => $user['id'],
                'name' => $user['full_name'],
                'avatar' => $user['avatar'],
                'branch' => $user['branch_name'],
                'score' => $score_data['score'],
                'hours' => $score_data['breakdown']['total_hours']
            ];
        }
        
        // ترتيب تصاعدي (الأضعف أولاً)
        usort($performers, fn($a, $b) => $a['score'] <=> $b['score']);
        
        // إضافة الرتب
        foreach ($performers as $i => &$p) {
            $p['rank'] = count($performers) - $i;
        }
        
        return array_slice($performers, 0, $limit);
        
    } catch (Exception $e) {
        error_log("Arena Guillotine Error: " . $e->getMessage());
        return [];
    }
}

/**
 * جلب إحصائيات حرب الفروع
 * Get Branch Warfare Statistics
 * 
 * @param string $period الفترة
 * @return array
 */
function get_branch_performance(string $period = 'month'): array {
    $dates = get_period_dates($period);
    $branches = [];
    
    try {
        // جلب جميع الفروع
        $branch_list = Database::fetchAll("SELECT id, name, code FROM branches WHERE is_active = 1");
        
        foreach ($branch_list as $branch) {
            // جلب موظفي الفرع
            $employees = Database::fetchAll(
                "SELECT id FROM users WHERE branch_id = :branch_id AND is_active = 1",
                ['branch_id' => $branch['id']]
            );
            
            if (empty($employees)) {
                continue;
            }
            
            $total_score = 0;
            $soldier_count = count($employees);
            
            foreach ($employees as $emp) {
                $score_data = calculate_performance_score($emp['id'], $dates['start'], $dates['end']);
                $total_score += $score_data['score'];
            }
            
            $avg_score = $soldier_count > 0 ? $total_score / $soldier_count : 0;
            
            $branches[] = [
                'id' => $branch['id'],
                'name' => $branch['name'],
                'code' => $branch['code'],
                'soldiers' => $soldier_count,
                'avg_score' => round($avg_score, 1),
                'total_score' => round($total_score, 1)
            ];
        }
        
        // ترتيب حسب المتوسط
        usort($branches, fn($a, $b) => $b['avg_score'] <=> $a['avg_score']);
        
        // إضافة الرتب
        foreach ($branches as $i => &$b) {
            $b['rank'] = $i + 1;
        }
        
        return $branches;
        
    } catch (Exception $e) {
        error_log("Branch Warfare Error: " . $e->getMessage());
        return [];
    }
}

/**
 * جلب تواريخ الفترة
 * Get Period Date Range
 * 
 * @param string $period
 * @return array ['start' => string, 'end' => string]
 */
function get_period_dates(string $period): array {
    $end = date('Y-m-d');
    
    switch ($period) {
        case 'week':
            $start = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'year':
            $start = date('Y-01-01');
            break;
        case 'month':
        default:
            $start = date('Y-m-01');
            break;
    }
    
    return ['start' => $start, 'end' => $end];
}

/**
 * جلب الأحرف الأولى من الاسم
 * Get Initials from Name
 * 
 * @param string $name
 * @return string
 */
function get_initials(string $name): string {
    $words = explode(' ', trim($name));
    $initials = '';
    
    foreach (array_slice($words, 0, 2) as $word) {
        if (!empty($word)) {
            $initials .= mb_substr($word, 0, 1, 'UTF-8');
        }
    }
    
    return $initials ?: '؟';
}

/**
 * حفظ صورة الموظف
 * Save Employee Photo from Base64
 * 
 * @param string $photo_data Base64 encoded image data (data:image/...)
 * @param string $emp_code Employee code for filename
 * @return string|false Filename on success, false on failure
 */
function saveEmployeePhoto(string $photo_data, string $emp_code): string|false {
    try {
        // Extract base64 data
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $photo_data, $matches)) {
            $image_type = strtolower($matches[1]);
            $image_data = base64_decode($matches[2]);
        } else {
            // If no data URI prefix, assume it's raw base64
            $image_data = base64_decode($photo_data);
            $image_type = 'jpg'; // Default to jpg
        }
        
        if ($image_data === false) {
            error_log("Failed to decode base64 image data");
            return false;
        }
        
        // Validate image type
        if (!in_array($image_type, ['jpg', 'jpeg', 'png', 'webp'])) {
            $image_type = 'jpg';
        }
        
        // Create uploads/avatars directory if it doesn't exist
        $upload_dir = UPLOADS_PATH . '/avatars';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate filename
        $filename = 'emp_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $emp_code) . '_' . time() . '.' . $image_type;
        $filepath = $upload_dir . '/' . $filename;
        
        // Save file
        if (file_put_contents($filepath, $image_data) === false) {
            error_log("Failed to save employee photo to: $filepath");
            return false;
        }
        
        // Verify image is valid
        $image_info = @getimagesize($filepath);
        if ($image_info === false) {
            @unlink($filepath);
            error_log("Invalid image file saved: $filepath");
            return false;
        }
        
        return $filename;
        
    } catch (Exception $e) {
        error_log("saveEmployeePhoto Error: " . $e->getMessage());
        return false;
    }
}
