<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * 💬 API غرف الدردشة - Chat Rooms API
 * ═══════════════════════════════════════════════════════════════
 */

require_once dirname(dirname(__DIR__)) . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'يجب تسجيل الدخول'], 401);
}

$userId = current_user_id();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($action, $userId);
            break;
        case 'POST':
            handlePostRequest($action, $userId);
            break;
        case 'PUT':
            handlePutRequest($action, $userId);
            break;
        case 'DELETE':
            handleDeleteRequest($action, $userId);
            break;
        default:
            json_response(['success' => false, 'message' => 'طريقة غير مدعومة'], 405);
    }
} catch (Exception $e) {
    error_log("Chat API Error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'حدث خطأ في الخادم'], 500);
}

/**
 * معالجة طلبات GET
 */
function handleGetRequest(string $action, int $userId): void {
    switch ($action) {
        case 'list':
            // جلب قائمة الغرف التي ينتمي إليها المستخدم
            $rooms = Database::fetchAll("
                SELECT 
                    cr.id,
                    cr.name,
                    cr.description,
                    cr.type,
                    cr.avatar,
                    cr.last_message_at,
                    crm.role as my_role,
                    crm.notifications_enabled,
                    crm.last_read_at,
                    (SELECT COUNT(*) FROM chat_messages WHERE room_id = cr.id AND created_at > COALESCE(crm.last_read_at, '1970-01-01')) as unread_count,
                    (SELECT content FROM chat_messages WHERE room_id = cr.id ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT u.full_name FROM chat_messages cm JOIN users u ON cm.user_id = u.id WHERE cm.room_id = cr.id ORDER BY cm.created_at DESC LIMIT 1) as last_message_by
                FROM chat_rooms cr
                INNER JOIN chat_room_members crm ON cr.id = crm.room_id AND crm.user_id = :user_id
                WHERE cr.is_active = 1
                ORDER BY COALESCE(cr.last_message_at, '1970-01-01') DESC, cr.created_at DESC
            ", ['user_id' => $userId]);
            
            json_response(['success' => true, 'rooms' => $rooms]);
            break;
            
        case 'details':
            $roomId = (int) ($_GET['room_id'] ?? 0);
            if ($roomId <= 0) {
                json_response(['success' => false, 'message' => 'معرف الغرفة مطلوب'], 400);
            }
            
            // التحقق من العضوية
            if (!isRoomMember($roomId, $userId)) {
                json_response(['success' => false, 'message' => 'لست عضواً في هذه الغرفة'], 403);
            }
            
            $room = Database::fetchOne("
                SELECT cr.*, crm.role as my_role, crm.notifications_enabled
                FROM chat_rooms cr
                INNER JOIN chat_room_members crm ON cr.id = crm.room_id AND crm.user_id = :user_id
                WHERE cr.id = :room_id
            ", ['room_id' => $roomId, 'user_id' => $userId]);
            
            if (!$room) {
                json_response(['success' => false, 'message' => 'الغرفة غير موجودة'], 404);
            }
            
            // جلب الأعضاء
            $members = Database::fetchAll("
                SELECT 
                    crm.user_id,
                    crm.role,
                    crm.nickname,
                    crm.joined_at,
                    u.full_name,
                    u.avatar,
                    u.is_online,
                    u.job_title
                FROM chat_room_members crm
                INNER JOIN users u ON crm.user_id = u.id
                WHERE crm.room_id = :room_id
                ORDER BY FIELD(crm.role, 'owner', 'admin', 'moderator', 'member'), u.full_name
            ", ['room_id' => $roomId]);
            
            // الرسائل المثبتة
            $pinnedMessages = Database::fetchAll("
                SELECT 
                    cm.*,
                    u.full_name as sender_name,
                    u.avatar as sender_avatar
                FROM chat_pinned_messages cpm
                INNER JOIN chat_messages cm ON cpm.message_id = cm.id
                INNER JOIN users u ON cm.user_id = u.id
                WHERE cpm.room_id = :room_id
                ORDER BY cpm.pinned_at DESC
            ", ['room_id' => $roomId]);
            
            $room['members'] = $members;
            $room['pinned_messages'] = $pinnedMessages;
            $room['members_count'] = count($members);
            
            json_response(['success' => true, 'room' => $room]);
            break;
            
        case 'search':
            $query = trim($_GET['q'] ?? '');
            if (strlen($query) < 2) {
                json_response(['success' => false, 'message' => 'كلمة البحث قصيرة جداً'], 400);
            }
            
            // البحث في الغرف العامة أو الغرف التي ينتمي إليها
            $rooms = Database::fetchAll("
                SELECT cr.id, cr.name, cr.description, cr.type,
                       (SELECT COUNT(*) FROM chat_room_members WHERE room_id = cr.id) as members_count
                FROM chat_rooms cr
                WHERE cr.is_active = 1
                AND (cr.type = 'public' OR cr.id IN (
                    SELECT room_id FROM chat_room_members WHERE user_id = :user_id
                ))
                AND (cr.name LIKE :query OR cr.description LIKE :query)
                LIMIT 20
            ", ['user_id' => $userId, 'query' => "%{$query}%"]);
            
            json_response(['success' => true, 'rooms' => $rooms]);
            break;
            
        case 'available':
            // الغرف المتاحة للانضمام (العامة + فرع المستخدم)
            $userBranch = $_SESSION['branch_id'] ?? 0;
            $rooms = Database::fetchAll("
                SELECT cr.id, cr.name, cr.description, cr.type,
                       (SELECT COUNT(*) FROM chat_room_members WHERE room_id = cr.id) as members_count
                FROM chat_rooms cr
                WHERE cr.is_active = 1
                AND cr.id NOT IN (SELECT room_id FROM chat_room_members WHERE user_id = :user_id)
                AND (cr.type = 'public' OR (cr.type = 'branch' AND cr.branch_id = :branch_id))
                LIMIT 50
            ", ['user_id' => $userId, 'branch_id' => $userBranch]);
            
            json_response(['success' => true, 'rooms' => $rooms]);
            break;
            
        default:
            json_response(['success' => false, 'message' => 'إجراء غير معروف'], 400);
    }
}

/**
 * معالجة طلبات POST
 */
function handlePostRequest(string $action, int $userId): void {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    switch ($action) {
        case 'create':
            // إنشاء غرفة جديدة (يتطلب صلاحيات)
            if (current_role_level() < 2) {
                json_response(['success' => false, 'message' => 'ليس لديك صلاحية لإنشاء غرف'], 403);
            }
            
            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            $type = $input['type'] ?? 'private';
            $memberIds = $input['members'] ?? [];
            
            if (empty($name) || strlen($name) < 3) {
                json_response(['success' => false, 'message' => 'اسم الغرفة مطلوب (3 أحرف على الأقل)'], 400);
            }
            
            if (!in_array($type, ['public', 'private', 'branch', 'department'])) {
                $type = 'private';
            }
            
            Database::beginTransaction();
            try {
                $roomId = Database::insert('chat_rooms', [
                    'name' => $name,
                    'description' => $description,
                    'type' => $type,
                    'branch_id' => $type === 'branch' ? ($_SESSION['branch_id'] ?? null) : null,
                    'created_by' => $userId,
                    'settings' => json_encode(['allow_reactions' => true, 'allow_replies' => true])
                ]);
                
                // إضافة المنشئ كمالك
                Database::insert('chat_room_members', [
                    'room_id' => $roomId,
                    'user_id' => $userId,
                    'role' => 'owner'
                ]);
                
                // إضافة الأعضاء المحددين
                foreach ($memberIds as $memberId) {
                    if ($memberId != $userId) {
                        Database::insert('chat_room_members', [
                            'room_id' => $roomId,
                            'user_id' => (int) $memberId,
                            'role' => 'member'
                        ]);
                    }
                }
                
                Database::commit();
                
                // رسالة نظام
                Database::insert('chat_messages', [
                    'room_id' => $roomId,
                    'user_id' => $userId,
                    'message_type' => 'system',
                    'content' => 'تم إنشاء الغرفة'
                ]);
                
                json_response(['success' => true, 'room_id' => $roomId, 'message' => 'تم إنشاء الغرفة بنجاح']);
                
            } catch (Exception $e) {
                Database::rollback();
                throw $e;
            }
            break;
            
        case 'join':
            $roomId = (int) ($input['room_id'] ?? 0);
            if ($roomId <= 0) {
                json_response(['success' => false, 'message' => 'معرف الغرفة مطلوب'], 400);
            }
            
            // التحقق من أن الغرفة قابلة للانضمام
            $room = Database::fetchOne(
                "SELECT * FROM chat_rooms WHERE id = :id AND is_active = 1",
                ['id' => $roomId]
            );
            
            if (!$room) {
                json_response(['success' => false, 'message' => 'الغرفة غير موجودة'], 404);
            }
            
            if ($room['type'] === 'private') {
                json_response(['success' => false, 'message' => 'هذه غرفة خاصة'], 403);
            }
            
            if ($room['type'] === 'branch' && $room['branch_id'] != ($_SESSION['branch_id'] ?? 0)) {
                json_response(['success' => false, 'message' => 'هذه الغرفة لفرع آخر'], 403);
            }
            
            // التحقق من العضوية
            if (isRoomMember($roomId, $userId)) {
                json_response(['success' => false, 'message' => 'أنت عضو بالفعل'], 400);
            }
            
            Database::insert('chat_room_members', [
                'room_id' => $roomId,
                'user_id' => $userId,
                'role' => 'member'
            ]);
            
            // رسالة نظام
            $userName = $_SESSION['full_name'] ?? 'مستخدم';
            Database::insert('chat_messages', [
                'room_id' => $roomId,
                'user_id' => $userId,
                'message_type' => 'system',
                'content' => "انضم {$userName} إلى الغرفة"
            ]);
            
            json_response(['success' => true, 'message' => 'تم الانضمام بنجاح']);
            break;
            
        case 'leave':
            $roomId = (int) ($input['room_id'] ?? 0);
            if ($roomId <= 0) {
                json_response(['success' => false, 'message' => 'معرف الغرفة مطلوب'], 400);
            }
            
            // التحقق من العضوية
            $membership = Database::fetchOne(
                "SELECT * FROM chat_room_members WHERE room_id = :room_id AND user_id = :user_id",
                ['room_id' => $roomId, 'user_id' => $userId]
            );
            
            if (!$membership) {
                json_response(['success' => false, 'message' => 'لست عضواً في هذه الغرفة'], 400);
            }
            
            if ($membership['role'] === 'owner') {
                json_response(['success' => false, 'message' => 'لا يمكن لمالك الغرفة مغادرتها'], 400);
            }
            
            Database::delete('chat_room_members', 'room_id = :room_id AND user_id = :user_id', [
                'room_id' => $roomId,
                'user_id' => $userId
            ]);
            
            // رسالة نظام
            $userName = $_SESSION['full_name'] ?? 'مستخدم';
            Database::insert('chat_messages', [
                'room_id' => $roomId,
                'user_id' => $userId,
                'message_type' => 'system',
                'content' => "غادر {$userName} الغرفة"
            ]);
            
            json_response(['success' => true, 'message' => 'تمت المغادرة بنجاح']);
            break;
            
        case 'add_member':
            $roomId = (int) ($input['room_id'] ?? 0);
            $newMemberId = (int) ($input['user_id'] ?? 0);
            
            if (!canManageRoom($roomId, $userId)) {
                json_response(['success' => false, 'message' => 'ليس لديك صلاحية'], 403);
            }
            
            if (isRoomMember($roomId, $newMemberId)) {
                json_response(['success' => false, 'message' => 'المستخدم عضو بالفعل'], 400);
            }
            
            Database::insert('chat_room_members', [
                'room_id' => $roomId,
                'user_id' => $newMemberId,
                'role' => 'member'
            ]);
            
            // إشعار المستخدم الجديد
            Database::insert('chat_notifications', [
                'user_id' => $newMemberId,
                'room_id' => $roomId,
                'type' => 'added_to_room',
                'content' => 'تمت إضافتك إلى غرفة جديدة'
            ]);
            
            json_response(['success' => true, 'message' => 'تم إضافة العضو']);
            break;
            
        default:
            json_response(['success' => false, 'message' => 'إجراء غير معروف'], 400);
    }
}

/**
 * معالجة طلبات PUT
 */
function handlePutRequest(string $action, int $userId): void {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $roomId = (int) ($input['room_id'] ?? 0);
    
    if ($roomId <= 0) {
        json_response(['success' => false, 'message' => 'معرف الغرفة مطلوب'], 400);
    }
    
    switch ($action) {
        case 'update':
            if (!canManageRoom($roomId, $userId)) {
                json_response(['success' => false, 'message' => 'ليس لديك صلاحية'], 403);
            }
            
            $updates = [];
            if (isset($input['name'])) $updates['name'] = trim($input['name']);
            if (isset($input['description'])) $updates['description'] = trim($input['description']);
            
            if (empty($updates)) {
                json_response(['success' => false, 'message' => 'لا توجد تحديثات'], 400);
            }
            
            Database::update('chat_rooms', $updates, 'id = :id', ['id' => $roomId]);
            json_response(['success' => true, 'message' => 'تم التحديث']);
            break;
            
        case 'settings':
            // تحديث إعدادات الإشعارات للعضو
            $notifications = (bool) ($input['notifications_enabled'] ?? true);
            
            Database::update('chat_room_members', 
                ['notifications_enabled' => $notifications ? 1 : 0], 
                'room_id = :room_id AND user_id = :user_id', 
                ['room_id' => $roomId, 'user_id' => $userId]
            );
            
            json_response(['success' => true, 'message' => 'تم تحديث الإعدادات']);
            break;
            
        case 'read':
            // تحديث آخر قراءة
            Database::update('chat_room_members', 
                ['last_read_at' => date('Y-m-d H:i:s')], 
                'room_id = :room_id AND user_id = :user_id', 
                ['room_id' => $roomId, 'user_id' => $userId]
            );
            
            json_response(['success' => true]);
            break;
            
        default:
            json_response(['success' => false, 'message' => 'إجراء غير معروف'], 400);
    }
}

/**
 * معالجة طلبات DELETE
 */
function handleDeleteRequest(string $action, int $userId): void {
    $roomId = (int) ($_GET['room_id'] ?? 0);
    
    if ($roomId <= 0) {
        json_response(['success' => false, 'message' => 'معرف الغرفة مطلوب'], 400);
    }
    
    switch ($action) {
        case 'delete':
            // التحقق من أن المستخدم هو المالك
            $membership = Database::fetchOne(
                "SELECT role FROM chat_room_members WHERE room_id = :room_id AND user_id = :user_id",
                ['room_id' => $roomId, 'user_id' => $userId]
            );
            
            if (!$membership || $membership['role'] !== 'owner') {
                json_response(['success' => false, 'message' => 'فقط مالك الغرفة يمكنه حذفها'], 403);
            }
            
            // الحذف الناعم
            Database::update('chat_rooms', ['is_active' => 0], 'id = :id', ['id' => $roomId]);
            
            json_response(['success' => true, 'message' => 'تم حذف الغرفة']);
            break;
            
        case 'remove_member':
            $memberId = (int) ($_GET['user_id'] ?? 0);
            
            if (!canManageRoom($roomId, $userId)) {
                json_response(['success' => false, 'message' => 'ليس لديك صلاحية'], 403);
            }
            
            // لا يمكن إزالة المالك
            $targetMember = Database::fetchOne(
                "SELECT role FROM chat_room_members WHERE room_id = :room_id AND user_id = :user_id",
                ['room_id' => $roomId, 'user_id' => $memberId]
            );
            
            if ($targetMember && $targetMember['role'] === 'owner') {
                json_response(['success' => false, 'message' => 'لا يمكن إزالة المالك'], 400);
            }
            
            Database::delete('chat_room_members', 'room_id = :room_id AND user_id = :user_id', [
                'room_id' => $roomId,
                'user_id' => $memberId
            ]);
            
            json_response(['success' => true, 'message' => 'تم إزالة العضو']);
            break;
            
        default:
            json_response(['success' => false, 'message' => 'إجراء غير معروف'], 400);
    }
}

// ═══════════════════════════════════════════════════════════════
// 🛠️ دوال مساعدة
// ═══════════════════════════════════════════════════════════════

function isRoomMember(int $roomId, int $userId): bool {
    return Database::fetchOne(
        "SELECT 1 FROM chat_room_members WHERE room_id = :room_id AND user_id = :user_id",
        ['room_id' => $roomId, 'user_id' => $userId]
    ) !== false;
}

function canManageRoom(int $roomId, int $userId): bool {
    $membership = Database::fetchOne(
        "SELECT role FROM chat_room_members WHERE room_id = :room_id AND user_id = :user_id",
        ['room_id' => $roomId, 'user_id' => $userId]
    );
    
    if (!$membership) return false;
    
    return in_array($membership['role'], ['owner', 'admin', 'moderator']);
}
