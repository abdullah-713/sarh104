<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * 💬 نظام الدردشة الجماعية - Group Chat System
 * ═══════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/functions.php';

check_login();

$pageTitle = 'الدردشة';
$currentPage = 'chat';
$hideBottomNav = true;
$bodyClass = 'chat-page';

$userId = current_user_id();
$userName = $_SESSION['full_name'] ?? 'مستخدم';
$userAvatar = $_SESSION['profile_image'] ?? null;

// جلب غرف المستخدم
try {
    // التحقق من وجود الجداول أولاً
    $tableExists = Database::fetchOne("SHOW TABLES LIKE 'chat_rooms'");
    if (!$tableExists) {
        $needsSetup = true;
    } else {
        // إضافة المستخدم للغرف العامة تلقائياً إذا لم يكن عضواً
        $publicRooms = Database::fetchAll("SELECT id FROM chat_rooms WHERE type = 'public' AND is_active = 1");
        foreach ($publicRooms as $room) {
            $isMember = Database::fetchOne(
                "SELECT id FROM chat_room_members WHERE room_id = :room_id AND user_id = :user_id",
                ['room_id' => $room['id'], 'user_id' => $userId]
            );
            if (!$isMember) {
                try {
                    Database::insert('chat_room_members', [
                        'room_id' => $room['id'],
                        'user_id' => $userId,
                        'role' => 'member'
                    ]);
                } catch (Exception $e) {
                    // تجاهل - قد يكون موجود
                }
            }
        }
        
        $rooms = Database::fetchAll("
            SELECT 
                cr.id,
                cr.name,
                cr.type,
                cr.avatar,
                cr.last_message_at,
                crm.role as my_role,
                (SELECT COUNT(*) FROM chat_messages WHERE room_id = cr.id AND created_at > COALESCE(crm.last_read_at, '1970-01-01') AND is_deleted = 0) as unread_count,
                (SELECT content FROM chat_messages WHERE room_id = cr.id AND is_deleted = 0 ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT u.full_name FROM chat_messages cm JOIN users u ON cm.user_id = u.id WHERE cm.room_id = cr.id AND cm.is_deleted = 0 ORDER BY cm.created_at DESC LIMIT 1) as last_message_by,
                (SELECT COUNT(*) FROM chat_room_members WHERE room_id = cr.id) as members_count
            FROM chat_rooms cr
            INNER JOIN chat_room_members crm ON cr.id = crm.room_id AND crm.user_id = :user_id
            WHERE cr.is_active = 1
            ORDER BY COALESCE(cr.last_message_at, cr.created_at) DESC
        ", ['user_id' => $userId]);
    }
    
} catch (Exception $e) {
    $needsSetup = true;
    error_log("Chat Error: " . $e->getMessage());
}

include INCLUDES_PATH . '/header.php';
?>

<style>
/* ═══════════════════════════════════════════════════════════════ */
/* 🎨 أنماط صفحة الدردشة */
/* ═══════════════════════════════════════════════════════════════ */

.chat-page {
    --chat-primary: #5865f2;
    --chat-secondary: #4752c4;
    --chat-bg: #36393f;
    --chat-sidebar: #2f3136;
    --chat-header: #292b2f;
    --chat-text: #dcddde;
    --chat-text-muted: #72767d;
    --chat-hover: #34373c;
    --chat-input-bg: #40444b;
    --chat-message-bg: #32353b;
    --chat-own-message: #5865f2;
    --chat-system-message: rgba(250, 168, 26, 0.1);
    background: var(--chat-bg);
    min-height: 100vh;
}

.chat-container {
    display: flex;
    height: calc(100vh - 60px);
    max-width: 1400px;
    margin: 0 auto;
    background: var(--chat-bg);
}

/* الشريط الجانبي */
.chat-sidebar {
    width: 320px;
    background: var(--chat-sidebar);
    border-left: 1px solid rgba(255,255,255,0.06);
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}

.chat-sidebar-header {
    padding: 1rem;
    background: var(--chat-header);
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

.chat-sidebar-header h3 {
    color: white;
    font-size: 1.1rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.chat-search {
    position: relative;
    margin-top: 0.75rem;
}

.chat-search input {
    width: 100%;
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    background: var(--chat-input-bg);
    border: none;
    border-radius: 6px;
    color: var(--chat-text);
    font-size: 0.9rem;
}

.chat-search input::placeholder {
    color: var(--chat-text-muted);
}

.chat-search i {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--chat-text-muted);
}

/* قائمة الغرف */
.chat-rooms-list {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem;
}

.room-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s;
    margin-bottom: 2px;
}

.room-item:hover {
    background: var(--chat-hover);
}

.room-item.active {
    background: rgba(88, 101, 242, 0.2);
}

.room-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--chat-primary), var(--chat-secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.room-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.room-info {
    flex: 1;
    min-width: 0;
}

.room-name {
    color: white;
    font-weight: 500;
    font-size: 0.95rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.room-last-message {
    color: var(--chat-text-muted);
    font-size: 0.8rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
}

.room-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}

.room-time {
    color: var(--chat-text-muted);
    font-size: 0.7rem;
}

.room-unread {
    background: var(--chat-primary);
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: 600;
}

.room-type-icon {
    font-size: 0.7rem;
    color: var(--chat-text-muted);
}

/* منطقة الدردشة الرئيسية */
.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.chat-header {
    padding: 1rem 1.5rem;
    background: var(--chat-header);
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.chat-header-info h4 {
    color: white;
    margin: 0;
    font-size: 1rem;
}

.chat-header-info p {
    color: var(--chat-text-muted);
    margin: 0;
    font-size: 0.8rem;
}

.chat-header-actions {
    margin-right: auto;
    display: flex;
    gap: 0.5rem;
}

.chat-header-actions button {
    background: transparent;
    border: none;
    color: var(--chat-text-muted);
    padding: 0.5rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.chat-header-actions button:hover {
    background: var(--chat-hover);
    color: white;
}

/* منطقة الرسائل */
.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 1rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.message-group {
    display: flex;
    gap: 1rem;
    max-width: 85%;
}

.message-group.own {
    flex-direction: row-reverse;
    margin-right: auto;
    margin-left: 0;
}

.message-group:not(.own) {
    margin-left: 0;
    margin-right: auto;
}

.message-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #5865f2, #7289da);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
    align-self: flex-end;
}

.message-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.message-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.message-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.message-sender {
    color: var(--chat-primary);
    font-weight: 600;
    font-size: 0.85rem;
}

.message-time {
    color: var(--chat-text-muted);
    font-size: 0.7rem;
}

.message-bubble {
    background: var(--chat-message-bg);
    color: var(--chat-text);
    padding: 0.75rem 1rem;
    border-radius: 18px;
    border-bottom-right-radius: 4px;
    max-width: 100%;
    word-wrap: break-word;
    font-size: 0.95rem;
    line-height: 1.5;
}

.message-group.own .message-bubble {
    background: var(--chat-own-message);
    color: white;
    border-bottom-right-radius: 18px;
    border-bottom-left-radius: 4px;
}

.message-bubble.system {
    background: var(--chat-system-message);
    color: #faa81a;
    text-align: center;
    font-size: 0.8rem;
    padding: 0.5rem 1rem;
    border-radius: 10px;
    width: fit-content;
    margin: 0.5rem auto;
}

.message-edited {
    font-size: 0.7rem;
    color: var(--chat-text-muted);
    font-style: italic;
}

.message-reactions {
    display: flex;
    gap: 0.25rem;
    margin-top: 4px;
}

.reaction-badge {
    background: var(--chat-hover);
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: background 0.2s;
}

.reaction-badge:hover {
    background: var(--chat-input-bg);
}

.reaction-count {
    font-size: 0.7rem;
    color: var(--chat-text-muted);
    margin-right: 2px;
}

/* مؤشر الكتابة */
.typing-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    color: var(--chat-text-muted);
    font-size: 0.85rem;
}

.typing-dots {
    display: flex;
    gap: 3px;
}

.typing-dots span {
    width: 6px;
    height: 6px;
    background: var(--chat-text-muted);
    border-radius: 50%;
    animation: typing 1.4s infinite;
}

.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
    30% { transform: translateY(-5px); opacity: 1; }
}

