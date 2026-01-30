/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - COGNITIVE TRAP ENGINE                                ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

(function() {
    'use strict';

    // ═══════════════════════════════════════════════════════════════════════════════
    // CONFIGURATION
    // ═══════════════════════════════════════════════════════════════════════════════
    
    const CONFIG = {
        apiUrl: 'api/trap_handler.php',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',
        checkIntervalMin: 120000,  // 2 minutes
        checkIntervalMax: 300000,  // 5 minutes
        sessionStart: Date.now(),
        gpsErrorCount: 0
    };

    let checkTimer = null;
    let activeTrap = null;

    // ═══════════════════════════════════════════════════════════════════════════════
    // API COMMUNICATION
    // ═══════════════════════════════════════════════════════════════════════════════
    
    async function apiCall(action, data = {}) {
        try {
            const response = await fetch(CONFIG.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CONFIG.csrfToken
                },
                body: JSON.stringify({ action, ...data })
            });
            return await response.json();
        } catch (e) {
            console.error('[TrapEngine] API Error:', e);
            return { success: false, error: e.message };
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // TRAP CHECKER
    // ═══════════════════════════════════════════════════════════════════════════════
    
    async function checkForTraps() {
        if (activeTrap) return;
        
        const result = await apiCall('check_for_traps', {
            page: window.location.pathname,
            gps_errors: CONFIG.gpsErrorCount,
            session_minutes: Math.floor((Date.now() - CONFIG.sessionStart) / 60000)
        });
        
        if (result.success && result.has_trap && result.trap) {
            showTrap(result.trap);
        }
        
        scheduleNextCheck();
    }
    
    function scheduleNextCheck() {
        const delay = CONFIG.checkIntervalMin + Math.random() * (CONFIG.checkIntervalMax - CONFIG.checkIntervalMin);
        checkTimer = setTimeout(checkForTraps, delay);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // TRAP ROUTER
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function showTrap(trap) {
        activeTrap = trap;
        
        switch (trap.display.type) {
            case 'modal':
                if (trap.display.theme === 'error') {
                    renderDataLeakModal(trap);
                } else if (trap.display.theme === 'official') {
                    renderRecruitmentModal(trap);
                }
                break;
            case 'panel':
                renderDebugPanel(trap);
                break;
            case 'floating_button':
                renderGhostButton(trap);
                break;
            case 'toast':
                renderConfidentialToast(trap);
                break;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // TRAP 1: DATA LEAK MODAL
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function renderDataLeakModal(trap) {
        const startTime = Date.now();
        const d = trap.display;
        
        const overlay = createElement('div', 'trap-overlay', `
            <div class="trap-modal trap-error-theme">
                <div class="trap-modal-header">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>${esc(d.title)}</span>
                </div>
                <div class="trap-modal-body">
                    <p class="trap-warning-text">${esc(d.message)}</p>
                    <div class="trap-leaked-data">
                        <div class="leaked-header">
                            <i class="bi bi-person-badge"></i>
                            بيانات الموظف: ${esc(d.data.name)}
                        </div>
                        <table class="leaked-table">
                            <tr><td>الكود</td><td>${esc(d.data.code)}</td></tr>
                            <tr class="sensitive"><td>الراتب الأساسي</td><td>${esc(d.data.salary)}</td></tr>
                            <tr class="sensitive"><td>المكافأة الأخيرة</td><td>${esc(d.data.bonus)}</td></tr>
                            <tr><td>تقييم الأداء</td><td>${esc(d.data.rating)}</td></tr>
                            <tr><td>آخر زيادة</td><td>${esc(d.data.raise_date)}</td></tr>
                        </table>
                    </div>
                </div>
                <div class="trap-modal-footer">
                    ${d.actions.map(a => `
                        <button class="trap-btn trap-btn-${a.style}" data-action="${a.id}">
                            ${esc(a.label)}
                        </button>
                    `).join('')}
                </div>
            </div>
        `);
        
        document.body.appendChild(overlay);
        
        overlay.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', () => handleTrapAction(trap, btn.dataset.action, Date.now() - startTime, overlay));
        });
        
        setTimeout(() => {
            if (document.body.contains(overlay)) {
                handleTrapAction(trap, 'timeout', Date.now() - startTime, overlay);
            }
        }, 15000);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // TRAP 2: GPS DEBUG PANEL
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function renderDebugPanel(trap) {
        const startTime = Date.now();
        const d = trap.display;
        
        const panel = createElement('div', 'trap-debug-panel', `
            <div class="debug-header">
                <i class="bi ${d.icon}"></i>
                <span>${esc(d.title)}</span>
                <button class="debug-close" data-action="close">×</button>
            </div>
            <p class="debug-message">${esc(d.message)}</p>
            <div class="debug-options">
                ${d.actions.map(a => `
                    <button class="debug-option" data-action="${a.id}">
                        <i class="bi ${a.icon}"></i>
                        <span>${esc(a.label)}</span>
                        ${a.badge ? `<small class="debug-badge">${esc(a.badge)}</small>` : ''}
                    </button>
                `).join('')}
            </div>
        `);
        
        const statusEl = document.querySelector('#status-display, .gps-status');
        if (statusEl) {
            const rect = statusEl.getBoundingClientRect();
            panel.style.top = (rect.bottom + 10) + 'px';
            panel.style.left = Math.max(10, rect.left) + 'px';
        } else {
            panel.style.top = '100px';
            panel.style.right = '20px';
        }
        
        document.body.appendChild(panel);
        
        panel.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', () => {
                handleTrapAction(trap, btn.dataset.action, Date.now() - startTime, panel);
            });
        });
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // TRAP 3: GHOST ADMIN BUTTON
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function renderGhostButton(trap) {
        const startTime = Date.now();
        const d = trap.display;
        
        const button = createElement('button', 'trap-ghost-button', `
            <i class="bi ${d.icon}"></i>
            <span>${esc(d.text)}</span>
            <small>${esc(d.subtext)}</small>
        `);
        
        button.style.opacity = '0';
        
        const actionBtn = document.querySelector('#actionBtn, .action-button, .btn-primary');
        if (actionBtn) {
            const rect = actionBtn.getBoundingClientRect();
            button.style.position = 'fixed';
            button.style.top = (rect.top - 60) + 'px';
            button.style.left = rect.left + 'px';
            button.style.width = rect.width + 'px';
        }
        
        document.body.appendChild(button);
        
        setTimeout(() => {
            button.style.opacity = '1';
            
            button.addEventListener('click', () => {
                button.innerHTML = '<i class="bi bi-hourglass-split"></i> جاري التحقق...';
                button.disabled = true;
                
                setTimeout(() => {
                    handleTrapAction(trap, 'clicked', Date.now() - startTime, button);
                }, 2500);
            });
        }, d.appear_delay_ms);
        
        setTimeout(() => {
            if (document.body.contains(button) && !button.disabled) {
                handleTrapAction(trap, 'ignored', Date.now() - startTime, button);
            }
        }, d.appear_delay_ms + d.disappear_delay_ms);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // TRAP 4: CONFIDENTIAL TOAST
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function renderConfidentialToast(trap) {
        const startTime = Date.now();
        const d = trap.display;
        
        const toast = createElement('div', 'trap-toast trap-confidential', `
            <div class="toast-icon">
                <i class="bi ${d.icon}"></i>
            </div>
            <div class="toast-content">
                <strong>${esc(d.title)}</strong>
                <p>${esc(d.message)}</p>
            </div>
            <div class="toast-actions">
                ${d.actions.map(a => `
                    <button class="toast-action" data-action="${a.id}">${esc(a.label)}</button>
                `).join('')}
            </div>
        `);
        
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 50);
        
        toast.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', () => {
                handleTrapAction(trap, btn.dataset.action, Date.now() - startTime, toast);
            });
        });
        
        setTimeout(() => {
            if (document.body.contains(toast)) {
                handleTrapAction(trap, 'timeout', Date.now() - startTime, toast);
            }
        }, d.auto_dismiss_ms);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // TRAP 5: RECRUITMENT MODAL
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function renderRecruitmentModal(trap) {
        const startTime = Date.now();
        const d = trap.display;
        
        const overlay = createElement('div', 'trap-overlay', `
            <div class="trap-modal trap-official-theme">
                <div class="trap-modal-header">
                    <i class="bi ${d.icon}"></i>
                    <span>${esc(d.title)}</span>
                    <span class="trap-badge">${esc(d.badge)}</span>
                </div>
                <div class="trap-modal-body">
                    <div class="message-meta">
                        <strong>من:</strong> ${esc(d.sender)}<br>
                        <strong>الموضوع:</strong> ${esc(d.subject)}
                    </div>
                    <div class="message-content">
                        <p class="message-body">${esc(d.body).replace(/\n/g, '<br>')}</p>
                        <p class="message-footer">${esc(d.footer)}</p>
                    </div>
                </div>
                <div class="trap-modal-footer trap-recruitment-actions">
                    ${d.actions.map(a => `
                        <button class="trap-btn trap-btn-${a.style}" data-action="${a.id}">
                            <i class="bi ${a.icon}"></i>
                            ${esc(a.label)}
                        </button>
                    `).join('')}
                </div>
            </div>
        `);
        
        document.body.appendChild(overlay);
        
        overlay.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', () => handleTrapAction(trap, btn.dataset.action, Date.now() - startTime, overlay));
        });
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // ACTION HANDLER
    // ═══════════════════════════════════════════════════════════════════════════════
    
    async function handleTrapAction(trap, action, responseTime, element) {
        const result = await apiCall('log_interaction', {
            trap_type: trap.trap_type,
            trap_id: trap.trap_id,
            user_action: action,
            response_time_ms: responseTime
        });
        
        closeElement(element);
        activeTrap = null;
        
        if (result.success && result.response && result.response.type !== 'none') {
            showResponse(result.response);
        }
    }
    
    function showResponse(response) {
        if (response.type === 'toast' && typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: response.style === 'success' ? 'success' : (response.style === 'error' ? 'error' : 'info'),
                title: response.message,
                showConfirmButton: false,
                timer: 3000
            });
        } else if (response.type === 'modal' && typeof Swal !== 'undefined') {
            Swal.fire({
                title: response.title || '',
                text: response.message,
                icon: response.style === 'success' ? 'success' : (response.style === 'error' ? 'error' : 'warning'),
                confirmButtonText: 'حسناً'
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // UTILITIES
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function createElement(tag, className, innerHTML) {
        const el = document.createElement(tag);
        el.className = className;
        el.innerHTML = innerHTML;
        return el;
    }
    
    function esc(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
    
    function closeElement(el) {
        if (!el) return;
        el.classList.add('closing');
        setTimeout(() => el.remove(), 300);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // INITIALIZATION
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function init() {
        setTimeout(checkForTraps, 30000);
        
        window.addEventListener('gps-error', () => CONFIG.gpsErrorCount++);
        
        window.SARH_TRAPS = {
            check: checkForTraps,
            config: CONFIG,
            forceShow: async (type) => {
                const result = await apiCall('force_trap', { trap_type: type });
                if (result.success && result.trap) showTrap(result.trap);
            }
        };
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
