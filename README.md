# SARH104 - نظام إدارة الموارد البشرية | HR Management System

## ماذا يجري؟ | What's Happening?

هذا المستودع يحتوي على قاعدة بيانات MySQL/MariaDB لنظام إدارة موارد بشرية متقدم يسمى "صرح الإتقان" (SARH). النظام مصمم لإدارة الموظفين والحضور والغياب مع ميزات متقدمة لمراقبة السلامة المؤسسية.

This repository contains a MySQL/MariaDB database dump for an advanced HR management system called "Sarh Al-Itqan" (SARH). The system is designed to manage employees, attendance, and leaves with advanced features for organizational integrity monitoring.

## ⚠️ تحذير قانوني مهم | Important Legal Notice

**يحتوي هذا النظام على ميزات مراقبة متقدمة للسلوك الوظيفي والتحليل النفسي للموظفين. قبل استخدام هذا النظام، يجب:**

- الحصول على موافقة صريحة من الموظفين على المراقبة
- الامتثال لقوانين حماية البيانات المحلية (مثل GDPR، قانون حماية البيانات الشخصية)
- الامتثال لقوانين العمل والخصوصية في بلدك
- الإفصاح الكامل للموظفين عن أنواع المراقبة المستخدمة
- توثيق الموافقات والامتثال القانوني

**This system contains advanced employee behavior monitoring and psychological profiling features. Before using this system, you must:**

- Obtain explicit employee consent for monitoring
- Comply with local data protection laws (e.g., GDPR, personal data protection laws)
- Comply with labor laws and privacy regulations in your jurisdiction
- Provide full disclosure to employees about monitoring methods
- Document consent and legal compliance

**عدم الامتثال قد يؤدي إلى عواقب قانونية خطيرة | Non-compliance may result in serious legal consequences**

## 📋 نظرة عامة | Overview

### اللغة | Language
- النظام يدعم اللغة العربية بشكل كامل
- Full Arabic language support with UTF-8MB4 encoding

### الوظائف الرئيسية | Main Features

#### 1. إدارة الموظفين | Employee Management
- نظام المستخدمين والأدوار (Users & Roles)
- جداول العمل والمرونة (Work Schedules)
- الصلاحيات المتعددة المستويات (Multi-level Permissions)

#### 2. الحضور والانصراف | Attendance & Time Tracking
- تسجيل الحضور بالموقع الجغرافي (Location-based Check-in)
- تتبع ساعات العمل (Working Hours Tracking)
- حساب التأخير والمكافآت (Late Penalties & Bonuses)
- دعم أوضاع الحضور المرنة (Flexible Attendance Modes)

#### 3. الفروع | Branch Management
- إدارة متعددة الفروع (Multi-branch Support)
- تحديد الموقع الجغرافي للفروع (Geofencing)
- الفروع الوهمية للمراقبة (Ghost Branches)

#### 4. الإجازات | Leave Management
- إدارة طلبات الإجازات (Leave Requests)
- أنواع متعددة من الإجازات (Multiple Leave Types)
- الموافقات والرفض (Approval Workflow)

#### 5. مراقبة السلامة المؤسسية | Integrity Monitoring
- **نظام الفخاخ السلوكية (Behavioral Traps System)**
  - فخاخ تسريب البيانات (Data Leak Traps)
  - أزرار إدارة وهمية (Ghost Admin Buttons)
  - إشعارات سرية مزيفة (Confidential Baits)
  
- **التحليل النفسي (Psychological Profiling)**
  - درجة الثقة (Trust Score)
  - درجة الفضول (Curiosity Score)
  - درجة النزاهة (Integrity Score)
  - تصنيف الموظفين (Employee Classification):
    - الحارس المخلص (Loyal Sentinel)
    - المراقب الفضولي (Curious Observer)
    - الانتهازي (Opportunist)
    - المستغل النشط (Active Exploiter)
    - التهديد الداخلي المحتمل (Potential Insider)

- **سجلات النزاهة (Integrity Logs)**
  - تسجيل الأنشطة المشبوهة (Suspicious Activity Logging)
  - تقارير المخالفات (Violation Reports)
  - نظام التقارير المجهولة (Anonymous Reporting)

