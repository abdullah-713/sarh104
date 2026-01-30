/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * نظام صرح الإتقان - وحدة الإشعارات المتقدمة
 * Sarh Al-Itqan - Advanced Notifications Module
 * ═══════════════════════════════════════════════════════════════════════════════
 * @version 2.0.0
 * @author Sarh Development Team
 */

(function() {
    'use strict';

    // ═══════════════════════════════════════════════════════════════════════════
    // التكوين العام - Configuration
    // ═══════════════════════════════════════════════════════════════════════════
    const CONFIG = {
        pollInterval: 30000,           // فحص كل 30 ثانية
        toastDuration: 5000,           // مدة ظهور التنبيه
        maxNotifications: 10,          // الحد الأقصى في القائمة المنسدلة
        soundEnabled: true,            // تفعيل الصوت
        desktopNotifications: true,    // إشعارات سطح المكتب
        apiBase: '/app/api/notifications/'
    };

    // ═══════════════════════════════════════════════════════════════════════════
    // الحالة - State
    // ═══════════════════════════════════════════════════════════════════════════
    let state = {
        notifications: [],
        unreadCount: 0,
        lastFetchTime: null,
        isOpen: false,
        isLoading: false,
        pollTimer: null,
        audioContext: null,
        notificationSound: null
    };

    // ═══════════════════════════════════════════════════════════════════════════
    // العناصر DOM - DOM Elements
    // ═══════════════════════════════════════════════════════════════════════════
    let elements = {};

    // ═══════════════════════════════════════════════════════════════════════════
    // أيقونات الإشعارات حسب النوع
    // ═══════════════════════════════════════════════════════════════════════════
    const NOTIFICATION_ICONS = {
        success: { icon: 'bi-check-circle-fill', color: '#2ed573', bg: 'rgba(46, 213, 115, 0.1)' },
        warning: { icon: 'bi-exclamation-triangle-fill', color: '#ffa502', bg: 'rgba(255, 165, 2, 0.1)' },
        danger: { icon: 'bi-x-circle-fill', color: '#ff4757', bg: 'rgba(255, 71, 87, 0.1)' },
        error: { icon: 'bi-x-circle-fill', color: '#ff4757', bg: 'rgba(255, 71, 87, 0.1)' },
        info: { icon: 'bi-info-circle-fill', color: '#3742fa', bg: 'rgba(55, 66, 250, 0.1)' },
        attendance: { icon: 'bi-calendar-check-fill', color: '#ff6f00', bg: 'rgba(255, 111, 0, 0.1)' },
        points: { icon: 'bi-star-fill', color: '#ffa502', bg: 'rgba(255, 165, 2, 0.1)' },
        leave: { icon: 'bi-calendar-x-fill', color: '#0288d1', bg: 'rgba(2, 136, 209, 0.1)' },
        system: { icon: 'bi-gear-fill', color: '#6c757d', bg: 'rgba(108, 117, 125, 0.1)' },
        achievement: { icon: 'bi-trophy-fill', color: '#ffd700', bg: 'rgba(255, 215, 0, 0.1)' },
        message: { icon: 'bi-chat-dots-fill', color: '#9b59b6', bg: 'rgba(155, 89, 182, 0.1)' }
    };

    // ═══════════════════════════════════════════════════════════════════════════
    // التهيئة - Initialization
    // ═══════════════════════════════════════════════════════════════════════════
    function init() {
        // إنشاء عناصر DOM
        createNotificationCenter();
        createToastContainer();
        
        // ربط الأحداث
        bindEvents();
        
        // تحميل الإعدادات من localStorage
        loadSettings();
        
        // تحميل الإشعارات الأولية
        fetchNotifications();
        
        // بدء الفحص الدوري
        startPolling();
        
        // طلب إذن إشعارات المتصفح
        requestNotificationPermission();
        
        // تهيئة الصوت
        initSound();
        
        console.log('📢 Sarh Notifications Module initialized');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // إنشاء مركز الإشعارات - Create Notification Center
    // ═══════════════════════════════════════════════════════════════════════════
    function createNotificationCenter() {
        // البحث عن أيقونة الإشعارات الموجودة
        const existingIcon = document.querySelector('.nav-icon[href*="notifications"]');
        if (!existingIcon) return;

        // إنشاء wrapper جديد
        const wrapper = document.createElement('div');
        wrapper.className = 'notification-center-wrapper';
        wrapper.innerHTML = `
            <button class="nav-icon notification-trigger" id="notificationTrigger" title="الإشعارات" aria-expanded="false">
                <i class="bi bi-bell"></i>
                <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                <span class="notification-ping" id="notificationPing"></span>
            </button>
            
            <div class="notification-dropdown" id="notificationDropdown" aria-hidden="true">
                <div class="notification-dropdown-header">
                    <h6><i class="bi bi-bell-fill me-2"></i>الإشعارات</h6>
                    <div class="notification-header-actions">
                        <button class="btn-icon" id="btnMarkAllRead" title="تعيين الكل كمقروء">
                            <i class="bi bi-check-all"></i>
                        </button>
                        <button class="btn-icon" id="btnNotifSettings" title="الإعدادات">
                            <i class="bi bi-gear"></i>
                        </button>
                    </div>
                </div>
                
                <div class="notification-dropdown-body" id="notificationList">
                    <div class="notification-loading">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <span>جاري التحميل...</span>
                    </div>
                </div>
                
                <div class="notification-dropdown-footer">
                    <a href="/app/notifications.php" class="view-all-link">
                        <span>عرض جميع الإشعارات</span>
                        <i class="bi bi-arrow-left"></i>
                    </a>
                </div>
            </div>
        `;

        // استبدال الأيقونة القديمة
        existingIcon.parentNode.replaceChild(wrapper, existingIcon);

        // حفظ المراجع
        elements.wrapper = wrapper;
        elements.trigger = wrapper.querySelector('#notificationTrigger');
        elements.dropdown = wrapper.querySelector('#notificationDropdown');
        elements.badge = wrapper.querySelector('#notificationBadge');
        elements.ping = wrapper.querySelector('#notificationPing');
        elements.list = wrapper.querySelector('#notificationList');
        elements.markAllBtn = wrapper.querySelector('#btnMarkAllRead');
        elements.settingsBtn = wrapper.querySelector('#btnNotifSettings');

        // إضافة الأنماط
        injectStyles();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // إنشاء حاوية Toast - Create Toast Container
    // ═══════════════════════════════════════════════════════════════════════════
    function createToastContainer() {
        if (document.getElementById('toastContainer')) return;
        
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-atomic', 'true');
        document.body.appendChild(container);
        
        elements.toastContainer = container;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ربط الأحداث - Bind Events
    // ═══════════════════════════════════════════════════════════════════════════
    function bindEvents() {
        // فتح/إغلاق القائمة المنسدلة
        if (elements.trigger) {
            elements.trigger.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleDropdown();
            });
        }

        // إغلاق عند النقر خارجاً
        document.addEventListener('click', (e) => {
            if (state.isOpen && elements.wrapper && !elements.wrapper.contains(e.target)) {
                closeDropdown();
            }
        });

        // إغلاق بـ Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && state.isOpen) {
                closeDropdown();
            }
        });

        // تعيين الكل كمقروء
        if (elements.markAllBtn) {
            elements.markAllBtn.addEventListener('click', markAllAsRead);
        }

        // فتح الإعدادات
        if (elements.settingsBtn) {
            elements.settingsBtn.addEventListener('click', openSettings);
        }

        // التعامل مع عناصر الإشعارات
        if (elements.list) {
            elements.list.addEventListener('click', handleNotificationClick);
        }

        // عند إخفاء الصفحة (تقليل الاستعلامات)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopPolling();
            } else {
                startPolling();
                fetchNotifications();
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // فتح/إغلاق القائمة - Toggle Dropdown
    // ═══════════════════════════════════════════════════════════════════════════
    function toggleDropdown() {
        if (state.isOpen) {
            closeDropdown();
        } else {
            openDropdown();
        }
    }

    function openDropdown() {
        if (!elements.dropdown) return;
        
        state.isOpen = true;
        elements.dropdown.classList.add('show');
        elements.trigger.setAttribute('aria-expanded', 'true');
        elements.dropdown.setAttribute('aria-hidden', 'false');
        
        // إيقاف تأثير النبض
        if (elements.ping) {
            elements.ping.classList.remove('active');
        }
        
        // تحديث الإشعارات
        fetchNotifications();
    }

    function closeDropdown() {
        if (!elements.dropdown) return;
        
        state.isOpen = false;
        elements.dropdown.classList.remove('show');
        elements.trigger.setAttribute('aria-expanded', 'false');
        elements.dropdown.setAttribute('aria-hidden', 'true');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // جلب الإشعارات - Fetch Notifications
    // ═══════════════════════════════════════════════════════════════════════════
    async function fetchNotifications() {
        if (state.isLoading) return;
        
        state.isLoading = true;
        
        try {
            const response = await fetch(`${CONFIG.apiBase}list.php?limit=${CONFIG.maxNotifications}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) throw new Error('Network error');
            
            const data = await response.json();
            
            if (data.success) {
                const oldUnreadCount = state.unreadCount;
                
                state.notifications = data.notifications || [];
                state.unreadCount = data.unread_count || 0;
                state.lastFetchTime = new Date();
                
                // تحديث العرض
                renderNotifications();
                updateBadge();
                
                // إذا وصلت إشعارات جديدة
                if (data.new_notifications && data.new_notifications.length > 0) {
                    handleNewNotifications(data.new_notifications);
                } else if (state.unreadCount > oldUnreadCount) {
                    // إذا زاد عدد غير المقروءة
                    triggerNewNotificationAlert();
                }
            }
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
            showError();
        } finally {
            state.isLoading = false;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // عرض الإشعارات - Render Notifications
    // ═══════════════════════════════════════════════════════════════════════════
    function renderNotifications() {
        if (!elements.list) return;
        
        if (state.notifications.length === 0) {
            elements.list.innerHTML = `
                <div class="notification-empty">
                    <i class="bi bi-bell-slash"></i>
                    <p>لا توجد إشعارات</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        
        state.notifications.forEach(notif => {
            const style = NOTIFICATION_ICONS[notif.type] || NOTIFICATION_ICONS.info;
            const isUnread = !notif.is_read;
            const timeAgo = formatTimeAgo(notif.created_at);
            
            html += `
                <div class="notification-item ${isUnread ? 'unread' : ''}" 
                     data-id="${notif.id}" 
                     data-url="${notif.url || '#'}"
                     role="button"
                     tabindex="0">
                    <div class="notification-item-icon" style="background: ${style.bg}; color: ${style.color}">
                        <i class="bi ${style.icon}"></i>
                    </div>
                    <div class="notification-item-content">
                        <div class="notification-item-title">${escapeHtml(notif.title)}</div>
                        <div class="notification-item-message">${escapeHtml(notif.message)}</div>
                        <div class="notification-item-time">
                            <i class="bi bi-clock"></i>
                            ${timeAgo}
                        </div>
                    </div>
                    ${isUnread ? '<div class="notification-item-dot"></div>' : ''}
                </div>
            `;
        });
        
        elements.list.innerHTML = html;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // تحديث الشارة - Update Badge
    // ═══════════════════════════════════════════════════════════════════════════
    function updateBadge() {
        if (!elements.badge) return;
        
        if (state.unreadCount > 0) {
            elements.badge.textContent = state.unreadCount > 99 ? '99+' : state.unreadCount;
            elements.badge.style.display = 'flex';
        } else {
            elements.badge.style.display = 'none';
        }
        
        // تحديث عنوان الصفحة
        updatePageTitle();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // التعامل مع الإشعارات الجديدة
    // ═══════════════════════════════════════════════════════════════════════════
    function handleNewNotifications(newNotifications) {
        newNotifications.forEach(notif => {
            // عرض Toast
            showToast(notif);
            
            // إشعار سطح المكتب
            if (CONFIG.desktopNotifications && Notification.permission === 'granted') {
                showDesktopNotification(notif);
            }
        });
        
        // تشغيل الصوت
        if (CONFIG.soundEnabled) {
            playNotificationSound();
        }
        
        // تفعيل تأثير النبض
        triggerNewNotificationAlert();
    }

    function triggerNewNotificationAlert() {
        if (elements.ping) {
            elements.ping.classList.add('active');
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Toast الإشعارات - Show Toast
    // ═══════════════════════════════════════════════════════════════════════════
    function showToast(notification) {
        if (!elements.toastContainer) return;
        
        const style = NOTIFICATION_ICONS[notification.type] || NOTIFICATION_ICONS.info;
        
        const toast = document.createElement('div');
        toast.className = 'notification-toast';
        toast.innerHTML = `
            <div class="toast-icon" style="background: ${style.bg}; color: ${style.color}">
                <i class="bi ${style.icon}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${escapeHtml(notification.title)}</div>
                <div class="toast-message">${escapeHtml(notification.message)}</div>
            </div>
            <button class="toast-close" aria-label="إغلاق">
                <i class="bi bi-x-lg"></i>
            </button>
            <div class="toast-progress"></div>
        `;
        
        // زر الإغلاق
        toast.querySelector('.toast-close').addEventListener('click', () => {
            removeToast(toast);
        });
        
        // النقر على Toast
        toast.addEventListener('click', (e) => {
            if (!e.target.closest('.toast-close')) {
                if (notification.url) {
                    window.location.href = notification.url;
                }
                removeToast(toast);
            }
        });
        
        elements.toastContainer.appendChild(toast);
        
        // تفعيل الحركة
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });
        
        // إزالة تلقائية
        setTimeout(() => {
            removeToast(toast);
        }, CONFIG.toastDuration);
    }

    function removeToast(toast) {
        toast.classList.remove('show');
        toast.classList.add('hide');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // إشعارات سطح المكتب - Desktop Notifications
    // ═══════════════════════════════════════════════════════════════════════════
    function requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }

    function showDesktopNotification(notification) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        
        const n = new Notification(notification.title, {
            body: notification.message,
            icon: '/app/assets/images/pwa/icon-192.png',
            badge: '/app/assets/images/pwa/badge-72.png',
            tag: `sarh-notif-${notification.id}`,
            requireInteraction: false,
            silent: true // الصوت يتم التحكم فيه منفصلاً
        });
        
        n.onclick = () => {
            window.focus();
            if (notification.url) {
                window.location.href = notification.url;
            }
            n.close();
        };
        
        // إغلاق تلقائي بعد 10 ثواني
        setTimeout(() => n.close(), 10000);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // الصوت - Sound
    // ═══════════════════════════════════════════════════════════════════════════
    function initSound() {
        try {
            // إنشاء Audio element مع fallback عند عدم وجود الملف
            state.notificationSound = new Audio('/app/assets/audio/notification.mp3');
            state.notificationSound.volume = 0.5;
            
            // معالجة خطأ تحميل الملف
            state.notificationSound.addEventListener('error', (e) => {
                console.warn('[Notifications] Audio file not found, using fallback beep');
                state.notificationSound = null; // سيتحول لـ Web Audio API
            });
        } catch (error) {
            console.warn('[Notifications] Failed to initialize audio:', error);
            state.notificationSound = null;
        }
    }

    function playNotificationSound() {
        if (!CONFIG.soundEnabled) return;
        
        // استخدام Web Audio API كـ fallback إذا لم يكن الملف موجوداً
        if (!state.notificationSound) {
            try {
                // إنشاء beep صوتي بسيط باستخدام Web Audio API
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.value = 800;
                oscillator.type = 'sine';
                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.2);
            } catch (e) {
                // فشل تشغيل الصوت - تجاهل بصمت
            }
            return;
        }
        
        try {
            state.notificationSound.currentTime = 0;
            state.notificationSound.play().catch(() => {
                // المستخدم لم يتفاعل بعد - تجاهل الخطأ
            });
        } catch (e) {
            // فشل تشغيل الصوت
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // التعامل مع النقر على الإشعار
    // ═══════════════════════════════════════════════════════════════════════════
    function handleNotificationClick(e) {
        const item = e.target.closest('.notification-item');
        if (!item) return;
        
        const id = item.dataset.id;
        const url = item.dataset.url;
        
        // تعيين كمقروء
        markAsRead(id);
        
        // الانتقال للرابط
        if (url && url !== '#') {
            window.location.href = url;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // تعيين كمقروء - Mark As Read
    // ═══════════════════════════════════════════════════════════════════════════
    async function markAsRead(id) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                              document.querySelector('input[name="sarh_csrf_token"]')?.value || '';
            
            await fetch(`${CONFIG.apiBase}mark-read.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ id: id })
            });
            
            // تحديث محلي
            const item = elements.list?.querySelector(`[data-id="${id}"]`);
            if (item) {
                item.classList.remove('unread');
                const dot = item.querySelector('.notification-item-dot');
                if (dot) dot.remove();
            }
            
            // تقليل العداد
            if (state.unreadCount > 0) {
                state.unreadCount--;
                updateBadge();
            }
            
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    }

    async function markAllAsRead() {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                              document.querySelector('input[name="sarh_csrf_token"]')?.value || '';
            
            const response = await fetch(`${CONFIG.apiBase}mark-read.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ all: true })
            });
            
            if (response.ok) {
                // تحديث محلي
                state.unreadCount = 0;
                updateBadge();
                
                // إزالة علامات unread
                elements.list?.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                    const dot = item.querySelector('.notification-item-dot');
                    if (dot) dot.remove();
                });
                
                showToast({
                    type: 'success',
                    title: 'تم',
                    message: 'تم تعيين جميع الإشعارات كمقروءة'
                });
            }
            
        } catch (error) {
            console.error('Failed to mark all as read:', error);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // الفحص الدوري - Polling
    // ═══════════════════════════════════════════════════════════════════════════
    function startPolling() {
        if (state.pollTimer) return;
        
        state.pollTimer = setInterval(() => {
            if (!document.hidden) {
                fetchNotifications();
            }
        }, CONFIG.pollInterval);
    }

    function stopPolling() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // الإعدادات - Settings
    // ═══════════════════════════════════════════════════════════════════════════
    function loadSettings() {
        const saved = localStorage.getItem('sarh_notification_settings');
        if (saved) {
            try {
                const settings = JSON.parse(saved);
                CONFIG.soundEnabled = settings.soundEnabled ?? true;
                CONFIG.desktopNotifications = settings.desktopNotifications ?? true;
            } catch (e) {}
        }
    }

    function saveSettings() {
        localStorage.setItem('sarh_notification_settings', JSON.stringify({
            soundEnabled: CONFIG.soundEnabled,
            desktopNotifications: CONFIG.desktopNotifications
        }));
    }

    function openSettings() {
        // إنشاء modal الإعدادات
        const modal = document.createElement('div');
        modal.className = 'notification-settings-modal';
        modal.innerHTML = `
            <div class="settings-backdrop"></div>
            <div class="settings-dialog">
                <div class="settings-header">
                    <h5><i class="bi bi-gear me-2"></i>إعدادات الإشعارات</h5>
                    <button class="btn-close-settings" aria-label="إغلاق">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="settings-body">
                    <div class="setting-item">
                        <div class="setting-info">
                            <div class="setting-title">
                                <i class="bi bi-volume-up text-primary me-2"></i>
                                الأصوات
                            </div>
                            <div class="setting-desc">تشغيل صوت عند وصول إشعار جديد</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="settingSound" ${CONFIG.soundEnabled ? 'checked' : ''}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <div class="setting-title">
                                <i class="bi bi-window text-info me-2"></i>
                                إشعارات سطح المكتب
                            </div>
                            <div class="setting-desc">عرض إشعارات في سطح المكتب</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="settingDesktop" ${CONFIG.desktopNotifications ? 'checked' : ''}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="settings-footer">
                    <button class="btn btn-primary btn-save-settings">
                        <i class="bi bi-check-lg me-2"></i>
                        حفظ
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // إغلاق
        modal.querySelector('.settings-backdrop').addEventListener('click', () => modal.remove());
        modal.querySelector('.btn-close-settings').addEventListener('click', () => modal.remove());
        
        // حفظ
        modal.querySelector('.btn-save-settings').addEventListener('click', () => {
            CONFIG.soundEnabled = modal.querySelector('#settingSound').checked;
            CONFIG.desktopNotifications = modal.querySelector('#settingDesktop').checked;
            saveSettings();
            modal.remove();
            
            showToast({
                type: 'success',
                title: 'تم الحفظ',
                message: 'تم حفظ إعدادات الإشعارات'
            });
        });
        
        // تفعيل الحركة
        requestAnimationFrame(() => modal.classList.add('show'));
        
        // إغلاق القائمة المنسدلة
        closeDropdown();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // دوال مساعدة - Helper Functions
    // ═══════════════════════════════════════════════════════════════════════════
    function formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        
        if (seconds < 60) return 'الآن';
        if (seconds < 3600) return `منذ ${Math.floor(seconds / 60)} دقيقة`;
        if (seconds < 86400) return `منذ ${Math.floor(seconds / 3600)} ساعة`;
        if (seconds < 604800) return `منذ ${Math.floor(seconds / 86400)} يوم`;
        
        return date.toLocaleDateString('ar-SA');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function updatePageTitle() {
        const baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
        if (state.unreadCount > 0) {
            document.title = `(${state.unreadCount}) ${baseTitle}`;
        } else {
            document.title = baseTitle;
        }
    }

    function showError() {
        if (!elements.list) return;
        elements.list.innerHTML = `
            <div class="notification-error">
                <i class="bi bi-exclamation-triangle"></i>
                <p>فشل تحميل الإشعارات</p>
                <button class="btn btn-sm btn-outline-primary" onclick="SarhNotifications.refresh()">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    إعادة المحاولة
                </button>
            </div>
        `;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // حقن الأنماط - Inject Styles
    // ═══════════════════════════════════════════════════════════════════════════
    function injectStyles() {
        if (document.getElementById('sarh-notification-styles')) return;
        
        const styles = document.createElement('style');
        styles.id = 'sarh-notification-styles';
        styles.textContent = `
            /* ═══════════════════════════════════════════════════════════════════════
               مركز الإشعارات - Notification Center
               ═══════════════════════════════════════════════════════════════════════ */
            .notification-center-wrapper {
                position: relative;
            }
            
            .notification-trigger {
                background: none;
                border: none;
                cursor: pointer;
                position: relative;
                color: white;
                font-size: 1.25rem;
                padding: 8px;
                border-radius: 50%;
                transition: all 0.2s ease;
            }
            
            .notification-trigger:hover {
                background: rgba(255,255,255,0.15);
            }
            
            .notification-badge {
                position: absolute;
                top: 2px;
                right: 2px;
                background: linear-gradient(135deg, #ff4757, #ff6b6b);
                color: white;
                font-size: 0.65rem;
                min-width: 18px;
                height: 18px;
                border-radius: 9px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                box-shadow: 0 2px 6px rgba(255,71,87,0.4);
                animation: badgePulse 2s ease-in-out infinite;
            }
            
            @keyframes badgePulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }
            
            .notification-ping {
                position: absolute;
                top: 0;
                right: 0;
                width: 100%;
                height: 100%;
                border-radius: 50%;
                pointer-events: none;
            }
            
            .notification-ping.active::before {
                content: '';
                position: absolute;
                top: -4px;
                right: -4px;
                width: calc(100% + 8px);
                height: calc(100% + 8px);
                border-radius: 50%;
                border: 2px solid #ff4757;
                animation: pingAnimation 1.5s ease-out infinite;
            }
            
            @keyframes pingAnimation {
                0% { transform: scale(1); opacity: 1; }
                100% { transform: scale(1.5); opacity: 0; }
            }
            
            /* ═══════════════════════════════════════════════════════════════════════
               القائمة المنسدلة - Dropdown
               ═══════════════════════════════════════════════════════════════════════ */
            .notification-dropdown {
                position: absolute;
                top: calc(100% + 12px);
                left: 0;
                width: 380px;
                max-width: calc(100vw - 32px);
                background: white;
                border-radius: 16px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2), 0 0 0 1px rgba(0,0,0,0.05);
                opacity: 0;
                visibility: hidden;
                transform: translateY(-10px) scale(0.95);
                transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 1050;
                overflow: hidden;
            }
            
            .notification-dropdown.show {
                opacity: 1;
                visibility: visible;
                transform: translateY(0) scale(1);
            }
            
            .notification-dropdown-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 20px;
                border-bottom: 1px solid #f0f0f0;
                background: linear-gradient(135deg, #f8f9fa 0%, white 100%);
            }
            
            .notification-dropdown-header h6 {
                margin: 0;
                font-weight: 700;
                color: #1a1a2e;
                font-size: 1rem;
            }
            
            .notification-header-actions {
                display: flex;
                gap: 8px;
            }
            
            .btn-icon {
                background: none;
                border: none;
                width: 32px;
                height: 32px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #6c757d;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .btn-icon:hover {
                background: #f0f0f0;
                color: #ff6f00;
            }
            
            /* ═══════════════════════════════════════════════════════════════════════
               قائمة الإشعارات - Notification List
               ═══════════════════════════════════════════════════════════════════════ */
            .notification-dropdown-body {
                max-height: 400px;
                overflow-y: auto;
                overscroll-behavior: contain;
            }
            
            .notification-dropdown-body::-webkit-scrollbar {
                width: 6px;
            }
            
            .notification-dropdown-body::-webkit-scrollbar-track {
                background: #f0f0f0;
            }
            
            .notification-dropdown-body::-webkit-scrollbar-thumb {
                background: #ccc;
                border-radius: 3px;
            }
            
            .notification-item {
                display: flex;
                gap: 12px;
                padding: 14px 20px;
                cursor: pointer;
                transition: all 0.2s;
                position: relative;
                border-bottom: 1px solid #f5f5f5;
            }
            
            .notification-item:hover {
                background: #f8f9fa;
            }
            
            .notification-item.unread {
                background: linear-gradient(90deg, rgba(255,111,0,0.08) 0%, rgba(255,111,0,0.02) 100%);
            }
            
            .notification-item.unread::before {
                content: '';
                position: absolute;
                right: 0;
                top: 0;
                bottom: 0;
                width: 3px;
                background: linear-gradient(180deg, #ff6f00, #ffa040);
            }
            
            .notification-item-icon {
                width: 44px;
                height: 44px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.1rem;
                flex-shrink: 0;
            }
            
            .notification-item-content {
                flex: 1;
                min-width: 0;
            }
            
            .notification-item-title {
                font-weight: 600;
                color: #1a1a2e;
                margin-bottom: 2px;
                font-size: 0.9rem;
            }
            
            .notification-item-message {
                color: #6c757d;
                font-size: 0.82rem;
                line-height: 1.4;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            
            .notification-item-time {
                font-size: 0.7rem;
                color: #adb5bd;
                margin-top: 4px;
                display: flex;
                align-items: center;
                gap: 4px;
            }
            
            .notification-item-dot {
                position: absolute;
                left: 16px;
                top: 50%;
                transform: translateY(-50%);
                width: 8px;
                height: 8px;
                background: #ff6f00;
                border-radius: 50%;
                box-shadow: 0 0 0 3px rgba(255,111,0,0.2);
            }
            
            /* ═══════════════════════════════════════════════════════════════════════
               حالات خاصة - Empty & Loading
               ═══════════════════════════════════════════════════════════════════════ */
            .notification-empty,
            .notification-loading,
            .notification-error {
                text-align: center;
                padding: 40px 20px;
                color: #adb5bd;
            }
            
            .notification-empty i,
            .notification-error i {
                font-size: 3rem;
                display: block;
                margin-bottom: 12px;
                opacity: 0.5;
            }
            
            .notification-loading {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 12px;
            }
            
            /* ═══════════════════════════════════════════════════════════════════════
               التذييل - Footer
               ═══════════════════════════════════════════════════════════════════════ */
            .notification-dropdown-footer {
                padding: 12px 20px;
                border-top: 1px solid #f0f0f0;
                background: #fafafa;
            }
            
            .view-all-link {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 8px;
                color: #ff6f00;
                text-decoration: none;
                font-weight: 600;
                font-size: 0.9rem;
                padding: 8px;
                border-radius: 8px;
                transition: all 0.2s;
            }
            
            .view-all-link:hover {
                background: rgba(255,111,0,0.1);
                color: #e65100;
            }
            
            /* ═══════════════════════════════════════════════════════════════════════
               Toast إشعارات
               ═══════════════════════════════════════════════════════════════════════ */
            .toast-container {
                position: fixed;
                top: 80px;
                left: 20px;
                z-index: 1100;
                display: flex;
                flex-direction: column;
                gap: 12px;
                pointer-events: none;
            }
            
            .notification-toast {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                background: white;
                padding: 16px;
                border-radius: 12px;
                box-shadow: 0 8px 30px rgba(0,0,0,0.15), 0 0 0 1px rgba(0,0,0,0.05);
                min-width: 320px;
                max-width: 400px;
                pointer-events: auto;
                cursor: pointer;
                transform: translateX(-100%);
                opacity: 0;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                overflow: hidden;
            }
            
            .notification-toast.show {
                transform: translateX(0);
                opacity: 1;
            }
            
            .notification-toast.hide {
                transform: translateX(-100%);
                opacity: 0;
            }
            
            .toast-icon {
                width: 40px;
                height: 40px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1rem;
                flex-shrink: 0;
            }
            
            .toast-content {
                flex: 1;
                min-width: 0;
            }
            
            .toast-title {
                font-weight: 700;
                color: #1a1a2e;
                margin-bottom: 2px;
            }
            
            .toast-message {
                color: #6c757d;
                font-size: 0.875rem;
            }
            
            .toast-close {
                background: none;
                border: none;
                color: #adb5bd;
                cursor: pointer;
                padding: 4px;
                border-radius: 6px;
                transition: all 0.2s;
            }
            
            .toast-close:hover {
                background: #f0f0f0;
                color: #6c757d;
            }
            
            .toast-progress {
                position: absolute;
                bottom: 0;
                right: 0;
                left: 0;
                height: 3px;
                background: linear-gradient(90deg, #ff6f00, #ffa040);
                animation: toastProgress 5s linear forwards;
            }
            
            @keyframes toastProgress {
                from { width: 100%; }
                to { width: 0%; }
            }
            
            /* ═══════════════════════════════════════════════════════════════════════
               Modal الإعدادات - Settings Modal
               ═══════════════════════════════════════════════════════════════════════ */
            .notification-settings-modal {
                position: fixed;
                inset: 0;
                z-index: 1200;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .settings-backdrop {
                position: absolute;
                inset: 0;
                background: rgba(0,0,0,0.5);
                opacity: 0;
                transition: opacity 0.3s;
            }
            
            .notification-settings-modal.show .settings-backdrop {
                opacity: 1;
            }
            
            .settings-dialog {
                position: relative;
                background: white;
                border-radius: 20px;
                width: 100%;
                max-width: 400px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                transform: scale(0.9) translateY(20px);
                opacity: 0;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            .notification-settings-modal.show .settings-dialog {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
            
            .settings-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px 24px;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .settings-header h5 {
                margin: 0;
                font-weight: 700;
            }
            
            .btn-close-settings {
                background: #f0f0f0;
                border: none;
                width: 36px;
                height: 36px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .btn-close-settings:hover {
                background: #e0e0e0;
            }
            
            .settings-body {
                padding: 20px 24px;
            }
            
            .setting-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 0;
                border-bottom: 1px solid #f5f5f5;
            }
            
            .setting-item:last-child {
                border-bottom: none;
            }
            
            .setting-title {
                font-weight: 600;
                color: #1a1a2e;
                margin-bottom: 4px;
            }
            
            .setting-desc {
                font-size: 0.8rem;
                color: #6c757d;
            }
            
            /* Toggle Switch */
            .toggle-switch {
                position: relative;
                display: inline-block;
                width: 50px;
                height: 28px;
            }
            
            .toggle-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            
            .toggle-slider {
                position: absolute;
                cursor: pointer;
                inset: 0;
                background: #e0e0e0;
                border-radius: 28px;
                transition: all 0.3s;
            }
            
            .toggle-slider::before {
                content: '';
                position: absolute;
                width: 22px;
                height: 22px;
                left: 3px;
                bottom: 3px;
                background: white;
                border-radius: 50%;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                transition: all 0.3s;
            }
            
            .toggle-switch input:checked + .toggle-slider {
                background: linear-gradient(135deg, #ff6f00, #ffa040);
            }
            
            .toggle-switch input:checked + .toggle-slider::before {
                transform: translateX(22px);
            }
            
            .settings-footer {
                padding: 16px 24px 24px;
            }
            
            .btn-save-settings {
                width: 100%;
                padding: 14px;
                font-weight: 700;
            }
            
            /* ═══════════════════════════════════════════════════════════════════════
               التجاوب - Responsive
               ═══════════════════════════════════════════════════════════════════════ */
            @media (max-width: 576px) {
                .notification-dropdown {
                    position: fixed;
                    top: auto;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    width: 100%;
                    max-width: none;
                    border-radius: 20px 20px 0 0;
                    transform: translateY(100%);
                    max-height: 70vh;
                }
                
                .notification-dropdown.show {
                    transform: translateY(0);
                }
                
                .toast-container {
                    left: 10px;
                    right: 10px;
                    top: 70px;
                }
                
                .notification-toast {
                    min-width: auto;
                    max-width: none;
                }
            }
        `;
        
        document.head.appendChild(styles);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // API العامة - Public API
    // ═══════════════════════════════════════════════════════════════════════════
    window.SarhNotifications = {
        init: init,
        refresh: fetchNotifications,
        showToast: showToast,
        markAsRead: markAsRead,
        markAllAsRead: markAllAsRead,
        getUnreadCount: () => state.unreadCount,
        setSoundEnabled: (enabled) => {
            CONFIG.soundEnabled = enabled;
            saveSettings();
        },
        setDesktopNotificationsEnabled: (enabled) => {
            CONFIG.desktopNotifications = enabled;
            saveSettings();
        }
    };

    // ═══════════════════════════════════════════════════════════════════════════
    // التهيئة التلقائية - Auto Init
    // ═══════════════════════════════════════════════════════════════════════════
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
