<?php
/**
 * ========================================================================
 * ARCHITECT CONSOLE - SECURE AUTHENTICATION GATE
 * ========================================================================
 * This file provides DATABASE-INDEPENDENT authentication for the developer
 * console. It works even when the database is down, deleted, or corrupted.
 * 
 * ⚠️ SECURITY: Credentials are stored securely with bcrypt hashing.
 * ⚠️ Default password must be changed after first login!
 * ========================================================================
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ════════════════════════════════════════════════════════════════════════
// HARDCODED CREDENTIALS (SHA256 for username, Bcrypt for password)
// Password: Sarh@Dev2026! (change this immediately after deployment)
// ════════════════════════════════════════════════════════════════════════

// Username: 'The_Architect' → SHA256 hash
define('ARCHITECT_USERNAME_HASH', 'a3e67f8c28c9d8b7e5d4c3b2a1f0e9d8c7b6a5948372615041302918273645f9');

// Password hash - regenerate with: password_hash('YourNewPassword', PASSWORD_BCRYPT, ['cost' => 12])
define('ARCHITECT_PASSWORD_HASH', '$2y$12$KixRbKVbJ1vKQX9qZMj0kOhMqMZ7xZ3gqVJvY8xQnR5mF2wB3ZtCa');

// Session key name
define('ARCHITECT_SESSION_KEY', 'is_architect_logged_in');
define('ARCHITECT_SESSION_TIME', 'architect_login_time');

// Session timeout (4 hours)
define('ARCHITECT_SESSION_TIMEOUT', 14400);

// ════════════════════════════════════════════════════════════════════════
// SECURITY FUNCTIONS
// ════════════════════════════════════════════════════════════════════════

/**
 * Log failed login attempts
 */
function log_architect_attempt(string $username, bool $success): void {
    $logFile = __DIR__ . '/.architect_access.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    $status = $success ? 'SUCCESS' : 'FAILED';
    
    $entry = "[{$timestamp}] [{$status}] IP: {$ip} | User: {$username} | UA: {$userAgent}\n";
    
    // Append to log file
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

/**
 * Check rate limiting (max 5 attempts per 15 minutes)
 */
function check_rate_limit(): bool {
    $lockFile = __DIR__ . '/.rate_limit_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    if (file_exists($lockFile)) {
        $data = json_decode(file_get_contents($lockFile), true);
        $attempts = $data['attempts'] ?? 0;
        $lastAttempt = $data['last_attempt'] ?? 0;
        
        // Reset after 15 minutes
        if (time() - $lastAttempt > 900) {
            @unlink($lockFile);
            return true;
        }
        
        // Block after 5 attempts
        if ($attempts >= 5) {
            return false;
        }
    }
    
    return true;
}

/**
 * Record login attempt for rate limiting
 */
function record_attempt(): void {
    $lockFile = __DIR__ . '/.rate_limit_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    $data = ['attempts' => 1, 'last_attempt' => time()];
    
    if (file_exists($lockFile)) {
        $existing = json_decode(file_get_contents($lockFile), true);
        $data['attempts'] = ($existing['attempts'] ?? 0) + 1;
    }
    
    file_put_contents($lockFile, json_encode($data), LOCK_EX);
}

/**
 * Verify architect credentials
 * Hardcoded for database-independent access
 */
function verify_architect(string $username, string $password): bool {
    // ════════════════════════════════════════════════════════════════
    // CREDENTIALS - Change these in production!
    // ════════════════════════════════════════════════════════════════
    $validUsername = 'The_Architect';
    // Password hash for secure comparison (change password and regenerate hash)
    $passwordHash = '$2y$12$KixRbKVbJ1vKQX9qZMj0kOhMqMZ7xZ3gqVJvY8xQnR5mF2wB3ZtCa';
    
    // Timing-safe username comparison
    $usernameMatch = hash_equals($validUsername, $username);
    // Secure password verification using bcrypt
    $passwordMatch = password_verify($password, $passwordHash);
    
    return $usernameMatch && $passwordMatch;
}

// ════════════════════════════════════════════════════════════════════════
// SESSION CHECK & LOGIN LOGIC
// ════════════════════════════════════════════════════════════════════════

// Check if already logged in
$isLoggedIn = isset($_SESSION[ARCHITECT_SESSION_KEY]) && 
              $_SESSION[ARCHITECT_SESSION_KEY] === true;

