# 🚀 دليل نشر نظام صرح الإتقان على Hostinger
# Sarh Al-Itqan Deployment Guide for Hostinger

## 📋 معلومات النشر | Deployment Info

| المعلومة | القيمة |
|----------|--------|
| **الرابط** | https://sarh.site/app |
| **المضيف** | Hostinger Shared Hosting |
| **قاعدة البيانات** | u307296675_308 |
| **مستخدم قاعدة البيانات** | u307296675_308 |
| **PHP Version** | 8.1+ (مطلوب) |
| **المقر الرئيسي** | الرياض |
| **إحداثيات المقر** | 24.5723738, 46.6028185 |
| **نطاق التسجيل** | 150 متر |

---

## 📁 خطوات الرفع | Upload Steps

### 1️⃣ تجهيز الملفات

```
✅ config/database.php - تم تحديث بيانات الاتصال
✅ config/app.php - تم تحديث BASE_URL
✅ .htaccess - تم تفعيل HTTPS
✅ .user.ini - إعدادات PHP للاستضافة المشتركة
```

### 2️⃣ رفع الملفات إلى Hostinger

1. ادخل إلى **File Manager** في لوحة تحكم Hostinger
2. انتقل إلى مجلد `public_html`
3. أنشئ مجلد باسم `app`
4. ارفع جميع ملفات المشروع داخل مجلد `app`

```
public_html/
└── app/
    ├── config/
    ├── includes/
    ├── assets/
    ├── api/
    ├── admin/
    ├── developer/
    ├── uploads/
    ├── logs/
    ├── errors/
    ├── install/
    ├── .htaccess
    ├── .user.ini
    ├── index.php
    └── ... (باقي الملفات)
```

### 3️⃣ إعداد قاعدة البيانات

1. من لوحة تحكم Hostinger، افتح **phpMyAdmin**
2. اختر قاعدة البيانات `u307296675_308`
3. استورد ملف `install/master.sql`

### 4️⃣ صلاحيات المجلدات

```bash
# المجلدات التي تحتاج صلاحيات كتابة
chmod 755 uploads/
chmod 755 logs/
chmod 755 backups/
```

---

## 🔐 إعدادات الأمان | Security Settings

### الملفات المحمية تلقائياً:
- ✅ `config/` - ممنوع الوصول المباشر
- ✅ `includes/` - ممنوع الوصول المباشر
- ✅ `logs/` - ممنوع الوصول المباشر
- ✅ `.htaccess` و `.user.ini` - ممنوع الوصول

### HTTPS:
- ✅ إعادة التوجيه التلقائي لـ HTTPS مفعّل
- ✅ HSTS Header مفعّل

---

## 🧪 اختبار النشر | Testing Deployment

بعد الرفع، تحقق من:

1. **الصفحة الرئيسية**: https://sarh.site/app
2. **صفحة تسجيل الدخول**: https://sarh.site/app/login.php
3. **صفحة الحضور**: https://sarh.site/app/attendance.php
4. **لوحة المطور**: https://sarh.site/app/developer/

---

## 👤 بيانات الدخول الافتراضية | Default Credentials

### مدير النظام (System Admin):
```
اسم المستخدم: admin
كلمة المرور: Admin@2026
البريد: admin@sarh.site
```

### المطور (Developer - The_Architect):
```
اسم المستخدم: The_Architect
كلمة المرور: MySecretPass2026
البريد: architect@sarh.site
```

### لوحة المطور (Developer Console):
```
الرابط: https://sarh.site/app/developer/
اسم المستخدم: The_Architect
كلمة المرور: MySecretPass2026
```

### الموظفين التجريبيين:
```
أسماء المستخدمين: ahmed, sara, khalid, fatima, omar
كلمة المرور: Admin@2026
```

---

## ⚠️ ملاحظات مهمة | Important Notes

1. **تغيير كلمات المرور**: يُنصح بتغيير كلمات المرور الافتراضية فوراً
2. **النسخ الاحتياطي**: قم بعمل نسخة احتياطية قبل أي تحديث
3. **SSL**: تأكد من تفعيل شهادة SSL على Hostinger
4. **الموقع الجغرافي**: النظام يستخدم GPS للحضور

---

## 📞 الدعم الفني | Technical Support

في حالة وجود مشاكل:
1. تحقق من سجل الأخطاء في `logs/`
2. تحقق من إعدادات قاعدة البيانات
3. تأكد من إصدار PHP (8.1+)

---

## 📅 تاريخ آخر تحديث | Last Updated

**2026-01-16** - تجهيز للنشر على Hostinger
