<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - TRAP HANDLER API                                     ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/traps.php';

// ═══════════════════════════════════════════════════════════════════════════════
// AUTHENTICATION
// ═══════════════════════════════════════════════════════════════════════════════

if (!is_logged_in()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'unauthorized']));
}

// ═══════════════════════════════════════════════════════════════════════════════
// CSRF VERIFICATION
// ═══════════════════════════════════════════════════════════════════════════════

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrfToken)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'csrf_invalid']));
}

// ═══════════════════════════════════════════════════════════════════════════════
// REQUEST PARSING
// ═══════════════════════════════════════════════════════════════════════════════

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];
$roleLevel = $_SESSION['role_level'] ?? 1;

// ═══════════════════════════════════════════════════════════════════════════════
// ACTION ROUTER
// ═══════════════════════════════════════════════════════════════════════════════

try {
    switch ($action) {
        
        /**
         * CHECK FOR TRAPS
         * Called periodically by frontend (every 2-5 minutes)
         */
        case 'check_for_traps':
            $context = [
                'page' => $input['page'] ?? 'unknown',
                'gps_errors' => intval($input['gps_errors'] ?? 0),
                'session_minutes' => intval($input['session_minutes'] ?? 0)
            ];
            
            // Special handling for GPS debug trap
            if ($context['gps_errors'] >= 2) {
                $gpsTrap = TrapFactory::create('gps_debug', $userId);
                if ($gpsTrap && $gpsTrap->canTrigger()) {
                    echo json_encode([
                        'success' => true,
                        'has_trap' => true,
                        'trap' => $gpsTrap->render()
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
            
            // Random trap selection
            $trap = TrapFactory::getRandomTrap($userId);
            
            if ($trap) {
                $rendered = $trap->render();
                if (!isset($rendered['error'])) {
                    echo json_encode([
                        'success' => true,
                        'has_trap' => true,
                        'trap' => $rendered
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
            
            echo json_encode([
                'success' => true,
                'has_trap' => false,
                'trap' => null
            ]);
            break;
            
        /**
         * LOG INTERACTION
         * Called when user interacts with a trap
         */
        case 'log_interaction':
            $trapType = $input['trap_type'] ?? '';
            $trapId = $input['trap_id'] ?? '';
            $userAction = $input['user_action'] ?? '';
            $responseTimeMs = intval($input['response_time_ms'] ?? 0);
            $contextData = $input['context'] ?? [];
            
            if (empty($trapType) || empty($userAction)) {
                throw new Exception('Missing required parameters');
            }
            
            $trap = TrapFactory::create($trapType, $userId);
            if (!$trap) {
                throw new Exception('Invalid trap type');
            }
            
            $result = $trap->process($userAction);
            
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
            
        /**
         * GET USER PROFILE (Admin only)
         */
        case 'get_profile':
            if ($roleLevel < 8) {
                throw new Exception('Insufficient permissions');
            }
            
            $targetUserId = intval($input['user_id'] ?? $userId);
            $profile = ProfileManager::getProfile($targetUserId);
            $logs = ProfileManager::getProfileLogs($targetUserId);
            
            echo json_encode([
                'success' => true,
                'profile' => $profile,
                'logs' => $logs
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        /**
         * GET ALL PROFILES (Admin only)
         */
        case 'get_all_profiles':
            if ($roleLevel < 8) {
                throw new Exception('Insufficient permissions');
            }
            
            $profiles = ProfileManager::getAllProfiles();
            $statistics = ProfileManager::getStatistics();
            
            echo json_encode([
                'success' => true,
                'profiles' => $profiles,
                'statistics' => $statistics
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        /**
         * GET TRAP CONFIGURATIONS (Admin only)
         */
        case 'get_configurations':
            if ($roleLevel < 8) {
                throw new Exception('Insufficient permissions');
            }
            
            $configs = Database::fetchAll("SELECT * FROM trap_configurations ORDER BY trap_type");
            
            echo json_encode([
                'success' => true,
                'configurations' => $configs
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        /**
         * UPDATE TRAP CONFIGURATION (Admin only)
         */
        case 'update_configuration':
            if ($roleLevel < 10) {
                throw new Exception('Insufficient permissions');
            }
            
            $configId = intval($input['config_id'] ?? 0);
            $updates = [];
            
            if (isset($input['is_active'])) {
                $updates['is_active'] = intval($input['is_active']);
            }
            if (isset($input['trigger_chance'])) {
                $updates['trigger_chance'] = max(0, min(1, floatval($input['trigger_chance'])));
            }
            if (isset($input['cooldown_minutes'])) {
                $updates['cooldown_minutes'] = max(60, intval($input['cooldown_minutes']));
            }
            
            if ($configId > 0 && !empty($updates)) {
                Database::update('trap_configurations', $updates, 'id = :id', ['id' => $configId]);
            }
            
            echo json_encode(['success' => true]);
            break;
            
        /**
         * FORCE TRAP (Testing - Super Admin only)
         */
        case 'force_trap':
            if ($roleLevel < 10) {
                throw new Exception('Insufficient permissions');
            }
            
            $trapType = $input['trap_type'] ?? '';
            $targetUserId = intval($input['target_user_id'] ?? $userId);
            
            $trap = TrapFactory::create($trapType, $targetUserId);
            if (!$trap) {
                throw new Exception('Invalid trap type');
            }
            
            echo json_encode([
                'success' => true,
                'trap' => $trap->render()
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            throw new Exception('Unknown action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
