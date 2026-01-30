/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - ATTENDANCE CORE ENGINE v4.0                          ║
 * ║           محرك نظام الحضور - مع الشروط الثلاثة للتسجيل التلقائي              ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  شروط الحضور التلقائي:                                                       ║
 * ║  1. الوقت: من ساعة قبل الدوام إلى ساعة بعده                                  ║
 * ║  2. الموقع: داخل نطاق أحد الفروع                                             ║
 * ║  3. عدم التسجيل: لم يسجل حضوره بعد                                           ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

(function() {
    'use strict';

    // ═══════════════════════════════════════════════════════════════════════════════
    // CONFIGURATION
    // ═══════════════════════════════════════════════════════════════════════════════
    
    const APP = document.getElementById('attendance-app');
    if (!APP) {
        console.error('[SARH] attendance-app not found!');
        return;
    }

    function safeParseFloat(value, defaultVal) {
        const parsed = parseFloat(value);
        return isNaN(parsed) ? defaultVal : parsed;
    }
    
    function safeParseJSON(jsonStr, defaultVal) {
        try {
            return JSON.parse(jsonStr || JSON.stringify(defaultVal));
        } catch (e) {
            return defaultVal;
        }
    }
    
    const CONFIG = {
        userId: parseInt(APP.dataset.userId, 10) || 0,
        userName: APP.dataset.userName || 'مستخدم',
        branchId: parseInt(APP.dataset.branchId, 10) || 0,
        branchName: APP.dataset.branchName || 'غير محدد',
        hasBranch: APP.dataset.hasBranch === 'true',
        roleLevel: parseInt(APP.dataset.roleLevel, 10) || 1,
        
        targetLat: safeParseFloat(APP.dataset.targetLat, 24.7136),
        targetLng: safeParseFloat(APP.dataset.targetLng, 46.6753),
        targetRadius: safeParseFloat(APP.dataset.targetRadius, 100),
        allBranches: safeParseJSON(APP.dataset.allBranches, []),
        
        // ═══════════════════════════════════════════════════════════════════════
        // إعدادات وقت الدوام - للشرط الأول
        // ═══════════════════════════════════════════════════════════════════════
        workStart: APP.dataset.workStart || '08:00',
        workEnd: APP.dataset.workEnd || '17:00',
        earlyCheckinMinutes: parseInt(APP.dataset.earlyCheckinMinutes, 10) || 60, // ساعة قبل
        lateCheckinMinutes: parseInt(APP.dataset.lateCheckinMinutes, 10) || 60,   // ساعة بعد
        workingDays: safeParseJSON(APP.dataset.workingDays, [0,1,2,3,4]),
        
        // ═══════════════════════════════════════════════════════════════════════
        // حالة الحضور - للشرط الثالث
        // ═══════════════════════════════════════════════════════════════════════
        actionType: APP.dataset.actionType || 'checkin', // checkin, checkout, done
        attendanceId: APP.dataset.attendanceId || null,
        checkInTime: APP.dataset.checkInTime || null,
        
        csrfToken: APP.dataset.csrfToken || '',
        actionUrl: APP.dataset.actionUrl || 'api/attendance/action.php',
        heartbeatUrl: APP.dataset.heartbeatUrl || 'api/heartbeat.php',
        
        heartbeatInterval: parseInt(APP.dataset.heartbeatInterval, 10) || 10000,
        liveMode: APP.dataset.liveMode === 'true',
        showNames: APP.dataset.showNames === 'true'
    };

    // ═══════════════════════════════════════════════════════════════════════════════
    // STATE
    // ═══════════════════════════════════════════════════════════════════════════════
    
    const STATE = {
        map: null,
        userMarker: null,
        userLat: null,
        userLng: null,
        userAccuracy: null,
        
        watchId: null,
        gpsReady: false,
        
        distance: null,
        isInRange: false,
        nearestBranch: null,
        nearestDistance: null,
        
        // الحضور التلقائي
        autoCheckinTriggered: false,
        autoCheckinProcessing: false,
        autoCheckinDebounce: null, // Debounce timer
        
        // AWOL
        awolAlertActive: false,
        previouslyInGeofence: new Map(),
        awolCooldown: new Map(),
        
        // زملاء
        colleagueMarkers: new Map(),
        pendingColleagueUpdates: new Map(),
        
        // عام
        heartbeatTimerId: null,
        clockTimer: null,
        allBranchCircles: [],
        branchRadarOverlays: [],
        
        // رادار
        radarAngle: 0,
        radarAnimationId: null
    };

    // ═══════════════════════════════════════════════════════════════════════════════
    // DOM ELEMENTS
    // ═══════════════════════════════════════════════════════════════════════════════
    
    const DOM = {
        map: document.getElementById('map'),
        uiLayer: document.getElementById('ui-layer'),
        btmPanel: document.getElementById('btmPanel'),
        currentTime: document.getElementById('current-time'),
        connectionStatus: document.getElementById('connection-status'),
        statusDisplay: document.getElementById('status-display'),
        distanceInfo: document.getElementById('distance-info'),
        distNumber: document.getElementById('dist-number'),
        distUnit: document.getElementById('dist-unit'),
        actionBtn: document.getElementById('actionBtn'),
        locBtn: document.getElementById('locBtn'),
        colleaguesToggle: document.getElementById('colleagues-toggle'),
        colleaguesCount: document.getElementById('colleagues-count'),
        colleaguesList: document.getElementById('colleagues-list'),
        colleaguesItems: document.getElementById('colleagues-items'),
        closeColleagues: document.getElementById('close-colleagues')
    };

    // ═══════════════════════════════════════════════════════════════════════════════
    // UTILITY FUNCTIONS
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function haversineDistance(lat1, lon1, lat2, lon2) {
        const R = 6371000;
        const φ1 = lat1 * Math.PI / 180;
        const φ2 = lat2 * Math.PI / 180;
        const Δφ = (lat2 - lat1) * Math.PI / 180;
        const Δλ = (lon2 - lon1) * Math.PI / 180;

        const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
                  Math.cos(φ1) * Math.cos(φ2) *
                  Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
        
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function formatDistance(meters) {
        if (meters < 1000) {
            return { value: Math.round(meters), unit: 'م' };
        }
        return { value: (meters / 1000).toFixed(1), unit: 'كم' };
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getInitials(name) {
        if (!name) return '؟';
        const parts = name.trim().split(/\s+/);
        return parts.length >= 2 
            ? (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase()
            : name.charAt(0).toUpperCase();
    }

    function updateClock() {
        if (DOM.currentTime) {
            DOM.currentTime.textContent = new Date().toLocaleTimeString('ar-SA', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // شرط 1: التحقق من وقت الدوام
    // من ساعة قبل بداية الدوام إلى ساعة بعد بداية الدوام
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function isWithinAutoCheckinTimeWindow() {
        const now = new Date();
        const currentDay = now.getDay();
        
        // التحقق من يوم العمل
        if (!CONFIG.workingDays.includes(currentDay)) {
            console.log('[SARH] ❌ شرط 1: ليس يوم عمل');
            return false;
        }
        
        // تحويل الوقت الحالي لدقائق
        const currentMinutes = now.getHours() * 60 + now.getMinutes();
        
        // تحويل وقت بداية الدوام لدقائق
        const [startHour, startMin] = CONFIG.workStart.split(':').map(Number);
        const workStartMinutes = startHour * 60 + startMin;
        
        // نافذة التسجيل التلقائي:
        // من ساعة قبل الدوام إلى ساعة بعد بداية الدوام
        const windowStart = workStartMinutes - CONFIG.earlyCheckinMinutes; // ساعة قبل
        const windowEnd = workStartMinutes + CONFIG.lateCheckinMinutes;    // ساعة بعد
        
        const isInWindow = currentMinutes >= windowStart && currentMinutes <= windowEnd;
        
        console.log(`[SARH] شرط 1 - الوقت: ${now.toLocaleTimeString('ar-SA')}`);
        console.log(`[SARH] نافذة التسجيل: ${Math.floor(windowStart/60)}:${(windowStart%60).toString().padStart(2,'0')} - ${Math.floor(windowEnd/60)}:${(windowEnd%60).toString().padStart(2,'0')}`);
        console.log(`[SARH] ${isInWindow ? '✅' : '❌'} شرط 1: ${isInWindow ? 'ضمن النافذة' : 'خارج النافذة'}`);
        
        return isInWindow;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // شرط 2: التحقق من الموقع - داخل نطاق أحد الفروع
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function checkLocationCondition() {
        if (!STATE.userLat || !STATE.userLng) {
            console.log('[SARH] ❌ شرط 2: لا يوجد موقع GPS');
            return { passed: false, branch: null, distance: null };
        }
        
        const tolerance = Math.min(STATE.userAccuracy || 0, 20);
        let nearestBranch = null;
        let nearestDistance = Infinity;
        
        // فحص جميع الفروع
        STATE.allBranchCircles.forEach(({ lat, lng, radius, branch }) => {
            const distance = haversineDistance(STATE.userLat, STATE.userLng, lat, lng);
            
            if (distance <= radius + tolerance && distance < nearestDistance) {
                nearestDistance = distance;
                nearestBranch = { lat, lng, radius, branch, distance };
            }
        });
        
        if (nearestBranch) {
            console.log(`[SARH] ✅ شرط 2: داخل نطاق "${nearestBranch.branch.name}" (${Math.round(nearestDistance)}م)`);
            return { passed: true, branch: nearestBranch, distance: nearestDistance };
        } else {
            console.log('[SARH] ❌ شرط 2: خارج نطاق جميع الفروع');
            return { passed: false, branch: null, distance: null };
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // شرط 3: لم يسجل حضوره بعد
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function hasNotCheckedInYet() {
        // فحص متعدد: CONFIG.actionType + STATE flags
        const notCheckedIn = CONFIG.actionType === 'checkin' && 
                             !STATE.autoCheckinTriggered && 
                             !STATE.autoCheckinProcessing;
        console.log(`[SARH] ${notCheckedIn ? '✅' : '❌'} شرط 3: ${notCheckedIn ? 'لم يسجل حضوره' : 'سجل حضوره بالفعل أو قيد المعالجة'}`);
        return notCheckedIn;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // التحقق من جميع الشروط الثلاثة للحضور التلقائي
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function checkAllAutoCheckinConditions() {
        console.log('[SARH] ══════════════════════════════════════════════');
        console.log('[SARH] فحص شروط الحضور التلقائي الثلاثة:');
        
        // شرط 1: الوقت
        const timeCondition = isWithinAutoCheckinTimeWindow();
        
        // شرط 2: الموقع
        const locationResult = checkLocationCondition();
        
        // شرط 3: لم يسجل بعد
        const notCheckedIn = hasNotCheckedInYet();
        
        const allPassed = timeCondition && locationResult.passed && notCheckedIn;
        
        console.log('[SARH] ══════════════════════════════════════════════');
        console.log(`[SARH] النتيجة: ${allPassed ? '✅ جميع الشروط متحققة - سيتم التسجيل التلقائي' : '❌ لم تتحقق جميع الشروط'}`);
        
        return {
            allPassed,
            timeCondition,
            locationCondition: locationResult.passed,
            notCheckedIn,
            nearestBranch: locationResult.branch,
            nearestDistance: locationResult.distance
        };
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // GPS TRACKING
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function startGPSTracking() {
        console.log('[SARH] Starting GPS tracking...');
        
        if (!navigator.geolocation) {
            updateStatus('danger', 'bi-exclamation-triangle', 'GPS غير متاح');
            return;
        }
        
        updateStatus('wait', 'bi-hourglass-split', 'جاري تحديد الموقع...');
        
        navigator.geolocation.getCurrentPosition(handleGPSSuccess, handleGPSError, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        });
        
        STATE.watchId = navigator.geolocation.watchPosition(handleGPSSuccess, handleGPSError, {
            enableHighAccuracy: true,
            maximumAge: 0,
            timeout: 30000
        });
    }

    function handleGPSSuccess(position) {
        STATE.gpsReady = true;
        STATE.userLat = position.coords.latitude;
        STATE.userLng = position.coords.longitude;
        STATE.userAccuracy = position.coords.accuracy;
        
        updateUserMarker();
        
        // ═══════════════════════════════════════════════════════════════════════
        // فحص الشروط الثلاثة للحضور التلقائي - فقط إذا لم يُسجل الحضور بعد
        // ═══════════════════════════════════════════════════════════════════════
        
        // ⚠️ حماية قوية: لا نفحص الشروط إذا كان الحضور قد سُجل بالفعل
        if (CONFIG.actionType !== 'checkin') {
            // تحديث الموقع فقط بدون فحص الشروط
            updateStatusDisplay({ nearestBranch: null, locationCondition: false });
            updateDistanceDisplay();
            detectAWOL(false);
            return;
        }
        
        // ⚠️ حماية قوية: منع الفحص المتكرر إذا كان هناك عملية جارية أو تم التسجيل
        if (STATE.autoCheckinProcessing || STATE.autoCheckinTriggered) {
            console.log('[SARH] ⚠️ Skipping GPS check - check-in already processed');
            return;
        }
        
        // ⚠️ حماية إضافية: فحص DOM للتأكد من أن الزر لا يزال مخفياً (يعني لم يُسجل بعد)
        const actionBtn = document.getElementById('actionBtn');
        if (actionBtn && actionBtn.style.display !== 'none' && actionBtn.classList.contains('checkout')) {
            console.log('[SARH] ⚠️ Check-out button visible, skipping auto check-in');
            CONFIG.actionType = 'checkout';
            return;
        }
        
        const conditions = checkAllAutoCheckinConditions();
        
        STATE.nearestBranch = conditions.nearestBranch;
        STATE.nearestDistance = conditions.nearestDistance;
        
        // تحديث واجهة المستخدم
        updateStatusDisplay(conditions);
            updateDistanceDisplay();
        
        // تنفيذ الحضور التلقائي إذا تحققت جميع الشروط
        // ⚠️ فحص إضافي قبل الاستدعاء
        if (conditions.allPassed && 
            !STATE.autoCheckinTriggered && 
            !STATE.autoCheckinProcessing &&
            CONFIG.actionType === 'checkin') {
            
            // تأخير بسيط (debounce) لمنع الاستدعاءات المتكررة السريعة
            if (!STATE.autoCheckinDebounce) {
                STATE.autoCheckinDebounce = setTimeout(() => {
                    STATE.autoCheckinDebounce = null;
                    if (!STATE.autoCheckinTriggered && !STATE.autoCheckinProcessing && CONFIG.actionType === 'checkin') {
            triggerAutoCheckin(conditions.nearestBranch);
        }
                }, 500); // تأخير 500ms
            }
        } else {
            // إلغاء أي debounce معلق إذا لم تعد الشروط متحققة
            if (STATE.autoCheckinDebounce) {
                clearTimeout(STATE.autoCheckinDebounce);
                STATE.autoCheckinDebounce = null;
            }
        }
        
        // AWOL Detection - فقط بعد تسجيل الحضور
        if (CONFIG.actionType === 'checkout') {
        detectAWOL(conditions.locationCondition);
        }
    }

    function handleGPSError(error) {
        let message = 'خطأ في تحديد الموقع';
        let icon = 'bi-exclamation-triangle';
        
        switch (error.code) {
            case 1: message = 'يرجى السماح بالوصول للموقع'; break;
            case 2: message = 'الموقع غير متاح'; break;
            case 3: 
                message = 'انتهت المهلة'; 
                setTimeout(startGPSTracking, 3000);
                break;
        }
        
        updateStatus('danger', icon, message);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // تسجيل الحضور التلقائي
    // ═══════════════════════════════════════════════════════════════════════════════
    
    async function triggerAutoCheckin(branchInfo) {
        // ⚠️ حماية مضاعفة: منع الاستدعاءات المتكررة
        if (STATE.autoCheckinProcessing || STATE.autoCheckinTriggered) {
            console.log('[SARH] ⚠️ Auto check-in already in progress, skipping...');
            return;
        }
        
        // ⚠️ حماية: التأكد من أن الحضور لم يُسجل بعد
        if (CONFIG.actionType !== 'checkin') {
            console.log('[SARH] ⚠️ Attendance already registered, skipping auto check-in');
            return;
        }
        
        // ⚠️ حماية إضافية: فحص DOM
        const actionBtn = document.getElementById('actionBtn');
        if (actionBtn && actionBtn.style.display !== 'none' && actionBtn.classList.contains('checkout')) {
            console.log('[SARH] ⚠️ Check-out button already visible, skipping');
            return;
        }
        
        // ⚠️ إيقاف GPS tracking فوراً قبل بدء العملية لمنع الاستدعاءات المتكررة
        if (STATE.watchId !== null) {
            navigator.geolocation.clearWatch(STATE.watchId);
            STATE.watchId = null;
            console.log('[SARH] GPS tracking stopped before check-in to prevent duplicates');
        }
        
        STATE.autoCheckinProcessing = true;
        STATE.autoCheckinTriggered = true;
        
        console.log('[SARH] 🚀 تسجيل الحضور التلقائي في:', branchInfo.branch.name);
        
        // إشعار مرئي - مرة واحدة فقط (بدون toast لتجنب التكرار)
        if (typeof Swal !== 'undefined') {
            // إغلاق أي إشعارات سابقة أولاً
            Swal.close();
            
            // إشعار واحد فقط
            Swal.fire({
                toast: true,
                position: 'top',
                icon: 'info',
                title: '📍 جاري تسجيل حضورك تلقائياً...',
                text: branchInfo.branch.name,
                showConfirmButton: false,
                timer: 2000,
                allowOutsideClick: false,
                allowEscapeKey: false
            });
        }
        
        try {
            const response = await fetch(CONFIG.actionUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CONFIG.csrfToken
                },
                body: JSON.stringify({
                    action: 'checkin',
                    auto_checkin: true,
                    detected_branch_id: branchInfo.branch.id,
                    latitude: STATE.userLat,
                    longitude: STATE.userLng,
                    accuracy: STATE.userAccuracy
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                playSuccessSound();
                
                // ⚠️ GPS tracking تم إيقافه مسبقاً، لكن نتأكد مرة أخرى
                if (STATE.watchId !== null) {
                    navigator.geolocation.clearWatch(STATE.watchId);
                    STATE.watchId = null;
                }
                
                // تحديث الحالة فوراً قبل أي شيء آخر لمنع المحاولات المتكررة
                CONFIG.actionType = 'checkout';
                CONFIG.attendanceId = data.attendance_id || CONFIG.attendanceId;
                
                // تعطيل جميع الفحوصات المستقبلية فوراً
                STATE.autoCheckinTriggered = true;
                STATE.autoCheckinProcessing = false;
                
                // إغلاق أي إشعارات سابقة
                if (typeof Swal !== 'undefined') {
                    Swal.close();
                    
                    // إشعار النجاح - مرة واحدة فقط
                    Swal.fire({
                        icon: 'success',
                        title: '✅ تم تسجيل حضورك تلقائياً!',
                        html: `
                                <p><strong>${branchInfo.branch.name}</strong></p>
                                <p>الوقت: <strong>${new Date().toLocaleTimeString('ar-SA')}</strong></p>
                        `,
                        confirmButtonText: 'حسناً',
                        confirmButtonColor: '#00b894',
                        timer: 2000,
                        allowOutsideClick: true
                    }).then(() => {
                        // إعادة تحميل الصفحة فوراً
                        window.location.reload();
                    });
                } else {
                    // إذا لم يكن Swal متاحاً، إعادة تحميل مباشرة
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
                
                showCheckoutButton();
                
                console.log('[SARH] ✅ Auto check-in successful, page will reload');
                
            } else {
                // في حالة الفشل، نعيد تفعيل GPS tracking
                if (STATE.watchId === null && navigator.geolocation) {
                    STATE.watchId = navigator.geolocation.watchPosition(
                        handleGPSSuccess, 
                        handleGPSError, 
                        {
                            enableHighAccuracy: true,
                            maximumAge: 0,
                            timeout: 30000
                        }
                    );
                }
                throw new Error(data.message || 'فشل التسجيل');
            }
            
        } catch (error) {
            console.error('[SARH] ❌ Auto check-in failed:', error);
            
            // إعادة تعيين الحالة للسماح بالمحاولة مرة أخرى
            STATE.autoCheckinTriggered = false;
            STATE.autoCheckinProcessing = false;
            
            // إغلاق الإشعارات في حالة الخطأ
            if (typeof Swal !== 'undefined') {
                Swal.close();
                
                // إشعار الخطأ
                Swal.fire({
                    icon: 'error',
                    title: '❌ فشل تسجيل الحضور',
                    text: error.message || 'حدث خطأ أثناء تسجيل الحضور',
                    confirmButtonText: 'حسناً',
                    confirmButtonColor: '#ff4757'
                });
            }
        }
    }
    
    function showCheckoutButton() {
        if (DOM.btmPanel) {
            DOM.btmPanel.style.display = '';
        }
        if (DOM.actionBtn) {
            DOM.actionBtn.style.display = 'flex';
            DOM.actionBtn.className = 'action-btn checkout ready';
            DOM.actionBtn.innerHTML = '<i class="bi bi-box-arrow-right"></i><span>تسجيل الانصراف</span>';
            DOM.actionBtn.disabled = false;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // AWOL DETECTION - تنبيه الخروج من النطاق
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function detectAWOL(currentlyInGeofence) {
        // فقط بعد تسجيل الحضور
        if (CONFIG.actionType !== 'checkout') return;
        
        const wasIn = STATE.previouslyInGeofence.get(CONFIG.userId);
        
        if (wasIn === undefined) {
            STATE.previouslyInGeofence.set(CONFIG.userId, currentlyInGeofence);
            return;
        }
        
        // كان داخل والآن خارج
        if (wasIn && !currentlyInGeofence) {
            const lastAlert = STATE.awolCooldown.get(CONFIG.userId);
            if (!lastAlert || (Date.now() - lastAlert) > 300000) {
                triggerAWOLAlert();
                STATE.awolCooldown.set(CONFIG.userId, Date.now());
            }
        }
        
        STATE.previouslyInGeofence.set(CONFIG.userId, currentlyInGeofence);
    }

    function triggerAWOLAlert() {
        if (STATE.awolAlertActive) return;
        STATE.awolAlertActive = true;
        
        console.log('[SARH] 🚨 AWOL ALERT!');
        
        // صوت إنذار
        playAlarmSound();
        
        // طبقة حمراء
        let overlay = document.getElementById('awol-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'awol-overlay';
            overlay.innerHTML = `
                <div class="awol-message">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>⚠️ تحذير: خروج من نطاق العمل!</span>
                </div>
            `;
            document.body.appendChild(overlay);
        }
        overlay.classList.add('active');
        
        // رسالة تحذير
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: '🚨 تنبيه!',
                text: 'تم الكشف عن خروجك من منطقة العمل',
                confirmButtonText: 'فهمت',
                confirmButtonColor: '#ff4757',
                timer: 10000
            });
        }
        
        // إرسال للخادم
        reportAWOL();
        
        // إنهاء بعد 5 ثواني
        setTimeout(() => {
            STATE.awolAlertActive = false;
            overlay?.classList.remove('active');
        }, 5000);
    }

    async function reportAWOL() {
        try {
            await fetch(CONFIG.heartbeatUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CONFIG.csrfToken
                },
                body: JSON.stringify({
                    awol_alert: true,
                    latitude: STATE.userLat,
                    longitude: STATE.userLng
                })
            });
        } catch (e) {}
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // UI UPDATES
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function updateStatus(type, icon, text) {
        if (!DOM.statusDisplay) return;
        DOM.statusDisplay.className = `status-badge ${type}`;
        DOM.statusDisplay.innerHTML = `<i class="bi ${icon}"></i><span>${escapeHtml(text)}</span>`;
    }

    function updateStatusDisplay(conditions) {
        if (conditions.nearestBranch) {
            const dist = Math.round(conditions.nearestDistance);
            updateStatus('success', 'bi-check-circle', `داخل: ${conditions.nearestBranch.branch.name} (${dist}م)`);
        } else if (STATE.userLat) {
            // أقرب فرع
            let minDist = Infinity, nearest = null;
            STATE.allBranchCircles.forEach(({ lat, lng, branch }) => {
                const d = haversineDistance(STATE.userLat, STATE.userLng, lat, lng);
                if (d < minDist) { minDist = d; nearest = branch; }
            });
            if (nearest) {
                const f = formatDistance(minDist);
                updateStatus('warning', 'bi-geo-alt', `أقرب فرع: ${nearest.name} (${f.value} ${f.unit})`);
            }
        }
    }

    function updateDistanceDisplay() {
        if (!DOM.distanceInfo || !STATE.nearestDistance) {
            if (DOM.distanceInfo) DOM.distanceInfo.classList.add('hidden');
            return;
        }
        
        DOM.distanceInfo.classList.remove('hidden');
        const f = formatDistance(STATE.nearestDistance);
        if (DOM.distNumber) DOM.distNumber.textContent = f.value;
        if (DOM.distUnit) DOM.distUnit.textContent = f.unit;
        
        DOM.distanceInfo.classList.toggle('in-range', !!STATE.nearestBranch);
        DOM.distanceInfo.classList.toggle('out-of-range', !STATE.nearestBranch);
    }

    function updateUserMarker() {
        if (!STATE.map || !STATE.userLat || !STATE.userLng) return;
        
        const pos = [STATE.userLat, STATE.userLng];
        
        if (STATE.userMarker) {
            STATE.userMarker.setLatLng(pos);
        } else {
            const icon = L.divIcon({
                className: 'user-marker-container',
                html: `<div class="user-marker-pulse"></div><div class="user-marker"><i class="bi bi-person-fill"></i></div>`,
                iconSize: [50, 50],
                iconAnchor: [25, 25]
            });
            
            STATE.userMarker = L.marker(pos, { icon, zIndexOffset: 1000 }).addTo(STATE.map);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // MAP INITIALIZATION
    // ═══════════════════════════════════════════════════════════════════════════════
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // RADAR COLORS - ألوان مختلفة لكل رادار
    // ═══════════════════════════════════════════════════════════════════════════════
    const RADAR_COLORS = [
        { primary: '#00ff88', glow: 'rgba(0, 255, 136, 0.5)', name: 'أخضر' },
        { primary: '#00d4ff', glow: 'rgba(0, 212, 255, 0.5)', name: 'أزرق' },
        { primary: '#ff6b35', glow: 'rgba(255, 107, 53, 0.5)', name: 'برتقالي' },
        { primary: '#a855f7', glow: 'rgba(168, 85, 247, 0.5)', name: 'بنفسجي' },
        { primary: '#f43f5e', glow: 'rgba(244, 63, 94, 0.5)', name: 'وردي' },
        { primary: '#eab308', glow: 'rgba(234, 179, 8, 0.5)', name: 'ذهبي' },
    ];

    /**
     * إنشاء رادار SVG لفرع
     */
    function createBranchRadar(lat, lng, radiusMeters, name, index) {
        const color = RADAR_COLORS[index % RADAR_COLORS.length];
        const speed = 3 + Math.random() * 3; // سرعة عشوائية 3-6 ثواني
        
        const radarIcon = L.divIcon({
            className: 'branch-radar-overlay',
            html: `
                <div class="radar-sweep-container" data-branch="${index}">
                    <svg class="radar-svg" viewBox="0 0 200 200">
                        <!-- الحلقة الخارجية -->
                        <circle cx="100" cy="100" r="95" fill="none" 
                                stroke="${color.primary}" stroke-width="2" opacity="0.8"/>
                        <!-- الحلقة الوسطى -->
                        <circle cx="100" cy="100" r="63" fill="none" 
                                stroke="${color.primary}" stroke-width="1" opacity="0.4" stroke-dasharray="5,5"/>
                        <!-- الحلقة الداخلية -->
                        <circle cx="100" cy="100" r="31" fill="none" 
                                stroke="${color.primary}" stroke-width="1" opacity="0.3"/>
                        <!-- الخطوط المتقاطعة -->
                        <line x1="100" y1="5" x2="100" y2="195" stroke="${color.primary}" stroke-width="1" opacity="0.3"/>
                        <line x1="5" y1="100" x2="195" y2="100" stroke="${color.primary}" stroke-width="1" opacity="0.3"/>
                        <!-- تأثير المسح -->
                        <defs>
                            <linearGradient id="sweepGrad${index}" gradientTransform="rotate(90)">
                                <stop offset="0%" stop-color="${color.primary}" stop-opacity="0"/>
                                <stop offset="50%" stop-color="${color.primary}" stop-opacity="0.4"/>
                                <stop offset="100%" stop-color="${color.primary}" stop-opacity="0.8"/>
                            </linearGradient>
                        </defs>
                        <path class="radar-sweep" 
                              d="M100,100 L100,5 A95,95 0 0,1 195,100 Z" 
                              fill="url(#sweepGrad${index})"
                              style="transform-origin: center; animation: radarSweep${index} ${speed}s linear infinite;"/>
                        <!-- خط المسح اللامع -->
                        <line class="radar-line" x1="100" y1="100" x2="100" y2="5" 
                              stroke="#ffffff" stroke-width="2" opacity="0.9"
                              style="transform-origin: center; animation: radarSweep${index} ${speed}s linear infinite;"/>
                        <!-- المركز النابض -->
                        <circle cx="100" cy="100" r="8" fill="${color.primary}" opacity="0.8">
                            <animate attributeName="r" values="6;10;6" dur="2s" repeatCount="indefinite"/>
                        </circle>
                        <circle cx="100" cy="100" r="4" fill="#ffffff"/>
                    </svg>
                    <div class="radar-label" style="background: ${color.primary}; color: #000;">${escapeHtml(name)}</div>
                </div>
                <style>
                    @keyframes radarSweep${index} {
                        from { transform: rotate(0deg); }
                        to { transform: rotate(360deg); }
                    }
                </style>
            `,
            iconSize: [200, 200],
            iconAnchor: [100, 100]
        });
        
        const marker = L.marker([lat, lng], {
            icon: radarIcon,
            interactive: false,
            zIndexOffset: -100
        }).addTo(STATE.map);
        
        marker._radarData = { lat, lng, radiusMeters, name, index, color };
        
        return marker;
    }

    /**
     * تحديث حجم الرادار حسب الزوم
     */
    function updateRadarSize(marker) {
        if (!marker._radarData || !STATE.map) return;
        
        const { lat, radiusMeters } = marker._radarData;
        const zoom = STATE.map.getZoom();
        const metersPerPixel = 40075016.686 * Math.abs(Math.cos(lat * Math.PI / 180)) / Math.pow(2, zoom + 8);
        const pixelRadius = radiusMeters / metersPerPixel;
        const size = Math.max(pixelRadius * 2.5, 120);
        
        const el = marker.getElement();
        if (el) {
            el.style.width = size + 'px';
            el.style.height = size + 'px';
            el.style.marginLeft = -(size / 2) + 'px';
            el.style.marginTop = -(size / 2) + 'px';
        }
    }

    /**
     * تحديث جميع أحجام الرادارات
     */
    function updateAllRadarSizes() {
        STATE.branchRadarOverlays.forEach(updateRadarSize);
    }

    function initMap() {
        if (!DOM.map) return;
        
        const startLat = CONFIG.targetLat || 24.7136;
        const startLng = CONFIG.targetLng || 46.6753;
        
        STATE.map = L.map('map', {
            zoomControl: false,
            attributionControl: false
        }).setView([startLat, startLng], 15);
        
        // Satellite tiles
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 20
        }).addTo(STATE.map);
        
        // Labels overlay
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}{r}.png', {
            maxZoom: 20,
            opacity: 0.8,
            subdomains: 'abcd'
        }).addTo(STATE.map);
        
        // ═══════════════════════════════════════════════════════════════════════
        // إضافة الفروع مع الرادارات
        // ═══════════════════════════════════════════════════════════════════════
        STATE.allBranchCircles = [];
        STATE.branchRadarOverlays = [];
        
        if (CONFIG.allBranches && CONFIG.allBranches.length > 0) {
            CONFIG.allBranches.forEach((branch, index) => {
                const lat = parseFloat(branch.latitude);
                const lng = parseFloat(branch.longitude);
                if (isNaN(lat) || isNaN(lng) || lat === 0) return;
                
                const radius = parseFloat(branch.geofence_radius) || 100;
                
                // إنشاء الرادار SVG
                const radar = createBranchRadar(lat, lng, radius, branch.name, index);
                STATE.branchRadarOverlays.push(radar);
                
                STATE.allBranchCircles.push({ lat, lng, radius, branch });
                
                console.log('[SARH] Radar created:', branch.name);
            });
        }
        
        // تحديث أحجام الرادارات عند تغيير الزوم
        STATE.map.on('zoomend', updateAllRadarSizes);
        setTimeout(updateAllRadarSizes, 300);
        
        // Fit to show all branches
        if (STATE.allBranchCircles.length > 0) {
            const bounds = L.latLngBounds();
            STATE.allBranchCircles.forEach(({ lat, lng }) => bounds.extend([lat, lng]));
            STATE.map.fitBounds(bounds, { padding: [50, 50], maxZoom: 16 });
        }
        
        // Start tracking
        startGPSTracking();
        
        // Start heartbeat
        if (CONFIG.liveMode) {
            startHeartbeat();
        }
        
        // Clock
        updateClock();
        STATE.clockTimer = setInterval(updateClock, 1000);
        
        // UI active
        DOM.uiLayer?.classList.add('active');
        DOM.locBtn?.classList.add('show');
        
        console.log('[SARH] Map initialized with', STATE.branchRadarOverlays.length, 'radars');
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // HEARTBEAT
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function startHeartbeat() {
        fetchHeartbeat();
        STATE.heartbeatTimerId = setInterval(fetchHeartbeat, CONFIG.heartbeatInterval);
    }

    async function fetchHeartbeat() {
        if (!STATE.userLat || !STATE.userLng) return;
        
        try {
            const response = await fetch(CONFIG.heartbeatUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CONFIG.csrfToken
                },
                body: JSON.stringify({
                    latitude: STATE.userLat,
                    longitude: STATE.userLng,
                    accuracy: STATE.userAccuracy
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                updateConnectionStatus('connected');
                if (data.colleagues) updateColleagues(data.colleagues);
            }
        } catch (e) {
            updateConnectionStatus('disconnected');
        }
    }

    function updateConnectionStatus(status) {
        if (!DOM.connectionStatus) return;
        DOM.connectionStatus.className = status;
        const text = DOM.connectionStatus.querySelector('.status-text');
        if (text) {
            text.textContent = status === 'connected' ? 'متصل' : 'غير متصل';
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // COLLEAGUES
    // ═══════════════════════════════════════════════════════════════════════════════
    
    function updateColleagues(colleagues) {
        const filtered = colleagues.filter(c => c.user_id !== CONFIG.userId);
        
        if (DOM.colleaguesCount) {
            DOM.colleaguesCount.textContent = filtered.length;
        }
        
        // Update markers
        const currentIds = new Set();
        
        filtered.forEach(c => {
            currentIds.add(c.user_id);
            
            if (!c.latitude || !c.longitude) return;
            
            const existing = STATE.colleagueMarkers.get(c.user_id);
            
            if (existing) {
                existing.setLatLng([c.latitude, c.longitude]);
            } else {
                const icon = L.divIcon({
                    className: `colleague-marker ${c.is_within_geofence ? '' : 'out-of-geofence'}`,
                    html: `<span>${getInitials(c.full_name)}</span>`,
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                });
                
                const marker = L.marker([c.latitude, c.longitude], { icon, zIndexOffset: 500 }).addTo(STATE.map);
                
                if (CONFIG.showNames) {
                    marker.bindTooltip(c.full_name, { direction: 'top', offset: [0, -15] });
                }
                
                STATE.colleagueMarkers.set(c.user_id, marker);
            }
        });
        
        // Remove old
        STATE.colleagueMarkers.forEach((marker, id) => {
            if (!currentIds.has(id)) {
                STATE.map.removeLayer(marker);
                STATE.colleagueMarkers.delete(id);
            }
        });
        
        // Update list
        if (DOM.colleaguesItems) {
            if (filtered.length === 0) {
                DOM.colleaguesItems.innerHTML = '<p class="no-colleagues"><i class="bi bi-person-x"></i> لا يوجد زملاء نشطون</p>';
            } else {
                DOM.colleaguesItems.innerHTML = filtered.map(c => `
                    <div class="colleague-item">
                        <div class="colleague-avatar">${getInitials(c.full_name)}</div>
                            <div class="colleague-info">
                                <div class="colleague-name">${escapeHtml(c.full_name)}</div>
                            <div class="colleague-meta">${c.branch_name || ''}</div>
                                </div>
                            </div>
                `).join('');
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // AUDIO
    // ═══════════════════════════════════════════════════════════════════════════════
    
    let audioCtx = null;
    
    function getAudioContext() {
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        return audioCtx;
    }
    
    function playBeep(freq, dur, vol) {
        try {
            const ctx = getAudioContext();
            if (ctx.state === 'suspended') ctx.resume();
            
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            
            osc.connect(gain);
            gain.connect(ctx.destination);
            
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, ctx.currentTime);
            gain.gain.setValueAtTime(vol, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + dur);
            
            osc.start();
            osc.stop(ctx.currentTime + dur);
        } catch (e) {}
    }
    
    function playSuccessSound() {
        playBeep(523, 0.1, 0.3);
        setTimeout(() => playBeep(659, 0.1, 0.3), 100);
        setTimeout(() => playBeep(784, 0.15, 0.4), 200);
    }
    
    function playAlarmSound() {
        let count = 0;
        const interval = setInterval(() => {
            if (count >= 4) { clearInterval(interval); return; }
            playBeep(880, 0.3, 0.4);
            count++;
        }, 400);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // EVENT LISTENERS
    // ═══════════════════════════════════════════════════════════════════════════════
    
    // زر تحديث الموقع
    DOM.locBtn?.addEventListener('click', () => {
        DOM.locBtn.classList.add('loading');
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                handleGPSSuccess(pos);
                STATE.map?.setView([pos.coords.latitude, pos.coords.longitude], 17);
                DOM.locBtn.classList.remove('loading');
            },
            () => DOM.locBtn.classList.remove('loading'),
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });
    
    // زر الانصراف
    DOM.actionBtn?.addEventListener('click', async () => {
        if (CONFIG.actionType !== 'checkout' || DOM.actionBtn.disabled) return;
        
        DOM.actionBtn.disabled = true;
        
        try {
            const response = await fetch(CONFIG.actionUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CONFIG.csrfToken
                },
                body: JSON.stringify({
                    action: 'checkout',
                    latitude: STATE.userLat,
                    longitude: STATE.userLng
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                playSuccessSound();
                
                Swal?.fire({
                        icon: 'success',
                    title: '✅ تم تسجيل الانصراف',
                    confirmButtonText: 'حسناً'
                }).then(() => {
                    window.location.href = 'index.php';
                    });
                } else {
                throw new Error(data.message);
            }
        } catch (e) {
            Swal?.fire({ icon: 'error', title: 'خطأ', text: e.message });
            DOM.actionBtn.disabled = false;
        }
    });
    
    // لوحة الزملاء
        DOM.colleaguesToggle?.addEventListener('click', () => {
        DOM.colleaguesList?.classList.toggle('hidden');
        });
        
        DOM.closeColleagues?.addEventListener('click', () => {
            DOM.colleaguesList?.classList.add('hidden');
    });

    // ═══════════════════════════════════════════════════════════════════════════════
    // INITIALIZATION
    // ═══════════════════════════════════════════════════════════════════════════════
    
    document.addEventListener('DOMContentLoaded', initMap);

})();
