<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - WALLET API                                           ║
 * ║           واجهة المحفظة الإلكترونية                                           ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Version: 1.0.0                                                              ║
 * ║  Endpoint: /api/market/wallet.php                                            ║
 * ║  Methods: GET (balance), POST (transfer, convert)                            ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

// ═══════════════════════════════════════════════════════════════════════════════
// AUTHENTICATION CHECK
// ═══════════════════════════════════════════════════════════════════════════════

if (!is_logged_in()) {
    http_response_code(401);
    die(json_encode([
        'success' => false,
        'error' => 'unauthorized',
        'message' => 'غير مصرح بالوصول'
    ], JSON_UNESCAPED_UNICODE));
}

$user_id = intval($_SESSION['user_id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'];

// ═══════════════════════════════════════════════════════════════════════════════
// GET - FETCH WALLET BALANCE & HISTORY
// ═══════════════════════════════════════════════════════════════════════════════

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'balance';
    
    try {
        if ($action === 'balance') {
            // جلب رصيد المحفظة
            $wallet = Database::fetchOne(
                "SELECT w.*, u.current_points, u.full_name
                 FROM employee_wallets w
                 RIGHT JOIN users u ON u.id = w.user_id
                 WHERE u.id = ?",
                [$user_id]
            );
            
            if (!$wallet) {
                // إنشاء محفظة جديدة
                Database::insert('employee_wallets', [
                    'user_id' => $user_id,
                    'balance' => 0,
                    'total_earned' => 0,
                    'total_spent' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $wallet = [
                    'balance' => 0,
                    'total_earned' => 0,
                    'total_spent' => 0,
                    'current_points' => 0
                ];
            }
            
            // إعدادات التحويل
            $points_to_sar_rate = floatval(get_setting('points_to_sar_rate', '0.1'));
            $min_convertible_points = intval(get_setting('min_convertible_points', '100'));
            
            echo json_encode([
                'success' => true,
                'wallet' => [
                    'balance' => floatval($wallet['balance'] ?? 0),
                    'current_points' => intval($wallet['current_points'] ?? 0),
                    'total_earned' => floatval($wallet['total_earned'] ?? 0),
                    'total_spent' => floatval($wallet['total_spent'] ?? 0),
                    'convertible_value' => round($wallet['current_points'] * $points_to_sar_rate, 2),
                    'points_to_sar_rate' => $points_to_sar_rate,
                    'min_convertible_points' => $min_convertible_points
                ]
            ], JSON_UNESCAPED_UNICODE);
            
        } elseif ($action === 'history') {
            // سجل المعاملات
            $limit = min(intval($_GET['limit'] ?? 20), 100);
            $offset = intval($_GET['offset'] ?? 0);
            
            $transactions = Database::fetchAll(
                "SELECT * FROM wallet_transactions 
                 WHERE user_id = ? 
                 ORDER BY created_at DESC 
                 LIMIT ? OFFSET ?",
                [$user_id, $limit, $offset]
            );
            
            $total = Database::fetchValue(
                "SELECT COUNT(*) FROM wallet_transactions WHERE user_id = ?",
                [$user_id]
            );
            
            echo json_encode([
                'success' => true,
                'transactions' => $transactions,
                'total' => intval($total),
                'limit' => $limit,
                'offset' => $offset
            ], JSON_UNESCAPED_UNICODE);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'server_error',
            'message' => 'حدث خطأ في جلب بيانات المحفظة'
        ], JSON_UNESCAPED_UNICODE);
    }
    
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
// POST - CONVERT POINTS TO MONEY
// ═══════════════════════════════════════════════════════════════════════════════

if ($method === 'POST') {
    // CSRF verification
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($csrf_token) || !verify_csrf($csrf_token)) {
        http_response_code(403);
        die(json_encode([
            'success' => false,
            'error' => 'csrf_invalid',
            'message' => 'رمز الأمان غير صالح'
        ], JSON_UNESCAPED_UNICODE));
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    try {
        Database::beginTransaction();
        
        if ($action === 'convert_points') {
            // تحويل النقاط إلى رصيد
            $points_to_convert = intval($input['points'] ?? 0);
            
            // التحقق من الإعدادات
            $points_to_sar_rate = floatval(get_setting('points_to_sar_rate', '0.1'));
            $min_convertible_points = intval(get_setting('min_convertible_points', '100'));
            
            if ($points_to_convert < $min_convertible_points) {
                throw new Exception("الحد الأدنى للتحويل هو {$min_convertible_points} نقطة");
            }
            
            // التحقق من رصيد النقاط
            $user = Database::fetchOne("SELECT current_points FROM users WHERE id = ?", [$user_id]);
            
            if ($user['current_points'] < $points_to_convert) {
                throw new Exception('رصيد النقاط غير كافٍ');
            }
            
            // حساب المبلغ
            $amount = round($points_to_convert * $points_to_sar_rate, 2);
            
            // خصم النقاط
            Database::query(
                "UPDATE users SET current_points = current_points - ? WHERE id = ?",
                [$points_to_convert, $user_id]
            );
            
            // التحقق من وجود المحفظة وإنشاؤها إذا لم تكن موجودة
            $existing_wallet = Database::fetchOne(
                "SELECT id FROM employee_wallets WHERE user_id = ?",
                [$user_id]
            );
            
            if (!$existing_wallet) {
                // إنشاء محفظة جديدة إذا لم تكن موجودة
                Database::insert('employee_wallets', [
                    'user_id' => $user_id,
                    'balance' => 0,
                    'total_earned' => 0,
                    'total_spent' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // تحديث رصيد المحفظة
            Database::query(
                "UPDATE employee_wallets 
                 SET balance = balance + ?, 
                     total_earned = total_earned + ?,
                     updated_at = NOW()
                 WHERE user_id = ?",
                [$amount, $amount, $user_id]
            );
            
            // تسجيل المعاملة
            Database::insert('wallet_transactions', [
                'user_id' => $user_id,
                'type' => 'points_conversion',
                'amount' => $amount,
                'points_used' => $points_to_convert,
                'description' => "تحويل {$points_to_convert} نقطة إلى {$amount} ريال",
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Database::commit();
            
            // جلب الرصيد الجديد
            $new_balance = Database::fetchOne(
                "SELECT w.balance, u.current_points 
                 FROM employee_wallets w 
                 JOIN users u ON u.id = w.user_id 
                 WHERE w.user_id = ?",
                [$user_id]
            );
            
            echo json_encode([
                'success' => true,
                'message' => "تم تحويل {$points_to_convert} نقطة إلى {$amount} ريال",
                'converted_amount' => $amount,
                'new_balance' => floatval($new_balance['balance']),
                'new_points' => intval($new_balance['current_points'])
            ], JSON_UNESCAPED_UNICODE);
            
        } else {
            throw new Exception('العملية غير مدعومة');
        }
        
    } catch (Exception $e) {
        Database::rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'operation_failed',
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    
    exit;
}

http_response_code(405);
echo json_encode([
    'success' => false,
    'error' => 'method_not_allowed',
    'message' => 'طريقة الطلب غير مدعومة'
], JSON_UNESCAPED_UNICODE);
