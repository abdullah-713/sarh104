/**
 * =====================================================
 * صرح الإتقان - PWA Handler
 * نظام السيطرة الميدانية
 * =====================================================
 * يدعم: تثبيت التطبيق، التحديثات، إشعارات الدفع
 * =====================================================
 */

(() => {
  'use strict';

  // =====================================================
  // الحالة والإعدادات
  // =====================================================
  
  const state = {
    deferredPrompt: null,
    installBanner: null,
    installButton: null,
    swRegistration: null,
    updateAvailable: false,
    newWorker: null
  };

  const config = {
    swPath: '/app/service-worker.js',
    swScope: '/app/',
    updateCheckInterval: 60 * 60 * 1000, // ساعة واحدة
    showInstallAfter: 3000 // 3 ثواني
  };

  // =====================================================
  // دوال مساعدة
  // =====================================================

  const isStandalone = () =>
    window.matchMedia('(display-mode: standalone)').matches ||
    window.navigator.standalone === true ||
    document.referrer.includes('android-app://');

  const isIOS = () => /iphone|ipad|ipod/i.test(navigator.userAgent);

  const isAndroid = () => /android/i.test(navigator.userAgent);

  const isMobile = () => window.matchMedia('(max-width: 991px)').matches;

  const isSafari = () => /^((?!chrome|android).)*safari/i.test(navigator.userAgent);

  /**
   * عرض تنبيه للمستخدم
   */
  const showAlert = (title, message, icon = 'info', options = {}) => {
    if (window.Swal) {
      return Swal.fire({
        title,
        html: message,
        icon,
        confirmButtonText: options.confirmText || 'حسناً',
        confirmButtonColor: '#ff6f00',
        showCancelButton: options.showCancel || false,
        cancelButtonText: options.cancelText || 'إلغاء',
        allowOutsideClick: options.allowOutsideClick !== false,
        ...options
      });
    }
    alert(`${title}\n\n${message}`);
    return Promise.resolve({ isConfirmed: true });
  };

  /**
   * عرض toast
   */
  const showToast = (message, icon = 'success') => {
    if (window.Swal) {
      return Swal.fire({
        toast: true,
        position: 'top',
        icon,
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
      });
    }
  };

  // =====================================================
  // بانر التثبيت
  // =====================================================

  /**
   * بناء بانر التثبيت
   */
  const buildInstallBanner = () => {
    if (state.installBanner) return;

    const banner = document.createElement('div');
    banner.className = 'pwa-install-banner';
    banner.id = 'pwa-install-banner';
    banner.innerHTML = `
      <button type="button" class="pwa-install-button" id="pwa-install-button">
        <i class="bi bi-download"></i>
        <span>تثبيت التطبيق</span>
      </button>
      <button type="button" class="pwa-install-close" id="pwa-install-close" aria-label="إغلاق">
        <i class="bi bi-x-lg"></i>
      </button>
    `;

    // إضافة الأنماط
    const style = document.createElement('style');
    style.textContent = `
      .pwa-install-banner {
        position: fixed;
        left: 16px;
        right: 16px;
        bottom: calc(var(--bottom-nav-height, 70px) + 16px + env(safe-area-inset-bottom, 0px));
        z-index: 1100;
        display: none;
        justify-content: center;
        align-items: center;
        gap: 8px;
        pointer-events: none;
        animation: slideUp 0.3s ease;
      }
      body:not(.has-bottom-nav) .pwa-install-banner {
        bottom: calc(16px + env(safe-area-inset-bottom, 0px));
      }
      .pwa-install-banner.show {
        display: flex;
      }
      .pwa-install-button {
        pointer-events: auto;
        background: linear-gradient(135deg, #ff6f00 0%, #e65100 100%);
        color: #fff;
        border: none;
        border-radius: 999px;
        padding: 14px 24px;
        min-height: 52px;
        font-weight: 700;
        font-size: 1rem;
        font-family: 'Tajawal', sans-serif;
        box-shadow: 0 6px 20px rgba(255, 111, 0, 0.4);
        display: inline-flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }
      .pwa-install-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(255, 111, 0, 0.5);
      }
      .pwa-install-button:active {
        transform: translateY(1px) scale(0.98);
        box-shadow: 0 4px 12px rgba(255, 111, 0, 0.3);
      }
      .pwa-install-button i {
        font-size: 1.3rem;
      }
      .pwa-install-close {
        pointer-events: auto;
        background: rgba(0, 0, 0, 0.6);
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.2s;
      }
      .pwa-install-close:hover {
        background: rgba(0, 0, 0, 0.8);
      }
      @keyframes slideUp {
        from { transform: translateY(100px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
      }
      
      /* بانر التحديث */
      .pwa-update-banner {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
        color: #fff;
        padding: 12px 16px;
        display: none;
        justify-content: center;
        align-items: center;
        gap: 16px;
        z-index: 9999;
        font-family: 'Tajawal', sans-serif;
        animation: slideDown 0.3s ease;
      }
      .pwa-update-banner.show {
        display: flex;
      }
      .pwa-update-banner button {
        background: #fff;
        color: #2e7d32;
        border: none;
        border-radius: 8px;
        padding: 8px 16px;
        font-weight: 700;
        cursor: pointer;
        transition: transform 0.2s;
      }
      .pwa-update-banner button:hover {
        transform: scale(1.05);
      }
      @keyframes slideDown {
        from { transform: translateY(-100%); }
        to { transform: translateY(0); }
      }
    `;
    document.head.appendChild(style);
    document.body.appendChild(banner);

    state.installBanner = banner;
    state.installButton = banner.querySelector('#pwa-install-button');

    // أحداث
    state.installButton.addEventListener('click', handleInstallClick);
    banner.querySelector('#pwa-install-close').addEventListener('click', () => {
      hideInstallBanner();
      localStorage.setItem('pwa_install_dismissed', Date.now().toString());
    });
  };

  /**
   * عرض بانر التثبيت
   */
  const showInstallBanner = () => {
    // التحقق من تجاهل المستخدم
    const dismissed = localStorage.getItem('pwa_install_dismissed');
    if (dismissed) {
      const dismissedTime = parseInt(dismissed, 10);
      const dayInMs = 24 * 60 * 60 * 1000;
      if (Date.now() - dismissedTime < dayInMs * 7) {
        return; // لا تعرض لمدة أسبوع
      }
    }

    if (!state.installBanner) {
      buildInstallBanner();
    }
    state.installBanner.classList.add('show');
  };

  /**
   * إخفاء بانر التثبيت
   */
  const hideInstallBanner = () => {
    if (state.installBanner) {
      state.installBanner.classList.remove('show');
    }
  };

  /**
   * معالجة النقر على زر التثبيت
   */
  const handleInstallClick = async () => {
    // لو فيه prompt جاهز
    if (state.deferredPrompt) {
      state.deferredPrompt.prompt();
      const { outcome } = await state.deferredPrompt.userChoice;
      
      console.log('[PWA] Install prompt outcome:', outcome);
      
      if (outcome === 'accepted') {
        localStorage.setItem('pwa_installed', '1');
        localStorage.removeItem('pwa_install_dismissed');
        hideInstallBanner();
        showToast('تم تثبيت التطبيق بنجاح! 🎉');
      }
      
      state.deferredPrompt = null;
      return;
    }

    // iOS
    if (isIOS()) {
      await showAlert(
        'تثبيت التطبيق',
        `<div style="text-align: right; line-height: 1.8;">
          <p><strong>لتثبيت التطبيق على جهازك:</strong></p>
          <ol style="padding-right: 20px;">
            <li>اضغط على زر <strong>المشاركة</strong> <i class="bi bi-box-arrow-up"></i></li>
            <li>اختر <strong>"إضافة إلى الشاشة الرئيسية"</strong></li>
            <li>اضغط <strong>"إضافة"</strong></li>
          </ol>
        </div>`,
        'info'
      );
      return;
    }

    // Android بدون prompt
    await showAlert(
      'التثبيت غير متاح',
      'لتثبيت التطبيق، استخدم قائمة المتصفح واختر "إضافة إلى الشاشة الرئيسية" أو "تثبيت التطبيق".',
      'info'
    );
  };

  // =====================================================
  // التحديثات
  // =====================================================

  /**
   * بناء بانر التحديث
   */
  const buildUpdateBanner = () => {
    const existing = document.getElementById('pwa-update-banner');
    if (existing) return existing;

    const banner = document.createElement('div');
    banner.className = 'pwa-update-banner';
    banner.id = 'pwa-update-banner';
    banner.innerHTML = `
      <span><i class="bi bi-arrow-repeat me-2"></i>يتوفر تحديث جديد للتطبيق</span>
      <button type="button" id="pwa-update-btn">تحديث الآن</button>
    `;
    document.body.appendChild(banner);

    banner.querySelector('#pwa-update-btn').addEventListener('click', applyUpdate);
    
    return banner;
  };

  /**
   * عرض بانر التحديث
   */
  const showUpdateBanner = () => {
    const banner = buildUpdateBanner();
    banner.classList.add('show');
  };

  /**
   * تطبيق التحديث
   */
  const applyUpdate = () => {
    if (state.newWorker) {
      state.newWorker.postMessage({ type: 'SKIP_WAITING' });
    }
  };

  /**
   * التحقق من التحديثات
   */
  const checkForUpdates = async () => {
    if (!state.swRegistration) return;

    try {
      await state.swRegistration.update();
      console.log('[PWA] Checked for updates');
    } catch (error) {
      console.warn('[PWA] Update check failed:', error);
    }
  };

  // =====================================================
  // تسجيل Service Worker
  // =====================================================

  const registerServiceWorker = async () => {
    if (!('serviceWorker' in navigator)) {
      console.warn('[PWA] Service Worker not supported');
      return;
    }

    try {
      const registration = await navigator.serviceWorker.register(config.swPath, {
        scope: config.swScope
      });

      state.swRegistration = registration;
      console.log('[PWA] Service Worker registered:', registration.scope);

      // التعامل مع التحديثات
      registration.addEventListener('updatefound', () => {
        const newWorker = registration.installing;
        
        if (!newWorker) return;

        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
            console.log('[PWA] New version available');
            state.updateAvailable = true;
            state.newWorker = newWorker;
            showUpdateBanner();
          }
        });
      });

      // الاستماع لرسائل من SW
      navigator.serviceWorker.addEventListener('message', (event) => {
        const { data } = event;
        
        if (!data || !data.type) return;

        switch (data.type) {
          case 'SW_ACTIVATED':
            console.log('[PWA] New SW activated, version:', data.version);
            window.location.reload();
            break;
            
          case 'UPDATE_AVAILABLE':
            state.updateAvailable = true;
            showUpdateBanner();
            break;
            
          case 'NOTIFICATION_CLICK':
            if (data.url) {
              window.location.href = data.url;
            }
            break;
        }
      });

      // فحص دوري للتحديثات
      setInterval(checkForUpdates, config.updateCheckInterval);

    } catch (error) {
      console.error('[PWA] Service Worker registration failed:', error);
    }
  };

  // =====================================================
  // إشعارات الدفع
  // =====================================================

  /**
   * تحويل VAPID key
   */
  const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; i++) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  };

  /**
   * إرسال الاشتراك للسيرفر
   */
  const sendSubscriptionToServer = async (subscription) => {
    if (!window.SARH || !SARH.isLoggedIn) {
      throw new Error('User not logged in');
    }

    const response = await fetch(`${SARH.baseUrl}/api/notifications/subscribe.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': SARH.csrfToken
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        subscription: subscription.toJSON(),
        device_type: isMobile() ? 'mobile' : 'desktop',
        platform: isIOS() ? 'ios' : (isAndroid() ? 'android' : 'web'),
        source: 'pwa'
      })
    });

    if (!response.ok) {
      throw new Error('Failed to store subscription');
    }

    return response.json();
  };

  /**
   * طلب إذن الإشعارات والاشتراك
   */
  const subscribeToNotifications = async () => {
    // التحقق من الدعم
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      await showAlert('غير مدعوم', 'المتصفح لا يدعم إشعارات الدفع.', 'warning');
      return null;
    }

    // التحقق من VAPID key
    if (!window.SARH || !SARH.vapidPublicKey) {
      console.warn('[PWA] VAPID key not configured');
      await showAlert(
        'إعداد ناقص',
        'مفتاح الإشعارات غير مُعَد. يرجى التواصل مع الدعم الفني.',
        'error'
      );
      return null;
    }

    // طلب الإذن
    const permission = await Notification.requestPermission();
    
    if (permission === 'denied') {
      await showAlert(
        'تم رفض الإذن',
        'لتفعيل الإشعارات، يرجى السماح بها من إعدادات المتصفح.',
        'warning'
      );
      return null;
    }

    if (permission !== 'granted') {
      return null;
    }

    try {
      const registration = await navigator.serviceWorker.ready;
      
      // التحقق من اشتراك موجود
      let subscription = await registration.pushManager.getSubscription();
      
      // إنشاء اشتراك جديد إذا لم يوجد
      if (!subscription) {
        subscription = await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(SARH.vapidPublicKey)
        });
      }

      // إرسال للسيرفر
      await sendSubscriptionToServer(subscription);
      
      showToast('تم تفعيل الإشعارات بنجاح! 🔔');
      localStorage.setItem('push_subscribed', '1');
      
      return subscription;
      
    } catch (error) {
      console.error('[PWA] Push subscription failed:', error);
      await showAlert('خطأ', 'تعذر تفعيل الإشعارات. يرجى المحاولة مرة أخرى.', 'error');
      return null;
    }
  };

  /**
   * مزامنة الاشتراك الموجود
   */
  const syncExistingSubscription = async () => {
    if (!window.SARH || !SARH.isLoggedIn || !SARH.vapidPublicKey) {
      return;
    }

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      return;
    }

    if (Notification.permission !== 'granted') {
      return;
    }

    try {
      const registration = await navigator.serviceWorker.ready;
      const subscription = await registration.pushManager.getSubscription();
      
      if (subscription) {
        await sendSubscriptionToServer(subscription);
        console.log('[PWA] Subscription synced');
      }
    } catch (error) {
      console.warn('[PWA] Subscription sync failed:', error);
    }
  };

  /**
   * إلغاء الاشتراك
   */
  const unsubscribeFromNotifications = async () => {
    try {
      const registration = await navigator.serviceWorker.ready;
      const subscription = await registration.pushManager.getSubscription();
      
      if (subscription) {
        await subscription.unsubscribe();
        localStorage.removeItem('push_subscribed');
        showToast('تم إلغاء الإشعارات');
        return true;
      }
      
      return false;
    } catch (error) {
      console.error('[PWA] Unsubscribe failed:', error);
      return false;
    }
  };

  // =====================================================
  // التهيئة
  // =====================================================

  const initInstallPrompt = () => {
    // لا تعرض إذا مثبت
    if (isStandalone() || localStorage.getItem('pwa_installed') === '1') {
      console.log('[PWA] App already installed');
      return;
    }

    buildInstallBanner();

    // حدث beforeinstallprompt (Chrome, Edge, etc.)
    window.addEventListener('beforeinstallprompt', (event) => {
      event.preventDefault();
      state.deferredPrompt = event;
      console.log('[PWA] Install prompt captured');
      
      setTimeout(showInstallBanner, config.showInstallAfter);
    });

    // حدث appinstalled
    window.addEventListener('appinstalled', () => {
      console.log('[PWA] App installed');
      localStorage.setItem('pwa_installed', '1');
      localStorage.removeItem('pwa_install_dismissed');
      hideInstallBanner();
      state.deferredPrompt = null;
    });

    // iOS - عرض تعليمات التثبيت
    if (isIOS() && isMobile() && !isStandalone()) {
      setTimeout(showInstallBanner, config.showInstallAfter);
    }
  };

  /**
   * بدء PWA
   */
  const startPWA = async () => {
    console.log('[PWA] Initializing...');
    
    await registerServiceWorker();
    await syncExistingSubscription();
    
    console.log('[PWA] Ready');
  };

  // =====================================================
  // API العام
  // =====================================================

  window.SARH_PWA = {
    // التثبيت
    showInstallBanner,
    hideInstallBanner,
    isInstalled: () => isStandalone() || localStorage.getItem('pwa_installed') === '1',
    
    // التحديثات
    checkForUpdates,
    applyUpdate,
    isUpdateAvailable: () => state.updateAvailable,
    
    // الإشعارات
    subscribeToNotifications,
    unsubscribeFromNotifications,
    syncExistingSubscription,
    isNotificationsEnabled: () => Notification.permission === 'granted',
    
    // Service Worker
    getRegistration: () => state.swRegistration,
    
    // مسح التخزين
    clearCache: async () => {
      if (state.swRegistration && state.swRegistration.active) {
        state.swRegistration.active.postMessage({ type: 'CLEAR_CACHE' });
        showToast('تم مسح التخزين المؤقت');
      }
    },
    
    // معلومات
    isStandalone,
    isIOS,
    isAndroid,
    isMobile
  };

  // =====================================================
  // البدء
  // =====================================================

  initInstallPrompt();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startPWA);
  } else {
    startPWA();
  }

})();