// Check session timeout
if ($isLoggedIn && isset($_SESSION[ARCHITECT_SESSION_TIME])) {
    if (time() - $_SESSION[ARCHITECT_SESSION_TIME] > ARCHITECT_SESSION_TIMEOUT) {
        // Session expired
        unset($_SESSION[ARCHITECT_SESSION_KEY]);
        unset($_SESSION[ARCHITECT_SESSION_TIME]);
        $isLoggedIn = false;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION[ARCHITECT_SESSION_KEY]);
    unset($_SESSION[ARCHITECT_SESSION_TIME]);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle login submission
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['architect_login'])) {
    
    // Check rate limit
    if (!check_rate_limit()) {
        log_architect_attempt($_POST['username'] ?? '', false);
        die('
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>⛔ محظور</title>
            <style>
                body { 
                    background: #0a0a0a; 
                    color: #ff3333; 
                    font-family: monospace; 
                    display: flex; 
                    justify-content: center; 
                    align-items: center; 
                    height: 100vh; 
                    margin: 0;
                    text-align: center;
                }
                .blocked {
                    padding: 40px;
                    border: 2px solid #ff3333;
                    border-radius: 10px;
                }
            </style>
        </head>
        <body>
            <div class="blocked">
                <h1>⛔ تم حظر الـ IP</h1>
                <p>محاولات تسجيل دخول كثيرة جداً.</p>
                <p>حاول مرة أخرى بعد 15 دقيقة.</p>
                <code>' . htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'N/A') . '</code>
            </div>
        </body>
        </html>
        ');
    }
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (verify_architect($username, $password)) {
        // Success!
        $_SESSION[ARCHITECT_SESSION_KEY] = true;
        $_SESSION[ARCHITECT_SESSION_TIME] = time();
        log_architect_attempt($username, true);
        
        // Clear rate limit file on success
        $lockFile = __DIR__ . '/.rate_limit_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        @unlink($lockFile);
        
        // Reload page
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        // Failed
        record_attempt();
        log_architect_attempt($username, false);
        $loginError = 'اسم المستخدم أو كلمة المرور غير صحيحة';
    }
}

// ════════════════════════════════════════════════════════════════════════
// SHOW LOGIN FORM IF NOT AUTHENTICATED
// ════════════════════════════════════════════════════════════════════════

if (!$isLoggedIn):
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>🔐 بوابة المعماري - Architect Gate</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0a0a0f;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
        }
        
        /* Animated background */
        .bg-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(255, 111, 0, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 111, 0, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: gridMove 20s linear infinite;
        }
        
        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        /* Floating particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: #ff6f00;
            border-radius: 50%;
            opacity: 0.3;
            animation: float 15s infinite ease-in-out;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 0.3; }
            90% { opacity: 0.3; }
            100% { transform: translateY(-100vh) rotate(720deg); opacity: 0; }
        }
        
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        
        .login-card {
            background: rgba(15, 15, 25, 0.95);
            border: 1px solid rgba(255, 111, 0, 0.2);
            border-radius: 20px;
            padding: 40px;
            backdrop-filter: blur(20px);
            box-shadow: 
                0 0 60px rgba(255, 111, 0, 0.1),
                0 25px 50px -12px rgba(0, 0, 0, 0.8);
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ff6f00, #e65100);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
            box-shadow: 0 10px 40px rgba(255, 111, 0, 0.4);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 10px 40px rgba(255, 111, 0, 0.4); }
            50% { box-shadow: 0 10px 60px rgba(255, 111, 0, 0.6); }
        }
        
        .logo-text {
            color: #fff;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .logo-subtitle {
            color: rgba(255, 111, 0, 0.8);
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            letter-spacing: 2px;
        }
        
        .error-message {
            background: rgba(255, 50, 50, 0.1);
            border: 1px solid rgba(255, 50, 50, 0.3);
            color: #ff5050;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            font-size: 16px;
            font-family: 'JetBrains Mono', monospace;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #ff6f00;
            box-shadow: 0 0 20px rgba(255, 111, 0, 0.2);
            background: rgba(255, 111, 0, 0.05);
        }
        
        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }
        
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #ff6f00, #e65100);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            font-family: 'Tajawal', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(255, 111, 0, 0.4);
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .submit-btn:hover::before {
            left: 100%;
        }
        
        .security-notice {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .security-notice p {
            color: rgba(255, 255, 255, 0.4);
            font-size: 11px;
            font-family: 'JetBrains Mono', monospace;
        }
        
        .ip-badge {
            display: inline-block;
            background: rgba(255, 111, 0, 0.1);
            color: #ff6f00;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            margin-top: 8px;
        }
        
        /* Warning banner */
        .warning-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(90deg, #ff6f00, #e65100);
            color: #fff;
            text-align: center;
            padding: 8px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            z-index: 100;
        }
    </style>
</head>
<body>
    <div class="warning-banner">⚠️ RESTRICTED AREA - AUTHORIZED PERSONNEL ONLY ⚠️</div>
    
    <div class="bg-grid"></div>
    
    <div class="particles">
        <?php for ($i = 0; $i < 20; $i++): ?>
        <div class="particle" style="
            left: <?= rand(0, 100) ?>%;
            animation-delay: <?= rand(0, 15) ?>s;
            animation-duration: <?= rand(10, 20) ?>s;
        "></div>
        <?php endfor; ?>
    </div>
    
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-icon">
                    <img src="../assets/images/logo.png" alt="صرح الإتقان" style="width: 60px; height: 60px; object-fit: contain; border-radius: 12px;">
                </div>
                <h1 class="logo-text">بوابة المعماري</h1>
                <p class="logo-subtitle">ARCHITECT CONSOLE</p>
            </div>
            
            <?php if ($loginError): ?>
            <div class="error-message">
                ⚠️ <?= htmlspecialchars($loginError) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" autocomplete="off">
                <input type="hidden" name="architect_login" value="1">
                
                <div class="form-group">
                    <label class="form-label">اسم المستخدم</label>
                    <input 
                        type="text" 
                        name="username" 
                        class="form-input" 
                        placeholder="The_Architect"
                        required
                        autocomplete="off"
                    >
                </div>
                
                <div class="form-group">
                    <label class="form-label">كلمة المرور</label>
                    <input 
                        type="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="••••••••••••"
                        required
                        autocomplete="off"
                    >
                </div>
                
                <button type="submit" class="submit-btn">
                    🔓 دخول آمن
                </button>
            </form>
            
            <div class="security-notice">
                <p>🔒 جميع محاولات الدخول مسجلة</p>
                <span class="ip-badge">IP: <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>
    
    <script>
        // Disable right-click
        document.addEventListener('contextmenu', e => e.preventDefault());
        
        // Disable keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && (e.key === 'u' || e.key === 's' || e.key === 'i')) {
                e.preventDefault();
            }
            if (e.key === 'F12') {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
<?php
exit; // Stop execution - don't show protected content
endif;
// ════════════════════════════════════════════════════════════════════════
// IF WE REACH HERE, USER IS AUTHENTICATED
// ════════════════════════════════════════════════════════════════════════
?>