/* منطقة الإدخال */
.chat-input-area {
    padding: 1rem 1.5rem;
    background: var(--chat-header);
    border-top: 1px solid rgba(255,255,255,0.06);
}

.chat-input-wrapper {
    display: flex;
    align-items: flex-end;
    gap: 0.75rem;
    background: var(--chat-input-bg);
    border-radius: 12px;
    padding: 0.5rem 1rem;
}

.chat-input-wrapper textarea {
    flex: 1;
    background: transparent;
    border: none;
    color: var(--chat-text);
    font-size: 0.95rem;
    resize: none;
    max-height: 120px;
    line-height: 1.5;
}

.chat-input-wrapper textarea::placeholder {
    color: var(--chat-text-muted);
}

.chat-input-actions {
    display: flex;
    gap: 0.25rem;
}

.chat-input-actions button {
    background: transparent;
    border: none;
    color: var(--chat-text-muted);
    padding: 0.5rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.chat-input-actions button:hover {
    color: white;
}

.btn-send {
    background: var(--chat-primary) !important;
    color: white !important;
}

.btn-send:hover {
    background: var(--chat-secondary) !important;
}

.btn-send:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* الحالة الفارغة */
.chat-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--chat-text-muted);
    padding: 2rem;
    text-align: center;
}

.chat-empty-icon {
    font-size: 5rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.chat-empty h3 {
    color: white;
    margin-bottom: 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .chat-sidebar {
        position: fixed;
        right: 0;
        top: 60px;
        bottom: 0;
        z-index: 100;
        transform: translateX(100%);
        transition: transform 0.3s;
        width: 85%;
        max-width: 320px;
    }
    
    .chat-sidebar.show {
        transform: translateX(0);
    }
    
    .chat-sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 99;
    }
    
    .chat-sidebar-overlay.show {
        display: block;
    }
    
    .btn-toggle-sidebar {
        display: flex !important;
    }
    
    .chat-messages {
        padding: 1rem;
    }
    
    .message-group {
        max-width: 90%;
    }
}

