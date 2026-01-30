/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - NOTIFICATIONS WIDGET                                 ║
 * ║           ويدجت الإشعارات المنبثقة                                           ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Features:                                                                   ║
 * ║  - إشعارات منبثقة في الوقت الحقيقي                                           ║
 * ║  - صوت التنبيه                                                               ║
 * ║  - قائمة منسدلة للإشعارات                                                    ║
 * ║  - عداد الإشعارات غير المقروءة                                               ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

class NotificationsWidget {
    constructor() {
        this.unreadCount = 0;
        this.notifications = [];
        this.isOpen = false;
        this.checkInterval = 30000; // 30 seconds
        this.lastCheck = 0;
        
        this.init();
    }

    /**
     * تهيئة الويدجت
     */
    init() {
        this.createWidget();
        this.bindEvents();
        this.fetchNotifications();
        
        // فحص دوري
        setInterval(() => this.fetchNotifications(), this.checkInterval);
    }

    /**
     * إنشاء عناصر الويدجت
     */
    createWidget() {
        // Bell Icon in header (if exists)
        const existingBell = document.querySelector('.notification-bell');
        if (existingBell) {
            this.bellIcon = existingBell;
        } else {
            // Create floating bell
            this.bellIcon = document.createElement('div');
            this.bellIcon.className = 'notification-bell-float';
            this.bellIcon.innerHTML = `
                <i class="bi bi-bell"></i>
                <span class="notification-badge" style="display: none;">0</span>
            `;
            document.body.appendChild(this.bellIcon);
        }

        // Dropdown panel
        this.panel = document.createElement('div');
        this.panel.className = 'notification-panel';
        this.panel.innerHTML = `
            <div class="notification-header">
                <h6><i class="bi bi-bell me-2"></i>الإشعارات</h6>
                <button class="btn btn-sm btn-link mark-all-read">
                    <i class="bi bi-check-all"></i>
                </button>
            </div>
            <div class="notification-list"></div>
            <div class="notification-footer">
                <a href="announcements.php">عرض الكل</a>
            </div>
        `;
        document.body.appendChild(this.panel);

        // Add styles
        this.addStyles();
    }

