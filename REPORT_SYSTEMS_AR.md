# تقرير تفصيلي عن الأنظمة والميزات وواجهات الـ API

> هذا التقرير مبني على قراءة الشيفرة داخل مجلد `app/` وواجهات `app/api/`  
> لا يحتوي على بيانات تشغيلية حقيقية أو كلمات مرور أو سجلات مستخدمين.

## نطاق التقرير
- **النطاق التقني**: PHP + MySQL + واجهات REST داخل `app/api`
- **الواجهات**: صفحات PHP (HTML) + JavaScript (AJAX + Fetch)
- **المصادقة**: جلسات + CSRF عبر `X-CSRF-Token`

---

## ملخص العدّادات (حسب النظام)
- **نظام الحضور والانصراف**: 13 ميزة (4 واجهات API رئيسية + صفحات إدارة)
- **الإدارة (الفروع/الموظفون/النزاهة)**: 9 ميزات (واجهة API واحدة متعددة الإجراءات)
- **النزاهة والفخاخ والملفات النفسية**: 10 ميزات (واجهة API + صفحات إدارة)
- **الإشعارات**: 8 ميزات (5 واجهات API)
- **المحادثات**: 22 ميزة (واجهتا API: غرف + رسائل)
- **قاعدة البيانات الشاملة (God Mode)**: 6 ميزات (واجهة API واحدة)
- **التقارير والتحليلات**: 3 ميزات (صفحات HTML)
- **أدوات المطور**: 6 ميزات (صفحات HTML)

---

# 1) نظام الحضور والانصراف (Attendance)

### ✅ الميزة 1.1 — تسجيل الحضور (Check‑in)
- **واجهة**: `POST /app/api/attendance/action.php`
- **الطلب**:
  - **Headers**: `X-CSRF-Token: <token>`
  - **Body JSON**:
    - `action: "checkin"` (إجباري)
    - `latitude`, `longitude`, `accuracy` (إجباري)
    - `attendance_id` (غير مطلوب للحضور)
    - اختياري: `auto_checkin`, `detected_branch_id`
- **المعالجة الخلفية** (`api/attendance/action.php`):
  - مصادقة عبر الجلسة + CSRF.
  - قراءة جدول الموظف من `employee_schedules` أو القيم الافتراضية من `system_settings`.
  - التحقق من أيام العمل، نافذة الوقت، ونطاق الـ Geofence (Haversine).
  - إدراج سجل في `attendance`.
  - خصم نقاط التأخير من `users.current_points`.
  - تحديث حالة المستخدم `users.is_online` و `last_activity_at`.
  - تسجيل نشاط في `activity_log`.
- **الاستجابة** (نجاح):
  - `success: true`, `action: "checkin"`, `attendance_id`,
  - `check_in_time`, `late_minutes`, `penalty_points`, `status`, `branch_name`,
  - `details` (HTML snippets لواجهة المستخدم).
- **أخطاء شائعة**:
  - `401 unauthorized`, `403 csrf_invalid`, `400 missing_location`,
  - `400 out_of_geofence`, `400 not_working_day`, `400 checkin_too_early`.

### ✅ الميزة 1.2 — تسجيل الانصراف (Check‑out)
- **واجهة**: `POST /app/api/attendance/action.php`
- **الطلب**:
  - `action: "checkout"` + الموقع + `attendance_id` (اختياري؛ يستخدم آخر سجل مفتوح).
- **المعالجة الخلفية**:
  - جلب سجل حضور اليوم غير المُغلق.
  - حساب ساعات العمل، الخروج المبكر، العمل الإضافي.
  - تحديث `attendance` مع `check_out_time` والنتائج.
  - تعديل نقاط المستخدم (خصم/مكافأة).
- **الاستجابة**:
  - `success: true`, `action: "checkout"`, `work_minutes`,
  - `early_leave_minutes`, `overtime_minutes`, `net_points`, `details`.
- **أخطاء شائعة**:
  - `لا يوجد سجل حضور للانصراف منه`
  - `checkout_too_early` أو `checkout_closed`.