@media (min-width: 769px) {
    .btn-toggle-sidebar {
        display: none !important;
    }
}

/* تمرير مخصص */
.chat-rooms-list::-webkit-scrollbar,
.chat-messages::-webkit-scrollbar {
    width: 6px;
}

.chat-rooms-list::-webkit-scrollbar-track,
.chat-messages::-webkit-scrollbar-track {
    background: transparent;
}

.chat-rooms-list::-webkit-scrollbar-thumb,
.chat-messages::-webkit-scrollbar-thumb {
    background: var(--chat-hover);
    border-radius: 3px;
}

/* رسالة الرد */
.reply-preview {
    background: var(--chat-hover);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.reply-preview-content {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--chat-text-muted);
    font-size: 0.85rem;
}

.reply-preview-content strong {
    color: var(--chat-primary);
}

.btn-cancel-reply {
    background: transparent;
    border: none;
    color: var(--chat-text-muted);
    cursor: pointer;
}

/* الموظفين المتصلين */
.members-section {
    padding: 0.75rem;
    border-top: 1px solid rgba(255,255,255,0.06);
}

.members-section h5 {
    color: var(--chat-text-muted);
    font-size: 0.75rem;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}

.member-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    border-radius: 6px;
    transition: background 0.2s;
}

.member-item:hover {
    background: var(--chat-hover);
}

.member-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--chat-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.8rem;
    position: relative;
}

.member-status {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 10px;
    height: 10px;
    background: #43b581;
    border: 2px solid var(--chat-sidebar);
    border-radius: 50%;
}

.member-status.offline {
    background: #747f8d;
}

.member-name {
    color: var(--chat-text);
    font-size: 0.85rem;
}

/* زر إنشاء غرفة */
.btn-new-room {
    width: 100%;
    padding: 0.75rem;
    background: var(--chat-primary);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background 0.2s;
    margin: 0.5rem 0;
}

.btn-new-room:hover {
    background: var(--chat-secondary);
}

/* مودال إنشاء غرفة */
.chat-modal {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.7);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
}

.chat-modal.show {
    display: flex;
}

.chat-modal-content {
    background: var(--chat-sidebar);
    border-radius: 12px;
    width: 90%;
    max-width: 450px;
    max-height: 80vh;
    overflow-y: auto;
}

.chat-modal-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.chat-modal-header h4 {
    color: white;
    margin: 0;
}

.chat-modal-body {
    padding: 1.5rem;
}

