/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - DARK MODE / THEME MANAGER                            ║
 * ║           الوضع الليلي الذكي                                                  ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Features:                                                                   ║
 * ║  - وضع فاتح/داكن/تلقائي                                                      ║
 * ║  - تغيير تلقائي حسب الوقت                                                    ║
 * ║  - تغيير حسب إعدادات النظام                                                  ║
 * ║  - حفظ التفضيلات                                                             ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

class ThemeManager {
    constructor() {
        this.mode = localStorage.getItem('sarh_theme_mode') || 'auto';
        this.darkStart = localStorage.getItem('sarh_dark_start') || '18:00';
        this.darkEnd = localStorage.getItem('sarh_dark_end') || '06:00';
        this.init();
    }

    /**
     * تهيئة مدير السمات
     */
    init() {
        // Apply initial theme
        this.applyTheme();

        // Listen for system preference changes
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (this.mode === 'auto') {
                    this.applyTheme();
                }
            });
        }

        // Check every minute for time-based auto mode
        setInterval(() => {
            if (this.mode === 'auto') {
                this.applyTheme();
            }
        }, 60000);

        // Add toggle button if exists
        this.setupToggleButton();
    }

    /**
     * تطبيق السمة
     */
    applyTheme() {
        const isDark = this.shouldBeDark();
        
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
        document.body.classList.toggle('dark-mode', isDark);
        
        // Update meta theme-color
        const metaTheme = document.querySelector('meta[name="theme-color"]');
        if (metaTheme) {
            metaTheme.content = isDark ? '#1a1a2e' : '#ff6f00';
        }

        // Dispatch event for other components
        window.dispatchEvent(new CustomEvent('themeChanged', { detail: { isDark } }));
    }

    /**
     * هل يجب أن يكون داكناً؟
     */
    shouldBeDark() {
        if (this.mode === 'dark') return true;
        if (this.mode === 'light') return false;

        // Auto mode
        // First check system preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return true;
        }

        // Then check time-based
        return this.isNightTime();
    }

    /**
     * هل الوقت ليلاً؟
     */
    isNightTime() {
        const now = new Date();
        const currentMinutes = now.getHours() * 60 + now.getMinutes();
        
        const [startHour, startMin] = this.darkStart.split(':').map(Number);
        const [endHour, endMin] = this.darkEnd.split(':').map(Number);
        
        const startMinutes = startHour * 60 + startMin;
        const endMinutes = endHour * 60 + endMin;

        // Handle overnight (e.g., 18:00 to 06:00)
        if (startMinutes > endMinutes) {
            return currentMinutes >= startMinutes || currentMinutes < endMinutes;
        }
        
        return currentMinutes >= startMinutes && currentMinutes < endMinutes;
    }

    /**
     * تغيير الوضع
     */
    setMode(mode) {
        this.mode = mode;
        localStorage.setItem('sarh_theme_mode', mode);
        this.applyTheme();
    }

    /**
     * تبديل الوضع
     */
    toggle() {
        const modes = ['light', 'dark', 'auto'];
        const currentIndex = modes.indexOf(this.mode);
        const nextMode = modes[(currentIndex + 1) % modes.length];
        this.setMode(nextMode);
        return nextMode;
    }

    /**
     * تعيين أوقات الوضع الليلي
     */
    setSchedule(start, end) {
        this.darkStart = start;
        this.darkEnd = end;
        localStorage.setItem('sarh_dark_start', start);
        localStorage.setItem('sarh_dark_end', end);
        
        if (this.mode === 'auto') {
            this.applyTheme();
        }
    }

    /**
     * إعداد زر التبديل
     */
    setupToggleButton() {
        const toggleBtn = document.getElementById('themeToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                const newMode = this.toggle();
                this.updateToggleButton(toggleBtn, newMode);
            });
            this.updateToggleButton(toggleBtn, this.mode);
        }
    }

    /**
     * تحديث شكل زر التبديل
     */
    updateToggleButton(btn, mode) {
        const icons = {
            light: 'bi-sun-fill',
            dark: 'bi-moon-fill',
            auto: 'bi-circle-half'
        };
        
        const labels = {
            light: 'فاتح',
            dark: 'داكن',
            auto: 'تلقائي'
        };

        btn.innerHTML = `<i class="bi ${icons[mode]}"></i>`;
        btn.title = labels[mode];
    }

    /**
     * الحصول على الوضع الحالي
     */
    getCurrentMode() {
        return this.mode;
    }

    /**
     * هل الوضع داكن حالياً؟
     */
    isDarkMode() {
        return this.shouldBeDark();
    }
}