### ✅ الميزة 1.3 — جلب جدول الدوام + حالة الحضور
- **واجهة**: `GET /app/api/attendance/schedule.php?action=get`
- **الطلب**: Query فقط.
- **المعالجة الخلفية**:
  - `getEmployeeSchedule()` من `employee_schedules` أو `system_settings`.
  - `calculateAttendanceStatus()` من سجل اليوم في `attendance`.
- **الاستجابة**:
  - `schedule` (تفاصيل الدوام) + `status` (وقت/حالة اليوم).

### ✅ الميزة 1.4 — التحقق من إمكانية تسجيل الحضور
- **واجهة**:  
  `GET /app/api/attendance/schedule.php?action=can_checkin&lat=<>&lng=<>`
- **الاستجابة**:
  - `can_checkin: bool`, `reasons[]`, `warnings[]`
  - اختياري: `distance`, `required_radius`.

### ✅ الميزة 1.5 — التحقق من إمكانية تسجيل الانصراف
- **واجهة**:  
  `GET /app/api/attendance/schedule.php?action=can_checkout`
- **الاستجابة**:
  - `can_checkout: bool`, `reasons[]`, `warnings[]`

### ✅ الميزة 1.6 — قائمة المتصدرين اليومية
- **واجهة**: `GET /app/api/attendance/leaderboard.php`
- **الاستجابة**:
  - `leaders[]` (أول حضور لكل فرع)
  - `latecomers[]` (آخر حضور متأخر لكل فرع)
  - `timestamp`
- **الجداول**: `attendance`, `users`, `branches`.

### ✅ الميزة 1.7 — الرادار الحي (Heartbeat)
- **واجهة**: `POST /app/api/heartbeat.php`
- **الطلب**:
  - `latitude`, `longitude`, `accuracy`, `last_notification_id`
  - أو `offline: true` لإرسال إشارة إغلاق.
- **المعالجة الخلفية**:
  - تحديث موقع المستخدم `users.last_latitude/longitude`.
  - استرجاع الزملاء حسب `map_visibility_mode`.
  - استرجاع إشعارات جديدة حسب نطاقات (global/branch/role/user).
  - استرجاع الفروع الوهمية (Ghost Branches).
- **الاستجابة**:
  - `colleagues[]`, `notifications[]`, `ghost_branches[]`,
  - `server_time`, `visibility_mode`, `live_mode`.

### ✅ الميزة 1.8 — تتبع الزملاء
> نفس واجهة الـ Heartbeat، لكن المخرجات تركز على `colleagues[]`  
تتضمن: الاسم، الدور، موقع GPS، مسافة عن الفرع، الحالة.

### ✅ الميزة 1.9 — إشعارات مباشرة داخل الرادار
> عبر `heartbeat.php` تُعاد إشعارات جديدة ويجري عرضها فورياً داخل الرادار.

### ✅ الميزة 1.10 — الفروع الوهمية (Trap)
- تظهر ضمن `ghost_branches[]` في `heartbeat`.
- عند محاولة تسجيل حضور عليها:
  - **واجهة**: `POST /app/api/admin/command_action.php`
  - **Body**: `{ action: "log_ghost_probe", branch_id, latitude, longitude }`
- **الجداول**: `integrity_logs`, `branches`.

### ✅ الميزة 1.11 — إعدادات الحضور العامة (لوحة الإدارة)
- **واجهة**: `POST /app/admin/attendance-settings.php`
- **الطلب**: Form Data (أوقات الدوام، خصومات، مكافآت…).
- **المعالجة**: تحديث `system_settings` (group = attendance).
- **الاستجابة**: HTML Redirect + Flash Message.

### ✅ الميزة 1.12 — جداول دوام الموظفين
- **واجهة إدارة**: `admin/employee-schedules.php`
- **الطلب (تحديث)**: `POST` إلى نفس الصفحة.
- **الطلب (قراءة AJAX)**:  
  `GET admin/employee-schedules.php?get_schedule=1&user_id=<>`  
  **الاستجابة**: JSON بالجدول أو `{new: true}`.

### ✅ الميزة 1.13 — صفحات واجهة الحضور
> صفحات UI فقط (HTML):  
`attendance.php`, `checkin.php`, `quick-attendance.php`, `team-attendance.php`  
تعتمد على API المذكورة أعلاه.

