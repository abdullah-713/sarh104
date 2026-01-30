# 📱 Smart Variable Tracking System - Documentation

**نظام التتبع الذكي المتغير - صرح الإتقان**

---

## 📋 نظرة عامة

نظام تتبع ذكي يتكيف مع حالة الشاشة لتوفير البطارية مع الحفاظ على الجلسة نشطة.

### الميزات:
- ✅ **Dynamic Intervals**: 10 ثواني (Foreground) / 10 دقائق (Background)
- ✅ **Silent Audio Hack**: يحافظ على الـ main thread نشطاً في الخلفية
- ✅ **Time Bridge Logic**: تتبع الحضور السلبي عند وجود المستخدم داخل نطاق 20 متر

---

## 🔧 الملفات

### 1. Frontend: `assets/js/SmartTracker.js`

**الاستخدام:**
```javascript
// Initialize
const tracker = new SmartTracker({
    heartbeatUrl: '/app/api/heartbeat.php',
    csrfToken: 'your-csrf-token',
    foregroundInterval: 10000,  // 10 seconds
    backgroundInterval: 600000,  // 10 minutes
    debug: true
});

// Start tracking
tracker.start();

// Stop tracking (on page unload)
tracker.stop();
```

**الميزات:**
- **State Detection**: يستخدم `visibilitychange` event
- **GPS Watch**: `navigator.geolocation.watchPosition`
- **Silent Audio**: Base64 encoded MP3 (1 second loop)
- **Dynamic Intervals**: يتغير تلقائياً حسب حالة الشاشة

---

### 2. Backend: `api/heartbeat.php`

**Time Bridge Logic:**

```php
// عند استلام location update:
processTimeBridge($user_id, $branch_id, $latitude, $longitude);

// المنطق:
// 1. حساب المسافة من الفرع (Haversine)
// 2. IF distance <= 20m:
//    - IF NO record: INSERT (check_in_time = NOW, check_out_time = NOW)
//    - IF YES: UPDATE check_out_time = NOW
// 3. IF distance > 20m: Do nothing
```

**الميزات:**
- **Strict 20m Radius**: فقط داخل النطاق يتم التتبع
- **Implicit Time Bridge**: الفجوات بين التحديثات (10 دقائق) تُقبل تلقائياً
- **Auto GPS Method**: جميع السجلات بـ `check_in_method = 'auto_gps'`

---

## 📊 التدفق

### State A: Foreground (Screen ON)
```
User → GPS Update (every 1s) → SmartTracker (every 10s) → heartbeat.php → Time Bridge
```

### State B: Background (Screen OFF)
```
User → GPS Update (every 1s) → SmartTracker (every 10min) → heartbeat.php → Time Bridge
+ Silent Audio Loop (keeps thread alive)
```

---

## 🔋 توفير البطارية

| الحالة | Interval | GPS Updates | Network Calls | Battery Impact |
|--------|----------|-------------|---------------|----------------|
| **Foreground** | 10s | Continuous | 6/min | Medium |
| **Background** | 10min | Continuous | 1/10min | Low |

**Silent Audio Hack:**
- يحافظ على الـ main thread نشطاً
- يمنع المتصفح من إيقاف JavaScript
- حجم الملف: ~1KB (Base64)

---

## 📝 مثال على الاستخدام

### في `attendance.php`:

```javascript
// بعد تسجيل الحضور
if (typeof SmartTracker !== 'undefined') {
    window.smartTracker = new SmartTracker({
        heartbeatUrl: CONFIG.heartbeatUrl,
        csrfToken: CONFIG.csrfToken,
        debug: false
    });
    
    window.smartTracker.start();
}

// عند تسجيل الانصراف
if (window.smartTracker) {
    window.smartTracker.stop();
}
```

---

## 🗄️ Database Schema

**جدول `attendance`:**
```sql
-- عند INSERT (أول مرة داخل النطاق):
INSERT INTO attendance (
    user_id, branch_id, date,
    check_in_time, check_out_time,  -- كلاهما = NOW
    check_in_lat, check_in_lng,
    check_out_lat, check_out_lng,
    check_in_method = 'auto_gps',
    status = 'present'
);

-- عند UPDATE (تحديثات لاحقة):
UPDATE attendance 
SET check_out_time = NOW(),
    check_out_lat = ?,
    check_out_lng = ?,
    updated_at = NOW()
WHERE user_id = ? AND date = CURDATE();
```

---

## ⚠️ ملاحظات مهمة

1. **Silent Audio**: قد لا يعمل في بعض المتصفحات (Safari iOS) - النظام يعمل بدونها
2. **GPS Accuracy**: يستخدم `enableHighAccuracy: true` للحصول على أفضل دقة
3. **Network Errors**: يتم تجاهل الأخطاء بصمت (لا تعطل التطبيق)
4. **Battery Optimization**: في Background، التحديثات كل 10 دقائق فقط

---

## 🧪 الاختبار

```javascript
// Enable debug mode
const tracker = new SmartTracker({
    debug: true,
    // ...
});

// Check console for logs:
// [SmartTracker] [timestamp] State changed: BACKGROUND
// [SmartTracker] [timestamp] Starting tracking loop: 600000s interval (BACKGROUND)
// [SmartTracker] [timestamp] Location sent: 24.713800, 46.675500
```

---

**تم إنشاؤه بواسطة:** Senior PWA Engineer  
**التاريخ:** 2026-01-XX  
**الإصدار:** 1.0.0