.chat-form-group {
    margin-bottom: 1rem;
}

.chat-form-group label {
    display: block;
    color: var(--chat-text);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.chat-form-group input,
.chat-form-group select,
.chat-form-group textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    background: var(--chat-input-bg);
    border: none;
    border-radius: 8px;
    color: var(--chat-text);
    font-size: 0.95rem;
}

.chat-modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.06);
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.btn-chat {
    padding: 0.6rem 1.25rem;
    border-radius: 6px;
    font-size: 0.9rem;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.btn-chat-primary {
    background: var(--chat-primary);
    color: white;
}

.btn-chat-secondary {
    background: var(--chat-hover);
    color: var(--chat-text);
}

/* رسائل التنبيه */
.chat-alert {
    position: fixed;
    top: 80px;
    left: 50%;
    transform: translateX(-50%);
    padding: 0.75rem 1.5rem;
    background: var(--chat-primary);
    color: white;
    border-radius: 8px;
    z-index: 1001;
    display: none;
    animation: slideDown 0.3s ease;
}

.chat-alert.show {
    display: block;
}

@keyframes slideDown {
    from { opacity: 0; transform: translate(-50%, -20px); }
    to { opacity: 1; transform: translate(-50%, 0); }
}
</style>

<?php if (isset($needsSetup)): ?>
<div class="container py-5">
    <div class="alert alert-warning text-center">
        <h4><i class="bi bi-exclamation-triangle me-2"></i>نظام الدردشة يحتاج إعداد</h4>
        <p>يجب تشغيل سكريبت إنشاء جداول الدردشة أولاً.</p>
        <a href="<?= url('install/add_chat_tables.php') ?>" class="btn btn-warning" target="_blank">
            <i class="bi bi-gear me-2"></i>
            تشغيل سكريبت الإعداد
        </a>
    </div>
</div>
<?php else: ?>

<div class="chat-container">
    <!-- الشريط الجانبي -->
    <div class="chat-sidebar" id="chatSidebar">
        <div class="chat-sidebar-header">
            <h3>
                <i class="bi bi-chat-dots-fill"></i>
                الدردشات
            </h3>
            <div class="chat-search">
                <i class="bi bi-search"></i>
                <input type="text" placeholder="بحث في الغرف..." id="roomSearch">
            </div>
        </div>
        
        <div class="chat-rooms-list" id="roomsList">
            <?php if (empty($rooms)): ?>
            <div class="text-center py-4">
                <i class="bi bi-chat-square-text fs-1 text-muted d-block mb-2"></i>
                <p class="text-muted small">لا توجد غرف بعد</p>
            </div>
            <?php else: ?>
            <?php foreach ($rooms as $room): ?>
            <div class="room-item" data-room-id="<?= $room['id'] ?>" onclick="selectRoom(<?= $room['id'] ?>)">
                <div class="room-avatar">
                    <?php if ($room['avatar']): ?>
                    <img src="<?= e($room['avatar']) ?>" alt="">
                    <?php else: ?>
                    <?= mb_substr($room['name'], 0, 1) ?>
                    <?php endif; ?>
                </div>
                <div class="room-info">
                    <div class="room-name">
                        <?php
                        $typeIcons = [
                            'public' => '🌍',
                            'branch' => '🏢',
                            'private' => '🔒',
                            'department' => '👥'
                        ];
                        echo $typeIcons[$room['type']] ?? '';
                        ?>
                        <?= e($room['name']) ?>
                    </div>
                    <div class="room-last-message">
                        <?php if ($room['last_message']): ?>
                        <strong><?= e(mb_substr($room['last_message_by'] ?? '', 0, 10)) ?>:</strong>
                        <?= e(mb_substr($room['last_message'], 0, 30)) ?>
                        <?php else: ?>
                        <span class="text-muted">لا توجد رسائل</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="room-meta">
                    <?php if ($room['last_message_at']): ?>
                    <span class="room-time"><?= date('H:i', strtotime($room['last_message_at'])) ?></span>
                    <?php endif; ?>
                    <?php if ($room['unread_count'] > 0): ?>
                    <span class="room-unread"><?= $room['unread_count'] ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div style="padding: 0.5rem;">
            <button class="btn-new-room" onclick="showNewRoomModal()">
                <i class="bi bi-plus-lg me-2"></i>
                إنشاء غرفة جديدة
            </button>
        </div>
    </div>
    
    <!-- خلفية الشريط الجانبي للموبايل -->
    <div class="chat-sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- منطقة الدردشة الرئيسية -->
    <div class="chat-main">
        <div class="chat-header" id="chatHeader" style="display: none;">
            <button class="btn-toggle-sidebar" onclick="toggleSidebar()">
                <i class="bi bi-list fs-4"></i>
            </button>
            <div class="room-avatar" id="headerAvatar">
                <i class="bi bi-chat-dots"></i>
            </div>
            <div class="chat-header-info">
                <h4 id="headerRoomName">اختر غرفة</h4>
                <p id="headerRoomMembers"></p>
            </div>
            <div class="chat-header-actions">
                <button onclick="showRoomInfo()" title="معلومات الغرفة">
                    <i class="bi bi-info-circle fs-5"></i>
                </button>
                <button onclick="searchMessages()" title="بحث في الرسائل">
                    <i class="bi bi-search fs-5"></i>
                </button>
            </div>
        </div>
        
        <!-- الحالة الفارغة -->
        <div class="chat-empty" id="chatEmpty">
            <div class="chat-empty-icon">💬</div>
            <h3>مرحباً بك في الدردشة</h3>
            <p>اختر غرفة من القائمة للبدء في المحادثة</p>
            <button class="btn-new-room" style="width: auto; margin-top: 1rem;" onclick="showNewRoomModal()">
                <i class="bi bi-plus-lg me-2"></i>
                أو أنشئ غرفة جديدة
            </button>
        </div>
        
        <!-- منطقة الرسائل -->
        <div class="chat-messages" id="chatMessages" style="display: none;"></div>
        
        <!-- مؤشر الكتابة -->
        <div class="typing-indicator" id="typingIndicator" style="display: none;">
            <div class="typing-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <span id="typingNames">شخص ما يكتب...</span>
        </div>
        
        <!-- منطقة الإدخال -->
        <div class="chat-input-area" id="chatInputArea" style="display: none;">
            <div id="replyPreview" class="reply-preview" style="display: none;">
                <div class="reply-preview-content">
                    <i class="bi bi-reply"></i>
                    <span>رد على: <strong id="replyToName"></strong></span>
                    <span id="replyToContent"></span>
                </div>
                <button class="btn-cancel-reply" onclick="cancelReply()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="chat-input-wrapper">
                <textarea id="messageInput" rows="1" placeholder="اكتب رسالتك..." onkeydown="handleKeyDown(event)" oninput="handleTyping()"></textarea>
                <div class="chat-input-actions">
                    <button onclick="showEmojiPicker()" title="إيموجي">
                        <i class="bi bi-emoji-smile fs-5"></i>
                    </button>
                    <button onclick="attachFile()" title="إرفاق ملف">
                        <i class="bi bi-paperclip fs-5"></i>
                    </button>
                    <button class="btn-send" onclick="sendMessage()" id="btnSend" title="إرسال">
                        <i class="bi bi-send-fill fs-5"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال إنشاء غرفة -->
<div class="chat-modal" id="newRoomModal">
    <div class="chat-modal-content">
        <div class="chat-modal-header">
            <h4><i class="bi bi-plus-circle me-2"></i>إنشاء غرفة جديدة</h4>
            <button onclick="hideNewRoomModal()" style="background: none; border: none; color: var(--chat-text-muted); cursor: pointer;">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="chat-modal-body">
            <div class="chat-form-group">
                <label>اسم الغرفة</label>
                <input type="text" id="newRoomName" placeholder="أدخل اسم الغرفة">
            </div>
            <div class="chat-form-group">
                <label>الوصف</label>
                <textarea id="newRoomDesc" rows="2" placeholder="وصف مختصر للغرفة (اختياري)"></textarea>
            </div>
            <div class="chat-form-group">
                <label>نوع الغرفة</label>
                <select id="newRoomType">
                    <option value="private">خاصة 🔒</option>
                    <option value="public">عامة 🌍</option>
                </select>
            </div>
        </div>
        <div class="chat-modal-footer">
            <button class="btn-chat btn-chat-secondary" onclick="hideNewRoomModal()">إلغاء</button>
            <button class="btn-chat btn-chat-primary" onclick="createRoom()">
                <i class="bi bi-plus me-1"></i>
                إنشاء
            </button>
        </div>
    </div>
</div>

<!-- رسالة التنبيه -->
<div class="chat-alert" id="chatAlert"></div>

<script>
// ═══════════════════════════════════════════════════════════════
// 💬 JavaScript نظام الدردشة
// ═══════════════════════════════════════════════════════════════

const CHAT_API = '<?= url('api/chat') ?>';
const CURRENT_USER_ID = <?= $userId ?>;
const CURRENT_USER_NAME = '<?= e($userName) ?>';

let currentRoomId = null;
let lastMessageId = 0;
let replyToId = null;
let pollInterval = null;
let typingTimeout = null;

// ─────────────────────────────────────────────────────────────────
// اختيار غرفة
// ─────────────────────────────────────────────────────────────────
async function selectRoom(roomId) {
    if (currentRoomId === roomId) return;
    
    currentRoomId = roomId;
    lastMessageId = 0;
    
    // تحديث UI
    document.querySelectorAll('.room-item').forEach(el => el.classList.remove('active'));
    document.querySelector(`.room-item[data-room-id="${roomId}"]`)?.classList.add('active');
    
    document.getElementById('chatEmpty').style.display = 'none';
    document.getElementById('chatHeader').style.display = 'flex';
    document.getElementById('chatMessages').style.display = 'flex';
    document.getElementById('chatInputArea').style.display = 'block';
    
    // جلب تفاصيل الغرفة
    try {
        const response = await fetch(`${CHAT_API}/rooms.php?action=details&room_id=${roomId}`);
        const data = await response.json();
        
        if (data.success) {
            const room = data.room;
            document.getElementById('headerRoomName').textContent = room.name;
            document.getElementById('headerRoomMembers').textContent = `${room.members_count} عضو`;
            
            if (room.avatar) {
                document.getElementById('headerAvatar').innerHTML = `<img src="${room.avatar}" alt="">`;
            } else {
                document.getElementById('headerAvatar').textContent = room.name.charAt(0);
            }
        }
    } catch (e) {
        console.error('Error loading room:', e);
    }
    
    // جلب الرسائل
    await loadMessages();
    
    // بدء الـ polling
    startPolling();
    
    // إغلاق الشريط الجانبي على الموبايل
    if (window.innerWidth < 768) {
        toggleSidebar();
    }
}

// ─────────────────────────────────────────────────────────────────
// تحميل الرسائل
// ─────────────────────────────────────────────────────────────────
async function loadMessages(before = null) {
    const messagesDiv = document.getElementById('chatMessages');
    
    try {
        let url = `${CHAT_API}/messages.php?action=list&room_id=${currentRoomId}&limit=50`;
        if (before) url += `&before=${before}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            if (!before) {
                messagesDiv.innerHTML = '';
            }
            
            data.messages.forEach(msg => {
                appendMessage(msg, !before);
            });
            
            if (data.messages.length > 0) {
                lastMessageId = Math.max(lastMessageId, ...data.messages.map(m => m.id));
            }
            
            if (!before) {
                scrollToBottom();
            }
        }
    } catch (e) {
        console.error('Error loading messages:', e);
    }
}

// ─────────────────────────────────────────────────────────────────
// إضافة رسالة للعرض
// ─────────────────────────────────────────────────────────────────
function appendMessage(msg, append = true) {
    const messagesDiv = document.getElementById('chatMessages');
    
    if (msg.message_type === 'system') {
        const systemEl = document.createElement('div');
        systemEl.className = 'message-bubble system';
        systemEl.textContent = msg.content;
        if (append) {
            messagesDiv.appendChild(systemEl);
        } else {
            messagesDiv.prepend(systemEl);
        }
        return;
    }
    
    const isOwn = msg.is_mine || msg.user_id == CURRENT_USER_ID;
    
    const groupEl = document.createElement('div');
    groupEl.className = `message-group ${isOwn ? 'own' : ''}`;
    groupEl.dataset.messageId = msg.id;
    
    const initials = (msg.sender_name || 'م').split(' ').map(w => w[0]).join('').substring(0, 2);
    
    groupEl.innerHTML = `
        <div class="message-avatar">
            ${msg.sender_avatar ? `<img src="${msg.sender_avatar}" alt="">` : initials}
        </div>
        <div class="message-content">
            ${!isOwn ? `
            <div class="message-header">
                <span class="message-sender">${escapeHtml(msg.sender_name)}</span>
                <span class="message-time">${formatTime(msg.created_at)}</span>
            </div>
            ` : ''}
            ${msg.reply_content ? `
            <div class="reply-preview" style="margin-bottom: 0.25rem; padding: 0.5rem; font-size: 0.8rem;">
                <i class="bi bi-reply me-1"></i>
                <strong>${escapeHtml(msg.reply_sender)}</strong>: ${escapeHtml(msg.reply_content.substring(0, 50))}
            </div>
            ` : ''}
            <div class="message-bubble" oncontextmenu="showMessageMenu(event, ${msg.id})" ondblclick="replyTo(${msg.id}, '${escapeHtml(msg.sender_name)}', '${escapeHtml(msg.content.substring(0, 100))}')">
                ${escapeHtml(msg.content)}
                ${msg.is_edited ? '<span class="message-edited">(تم التعديل)</span>' : ''}
            </div>
            ${isOwn ? `<span class="message-time" style="text-align: left;">${formatTime(msg.created_at)}</span>` : ''}
            ${Object.keys(msg.reactions || {}).length > 0 ? `
            <div class="message-reactions">
                ${Object.entries(msg.reactions).map(([emoji, users]) => `
                    <span class="reaction-badge" onclick="react(${msg.id}, '${emoji}')">
                        ${emoji} <span class="reaction-count">${users.length}</span>
                    </span>
                `).join('')}
            </div>
            ` : ''}
        </div>
    `;
    
    if (append) {
        messagesDiv.appendChild(groupEl);
    } else {
        messagesDiv.prepend(groupEl);
    }
}

// ─────────────────────────────────────────────────────────────────
// إرسال رسالة
// ─────────────────────────────────────────────────────────────────
async function sendMessage() {
    const input = document.getElementById('messageInput');
    const content = input.value.trim();
    
    if (!content || !currentRoomId) return;
    
    const payload = {
        room_id: currentRoomId,
        content: content,
        type: 'text'
    };
    
    if (replyToId) {
        payload.reply_to = replyToId;
    }
    
    try {
        const response = await fetch(`${CHAT_API}/messages.php?action=send`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (data.success) {
            input.value = '';
            cancelReply();
            appendMessage(data.message);
            scrollToBottom();
            lastMessageId = Math.max(lastMessageId, data.message.id);
            
            // تحديث آخر رسالة في القائمة
            const roomItem = document.querySelector(`.room-item[data-room-id="${currentRoomId}"]`);
            if (roomItem) {
                roomItem.querySelector('.room-last-message').innerHTML = 
                    `<strong>أنت:</strong> ${escapeHtml(content.substring(0, 30))}`;
            }
        } else {
            showAlert(data.message || 'فشل إرسال الرسالة', 'error');
        }
    } catch (e) {
        console.error('Error sending message:', e);
        showAlert('خطأ في إرسال الرسالة', 'error');
    }
}

// ─────────────────────────────────────────────────────────────────
// Polling للرسائل الجديدة
// ─────────────────────────────────────────────────────────────────
function startPolling() {
    stopPolling();
    
    pollInterval = setInterval(async () => {
        if (!currentRoomId) return;
        
        try {
            const response = await fetch(
                `${CHAT_API}/messages.php?action=poll&room_id=${currentRoomId}&last_id=${lastMessageId}&timeout=5`
            );
            const data = await response.json();
            
            if (data.success) {
                // رسائل جديدة
                data.messages.forEach(msg => {
                    if (msg.id > lastMessageId) {
                        appendMessage(msg);
                        lastMessageId = msg.id;
                    }
                });
                
                if (data.messages.length > 0) {
                    scrollToBottom();
                }
                
                // مؤشر الكتابة
                const typingIndicator = document.getElementById('typingIndicator');
                if (data.typing && data.typing.length > 0) {
                    const names = data.typing.map(t => t.full_name).join('، ');
                    document.getElementById('typingNames').textContent = `${names} يكتب...`;
                    typingIndicator.style.display = 'flex';
                } else {
                    typingIndicator.style.display = 'none';
                }
            }
        } catch (e) {
            console.error('Polling error:', e);
        }
    }, 3000);
}

function stopPolling() {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

// ─────────────────────────────────────────────────────────────────
// حالة الكتابة
// ─────────────────────────────────────────────────────────────────
async function handleTyping() {
    if (!currentRoomId) return;
    
    clearTimeout(typingTimeout);
    
    try {
        await fetch(`${CHAT_API}/messages.php?action=typing`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ room_id: currentRoomId, typing: true })
        });
    } catch (e) {}
    
    typingTimeout = setTimeout(async () => {
        try {
            await fetch(`${CHAT_API}/messages.php?action=typing`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ room_id: currentRoomId, typing: false })
            });
        } catch (e) {}
    }, 3000);
}

// ─────────────────────────────────────────────────────────────────
// الرد على رسالة
// ─────────────────────────────────────────────────────────────────
function replyTo(messageId, senderName, content) {
    replyToId = messageId;
    document.getElementById('replyToName').textContent = senderName;
    document.getElementById('replyToContent').textContent = content.substring(0, 50) + (content.length > 50 ? '...' : '');
    document.getElementById('replyPreview').style.display = 'flex';
    document.getElementById('messageInput').focus();
}

function cancelReply() {
    replyToId = null;
    document.getElementById('replyPreview').style.display = 'none';
}

// ─────────────────────────────────────────────────────────────────
// التفاعل مع رسالة
// ─────────────────────────────────────────────────────────────────
async function react(messageId, emoji) {
    try {
        const response = await fetch(`${CHAT_API}/messages.php?action=react`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message_id: messageId, emoji: emoji })
        });
        
        const data = await response.json();
        if (data.success) {
            // تحديث عرض التفاعلات
        }
    } catch (e) {
        console.error('Error reacting:', e);
    }
}

// ─────────────────────────────────────────────────────────────────
// إنشاء غرفة جديدة
// ─────────────────────────────────────────────────────────────────
function showNewRoomModal() {
    document.getElementById('newRoomModal').classList.add('show');
}

function hideNewRoomModal() {
    document.getElementById('newRoomModal').classList.remove('show');
    document.getElementById('newRoomName').value = '';
    document.getElementById('newRoomDesc').value = '';
}

async function createRoom() {
    const name = document.getElementById('newRoomName').value.trim();
    const description = document.getElementById('newRoomDesc').value.trim();
    const type = document.getElementById('newRoomType').value;
    
    if (!name || name.length < 3) {
        showAlert('اسم الغرفة يجب أن يكون 3 أحرف على الأقل', 'error');
        return;
    }
    
    try {
        const response = await fetch(`${CHAT_API}/rooms.php?action=create`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, description, type })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('تم إنشاء الغرفة بنجاح', 'success');
            hideNewRoomModal();
            location.reload();
        } else {
            showAlert(data.message || 'فشل إنشاء الغرفة', 'error');
        }
    } catch (e) {
        console.error('Error creating room:', e);
        showAlert('خطأ في إنشاء الغرفة', 'error');
    }
}

// ─────────────────────────────────────────────────────────────────
// دوال مساعدة
// ─────────────────────────────────────────────────────────────────
function toggleSidebar() {
    document.getElementById('chatSidebar').classList.toggle('show');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}

function handleKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

function scrollToBottom() {
    const messagesDiv = document.getElementById('chatMessages');
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function formatTime(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showAlert(message, type = 'info') {
    const alert = document.getElementById('chatAlert');
    alert.textContent = message;
    alert.style.background = type === 'error' ? '#ef4444' : '#5865f2';
    alert.classList.add('show');
    setTimeout(() => alert.classList.remove('show'), 3000);
}

// البحث في الغرف
document.getElementById('roomSearch')?.addEventListener('input', function(e) {
    const query = e.target.value.toLowerCase();
    document.querySelectorAll('.room-item').forEach(item => {
        const name = item.querySelector('.room-name').textContent.toLowerCase();
        item.style.display = name.includes(query) ? 'flex' : 'none';
    });
});

// تحميل أول غرفة تلقائياً
<?php if (!empty($rooms)): ?>
// selectRoom(<?= $rooms[0]['id'] ?>);
<?php endif; ?>
</script>

<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
