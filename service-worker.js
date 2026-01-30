/**
 * =====================================================
 * صرح الإتقان - Service Worker
 * نظام السيطرة الميدانية - PWA
 * =====================================================
 * يدعم: التخزين المؤقت، التحديث التلقائي، إشعارات الدفع
 * =====================================================
 */

const CACHE_VERSION = 'v1.2.0';
const APP_PREFIX = 'sarh';
const PRECACHE_NAME = `${APP_PREFIX}-precache-${CACHE_VERSION}`;
const RUNTIME_NAME = `${APP_PREFIX}-runtime-${CACHE_VERSION}`;
const PAGES_NAME = `${APP_PREFIX}-pages-${CACHE_VERSION}`;
const IMAGES_NAME = `${APP_PREFIX}-images-${CACHE_VERSION}`;

// الصفحة البديلة عند عدم وجود اتصال
const OFFLINE_URL = '/app/offline.html';

// الملفات الأساسية للتخزين المسبق
const PRECACHE_URLS = [
  '/app/',
  '/app/index.php',
  '/app/manifest.json',
  OFFLINE_URL,
  '/app/assets/css/attendance.css',
  '/app/assets/js/attendance_core.js',
  '/app/assets/js/pwa.js',
  '/app/assets/images/favicon.png',
  '/app/assets/images/apple-touch-icon.png',
  '/app/assets/images/pwa/icon-192.png',
  '/app/assets/images/pwa/icon-512.png',
  '/app/assets/images/pwa/icon-192-maskable.png',
  '/app/assets/images/pwa/icon-512-maskable.png',
  '/app/assets/images/pwa/badge-72.png'
];

// الملفات التي لا يجب تخزينها مؤقتاً
const CACHE_BLACKLIST = [
  '/api/',
  'login.php',
  'logout.php',
  'checkin.php',
  'subscribe.php'
];

// =====================================================
// أحداث Service Worker
// =====================================================

/**
 * حدث التثبيت - تخزين الملفات الأساسية
 */
self.addEventListener('install', (event) => {
  console.log('[SW] Installing Service Worker...');
  
  event.waitUntil(
    caches.open(PRECACHE_NAME)
      .then((cache) => {
        console.log('[SW] Precaching app shell...');
        return cache.addAll(PRECACHE_URLS);
      })
      .then(() => {
        console.log('[SW] Installation complete, skipping waiting...');
        return self.skipWaiting();
      })
      .catch((error) => {
        console.error('[SW] Precache failed:', error);
      })
  );
});

/**
 * حدث التفعيل - حذف التخزين القديم
 */
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating Service Worker...');
  
  const currentCaches = [PRECACHE_NAME, RUNTIME_NAME, PAGES_NAME, IMAGES_NAME];
  
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((cacheName) => cacheName.startsWith(APP_PREFIX) && !currentCaches.includes(cacheName))
            .map((cacheName) => {
              console.log('[SW] Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            })
        );
      })
      .then(() => {
        console.log('[SW] Claiming clients...');
        return self.clients.claim();
      })
      .then(() => {
        // إخطار جميع العملاء بالتحديث
        return self.clients.matchAll({ type: 'window' });
      })
      .then((clients) => {
        clients.forEach((client) => {
          client.postMessage({ type: 'SW_ACTIVATED', version: CACHE_VERSION });
        });
      })
  );
});

// =====================================================
// استراتيجيات التخزين المؤقت
// =====================================================

/**
 * فحص إذا كان الطلب للتصفح (navigation)
 */
function isNavigationRequest(request) {
  const acceptHeader = request.headers.get('accept');
  return request.mode === 'navigate' || 
         (acceptHeader && acceptHeader.includes('text/html'));
}

/**
 * فحص إذا كان يجب تجاهل الطلب
 */
function shouldSkipCache(request) {
  const url = new URL(request.url);
  return CACHE_BLACKLIST.some((pattern) => url.pathname.includes(pattern));
}

/**
 * استراتيجية: الشبكة أولاً مع fallback للتخزين
 */
async function networkFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  
  try {
    const networkResponse = await fetch(request);
    
    if (networkResponse && (networkResponse.ok || networkResponse.type === 'opaque')) {
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    const cachedResponse = await cache.match(request);
    
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // إذا كان طلب تصفح، عرض صفحة offline
    if (isNavigationRequest(request)) {
      return caches.match(OFFLINE_URL);
    }
    
    throw error;
  }
}

