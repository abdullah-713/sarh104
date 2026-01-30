<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - MARKET SHOP API                                      ║
 * ║           واجهة سوق المكافآت والميزات                                         ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Version: 1.0.0                                                              ║
 * ║  Endpoint: /api/market/shop.php                                              ║
 * ║  Methods: GET (items), POST (purchase)                                       ║
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
// GET - FETCH MARKET ITEMS
// ═══════════════════════════════════════════════════════════════════════════════

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'items';
    $category = $_GET['category'] ?? null;
    
    try {
        if ($action === 'items') {
            // جلب المنتجات المتاحة
            $sql = "SELECT m.*, 
                           COALESCE(p.purchases_count, 0) as total_purchases,
                           (SELECT COUNT(*) FROM market_purchases mp 
                            WHERE mp.item_id = m.id AND mp.user_id = ? AND mp.status = 'active'
                            AND (mp.expires_at IS NULL OR mp.expires_at > NOW())) as user_owns
                    FROM sarh_market m
                    LEFT JOIN (
                        SELECT item_id, COUNT(*) as purchases_count 
                        FROM market_purchases 
                        GROUP BY item_id
                    ) p ON p.item_id = m.id
                    WHERE m.is_active = 1";
            
            $params = [$user_id];
            
            if ($category) {
                $sql .= " AND m.category = ?";
                $params[] = $category;
            }
            
            $sql .= " ORDER BY m.sort_order ASC, m.created_at DESC";
            
            $items = Database::fetchAll($sql, $params);
            
            // جلب رصيد المستخدم
            $user = Database::fetchOne("SELECT current_points FROM users WHERE id = ?", [$user_id]);
            
            // تصنيف المنتجات
            $categories = [
                'exemptions' => ['name' => 'الإعفاءات', 'icon' => 'bi-shield-check', 'items' => []],
                'privileges' => ['name' => 'الامتيازات', 'icon' => 'bi-star', 'items' => []],
                'bonuses' => ['name' => 'المكافآت', 'icon' => 'bi-gift', 'items' => []],
                'other' => ['name' => 'أخرى', 'icon' => 'bi-box', 'items' => []]
            ];
            
            foreach ($items as $item) {
                $cat = $item['category'] ?? 'other';
                if (!isset($categories[$cat])) {
                    $cat = 'other';
                }
                
                $item['can_afford'] = ($user['current_points'] >= $item['price_points']);
                $item['already_owned'] = ($item['user_owns'] > 0);
                $categories[$cat]['items'][] = $item;
            }
            
            echo json_encode([
                'success' => true,
                'user_points' => intval($user['current_points']),
                'categories' => $categories,
                'items_count' => count($items)
            ], JSON_UNESCAPED_UNICODE);
            
        } elseif ($action === 'my_purchases') {
            // مشترياتي النشطة
            $purchases = Database::fetchAll(
                "SELECT mp.*, m.name, m.description, m.icon, m.category,
                        TIMESTAMPDIFF(HOUR, NOW(), mp.expires_at) as hours_remaining
                 FROM market_purchases mp
                 JOIN sarh_market m ON m.id = mp.item_id
                 WHERE mp.user_id = ? AND mp.status = 'active'
                 AND (mp.expires_at IS NULL OR mp.expires_at > NOW())
                 ORDER BY mp.purchased_at DESC",
                [$user_id]
            );
            
            echo json_encode([
                'success' => true,
                'purchases' => $purchases
            ], JSON_UNESCAPED_UNICODE);
            
        } elseif ($action === 'history') {
            // سجل المشتريات
            $limit = min(intval($_GET['limit'] ?? 20), 100);
            $offset = intval($_GET['offset'] ?? 0);
            
            $purchases = Database::fetchAll(
                "SELECT mp.*, m.name, m.description, m.icon
                 FROM market_purchases mp
                 JOIN sarh_market m ON m.id = mp.item_id
                 WHERE mp.user_id = ?
                 ORDER BY mp.purchased_at DESC
                 LIMIT ? OFFSET ?",
                [$user_id, $limit, $offset]
            );
            
            echo json_encode([
                'success' => true,
                'purchases' => $purchases
            ], JSON_UNESCAPED_UNICODE);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'server_error',
            'message' => 'حدث خطأ في جلب بيانات السوق'
        ], JSON_UNESCAPED_UNICODE);
    }
    
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
// POST - PURCHASE ITEM
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
    $item_id = intval($input['item_id'] ?? 0);
    
    if (!$item_id) {
        http_response_code(400);
        die(json_encode([
            'success' => false,
            'error' => 'invalid_item',
            'message' => 'المنتج غير صالح'
        ], JSON_UNESCAPED_UNICODE));
    }
    
    try {
        Database::beginTransaction();
        
        // جلب المنتج
        $item = Database::fetchOne(
            "SELECT * FROM sarh_market WHERE id = ? AND is_active = 1",
            [$item_id]
        );
        
        if (!$item) {
            throw new Exception('المنتج غير موجود أو غير متاح');
        }
        
        // التحقق من الكمية
        if ($item['stock_limit'] !== null && $item['stock_limit'] <= 0) {
            throw new Exception('نفدت الكمية من هذا المنتج');
        }
        
        // التحقق من عدم الشراء المسبق (للمنتجات غير القابلة للتكرار)
        if (!$item['is_stackable']) {
            $existing = Database::fetchOne(
                "SELECT id FROM market_purchases 
                 WHERE user_id = ? AND item_id = ? AND status = 'active'
                 AND (expires_at IS NULL OR expires_at > NOW())",
                [$user_id, $item_id]
            );
            
            if ($existing) {
                throw new Exception('لديك هذا المنتج مفعّل بالفعل');
            }
        }
        
        // ═══════════════════════════════════════════════════════════════════════════
        // RACE CONDITION PROTECTION: Lock user row for update to prevent double-spend
        // ═══════════════════════════════════════════════════════════════════════════
        // Use FOR UPDATE to lock the row until transaction commits
        $user = Database::fetchOne(
            "SELECT current_points, full_name FROM users WHERE id = ? FOR UPDATE",
            [$user_id]
        );
        
        if ($user['current_points'] < $item['price_points']) {
            throw new Exception('رصيد النقاط غير كافٍ. تحتاج ' . $item['price_points'] . ' نقطة');
        }
        
        // خصم النقاط (row is locked, so no concurrent updates possible)
        Database::query(
            "UPDATE users SET current_points = current_points - ? WHERE id = ?",
            [$item['price_points'], $user_id]
        );
        
        // حساب تاريخ الانتهاء
        $expires_at = null;
        if ($item['duration_hours']) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$item['duration_hours']} hours"));
        }
        
        // تسجيل الشراء
        $purchase_id = Database::insert('market_purchases', [
            'user_id' => $user_id,
            'item_id' => $item_id,
            'points_paid' => $item['price_points'],
            'status' => 'active',
            'purchased_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expires_at,
            'activated_at' => date('Y-m-d H:i:s')
        ]);
        
        // تقليل المخزون إن وجد
        if ($item['stock_limit'] !== null) {
            Database::query(
                "UPDATE sarh_market SET stock_limit = stock_limit - 1 WHERE id = ?",
                [$item_id]
            );
        }
        
        // تطبيق التأثير حسب نوع المنتج
        $effect_applied = applyItemEffect($user_id, $item, $purchase_id, $expires_at);
        
        // تسجيل النشاط
        if (function_exists('log_activity')) {
            log_activity(
                'market_purchase',
                'market',
                $purchase_id,
                [
                    'item_name' => $item['name'],
                    'points_paid' => $item['price_points'],
                    'effect_type' => $item['effect_type']
                ]
            );
        }
        
        Database::commit();
        
        // جلب الرصيد الجديد
        $new_points = Database::fetchValue("SELECT current_points FROM users WHERE id = ?", [$user_id]);
        
        echo json_encode([
            'success' => true,
            'message' => "🎉 تم شراء \"{$item['name']}\" بنجاح!",
            'purchase_id' => $purchase_id,
            'item' => [
                'name' => $item['name'],
                'icon' => $item['icon'],
                'effect_type' => $item['effect_type']
            ],
            'points_paid' => $item['price_points'],
            'new_balance' => intval($new_points),
            'expires_at' => $expires_at,
            'effect_applied' => $effect_applied
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        Database::rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'purchase_failed',
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
// APPLY ITEM EFFECT
// ═══════════════════════════════════════════════════════════════════════════════

function applyItemEffect($user_id, $item, $purchase_id, $expires_at) {
    $effect_type = $item['effect_type'] ?? '';
    $effect_value = $item['effect_value'] ?? null;
    $effect_data = json_decode($item['effect_data'] ?? '{}', true);
    
    switch ($effect_type) {
        case 'late_exemption':
            // إعفاء من التأخير - يُطبق تلقائياً عند تسجيل الحضور
            Database::query(
                "INSERT INTO user_active_effects (user_id, effect_type, purchase_id, expires_at, created_at)
                 VALUES (?, 'late_exemption', ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)",
                [$user_id, $purchase_id, $expires_at]
            );
            return ['type' => 'late_exemption', 'message' => 'سيتم تجاهل التأخير القادم'];
            
        case 'early_leave_exemption':
            // إعفاء من الخروج المبكر
            Database::query(
                "INSERT INTO user_active_effects (user_id, effect_type, purchase_id, expires_at, created_at)
                 VALUES (?, 'early_leave_exemption', ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)",
                [$user_id, $purchase_id, $expires_at]
            );
            return ['type' => 'early_leave_exemption', 'message' => 'سيتم تجاهل الخروج المبكر القادم'];
            
        case 'points_multiplier':
            // مضاعف النقاط
            $multiplier = floatval($effect_value ?? 2);
            Database::query(
                "INSERT INTO user_active_effects (user_id, effect_type, effect_value, purchase_id, expires_at, created_at)
                 VALUES (?, 'points_multiplier', ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE effect_value = VALUES(effect_value), expires_at = VALUES(expires_at)",
                [$user_id, $multiplier, $purchase_id, $expires_at]
            );
            return ['type' => 'points_multiplier', 'multiplier' => $multiplier, 'message' => "نقاطك مضاعفة x{$multiplier}"];
            
        case 'immunity':
            // حصانة مؤقتة
            Database::query(
                "UPDATE users SET has_immunity = 1, immunity_until = ? WHERE id = ?",
                [$expires_at, $user_id]
            );
            return ['type' => 'immunity', 'until' => $expires_at, 'message' => 'أنت محمي من العقوبات'];
            
        case 'bonus_points':
            // نقاط إضافية فورية
            $bonus = intval($effect_value ?? 0);
            Database::query(
                "UPDATE users SET current_points = current_points + ? WHERE id = ?",
                [$bonus, $user_id]
            );
            return ['type' => 'bonus_points', 'amount' => $bonus, 'message' => "حصلت على {$bonus} نقطة إضافية"];
            
        case 'vacation_day':
            // يوم إجازة مدفوعة
            $vacation_date = $effect_data['date'] ?? date('Y-m-d', strtotime('+1 day'));
            Database::insert('employee_vacations', [
                'user_id' => $user_id,
                'type' => 'purchased',
                'start_date' => $vacation_date,
                'end_date' => $vacation_date,
                'status' => 'approved',
                'purchase_id' => $purchase_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            return ['type' => 'vacation_day', 'date' => $vacation_date, 'message' => "إجازة مجدولة: {$vacation_date}"];
            
        case 'custom_title':
            // لقب مخصص
            $title = $effect_data['title'] ?? 'VIP';
            Database::query(
                "UPDATE users SET custom_title = ? WHERE id = ?",
                [$title, $user_id]
            );
            return ['type' => 'custom_title', 'title' => $title, 'message' => "لقبك الجديد: {$title}"];
            
        default:
            return ['type' => 'none', 'message' => 'تم تفعيل المنتج'];
    }
}

http_response_code(405);
echo json_encode([
    'success' => false,
    'error' => 'method_not_allowed',
    'message' => 'طريقة الطلب غير مدعومة'
], JSON_UNESCAPED_UNICODE);