#### 6. الإشعارات | Notifications
- إشعارات الويب (Web Notifications)
- إشعارات الدفع | Push Notifications
- تتبع قراءة الإشعارات (Read Status Tracking)

#### 7. السجلات والتقارير | Logs & Reports
- سجل الأنشطة (Activity Log)
- سجل الجلسات (Session Log)
- سجل المواقع الجغرافية (Location History)
- إحصائيات الفخاخ (Trap Statistics)

## 🗄️ هيكل قاعدة البيانات | Database Structure

### الجداول الرئيسية | Main Tables

1. **users** - الموظفين والمستخدمين
2. **roles** - الأدوار والصلاحيات
3. **branches** - الفروع
4. **attendance** - سجلات الحضور
5. **employee_schedules** - جداول العمل
6. **leaves** - الإجازات
7. **trap_configurations** - إعدادات الفخاخ السلوكية
8. **trap_logs** - سجلات الفخاخ
9. **psychological_profiles** - الملفات النفسية للموظفين
10. **integrity_logs** - سجلات النزاهة
11. **integrity_reports** - تقارير المخالفات
12. **notifications** - الإشعارات
13. **activity_log** - سجل الأنشطة
14. **user_sessions** - الجلسات
15. **user_location_history** - سجل المواقع

### الإجراءات المخزنة | Stored Procedures

- **sp_update_psychological_profile** - تحديث الملف النفسي للموظف بناءً على سجلات الفخاخ

## 🚀 الاستخدام | Usage

### استيراد قاعدة البيانات | Import Database

```bash
# MySQL
mysql -u username -p database_name < u850419603_101.sql

# MariaDB
mariadb -u username -p database_name < u850419603_101.sql
```

### المتطلبات | Requirements

- MySQL 5.7+ أو MariaDB 10.3+
- دعم UTF-8MB4 (utf8mb4_unicode_520_ci)
- دعم JSON الأصلي (متوفر في MySQL 5.7+ و MariaDB 10.2+)

## ⚙️ الإعدادات | Configuration

### إعدادات الحضور | Attendance Modes
- `unrestricted` - غير مقيد
- `time_only` - بالوقت فقط
- `location_only` - بالموقع فقط
- `time_and_location` - بالوقت والموقع

### أنواع الفخاخ | Trap Types
- `data_leak` - تسريب البيانات | Data Leak
- `gps_debug` - وضع تصحيح GPS (Location Debug Mode)
- `admin_override` - زر المدير الوهمي | Ghost Admin Button
- `confidential_bait` - طُعم سري | Confidential Bait
- `recruitment` - اختبار التجنيد | Recruitment Test

## 🔒 الأمان | Security

- تشفير كلمات المرور (Password Hashing)
- تتبع عناوين IP
- تسجيل كافة الأنشطة
- نظام الكشف عن السلوك المشبوه
- التحليل النفسي للموظفين

## 📊 الإحصائيات | Statistics

النظام يوفر views للإحصائيات:
- `v_psychological_profiles` - إحصائيات الملفات النفسية
- `v_trap_statistics` - إحصائيات الفخاخ

## 📝 البيانات الأولية | Initial Data

يتضمن المستودع بيانات أولية لـ:
- 5 فروع (Branches)
- 5 أنواع فخاخ سلوكية (Trap Configurations)
- أدوار أساسية (Basic Roles)
- إعدادات النظام (System Settings)

### الفروع الافتراضية | Default Branches
1. صرح الإتقان الرئيسي (SARH01)
2. صرح الإتقان كورنر (SARH02)
3. صرح الإتقان 2 (SARH03)
4. فضاء المحركات 1 (FADA01)
5. فضاء المحركات 2 (FADA02)

## 🌍 المنطقة الزمنية | Timezone

النظام يستخدم بشكل افتراضي المنطقة الزمنية:
- `Asia/Riyadh` (توقيت الرياض)

## 📞 الاتصال | Contact

البريد الإلكتروني الافتراضي للنظام:
- sarh1@sarh.io to sarh5@sarh.io
- fada1@sarh.io, fada2@sarh.io

---

## 📄 الترخيص | License

الترخيص: لم يتم تحديده بعد | License: Not Yet Specified

يُرجى الاتصال بمالك المستودع لمزيد من المعلومات حول الترخيص والاستخدام.

Please contact the repository owner for more information about licensing and usage.