/**
 * استراتيجية: التخزين أولاً مع تحديث في الخلفية
 */
async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cachedResponse = await cache.match(request);
  
  const networkFetch = fetch(request)
    .then((networkResponse) => {
      if (networkResponse && (networkResponse.ok || networkResponse.type === 'opaque')) {
        cache.put(request, networkResponse.clone());
      }
      return networkResponse;
    })
    .catch(() => cachedResponse);
  
  return cachedResponse || networkFetch;
}

/**
 * استراتيجية: التخزين أولاً (للصور والخطوط)
 */
async function cacheFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cachedResponse = await cache.match(request);
  
  if (cachedResponse) {
    return cachedResponse;
  }
  
  try {
    const networkResponse = await fetch(request);
    
    if (networkResponse && (networkResponse.ok || networkResponse.type === 'opaque')) {
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    console.error('[SW] Cache first failed:', error);
    throw error;
  }
}

/**
 * حدث الطلبات - توجيه لاستراتيجية التخزين المناسبة
 */
self.addEventListener('fetch', (event) => {
  const { request } = event;
  
  // ═══════════════════════════════════════════════════════════════════
  // CRITICAL: Cache API لا يدعم POST/PUT/DELETE - تجاهل فوراً
  // ═══════════════════════════════════════════════════════════════════
  // لا تعالج الطلبات غير GET لتجنب "Request method 'POST' is unsupported"
  if (request.method !== 'GET') {
    // تجاهل تماماً - دع المتصفح يتعامل معها مباشرة
    return;
  }
  
  // تجاهل طلبات chrome-extension و غيرها
  if (!request.url.startsWith('http')) {
    return;
  }
  
  // تجاهل الملفات في القائمة السوداء
  if (shouldSkipCache(request)) {
    return;
  }
  
  // صفحات HTML - الشبكة أولاً
  if (isNavigationRequest(request)) {
    event.respondWith(networkFirst(request, PAGES_NAME));
    return;
  }
  
  const destination = request.destination;
  const url = new URL(request.url);
  
  // الصور - التخزين أولاً
  if (destination === 'image') {
    event.respondWith(cacheFirst(request, IMAGES_NAME));
    return;
  }
  
  // CSS و JavaScript - تحديث في الخلفية
  if (destination === 'style' || destination === 'script') {
    event.respondWith(staleWhileRevalidate(request, RUNTIME_NAME));
    return;
  }
  
  // الخطوط - التخزين أولاً
  if (destination === 'font' || url.pathname.includes('/fonts/')) {
    event.respondWith(cacheFirst(request, RUNTIME_NAME));
    return;
  }
  
  // باقي الطلبات - تحديث في الخلفية
  event.respondWith(staleWhileRevalidate(request, RUNTIME_NAME));
});

// =====================================================
// إشعارات الدفع (Push Notifications)
// =====================================================

/**
 * حدث استلام إشعار Push
 */
self.addEventListener('push', (event) => {
  console.log('[SW] Push notification received');
  
  let payload = {};
  
  if (event.data) {
    try {
      payload = event.data.json();
    } catch (error) {
      payload = { body: event.data.text() };
    }
  }
  
  const title = payload.title || 'صرح الإتقان';
  const options = {
    body: payload.body || 'لديك إشعار جديد',
    icon: payload.icon || '/app/assets/images/pwa/icon-192.png',
    badge: payload.badge || '/app/assets/images/pwa/badge-72.png',
    image: payload.image || null,
    vibrate: payload.vibrate || [100, 50, 100],
    data: {
      url: payload.url || '/app/notifications.php',
      action: payload.action || 'open',
      notificationId: payload.notificationId || null,
      timestamp: Date.now()
    },
    tag: payload.tag || 'sarh-notification',
    renotify: payload.renotify !== false,
    requireInteraction: payload.requireInteraction !== false,
    actions: payload.actions || [
      { action: 'open', title: 'فتح', icon: '/app/assets/images/pwa/action-open.png' },
      { action: 'dismiss', title: 'تجاهل', icon: '/app/assets/images/pwa/action-dismiss.png' }
    ],
    dir: 'rtl',
    lang: 'ar'
  };
  
  // إزالة الخيارات الفارغة
  if (!options.image) delete options.image;
  
  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

/**
 * حدث النقر على الإشعار
 */
self.addEventListener('notificationclick', (event) => {
  console.log('[SW] Notification clicked:', event.action);
  
  event.notification.close();
  
  const action = event.action || 'open';
  const data = event.notification.data || {};
  
  if (action === 'dismiss') {
    // فقط إغلاق الإشعار
    return;
  }
  
  const targetUrl = data.url || '/app/notifications.php';
  const absoluteUrl = new URL(targetUrl, self.registration.scope).href;
  
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clientList) => {
        // البحث عن نافذة مفتوحة
        for (const client of clientList) {
          if (client.url === absoluteUrl && 'focus' in client) {
            return client.focus();
          }
        }
        
        // البحث عن أي نافذة للتطبيق
        for (const client of clientList) {
          if (client.url.includes('/app/') && 'focus' in client) {
            client.postMessage({
              type: 'NOTIFICATION_CLICK',
              url: targetUrl,
              notificationId: data.notificationId
            });
            return client.focus();
          }
        }
        
        // فتح نافذة جديدة
        if (self.clients.openWindow) {
          return self.clients.openWindow(absoluteUrl);
        }
        
        return null;
      })
  );
});

