# 🕐 إعداد Cron Jobs - نظام صرح الإتقان

## الملفات المتاحة

| الملف | الوظيفة | التوقيت المقترح |
|-------|---------|-----------------|
| `daily_report.php` | تقرير الصباح للمدراء | `0 8 * * *` (8:00 صباحاً) |
| `auto_checkout.php` | إغلاق الانصراف المنسي | `0 0 * * *` (منتصف الليل) |
| `monthly_reset.php` | تصفير النقاط وأرشفة البيانات | `0 1 1 * *` (أول كل شهر) |
| `precrime_analyzer.php` | تحليل الأنماط والتنبؤ | `0 2 * * *` (2:00 صباحاً) |

---

## 📝 إعداد Crontab

### الطريقة 1: لوحة التحكم (cPanel)
1. ادخل إلى **Cron Jobs** في cPanel
2. أضف كل مهمة بالإعدادات التالية:

#### تقرير الصباح اليومي
```
Minute: 0
Hour: 8
Day: *
Month: *
Weekday: *
Command: /usr/local/bin/php /home/u307296675/domains/sarh.site/public_html/app/cron/daily_report.php >> /home/u307296675/logs/cron_daily.log 2>&1
```

#### إغلاق الانصراف التلقائي
```
Minute: 0
Hour: 0
Day: *
Month: *
Weekday: *
Command: /usr/local/bin/php /home/u307296675/domains/sarh.site/public_html/app/cron/auto_checkout.php >> /home/u307296675/logs/cron_checkout.log 2>&1
```

#### التصفير الشهري
```
Minute: 0
Hour: 1
Day: 1
Month: *
Weekday: *
Command: /usr/local/bin/php /home/u307296675/domains/sarh.site/public_html/app/cron/monthly_reset.php >> /home/u307296675/logs/cron_monthly.log 2>&1
```

#### التحليل التنبؤي
```
Minute: 0
Hour: 2
Day: *
Month: *
Weekday: *
Command: /usr/local/bin/php /home/u307296675/domains/sarh.site/public_html/app/cron/precrime_analyzer.php >> /home/u307296675/logs/cron_precrime.log 2>&1
```

---

### الطريقة 2: SSH Terminal
```bash
crontab -e
```

ثم أضف:
```cron
# ═══════════════════════════════════════════════════════════════
# SARH SYSTEM - AUTOMATED TASKS
# ═══════════════════════════════════════════════════════════════

# تقرير الصباح - 8:00 AM يومياً
0 8 * * * /usr/local/bin/php /home/u307296675/domains/sarh.site/public_html/app/cron/daily_report.php >> /home/u307296675/logs/cron_daily.log 2>&1

# إغلاق الانصراف المنسي - منتصف الليل
0 0 * * * /usr/local/bin/php /home/u307296675/domains/sarh.site/public_html/app/cron/auto_checkout.php >> /home/u307296675/logs/cron_checkout.log 2>&1

# التصفير الشهري - أول كل شهر 1:00 AM
0 1 1 * * /usr/local/bin/php /home/u307296675/domains/sarh.site/public_html/app/cron/monthly_reset.php >> /home/u307296675/logs/cron_monthly.log 2>&1

# التحليل التنبؤي - 2:00 AM يومياً
0 2 * * * /usr/local/bin/php /home/u307296675/domains/sarh.site/public_html/app/cron/precrime_analyzer.php >> /home/u307296675/logs/cron_precrime.log 2>&1
```

---

## 🧪 اختبار السكربتات يدوياً

```bash
# تقرير الصباح
php /home/u307296675/domains/sarh.site/public_html/app/cron/daily_report.php

# إغلاق الانصراف
php /home/u307296675/domains/sarh.site/public_html/app/cron/auto_checkout.php

# التصفير الشهري (⚠️ حذر - يصفر النقاط!)
php /home/u307296675/domains/sarh.site/public_html/app/cron/monthly_reset.php

# التحليل التنبؤي
php /home/u307296675/domains/sarh.site/public_html/app/cron/precrime_analyzer.php
```

---

## 📊 مراقبة السجلات

```bash
# مشاهدة آخر تشغيل
tail -f /home/u307296675/logs/cron_daily.log

# البحث عن أخطاء
grep -i error /home/u307296675/logs/cron_*.log
```

---

## ⚠️ ملاحظات مهمة

1. **مسار PHP**: تأكد من مسار PHP الصحيح (`/usr/local/bin/php` أو `/usr/bin/php`)
2. **الصلاحيات**: تأكد من صلاحيات التنفيذ على الملفات
3. **المنطقة الزمنية**: تأكد من ضبط المنطقة الزمنية في السيرفر
4. **مجلد السجلات**: أنشئ مجلد `/home/u307296675/logs/` إذا لم يكن موجوداً

```bash
mkdir -p /home/u307296675/logs
chmod 755 /home/u307296675/logs
```

---

## 🔧 الجداول المطلوبة في قاعدة البيانات

تأكد من وجود هذه الجداول:
- `monthly_archive` - لأرشفة البيانات الشهرية
- `wallet_transactions` - لمعاملات المحفظة
- `predictive_risk_scores` - لنتائج التحليل التنبؤي
- `influence_graph` - لشبكة التأثير
- `emulator_detection_logs` - لكشف المحاكيات

---

Created by SARH System v1.8.0