---

# 2) نظام الإدارة (الفروع/الموظفون/سجل النزاهة)

> جميع ميزات الإدارة تعتمد على:  
`POST /app/api/admin/command_action.php`

### ✅ الميزة 2.1 — إنشاء فرع
- **Body**: `{action:"create_branch", name, code, address, latitude, longitude, geofence_radius, is_active, is_ghost_branch}`
- **الجداول**: `branches`, `integrity_logs`.
- **الاستجابة**: `{success:true, id:<branch_id>}`

### ✅ الميزة 2.2 — تحديث فرع
- **Body**: `{action:"update_branch", id, ...}`
- **الاستجابة**: `{success:true}`

### ✅ الميزة 2.3 — حذف فرع
- **Body**: `{action:"delete_branch", id}`
- **تحقق**: يمنع الحذف إذا كان هناك موظفون مرتبطون.
- **الاستجابة**: `{success:true}`

### ✅ الميزة 2.4 — إنشاء موظف
- **Body**: `{action:"create_employee", full_name, emp_code, username, email, branch_id, role_id, is_active, password}`
- **التحقق**: تفرد username/email/emp_code.
- **الاستجابة**: `{success:true, id:<user_id>}`

### ✅ الميزة 2.5 — تحديث موظف
- **Body**: `{action:"update_employee", id, ...}`
- **الاستجابة**: `{success:true}`

### ✅ الميزة 2.6 — تفعيل/تعطيل موظف
- **Body**: `{action:"toggle_employee", id, is_active}`
- **الاستجابة**: `{success:true}`

### ✅ الميزة 2.7 — إعادة تعيين كلمة مرور
- **Body**: `{action:"reset_password", id}`
- **الاستجابة**: `{success:true, new_password: "...."}`

### ✅ الميزة 2.8 — مراجعة سجل نزاهة
- **Body**: `{action:"mark_reviewed", id}`
- **يتطلب صلاحية**: role_level >= 8
- **الاستجابة**: `{success:true}`

### ✅ الميزة 2.9 — تسجيل محاولة فرع وهمي
> تُستخدم من الرادار الحي كاختبار نزاهة.  
**Body**: `{action:"log_ghost_probe", branch_id, latitude, longitude}`

---

# 3) نظام النزاهة والفخاخ والملفات النفسية

### ✅ الميزة 3.1 — فحص الفخاخ العشوائية
- **واجهة**: `POST /app/api/trap_handler.php`
- **Body**: `{action:"check_for_traps", page, gps_errors, session_minutes}`
- **الاستجابة**:
  - `has_trap: true/false`
  - عند `true` يعود كائن `trap` جاهز للعرض.

### ✅ الميزة 3.2 — تسجيل تفاعل المستخدم مع الفخ
- **واجهة**: `POST /app/api/trap_handler.php`
- **Body**: `{action:"log_interaction", trap_type, trap_id, user_action, response_time_ms, context}`
- **المعالجة**:
  - كتابة في `trap_logs`.
  - استدعاء الإجراء `sp_update_psychological_profile`.
- **الاستجابة**:
  - `success: true`, `response` (toast/modal).

### ✅ الميزة 3.3 — جلب ملف نفسي لموظف
- **واجهة**: `POST /app/api/trap_handler.php`
- **Body**: `{action:"get_profile", user_id?}`
- **الاستجابة**:
  - `profile` من `v_psychological_profiles`
  - `logs` من `trap_logs`

### ✅ الميزة 3.4 — جلب جميع الملفات النفسية
- **واجهة**: `POST /app/api/trap_handler.php`
- **Body**: `{action:"get_all_profiles"}`
- **الاستجابة**: `profiles[]`, `statistics`

### ✅ الميزة 3.5 — جلب إعدادات الفخاخ
- **واجهة**: `POST /app/api/trap_handler.php`
- **Body**: `{action:"get_configurations"}`
- **الاستجابة**: `configurations[]` من `trap_configurations`

### ✅ الميزة 3.6 — تحديث إعداد فخ
- **واجهة**: `POST /app/api/trap_handler.php`
- **Body**: `{action:"update_configuration", config_id, is_active, trigger_chance, cooldown_minutes}`
- **الاستجابة**: `{success:true}`