/**
 * حدث إغلاق الإشعار
 */
self.addEventListener('notificationclose', (event) => {
  console.log('[SW] Notification closed');
  
  const data = event.notification.data || {};
  
  // يمكن إرسال إحصائيات هنا إذا لزم الأمر
  if (data.notificationId) {
    // تسجيل أن الإشعار تم تجاهله
  }
});

// =====================================================
// الرسائل من الصفحة
// =====================================================

/**
 * حدث استلام رسالة من الصفحة
 */
self.addEventListener('message', (event) => {
  console.log('[SW] Message received:', event.data);
  
  if (!event.data || !event.data.type) {
    return;
  }
  
  switch (event.data.type) {
    case 'SKIP_WAITING':
      // تفعيل Service Worker الجديد فوراً
      self.skipWaiting();
      break;
      
    case 'GET_VERSION':
      // إرسال رقم الإصدار
      event.source.postMessage({
        type: 'VERSION',
        version: CACHE_VERSION
      });
      break;
      
    case 'CLEAR_CACHE':
      // مسح التخزين المؤقت
      caches.keys().then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((name) => name.startsWith(APP_PREFIX))
            .map((name) => caches.delete(name))
        );
      }).then(() => {
        event.source.postMessage({ type: 'CACHE_CLEARED' });
      });
      break;
      
    case 'CACHE_URLS':
      // تخزين URLs محددة
      if (event.data.urls && Array.isArray(event.data.urls)) {
        caches.open(RUNTIME_NAME).then((cache) => {
          return cache.addAll(event.data.urls);
        });
      }
      break;
  }
});

// =====================================================
// مزامنة الخلفية (Background Sync)
// =====================================================

/**
 * حدث مزامنة الخلفية
 */
self.addEventListener('sync', (event) => {
  console.log('[SW] Background sync:', event.tag);
  
  if (event.tag === 'attendance-sync') {
    event.waitUntil(syncAttendance());
  }
});

/**
 * مزامنة بيانات الحضور المعلقة
 */
async function syncAttendance() {
  try {
    // جلب البيانات المعلقة من IndexedDB
    // وإرسالها للسيرفر
    console.log('[SW] Syncing pending attendance...');
  } catch (error) {
    console.error('[SW] Sync failed:', error);
    throw error;
  }
}

// =====================================================
// المزامنة الدورية (Periodic Sync)
// =====================================================

self.addEventListener('periodicsync', (event) => {
  console.log('[SW] Periodic sync:', event.tag);
  
  if (event.tag === 'check-updates') {
    event.waitUntil(checkForUpdates());
  }
});

async function checkForUpdates() {
  try {
    const response = await fetch('/app/api/heartbeat.php');
    const data = await response.json();
    
    if (data.newVersion && data.newVersion !== CACHE_VERSION) {
      // إخطار المستخدم بوجود تحديث
      self.clients.matchAll({ type: 'window' }).then((clients) => {
        clients.forEach((client) => {
          client.postMessage({
            type: 'UPDATE_AVAILABLE',
            version: data.newVersion
          });
        });
      });
    }
  } catch (error) {
    console.error('[SW] Update check failed:', error);
  }
}

console.log(`[SW] Service Worker loaded - Version: ${CACHE_VERSION}`);
