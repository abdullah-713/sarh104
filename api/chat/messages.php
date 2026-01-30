<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * 💬 API الرسائل - Chat Messages API
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
    error_log("Chat Messages API Error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'حدث خطأ في الخادم'], 500);
}

/**
 * معالجة طلبات GET
 */
function handleGetRequest(string $action, int $userId): void {
    switch ($action) {
        case 'list':
            $roomId = (int) ($_GET['room_id'] ?? 0);
            $limit = min(100, max(10, (int) ($_GET['limit'] ?? 50)));
            $before = $_GET['before'] ?? null; // للتحميل التدريجي
            $after = $_GET['after'] ?? null;   // للرسائل الجديدة
            
            if ($roomId <= 0) {
                json_response(['success' => false, 'message' => 'معرف الغرفة مطلوب'], 400);
            }
            
            // التحقق من العضوية
            if (!isRoomMember($roomId, $userId)) {
                json_response(['success' => false, 'message' => 'لست عضواً في هذه الغرفة'], 403);
            }
            
            $params = ['room_id' => $roomId];
            $whereClause = "cm.room_id = :room_id AND cm.is_deleted = 0";
            
            if ($before) {
                $whereClause .= " AND cm.id < :before";
                $params['before'] = (int) $before;
            }
            
            if ($after) {
                $whereClause .= " AND cm.id > :after";
                $params['after'] = (int) $after;
            }
            
            $messages = Database::fetchAll("
                SELECT 
                    cm.id,
                    cm.user_id,
                    cm.message_type,
                    cm.content,
                    cm.reply_to_id,
                    cm.attachments,
                    cm.reactions,
                    cm.is_edited,
                    cm.created_at,
                    u.full_name as sender_name,
                    u.avatar as sender_avatar,
                    u.job_title as sender_title,
                    (SELECT content FROM chat_messages WHERE id = cm.reply_to_id) as reply_content,
                    (SELECT u2.full_name FROM chat_messages cm2 JOIN users u2 ON cm2.user_id = u2.id WHERE cm2.id = cm.reply_to_id) as reply_sender
                FROM chat_messages cm
                INNER JOIN users u ON cm.user_id = u.id
                WHERE {$whereClause}
                ORDER BY cm.id DESC
                LIMIT {$limit}
            ", $params);
            
            // ترتيب الرسائل من الأقدم للأحدث
            $messages = array_reverse($messages);
            
            // تحديث آخر قراءة
            Database::update('chat_room_members', 
                ['last_read_at' => date('Y-m-d H:i:s')], 
                'room_id = :room_id AND user_id = :user_id', 
                ['room_id' => $roomId, 'user_id' => $userId]
            );
            
            // تحويل JSON في attachments و reactions
            foreach ($messages as &$msg) {
                $msg['attachments'] = $msg['attachments'] ? json_decode($msg['attachments'], true) : [];
                $msg['reactions'] = $msg['reactions'] ? json_decode($msg['reactions'], true) : [];
                $msg['is_mine'] = ($msg['user_id'] == $userId);
            }
            
            json_response([
                'success' => true, 
                'messages' => $messages,
                'has_more' => count($messages) === $limit
            ]);
            break;
            
        case 'poll':
            // Long polling للرسائل الجديدة
            $roomId = (int) ($_GET['room_id'] ?? 0);
            $lastId = (int) ($_GET['last_id'] ?? 0);
            $timeout = min(30, max(5, (int) ($_GET['timeout'] ?? 20)));
            
            if (!isRoomMember($roomId, $userId)) {
                json_response(['success' => false, 'message' => 'لست عضواً'], 403);
            }
            
            $startTime = time();
            $newMessages = [];
            $typingUsers = [];
            
            while (time() - $startTime < $timeout) {
                // جلب الرسائل الجديدة
                $newMessages = Database::fetchAll("
                    SELECT 
                        cm.id, cm.user_id, cm.message_type, cm.content, cm.reply_to_id,
                        cm.attachments, cm.reactions, cm.is_edited, cm.created_at,
                        u.full_name as sender_name, u.avatar as sender_avatar
                    FROM chat_messages cm
                    INNER JOIN users u ON cm.user_id = u.id
                    WHERE cm.room_id = :room_id AND cm.id > :last_id AND cm.is_deleted = 0
                    ORDER BY cm.id ASC
                ", ['room_id' => $roomId, 'last_id' => $lastId]);
                
                // جلب من يكتب الآن
                $typingUsers = Database::fetchAll("
                    SELECT ct.user_id, u.full_name
                    FROM chat_typing ct
                    INNER JOIN users u ON ct.user_id = u.id
                    WHERE ct.room_id = :room_id 
                    AND ct.user_id != :user_id
                    AND ct.started_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
                ", ['room_id' => $roomId, 'user_id' => $userId]);
                
                if (!empty($newMessages) || !empty($typingUsers)) {
                    break;
                }
                
                usleep(500000); // 0.5 ثانية
            }
            
            // تحويل JSON
            foreach ($newMessages as &$msg) {
                $msg['attachments'] = $msg['attachments'] ? json_decode($msg['attachments'], true) : [];
                $msg['reactions'] = $msg['reactions'] ? json_decode($msg['reactions'], true) : [];
                $msg['is_mine'] = ($msg['user_id'] == $userId);
            }
            
            json_response([
                'success' => true,
                'messages' => $newMessages,
                'typing' => $typingUsers,
                'timestamp' => time()
            ]);
            break;
            
        case 'search':
            $roomId = (int) ($_GET['room_id'] ?? 0);
            $query = trim($_GET['q'] ?? '');
            
            if (strlen($query) < 2) {
                json_response(['success' => false, 'message' => 'كلمة البحث قصيرة'], 400);
            }
            
            if (!isRoomMember($roomId, $userId)) {
                json_response(['success' => false, 'message' => 'لست عضواً'], 403);
            }
            
            $messages = Database::fetchAll("
                SELECT 
                    cm.id, cm.content, cm.created_at,
                    u.full_name as sender_name
                FROM chat_messages cm
                INNER JOIN users u ON cm.user_id = u.id
                WHERE cm.room_id = :room_id 
                AND cm.is_deleted = 0
                AND cm.content LIKE :query
                ORDER BY cm.created_at DESC
                LIMIT 50
            ", ['room_id' => $roomId, 'query' => "%{$query}%"]);
            
            json_response(['success' => true, 'messages' => $messages]);
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
        case 'send':
            $roomId = (int) ($input['room_id'] ?? 0);
            $content = trim($input['content'] ?? '');
            $messageType = $input['type'] ?? 'text';
            $replyToId = $input['reply_to'] ?? null;
            $attachments = $input['attachments'] ?? null;
            
            if ($roomId <= 0) {
                json_response(['success' => false, 'message' => 'معرف الغرفة مطلوب'], 400);
            }
            
            if (empty($content) && empty($attachments)) {
                json_response(['success' => false, 'message' => 'الرسالة فارغة'], 400);
            }
            
            // التحقق من العضوية وعدم الكتم
            $membership = Database::fetchOne(
                "SELECT * FROM chat_room_members WHERE room_id = :room_id AND user_id = :user_id",
                ['room_id' => $roomId, 'user_id' => $userId]
            );
            
            if (!$membership) {
                json_response(['success' => false, 'message' => 'لست عضواً في هذه الغرفة'], 403);
            }
            
            if ($membership['is_muted'] && (!$membership['muted_until'] || strtotime($membership['muted_until']) > time())) {
                json_response(['success' => false, 'message' => 'تم كتمك في هذه الغرفة'], 403);
            }
            
            // تنظيف المحتوى
            $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
            
            // إدراج الرسالة
            $messageId = Database::insert('chat_messages', [
                'room_id' => $roomId,
                'user_id' => $userId,
                'message_type' => in_array($messageType, ['text', 'image', 'file', 'voice', 'reply']) ? $messageType : 'text',
                'content' => $content,
                'reply_to_id' => $replyToId ? (int) $replyToId : null,
                'attachments' => $attachments ? json_encode($attachments) : null
            ]);
            
            // تحديث آخر رسالة في الغرفة
            Database::update('chat_rooms', 
                ['last_message_at' => date('Y-m-d H:i:s')], 
                'id = :id', 
                ['id' => $roomId]
            );
            
            // حذف حالة الكتابة
            Database::delete('chat_typing', 'room_id = :room_id AND user_id = :user_id', [
                'room_id' => $roomId,
                'user_id' => $userId
            ]);
            
            // إشعار الأعضاء (باستثناء المرسل)
            $mentionedUsers = extractMentions($content);
            if (!empty($mentionedUsers)) {
                foreach ($mentionedUsers as $mentionedId) {
                    if ($mentionedId != $userId) {
                        Database::insert('chat_notifications', [
                            'user_id' => $mentionedId,
                            'room_id' => $roomId,
                            'message_id' => $messageId,
                            'type' => 'mention',
                            'content' => 'تم ذكرك في رسالة'
                        ]);
                    }
                }
            }
            
            // إشعار صاحب الرسالة الأصلية عند الرد
            if ($replyToId) {
                $originalMessage = Database::fetchOne(
                    "SELECT user_id FROM chat_messages WHERE id = :id",
                    ['id' => $replyToId]
                );
                if ($originalMessage && $originalMessage['user_id'] != $userId) {
                    Database::insert('chat_notifications', [
                        'user_id' => $originalMessage['user_id'],
                        'room_id' => $roomId,
                        'message_id' => $messageId,
                        'type' => 'reply',
                        'content' => 'قام شخص بالرد على رسالتك'
                    ]);
                }
            }
            
            // جلب الرسالة المرسلة
            $message = Database::fetchOne("
                SELECT 
                    cm.*, u.full_name as sender_name, u.avatar as sender_avatar
                FROM chat_messages cm
                INNER JOIN users u ON cm.user_id = u.id
                WHERE cm.id = :id
            ", ['id' => $messageId]);
            
            $message['is_mine'] = true;
            $message['attachments'] = $message['attachments'] ? json_decode($message['attachments'], true) : [];
            $message['reactions'] = [];
            
            json_response(['success' => true, 'message' => $message]);
            break;
            
        case 'typing':
            $roomId = (int) ($input['room_id'] ?? 0);
            $isTyping = (bool) ($input['typing'] ?? false);
            
            if ($roomId <= 0) {
                json_response(['success' => false, 'message' => 'معرف الغرفة مطلوب'], 400);
            }
            
            if ($isTyping) {
                // إضافة/تحديث حالة الكتابة
                $existing = Database::fetchOne(
                    "SELECT id FROM chat_typing WHERE room_id = :room_id AND user_id = :user_id",
                    ['room_id' => $roomId, 'user_id' => $userId]
                );
                
                if ($existing) {
                    Database::update('chat_typing', 
                        ['started_at' => date('Y-m-d H:i:s')], 
                        'id = :id', 
                        ['id' => $existing['id']]
                    );
                } else {
                    Database::insert('chat_typing', [
                        'room_id' => $roomId,
                        'user_id' => $userId
                    ]);
                }
            } else {
                // حذف حالة الكتابة
                Database::delete('chat_typing', 'room_id = :room_id AND user_id = :user_id', [
                    'room_id' => $roomId,
                    'user_id' => $userId
                ]);
            }
            
            json_response(['success' => true]);
            break;
            
        case 'react':
            $messageId = (int) ($input['message_id'] ?? 0);
            $emoji = trim($input['emoji'] ?? '');
            
            if ($messageId <= 0 || empty($emoji)) {
                json_response(['success' => false, 'message' => 'بيانات ناقصة'], 400);
            }
            
            // التحقق من صلاحية الوصول للرسالة
            $message = Database::fetchOne(
                "SELECT cm.*, crm.user_id as member_check 
                 FROM chat_messages cm
                 LEFT JOIN chat_room_members crm ON cm.room_id = crm.room_id AND crm.user_id = :user_id
                 WHERE cm.id = :message_id",
                ['message_id' => $messageId, 'user_id' => $userId]
            );
            
            if (!$message || !$message['member_check']) {
                json_response(['success' => false, 'message' => 'لا يمكنك التفاعل'], 403);
            }
            
            // تحديث التفاعلات
            $reactions = $message['reactions'] ? json_decode($message['reactions'], true) : [];
            
            if (!isset($reactions[$emoji])) {
                $reactions[$emoji] = [];
            }
            
            $userIndex = array_search($userId, $reactions[$emoji]);
            if ($userIndex !== false) {
                // إزالة التفاعل
                unset($reactions[$emoji][$userIndex]);
                $reactions[$emoji] = array_values($reactions[$emoji]);
                if (empty($reactions[$emoji])) {
                    unset($reactions[$emoji]);
                }
            } else {
                // إضافة التفاعل
                $reactions[$emoji][] = $userId;
                
                // إشعار صاحب الرسالة
                if ($message['user_id'] != $userId) {
                    Database::insert('chat_notifications', [
                        'user_id' => $message['user_id'],
                        'room_id' => $message['room_id'],
                        'message_id' => $messageId,
                        'type' => 'reaction',
                        'content' => "تفاعل شخص على رسالتك: {$emoji}"
                    ]);
                }
            }
            
            Database::update('chat_messages', 
                ['reactions' => json_encode($reactions)], 
                'id = :id', 
                ['id' => $messageId]
            );
            
            json_response(['success' => true, 'reactions' => $reactions]);
            break;
            
        case 'pin':
            $messageId = (int) ($input['message_id'] ?? 0);
            
            $message = Database::fetchOne(
                "SELECT room_id FROM chat_messages WHERE id = :id",
                ['id' => $messageId]
            );
            
            if (!$message) {
                json_response(['success' => false, 'message' => 'الرسالة غير موجودة'], 404);
            }
            
            if (!canManageRoom($message['room_id'], $userId)) {
                json_response(['success' => false, 'message' => 'ليس لديك صلاحية'], 403);
            }
            
            // التحقق من عدم التثبيت المسبق
            $existing = Database::fetchOne(
                "SELECT id FROM chat_pinned_messages WHERE room_id = :room_id AND message_id = :message_id",
                ['room_id' => $message['room_id'], 'message_id' => $messageId]
            );
            
            if ($existing) {
                // إلغاء التثبيت
                Database::delete('chat_pinned_messages', 'id = :id', ['id' => $existing['id']]);
                json_response(['success' => true, 'pinned' => false, 'message' => 'تم إلغاء التثبيت']);
            } else {
                // تثبيت الرسالة
                Database::insert('chat_pinned_messages', [
                    'room_id' => $message['room_id'],
                    'message_id' => $messageId,
                    'pinned_by' => $userId
                ]);
                json_response(['success' => true, 'pinned' => true, 'message' => 'تم تثبيت الرسالة']);
            }
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
    
    switch ($action) {
        case 'edit':
            $messageId = (int) ($input['message_id'] ?? 0);
            $newContent = trim($input['content'] ?? '');
            
            if ($messageId <= 0 || empty($newContent)) {
                json_response(['success' => false, 'message' => 'بيانات ناقصة'], 400);
            }
            
            // التحقق من ملكية الرسالة
            $message = Database::fetchOne(
                "SELECT * FROM chat_messages WHERE id = :id AND user_id = :user_id",
                ['id' => $messageId, 'user_id' => $userId]
            );
            
            if (!$message) {
                json_response(['success' => false, 'message' => 'لا يمكنك تعديل هذه الرسالة'], 403);
            }
            
            // التحقق من أن الرسالة ليست قديمة جداً (24 ساعة)
            if (strtotime($message['created_at']) < strtotime('-24 hours')) {
                json_response(['success' => false, 'message' => 'لا يمكن تعديل رسائل أقدم من 24 ساعة'], 400);
            }
            
            $newContent = htmlspecialchars($newContent, ENT_QUOTES, 'UTF-8');
            
            Database::update('chat_messages', [
                'content' => $newContent,
                'is_edited' => 1,
                'edited_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $messageId]);
            
            json_response(['success' => true, 'message' => 'تم التعديل']);
            break;
            
        default:
            json_response(['success' => false, 'message' => 'إجراء غير معروف'], 400);
    }
}

/**
 * معالجة طلبات DELETE
 */
function handleDeleteRequest(string $action, int $userId): void {
    switch ($action) {
        case 'delete':
            $messageId = (int) ($_GET['message_id'] ?? 0);
            
            if ($messageId <= 0) {
                json_response(['success' => false, 'message' => 'معرف الرسالة مطلوب'], 400);
            }
            
            // التحقق من ملكية الرسالة أو صلاحية الإدارة
            $message = Database::fetchOne(
                "SELECT * FROM chat_messages WHERE id = :id",
                ['id' => $messageId]
            );
            
            if (!$message) {
                json_response(['success' => false, 'message' => 'الرسالة غير موجودة'], 404);
            }
            
            $canDelete = ($message['user_id'] == $userId) || canManageRoom($message['room_id'], $userId);
            
            if (!$canDelete) {
                json_response(['success' => false, 'message' => 'ليس لديك صلاحية'], 403);
            }
            
            // حذف ناعم
            Database::update('chat_messages', [
                'is_deleted' => 1,
                'deleted_at' => date('Y-m-d H:i:s'),
                'content' => '[تم حذف الرسالة]'
            ], 'id = :id', ['id' => $messageId]);
            
            json_response(['success' => true, 'message' => 'تم حذف الرسالة']);
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
    
    return $membership && in_array($membership['role'], ['owner', 'admin', 'moderator']);
}

/**
 * استخراج المستخدمين المذكورين في الرسالة
 * @param string $content
 * @return array User IDs
 */
function extractMentions(string $content): array {
    $mentions = [];
    
    // البحث عن @username أو @اسم
    if (preg_match_all('/@(\w+)/u', $content, $matches)) {
        foreach ($matches[1] as $username) {
            $user = Database::fetchOne(
                "SELECT id FROM users WHERE username = :username OR full_name LIKE :name",
                ['username' => $username, 'name' => "%{$username}%"]
            );
            if ($user) {
                $mentions[] = $user['id'];
            }
        }
    }
    
    return array_unique($mentions);
}