### ✅ الميزة 3.7 — فرض فخ (اختبار)
- **واجهة**: `POST /app/api/trap_handler.php`
- **Body**: `{action:"force_trap", trap_type, target_user_id?}`
- **الاستجابة**: `trap` جاهز للعرض

### ✅ الميزة 3.8 — لوحة إدارة الفخاخ
> صفحة HTML: `admin/traps.php`  
تحديث مباشر لـ `trap_configurations` عبر POST داخل الصفحة.

### ✅ الميزة 3.9 — لوحة الملفات النفسية
> صفحة HTML: `admin/profiles.php`  
تعرض `v_psychological_profiles` وملخصات المخاطر.

### ✅ الميزة 3.10 — أنواع الفخاخ المعرفة
من `includes/traps.php`:
1) `data_leak` تسريب بيانات  
2) `gps_debug` وضع تصحيح GPS  
3) `admin_override` زر مدير شبح  
4) `confidential_bait` إشعار سري  
5) `recruitment` عرض تجنيد مشبوه

---

# 4) نظام الإشعارات

### ✅ الميزة 4.1 — عد الإشعارات غير المقروءة
- **واجهة**: `GET /app/api/notifications/count.php`
- **الاستجابة**: `{success:true, count:<int>}`

### ✅ الميزة 4.2 — قائمة الإشعارات
- **واجهة**: `GET /app/api/notifications/list.php`
- **Query**: `limit`, `offset`, `type`, `unread_only`, `last_fetch`
- **الاستجابة**:
  - `notifications[]`, `unread_count`, `new_notifications[]`, `has_more`

### ✅ الميزة 4.3 — تعيين إشعار كمقروء
- **واجهة**: `POST /app/api/notifications/mark-read.php`
- **Body**: `{id: <notification_id>}`
- **Headers**: `X-CSRF-Token`
- **الاستجابة**: `{success:true, unread_count}`

### ✅ الميزة 4.4 — تعيين الكل كمقروء
- **واجهة**: نفس `mark-read.php`
- **Body**: `{all: true}`

### ✅ الميزة 4.5 — حفظ اشتراك Push
- **واجهة**: `POST /app/api/notifications/subscribe.php`
- **Body**: `{subscription:{endpoint, keys:{p256dh,auth}}, device_type}`
- **الاستجابة**: `{success:true, message}`
- **الجداول**: `push_subscriptions`

### ✅ الميزة 4.6 — إرسال إشعارات Push (إدارة)
- **واجهة**: `POST /app/api/notifications/send.php`
- **Body**:
  - `title`, `body`, `url`
  - `send_to_all` أو `user_ids[]`
  - اختياري: `branch_id`, `image`
- **الاستجابة**: `{success:true, data:{sent,failed,total}}`

### ✅ الميزة 4.7 — مركز الإشعارات في الواجهة
> من `assets/js/notifications.js`  
يعتمد على list + mark-read، ويقدم:
- Toasts  
- إشعارات سطح المكتب  
- أصوات إشعار  

### ✅ الميزة 4.8 — إشعارات PWA
> من `assets/js/pwa.js`  
يرسل الاشتراك إلى `notifications/subscribe.php`.

---

# 5) نظام المحادثات (Chat)

## A) غرف المحادثة — `api/chat/rooms.php`

### ✅ الميزة 5.1 — قائمة غرفي
 - **واجهة**: `GET /app/api/chat/rooms.php?action=list`
 - **الاستجابة**: `rooms[]` (مع آخر رسالة + عدد غير المقروءة)

### ✅ الميزة 5.2 — تفاصيل غرفة
 - **واجهة**: `GET /app/api/chat/rooms.php?action=details&room_id=<>`
 - **الاستجابة**: `room` + `members[]` + `pinned_messages[]`

### ✅ الميزة 5.3 — البحث في الغرف
 - **واجهة**: `GET /app/api/chat/rooms.php?action=search&q=...`
 - **الاستجابة**: `rooms[]`

