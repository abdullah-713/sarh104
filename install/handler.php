<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║                     صرح الإتقان - SARH AL-ITQAN                              ║
 * ║                     INSTALLATION HANDLER v1.8.0                              ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    die(json_encode(['success' => false, 'message' => 'طلب غير صالح']));
}

$action = $input['action'];

/**
 * Test Database Connection
 */
if ($action === 'test_db') {
    $host = $input['host'] ?? '';
    $name = $input['name'] ?? '';
    $user = $input['user'] ?? '';
    $pass = $input['pass'] ?? '';
    
    if (empty($host) || empty($name) || empty($user)) {
        die(json_encode(['success' => false, 'message' => 'بيانات ناقصة']));
    }
    
    try {
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_520_ci"
        ]);
        
        // Test if we can create tables
        $pdo->exec("CREATE TABLE IF NOT EXISTS _test_install (id INT) ENGINE=InnoDB");
        $pdo->exec("DROP TABLE IF EXISTS _test_install");
        
        echo json_encode(['success' => true, 'message' => 'اتصال ناجح']);
    } catch (PDOException $e) {
        $errorMsg = 'فشل الاتصال';
        if (strpos($e->getMessage(), 'Access denied') !== false) {
            $errorMsg = 'اسم المستخدم أو كلمة المرور غير صحيحة';
        } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
            $errorMsg = 'قاعدة البيانات غير موجودة';
        } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
            $errorMsg = 'لا يمكن الاتصال بالخادم';
        }
        echo json_encode(['success' => false, 'message' => $errorMsg]);
    }
    exit;
}

/**
 * Run Full Installation
 */
if ($action === 'install') {
    $db = $input['db'] ?? [];
    $company = $input['company'] ?? [];
    $branch = $input['branch'] ?? [];
    $admin = $input['admin'] ?? [];
    
    // Validate
    if (empty($db['host']) || empty($db['name']) || empty($db['user'])) {
        die(json_encode(['success' => false, 'message' => 'بيانات قاعدة البيانات ناقصة']));
    }
    
    if (empty($company['name'])) {
        die(json_encode(['success' => false, 'message' => 'اسم الشركة مطلوب']));
    }
    
    if (empty($admin['name']) || empty($admin['username']) || empty($admin['email']) || empty($admin['password'])) {
        die(json_encode(['success' => false, 'message' => 'بيانات المدير ناقصة']));
    }
    
    if (strlen($admin['password']) < 8) {
        die(json_encode(['success' => false, 'message' => 'كلمة المرور قصيرة جداً']));
    }
    
    try {
        // Connect to database
        $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['user'], $db['pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_520_ci"
        ]);
        
        // ====================================================================
        // STEP 1: Execute master.sql
        // ====================================================================
        $sqlFile = __DIR__ . '/master.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception('ملف master.sql غير موجود');
        }
        
        $sql = file_get_contents($sqlFile);
        
        // Split by delimiter for stored procedures
        $delimiter = ';';
        $statements = [];
        $buffer = '';
        
        foreach (explode("\n", $sql) as $line) {
            // Handle DELIMITER changes
            if (preg_match('/^DELIMITER\s+(\S+)/i', trim($line), $matches)) {
                $delimiter = $matches[1];
                continue;
            }
            
            $buffer .= $line . "\n";
            
            // Check if statement ends
            if (substr(trim($buffer), -strlen($delimiter)) === $delimiter) {
                $statement = trim(substr(trim($buffer), 0, -strlen($delimiter)));
                if (!empty($statement) && !preg_match('/^(--|#|\/\*)/', trim($statement))) {
                    $statements[] = $statement;
                }
                $buffer = '';
            }
        }
        
        // Execute statements
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) continue;
            if (preg_match('/^SELECT\s+.*AS\s+status/i', $statement)) continue; // Skip status messages
            
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignore duplicate entry errors for INSERT ... ON DUPLICATE KEY
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    // Log but don't fail
                    error_log("SQL Warning: " . $e->getMessage());
                }
            }
        }
        
        // ====================================================================
        // STEP 2: Update Company Settings
        // ====================================================================
        $companyNameJson = json_encode($company['name'], JSON_UNESCAPED_UNICODE);
        $logoJson = json_encode($company['logo'] ?? '', JSON_UNESCAPED_UNICODE);
        
        $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'app_name'")
            ->execute([$companyNameJson]);
        
        $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'app_logo'")
            ->execute([$logoJson]);
        
        // ====================================================================
        // STEP 3: Create/Update Main Branch
        // ====================================================================
        $branchLat = floatval($branch['lat'] ?? 24.7136);
        $branchLng = floatval($branch['lng'] ?? 46.6753);
        
        $branchSettings = json_encode([
            'work_start' => '08:00',
            'work_end' => '17:00',
            'grace_period' => 5,
            'geofence_radius' => 100
        ]);
        
        // Check if branch exists
        $stmt = $pdo->query("SELECT COUNT(*) FROM branches WHERE id = 1");
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            $pdo->prepare("UPDATE branches SET name = ?, latitude = ?, longitude = ?, settings = ? WHERE id = 1")
                ->execute([$company['name'] . ' - المقر الرئيسي', $branchLat, $branchLng, $branchSettings]);
        } else {
            $pdo->prepare("INSERT INTO branches (id, name, code, latitude, longitude, settings, is_active) VALUES (1, ?, 'HQ', ?, ?, ?, 1)")
                ->execute([$company['name'] . ' - المقر الرئيسي', $branchLat, $branchLng, $branchSettings]);
        }
        
        // ====================================================================
        // STEP 4: Create Super Admin
        // ====================================================================
        $passwordHash = password_hash($admin['password'], PASSWORD_DEFAULT);
        $empCode = 'SA' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Get Super Admin role ID
        $stmt = $pdo->query("SELECT id FROM roles WHERE role_level = 10 LIMIT 1");
        $roleId = $stmt->fetchColumn();
        if (!$roleId) {
            $roleId = 5; // Fallback
        }
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$admin['username'], $admin['email']]);
        $userExists = $stmt->fetchColumn() > 0;
        
        if ($userExists) {
            // Update existing
            $pdo->prepare("UPDATE users SET full_name = ?, password_hash = ?, role_id = ?, branch_id = 1, is_active = 1 WHERE username = ? OR email = ?")
                ->execute([$admin['name'], $passwordHash, $roleId, $admin['username'], $admin['email']]);
        } else {
            // Insert new
            $pdo->prepare("INSERT INTO users (emp_code, username, email, password_hash, full_name, role_id, branch_id, is_active) VALUES (?, ?, ?, ?, ?, ?, 1, 1)")
                ->execute([$empCode, $admin['username'], $admin['email'], $passwordHash, $admin['name'], $roleId]);
        }
        
        // ====================================================================
        // STEP 5: Generate config/database.php
        // ====================================================================
        $configContent = <<<PHP