    /**
     * إضافة الأنماط
     */
    addStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .notification-bell-float {
                position: fixed;
                top: 80px;
                left: 20px;
                width: 50px;
                height: 50px;
                background: var(--sarh-primary, #ff6f00);
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.3rem;
                cursor: pointer;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                z-index: 1050;
                transition: all 0.3s ease;
            }

            .notification-bell-float:hover {
                transform: scale(1.1);
            }

            .notification-bell-float.has-notifications {
                animation: bellRing 0.5s ease infinite;
            }

            @keyframes bellRing {
                0%, 100% { transform: rotate(0); }
                25% { transform: rotate(10deg); }
                75% { transform: rotate(-10deg); }
            }

            .notification-badge {
                position: absolute;
                top: -5px;
                right: -5px;
                min-width: 20px;
                height: 20px;
                background: #dc3545;
                color: white;
                border-radius: 10px;
                font-size: 0.7rem;
                font-weight: bold;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0 5px;
            }

            .notification-panel {
                position: fixed;
                top: 140px;
                left: 20px;
                width: 320px;
                max-height: 400px;
                background: white;
                border-radius: 15px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                z-index: 1060;
                display: none;
                flex-direction: column;
                overflow: hidden;
            }

            .notification-panel.open {
                display: flex;
                animation: slideIn 0.3s ease;
            }

            @keyframes slideIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .notification-header {
                padding: 15px;
                background: linear-gradient(135deg, var(--sarh-primary, #ff6f00), #ff9800);
                color: white;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .notification-header h6 {
                margin: 0;
            }

            .notification-header .btn-link {
                color: white;
                opacity: 0.8;
            }

            .notification-header .btn-link:hover {
                opacity: 1;
            }

            .notification-list {
                flex: 1;
                overflow-y: auto;
                max-height: 280px;
            }

            .notification-item {
                padding: 12px 15px;
                border-bottom: 1px solid #eee;
                display: flex;
                gap: 12px;
                cursor: pointer;
                transition: background 0.2s ease;
            }

            .notification-item:hover {
                background: #f8f9fa;
            }

            .notification-item.unread {
                background: rgba(255, 111, 0, 0.05);
                border-right: 3px solid var(--sarh-primary, #ff6f00);
            }

            .notification-icon {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .notification-content {
                flex: 1;
                min-width: 0;
            }

            .notification-title {
                font-weight: 600;
                font-size: 0.9rem;
                margin-bottom: 2px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .notification-message {
                font-size: 0.8rem;
                color: #666;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .notification-time {
                font-size: 0.7rem;
                color: #999;
                margin-top: 3px;
            }

            .notification-footer {
                padding: 12px;
                text-align: center;
                border-top: 1px solid #eee;
            }

            .notification-footer a {
                color: var(--sarh-primary, #ff6f00);
                text-decoration: none;
                font-size: 0.85rem;
            }

            .notification-empty {
                padding: 40px 20px;
                text-align: center;
                color: #999;
            }

            .notification-empty i {
                font-size: 3rem;
                margin-bottom: 10px;
                display: block;
            }

            /* Dark mode support */
            [data-theme="dark"] .notification-panel {
                background: #1e1e1e;
            }

            [data-theme="dark"] .notification-item {
                border-color: #333;
            }

            [data-theme="dark"] .notification-item:hover {
                background: #252525;
            }

            [data-theme="dark"] .notification-title {
                color: #e0e0e0;
            }

            [data-theme="dark"] .notification-message {
                color: #aaa;
            }

            [data-theme="dark"] .notification-footer {
                border-color: #333;
            }

            @media (max-width: 768px) {
                .notification-bell-float {
                    top: auto;
                    bottom: 180px;
                    width: 45px;
                    height: 45px;
                }

                .notification-panel {
                    top: auto;
                    bottom: 230px;
                    left: 15px;
                    right: 15px;
                    width: auto;
                }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * ربط الأحداث
     */
    bindEvents() {
        // Toggle panel
        this.bellIcon.addEventListener('click', (e) => {
            e.stopPropagation();
            this.togglePanel();
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!this.panel.contains(e.target) && !this.bellIcon.contains(e.target)) {
                this.closePanel();
            }
        });

        // Mark all as read
        this.panel.querySelector('.mark-all-read').addEventListener('click', () => {
            this.markAllAsRead();
        });
    }

    /**
     * جلب الإشعارات
     */
    async fetchNotifications() {
        try {
            const response = await fetch('/api/notifications.php?limit=10');
            const data = await response.json();
            
            if (data.success) {
                const hadUnread = this.unreadCount > 0;
                const newUnread = data.unread_count || 0;
                
                this.notifications = data.notifications || [];
                this.unreadCount = newUnread;
                
                this.updateBadge();
                this.renderNotifications();
                
                // Show toast for new notifications
                if (newUnread > hadUnread && newUnread > 0) {
                    this.showToast(this.notifications[0]);
                }
            }
        } catch (error) {
            console.error('Error fetching notifications:', error);
        }
    }

    /**
     * تحديث عداد الشارة
     */
    updateBadge() {
        const badge = this.bellIcon.querySelector('.notification-badge');
        
        if (this.unreadCount > 0) {
            badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
            badge.style.display = 'flex';
            this.bellIcon.classList.add('has-notifications');
        } else {
            badge.style.display = 'none';
            this.bellIcon.classList.remove('has-notifications');
        }
    }

    /**
     * عرض الإشعارات
     */
    renderNotifications() {
        const list = this.panel.querySelector('.notification-list');
        
        if (this.notifications.length === 0) {
            list.innerHTML = `
                <div class="notification-empty">
                    <i class="bi bi-bell-slash"></i>
                    <p>لا توجد إشعارات</p>
                </div>
            `;
            return;
        }

        list.innerHTML = this.notifications.map(n => `
            <div class="notification-item ${n.is_read ? '' : 'unread'}" data-id="${n.id}" data-type="${n.type}">
                <div class="notification-icon bg-${n.icon?.color || 'primary'}" style="background: var(--bs-${n.icon?.color || 'primary'}); color: white;">
                    <i class="bi ${n.icon?.icon || 'bi-bell'}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${n.title}</div>
                    <div class="notification-message">${n.message}</div>
                    <div class="notification-time">${this.timeAgo(n.created_at)}</div>
                </div>
            </div>
        `).join('');

        // Bind click events
        list.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => {
                this.handleNotificationClick(item.dataset.id, item.dataset.type);
            });
        });
    }

    /**
     * معالجة النقر على إشعار
     */
    handleNotificationClick(id, type) {
        // Mark as read
        fetch('/api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read', id: id })
        });

        // Navigate based on type
        const routes = {
            'announcement': 'announcements.php',
            'challenge': 'leaderboard.php',
            'badge': 'badges.php',
            'leave': 'leave-requests.php',
            'points': 'points-store.php'
        };

        const route = routes[type] || 'announcements.php';
        window.location.href = route;
    }

    /**
     * فتح/إغلاق اللوحة
     */
    togglePanel() {
        this.isOpen = !this.isOpen;
        this.panel.classList.toggle('open', this.isOpen);
        
        if (this.isOpen) {
            this.fetchNotifications();
        }
    }

    /**
     * إغلاق اللوحة
     */
    closePanel() {
        this.isOpen = false;
        this.panel.classList.remove('open');
    }

    /**
     * تعليم الكل كمقروء
     */
    async markAllAsRead() {
        try {
            await fetch('/api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_read' })
            });
            
            this.unreadCount = 0;
            this.updateBadge();
            
            // Update UI
            this.panel.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            
            if (typeof showSuccess === 'function') {
                showSuccess('تم تعليم جميع الإشعارات كمقروءة');
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
        }
    }

    /**
     * عرض إشعار منبثق
     */
    showToast(notification) {
        if (!notification) return;
        
        // Use SweetAlert2 toast if available
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-start',
                icon: 'info',
                title: notification.title,
                text: notification.message?.substring(0, 50),
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('click', () => {
                        this.togglePanel();
                        Swal.close();
                    });
                }
            });
        }

        // Play sound
        this.playNotificationSound();
    }

    /**
     * تشغيل صوت الإشعار
     */
    playNotificationSound() {
        try {
            // Try to create and play notification sound
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            gainNode.gain.value = 0.1;
            
            oscillator.start();
            oscillator.stop(audioContext.currentTime + 0.2);
        } catch (e) {
            // Ignore audio errors
        }
    }

    /**
     * حساب الوقت المنقضي
     */
    timeAgo(datetime) {
        const now = new Date();
        const time = new Date(datetime);
        const diff = Math.floor((now - time) / 1000);
        
        if (diff < 60) return 'الآن';
        if (diff < 3600) return Math.floor(diff / 60) + ' دقيقة';
        if (diff < 86400) return Math.floor(diff / 3600) + ' ساعة';
        if (diff < 604800) return Math.floor(diff / 86400) + ' يوم';
        return new Date(datetime).toLocaleDateString('ar-SA');
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (typeof SARH !== 'undefined' && SARH.isLoggedIn) {
        window.NotificationsWidget = new NotificationsWidget();
    }
});