### ✅ الميزة 5.4 — الغرف المتاحة للانضمام
 - **واجهة**: `GET /app/api/chat/rooms.php?action=available`
 - **الاستجابة**: `rooms[]`

### ✅ الميزة 5.5 — إنشاء غرفة
 - **واجهة**: `POST /app/api/chat/rooms.php?action=create`
 - **Body**: `{name, description, type, members[]}`
 - **الاستجابة**: `{success:true, room_id}`

### ✅ الميزة 5.6 — الانضمام لغرفة
 - **واجهة**: `POST /app/api/chat/rooms.php?action=join`
 - **Body**: `{room_id}`

### ✅ الميزة 5.7 — مغادرة غرفة
 - **واجهة**: `POST /app/api/chat/rooms.php?action=leave`
 - **Body**: `{room_id}`

### ✅ الميزة 5.8 — إضافة عضو
 - **واجهة**: `POST /app/api/chat/rooms.php?action=add_member`
 - **Body**: `{room_id, user_id}`

### ✅ الميزة 5.9 — تحديث بيانات غرفة
 - **واجهة**: `PUT /app/api/chat/rooms.php?action=update`
 - **Body**: `{room_id, name?, description?}`

### ✅ الميزة 5.10 — إعدادات إشعارات الغرفة
 - **واجهة**: `PUT /app/api/chat/rooms.php?action=settings`
 - **Body**: `{room_id, notifications_enabled}`

### ✅ الميزة 5.11 — تحديث آخر قراءة
 - **واجهة**: `PUT /app/api/chat/rooms.php?action=read`
 - **Body**: `{room_id}`

### ✅ الميزة 5.12 — حذف غرفة
 - **واجهة**: `DELETE /app/api/chat/rooms.php?action=delete&room_id=<>`
 - **الاستجابة**: `{success:true}`

### ✅ الميزة 5.13 — إزالة عضو
 - **واجهة**: `DELETE /app/api/chat/rooms.php?action=remove_member&room_id=<>&user_id=<>`
 - **الاستجابة**: `{success:true}`

## B) رسائل المحادثة — `api/chat/messages.php`

### ✅ الميزة 5.14 — جلب رسائل غرفة
 - **واجهة**: `GET /app/api/chat/messages.php?action=list&room_id=<>`
 - **Query**: `limit`, `before`, `after`
 - **الاستجابة**: `messages[]`, `has_more`

### ✅ الميزة 5.15 — جلب الرسائل الجديدة (Long Poll)
 - **واجهة**: `GET /app/api/chat/messages.php?action=poll&room_id=<>&last_id=<>`
 - **Query**: `timeout`
 - **الاستجابة**: `messages[]`, `typing[]`

### ✅ الميزة 5.16 — البحث داخل الرسائل
 - **واجهة**: `GET /app/api/chat/messages.php?action=search&room_id=<>&q=...`
 - **الاستجابة**: `messages[]`

### ✅ الميزة 5.17 — إرسال رسالة
 - **واجهة**: `POST /app/api/chat/messages.php?action=send`
 - **Body**: `{room_id, content, type, reply_to?, attachments?}`
 - **الاستجابة**: `message` (مع بيانات المرسل)

### ✅ الميزة 5.18 — مؤشر الكتابة
 - **واجهة**: `POST /app/api/chat/messages.php?action=typing`
 - **Body**: `{room_id, typing:true/false}`

### ✅ الميزة 5.19 — تفاعل على رسالة
 - **واجهة**: `POST /app/api/chat/messages.php?action=react`
 - **Body**: `{message_id, emoji}`
 - **الاستجابة**: `reactions`

### ✅ الميزة 5.20 — تثبيت رسالة
 - **واجهة**: `POST /app/api/chat/messages.php?action=pin`
 - **Body**: `{message_id}`
 - **الاستجابة**: `{pinned:true/false}`

### ✅ الميزة 5.21 — تعديل رسالة
 - **واجهة**: `PUT /app/api/chat/messages.php?action=edit`
 - **Body**: `{message_id, content}`

### ✅ الميزة 5.22 — حذف رسالة (حذف ناعم)
 - **واجهة**: `DELETE /app/api/chat/messages.php?action=delete&message_id=<>`

---