<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║                     صرح الإتقان - SARH AL-ITQAN                              ║
 * ║                     Database Configuration                                    ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Generated by Installation Wizard on: %s
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

// Prevent direct access
if (!defined('SARH_SYSTEM')) {
    define('SARH_SYSTEM', true);
}

// Database Configuration
define('DB_HOST', '%s');
define('DB_NAME', '%s');
define('DB_USER', '%s');
define('DB_PASS', '%s');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_520_ci');

/**
 * Database Connection Class
 */
class Database {
    private static \$instance = null;
    private \$connection = null;
    
    private function __construct() {
        try {
            \$dsn = sprintf(
                "mysql:host=%%s;dbname=%%s;charset=%%s",
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            
            \$options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_COLLATION
            ];
            
            \$this->connection = new PDO(\$dsn, DB_USER, DB_PASS, \$options);
        } catch (PDOException \$e) {
            error_log('Database Connection Error: ' . \$e->getMessage());
            throw new Exception('فشل الاتصال بقاعدة البيانات');
        }
    }
    
    public static function getInstance(): self {
        if (self::\$instance === null) {
            self::\$instance = new self();
        }
        return self::\$instance;
    }
    
    public function getConnection(): PDO {
        return \$this->connection;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Get PDO Connection (Helper Function)
 */
function get_db(): PDO {
    return Database::getInstance()->getConnection();
}
PHP;
        
        $configContent = sprintf(
            $configContent,
            date('Y-m-d H:i:s'),
            addslashes($db['host']),
            addslashes($db['name']),
            addslashes($db['user']),
            addslashes($db['pass'] ?? '')
        );
        
        $configPath = dirname(__DIR__) . '/config/database.php';
        
        // Create config directory if not exists
        if (!is_dir(dirname($configPath))) {
            mkdir(dirname($configPath), 0755, true);
        }
        
        if (file_put_contents($configPath, $configContent) === false) {
            throw new Exception('فشل في إنشاء ملف الإعدادات');
        }
        
        // ====================================================================
        // SUCCESS
        // ====================================================================
        echo json_encode([
            'success' => true,
            'message' => 'تم التثبيت بنجاح',
            'data' => [
                'company' => $company['name'],
                'admin_username' => $admin['username'],
                'admin_email' => $admin['email']
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Invalid action
echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