// CSS Variables for Dark Mode
const darkModeStyles = document.createElement('style');
darkModeStyles.textContent = `
    /* ═══════════════════════════════════════════════════════════════════════════════ */
    /* أنماط الوضع الليلي */
    /* ═══════════════════════════════════════════════════════════════════════════════ */
    
    :root {
        --transition-theme: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
    }

    [data-theme="dark"] {
        --sarh-bg-primary: #121212;
        --sarh-bg-card: #1e1e1e;
        --sarh-dark: #ffffff;
        --sarh-light: #2d2d2d;
        --sarh-gray: #9e9e9e;
        --sarh-white: #121212;
        
        --bs-body-bg: #121212;
        --bs-body-color: #e0e0e0;
        --bs-card-bg: #1e1e1e;
        --bs-border-color: #333;
    }

    [data-theme="dark"] body {
        background-color: #121212;
        color: #e0e0e0;
    }

    [data-theme="dark"] .card,
    [data-theme="dark"] .modal-content,
    [data-theme="dark"] .dropdown-menu,
    [data-theme="dark"] .offcanvas {
        background-color: #1e1e1e;
        border-color: #333;
        color: #e0e0e0;
    }

    [data-theme="dark"] .card-header,
    [data-theme="dark"] .modal-header,
    [data-theme="dark"] .offcanvas-header {
        background-color: #252525;
        border-color: #333;
    }

    [data-theme="dark"] .table {
        --bs-table-bg: #1e1e1e;
        --bs-table-color: #e0e0e0;
        --bs-table-border-color: #333;
        --bs-table-striped-bg: #252525;
        --bs-table-hover-bg: #2d2d2d;
    }

    [data-theme="dark"] .form-control,
    [data-theme="dark"] .form-select {
        background-color: #2d2d2d;
        border-color: #444;
        color: #e0e0e0;
    }

    [data-theme="dark"] .form-control:focus,
    [data-theme="dark"] .form-select:focus {
        background-color: #333;
        border-color: var(--sarh-primary);
        color: #fff;
    }

    [data-theme="dark"] .form-control::placeholder {
        color: #888;
    }

    [data-theme="dark"] .btn-light {
        background-color: #333;
        border-color: #444;
        color: #e0e0e0;
    }

    [data-theme="dark"] .btn-outline-secondary {
        color: #aaa;
        border-color: #555;
    }

    [data-theme="dark"] .text-dark {
        color: #e0e0e0 !important;
    }

    [data-theme="dark"] .text-muted {
        color: #888 !important;
    }

    [data-theme="dark"] .bg-light {
        background-color: #252525 !important;
    }

    [data-theme="dark"] .bg-white {
        background-color: #1e1e1e !important;
    }

    [data-theme="dark"] .border {
        border-color: #333 !important;
    }

    [data-theme="dark"] .list-group-item {
        background-color: #1e1e1e;
        border-color: #333;
        color: #e0e0e0;
    }

    [data-theme="dark"] .nav-tabs .nav-link {
        color: #aaa;
    }

    [data-theme="dark"] .nav-tabs .nav-link.active {
        background-color: #1e1e1e;
        border-color: #333;
        color: #fff;
    }

    [data-theme="dark"] .top-navbar {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    }

    [data-theme="dark"] .bottom-nav {
        background: #1a1a2e;
        border-top-color: #333;
    }

    [data-theme="dark"] .alert-info {
        background-color: rgba(23, 162, 184, 0.2);
        border-color: rgba(23, 162, 184, 0.3);
        color: #5bc0de;
    }

    [data-theme="dark"] .alert-warning {
        background-color: rgba(255, 193, 7, 0.2);
        border-color: rgba(255, 193, 7, 0.3);
        color: #f0ad4e;
    }

    [data-theme="dark"] .alert-success {
        background-color: rgba(40, 167, 69, 0.2);
        border-color: rgba(40, 167, 69, 0.3);
        color: #5cb85c;
    }

    [data-theme="dark"] .alert-danger {
        background-color: rgba(220, 53, 69, 0.2);
        border-color: rgba(220, 53, 69, 0.3);
        color: #d9534f;
    }

    [data-theme="dark"] hr {
        border-color: #333;
    }

    [data-theme="dark"] .shadow,
    [data-theme="dark"] .shadow-sm,
    [data-theme="dark"] .shadow-lg {
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.5) !important;
    }

    [data-theme="dark"] .stat-card,
    [data-theme="dark"] .chart-card,
    [data-theme="dark"] .leaderboard-list,
    [data-theme="dark"] .reward-card,
    [data-theme="dark"] .challenge-card {
        background-color: #1e1e1e;
    }

    [data-theme="dark"] .leaderboard-item:hover,
    [data-theme="dark"] .top-employee:hover {
        background-color: #252525;
    }

    [data-theme="dark"] .copyright-notice {
        background: #0a0a0a;
    }

    /* Smooth transitions */
    body,
    .card,
    .btn,
    .form-control,
    .modal-content,
    .dropdown-menu,
    .nav-link,
    .list-group-item {
        transition: var(--transition-theme);
    }

    /* Theme toggle button */
    .theme-toggle {
        position: fixed;
        bottom: 100px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--sarh-primary);
        color: white;
        border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        cursor: pointer;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        transition: transform 0.3s ease;
    }

    .theme-toggle:hover {
        transform: scale(1.1);
    }

    @media (max-width: 768px) {
        .theme-toggle {
            bottom: 160px;
            right: 15px;
            width: 45px;
            height: 45px;
        }
    }
`;

document.head.appendChild(darkModeStyles);

// Export singleton
window.ThemeManager = new ThemeManager();