# 6) نظام قاعدة البيانات الشاملة (God Mode)

> واجهة واحدة متعددة الإجراءات:  
`POST /app/api/universal_action.php`  
**شروط**: role_level >= 10 + CSRF

### ✅ الميزة 6.1 — تحديث قيمة خلية
- **Body**: `{action:"update", table, pk_column, pk_value, column, value}`
- **الاستجابة**: `{success:true, affected_rows}`

### ✅ الميزة 6.2 — حذف سجل
- **Body**: `{action:"delete", table, pk_column, pk_value}`
- **الاستجابة**: `{success:true, affected_rows}`

### ✅ الميزة 6.3 — إضافة سجل
- **Body**: `{action:"insert", table, data:{...}}`
- **الاستجابة**: `{success:true, id}`

### ✅ الميزة 6.4 — جلب سجل واحد
- **Body**: `{action:"fetch", table, pk_column, pk_value}`
- **الاستجابة**: `{success:true, data}`

### ✅ الميزة 6.5 — وصف هيكل جدول
- **Body**: `{action:"describe", table}`
- **الاستجابة**: `{success:true, columns[]}`

### ✅ الميزة 6.6 — تنفيذ SQL خام
- **Body**: `{action:"raw_query", sql}`
- **قيود**: محظور في الإنتاج + منع DROP/ALTER/CREATE...
- **الاستجابة**: `data[]` أو `affected_rows`

---

# 7) التقارير والتحليلات

### ✅ الميزة 7.1 — التقارير الشهرية
- **واجهة**: `GET /app/reports.php?month=YYYY-MM`
- **المعالجة**: تجميع من جدول `attendance` لمستخدم الجلسة.
- **الاستجابة**: HTML (ملخص حضور + نقاط + سجل).

### ✅ الميزة 7.2 — التحليلات المتقدمة
- **واجهة**: `GET /app/analytics.php`
- **المعالجة**:
  - `AnalyticsEngine::comprehensivePerformanceAnalysis`
  - `SuperAnalytics::ultraAnalysis`
- **الاستجابة**: HTML Dashboard.

### ✅ الميزة 7.3 — سجل النشاطات
- **واجهة**: `GET /app/activity-log.php?page=<>`
- **المعالجة**: قراءة `activity_log` مع ترقيم الصفحات.
- **الاستجابة**: HTML Table.

---

# 8) أدوات المطور (Developer Panel)

### ✅ الميزة 8.1 — إدارة النسخ الاحتياطي
`developer/backup-manager.php`

### ✅ الميزة 8.2 — إدارة الكاش
`developer/cache-manager.php`

### ✅ الميزة 8.3 — إدارة قاعدة البيانات (UI)
`developer/db-manager.php`

### ✅ الميزة 8.4 — مدير الملفات
`developer/file-manager.php`

### ✅ الميزة 8.5 — عرض السجلات
`developer/log-viewer.php`

### ✅ الميزة 8.6 — معلومات PHP
`developer/phpinfo.php`

---

# 9) واجهات المستخدم (بدون API مستقل)

> هذه الصفحات تعمل مباشرة بالـ PHP/HTML، والطلب/الاستجابة تكون HTML.

- **تسجيل الدخول**: `login.php` (POST بيانات الدخول)
- **تسجيل الخروج**: `logout.php` (GET)
- **الملف الشخصي**: `profile.php`
- **تغيير كلمة المرور**: `change-password.php`
- **الإعدادات**: `settings.php`
- **إدارة الموظفين (عرض)**: `employees.php`
- **الإجازات**: `leaves.php`
- **الإشعارات (واجهة)**: `notifications.php`
- **إرسال إشعار (واجهة)**: `send-notification.php`
- **المحادثة (واجهة)**: `chat.php`
- **لوحة إضافية**: `dashboard/arena.php`, `more.php`, `search.php`

---

## ملاحظات ختامية
- جميع واجهات الـ API تتطلب جلسة تسجيل دخول فعّالة.
- أغلب واجهات POST تتطلب CSRF عبر `X-CSRF-Token`.
- الأخطاء تُعاد غالباً بصيغة `{success:false, message|error}` مع كود HTTP مناسب.
