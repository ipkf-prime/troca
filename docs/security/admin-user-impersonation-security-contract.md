# قرارداد امنیتی ورود مدیر به حساب کاربر

## 1. وضعیت سند

- قابلیت: ورود مدیر به محیط کاربر / Admin User Impersonation
- دامنه: Core / Admin User Management
- وضعیت: Security & Audit Contract V1
- مدل نسخه اول: Read-only impersonation
- رمز، OTP و MFA کاربر هدف برای شروع Impersonation استفاده نمی‌شود.
- این قابلیت جایگزین Login عادی کاربر نیست.

---

## 2. هدف

قابلیت Impersonation برای پشتیبانی، عیب‌یابی و مشاهده تجربه واقعی کاربر طراحی می‌شود.

مدیر مجاز باید بتواند بدون دانستن یا تغییر رمز عبور کاربر، محیط سامانه را دقیقاً با دسترسی Effective همان کاربر مشاهده کند؛ در عین حال هویت مدیر واقعی باید در تمام طول Session قابل تشخیص، قابل ممیزی و قابل بازیابی باشد.

اصل پایه:

> Effective Identity ممکن است تغییر کند، اما Actor Identity هرگز نباید از بین برود.

---

## 3. تعاریف هویتی

### Actor User

کاربر مدیری که Impersonation را آغاز کرده است.

Actor مبنای موارد زیر است:

- مجوز شروع Impersonation
- ثبت Audit
- تشخیص مسئول واقعی عملیات مدیریتی
- بازگشت به حساب اصلی مدیر

### Effective User

کاربری که مدیر محیط سامانه را از دید او مشاهده می‌کند.

در حالت عادی:

- Actor User = Effective User

در حالت Impersonation:

- Actor User = مدیر
- Effective User = کاربر هدف

هیچ Audit امنیتی مجاز نیست این دو مفهوم را با یکدیگر ادغام کند.

---

## 4. Permission

Permission مستقل و پایدار این قابلیت:

`users.impersonate`

مشخصات الزامی:

- module: core
- resource: users
- action: impersonate
- is_sensitive: 1
- is_active: 1
- قابل مدیریت از Access Control موجود
- قابل تخصیص از مسیر Role Permission موجود
- عدم وابستگی به عنوان فارسی نقش
- عدم استفاده از role-name hardcode در Runtime

Default Grant:

- فقط نقش محافظت‌شده سطح بالای سامانه که در Seed/Migration صراحتاً تعیین می‌شود.
- هیچ نقش مدیریتی دیگر نباید به صورت ضمنی Permission را دریافت کند.

وجود Permissionهای دیگر مانند:

- permissions.assign
- access.roles.manage
- users.update

به تنهایی مجوز Impersonation محسوب نمی‌شود.

---

## 5. شروط شروع Impersonation

Start فقط زمانی مجاز است که تمام شروط زیر برقرار باشند:

1. Actor دارای `users.impersonate` باشد.
2. درخواست POST و دارای CSRF معتبر باشد.
3. Actor در حال Impersonation دیگری نباشد.
4. Target وجود داشته باشد.
5. Target حذف نرم نشده باشد.
6. Target فعال و قابل Authentication باشد.
7. Target با Actor یکسان نباشد.
8. Target از نظر Role Priority پایین‌تر از Actor باشد.
9. Target دارای Permission مؤثر `users.impersonate` نباشد، مگر اینکه Policy سطح بالاتر بعداً صراحتاً اجازه دهد.
10. حساب‌های Protected/System از Impersonation مستثنا باشند.
11. هر ابهام در Eligibility باید Fail Closed شود.

Nested Impersonation مطلقاً ممنوع است.

---

## 6. Session Contract

تمام عملیات Session باید فقط از Service اختصاصی Impersonation انجام شود.

تغییر مستقیم Session در View یا Route ممنوع است.

Namespace منطقی Session:

`auth_impersonation`

حداقل داده‌های مجاز:

- actor_user_id
- effective_user_id
- started_at
- expires_at
- nonce
- actor_auth_snapshot
- return_path

actor_auth_snapshot فقط می‌تواند شامل State لازم برای بازگشت امن باشد، مانند:

- auth_user_id
- auth_password_fingerprint
- auth_login_at
- auth_mfa_verified
- active_role_assignment_id

موارد ممنوع در Snapshot:

- Password
- Password Hash خام
- OTP
- MFA Secret
- Recovery Code
- Access Token
- Session ID خام
- API Token
- Secret

Session Identifier باید هنگام Start و Stop چرخش پیدا کند.

---

## 7. مدت اعتبار

TTL پیش‌فرض:

30 دقیقه

Config Key:

`IMPERSONATION_TTL_MINUTES`

پس از Expire:

- Effective Session دیگر معتبر نیست.
- Impersonation context پاک می‌شود.
- تلاش برای Restore فقط پس از اعتبارسنجی Actor Snapshot انجام می‌شود.
- اگر Actor دیگر واجد شرایط Session نباشد، Full Login اجباری است.

---

## 8. Password Fingerprint

سامانه فعلی از `auth_password_fingerprint` برای Invalidating Session پس از تغییر Credential استفاده می‌کند.

Impersonation حق دور زدن این Contract را ندارد.

در Start:

- Target باید Fingerprint معتبر خودش را به عنوان Effective Session دریافت کند.

در Stop:

- Fingerprint Actor باید مجدداً با وضعیت فعلی Actor اعتبارسنجی شود.

اگر Credential Actor در مدت Impersonation تغییر کرده باشد:

- Restore مستقیم ممنوع است.
- Session باید خاتمه یابد.
- Full Login لازم است.

اگر Credential Target در مدت Impersonation تغییر کند:

- Effective Session باید Fail Closed شود.

---

## 9. Active Role Assignment

Effective Access باید متعلق به Target باشد.

Role Assignment مدیر نباید هنگام Impersonation به Target نشت کند.

در Start:

- active_role_assignment_id مدیر Snapshot می‌شود.
- Preferred/Valid assignment کاربر هدف انتخاب می‌شود.

در Stop:

- Assignment مدیر فقط پس از اعتبارسنجی مجدد Restore می‌شود.

---

## 10. مدل نسخه اول: Read-only

نسخه اول Impersonation برای مشاهده محیط واقعی کاربر است.

در این نسخه، تمام درخواست‌های Mutating هنگام Impersonation باید Fail Closed شوند، به استثنای:

- پایان Impersonation
- Logout نهایی
- عملیات فنی صراحتاً Allow-list شده برای حفظ Session که هیچ Business Mutation ایجاد نمی‌کنند

حداقل عملیات ممنوع:

- تغییر Password
- تنظیم یا حذف MFA
- Recovery Code
- تغییر Email/Mobile هویتی
- تغییر اطلاعات امنیتی
- مدیریت User
- ایجاد User
- دعوت User
- فعال/غیرفعال کردن User
- Role Assignment
- Permission Assignment
- Permission Override
- Access Control
- تغییر Organizational Assignment
- تغییر Project Membership
- ایجاد/ویرایش/حذف Business Record
- ارسال پیام
- ثبت Ticket Reply
- Take Ownership
- Transfer
- Close/Reopen
- تغییر Work Item
- Approval
- هر POST/PUT/PATCH/DELETE ناشناخته

در نسخه‌های بعدی فقط عملیات مشخصی می‌توانند Write-enabled شوند که Actor/Effective Audit آنها به‌طور مستقل اثبات شده باشد.

---

## 11. Guard مرکزی

Block کردن Mutation نباید به صورت پراکنده و دستی در هر View پیاده‌سازی شود.

یک Guard مرکزی باید بتواند تشخیص دهد:

- آیا Session در حالت Impersonation است؟
- Actor کیست؟
- Effective User کیست؟
- آیا Session منقضی شده؟
- آیا Request از نوع Mutation است؟
- آیا Route در Allow-list محدود قرار دارد؟

Default Policy:

`DENY`

---

## 12. Audit دو لایه

### 12.1 Dedicated Impersonation Audit

Lifecycle Impersonation باید در Store اختصاصی ثبت شود.

نام پیشنهادی:

`auth_impersonation_events`

حداقل فیلدهای منطقی:

- id
- public_reference
- actor_user_id
- effective_user_id
- event_code
- reason_code
- request_id
- correlation_id
- ip_address
- user_agent
- metadata_json
- occurred_at

Event Codeهای اولیه:

- impersonation_started
- impersonation_ended
- impersonation_expired
- impersonation_start_denied
- impersonation_restore_denied
- impersonation_mutation_blocked

هیچ Secret یا Session ID خام نباید در Audit ذخیره شود.

### 12.2 Platform Audit

رویدادهای اصلی Impersonation باید علاوه بر Store اختصاصی، در Audit عمومی پلتفرم نیز ثبت شوند.

Platform Audit باید بتواند حداقل موارد زیر را تشخیص دهد:

- Actor
- Effective User
- نوع رویداد
- Target
- Request/Correlation
- زمان

---

## 13. Actor/Effective Audit Propagation

در هر عملیاتی که در آینده هنگام Impersonation اجازه Write پیدا می‌کند:

- actor_user_id = مدیر واقعی
- effective_user_id = کاربر هدف

ثبت Target به عنوان Actor واقعی ممنوع است.

تا زمانی که یک Module این قرارداد را پیاده نکرده باشد:

- Write آن Module در Impersonation ممنوع می‌ماند.

---

## 14. Login History

شروع Impersonation نباید به عنوان Login واقعی کاربر هدف ثبت شود.

موارد ممنوع:

- updateLastLogin برای Target
- Internal Login Notification برای Target
- Auth Login History به شکل Login واقعی Target

Impersonation یک Authentication Event مستقل است، نه Password/SSO Login کاربر هدف.

---

## 15. Logout

Logout هنگام Impersonation Terminal است.

Logout نباید مدیر را به Session قبلی برگرداند.

رفتار:

- Impersonation context پاک شود.
- Actor snapshot پاک شود.
- Authentication Session به طور کامل Destroy شود.

تنها Action صریح «بازگشت به حساب مدیر» مجاز است Actor Session را Restore کند.

---

## 16. Stop / Return to Admin

پایان Impersonation باید:

1. POST باشد.
2. CSRF معتبر داشته باشد.
3. Nonce معتبر داشته باشد.
4. Actor snapshot معتبر باشد.
5. Actor هنوز Active باشد.
6. Credential Fingerprint Actor هنوز معتبر باشد.
7. Session ID regenerate شود.
8. Effective Target پاک شود.
9. Actor به عنوان Current Auth User Restore شود.
10. Active Role Assignment مجدداً اعتبارسنجی شود.
11. Audit پایان ثبت شود.

---

## 17. رابط کاربری

تمام متن‌های UI باید Dynamic باشند.

Hardcode متن نمایشی در View/Route ممنوع است.

حداقل Content Keyهای مورد نیاز باید در Migration/Ui Content Catalog ثبت شوند:

- core.users.impersonation.action
- core.users.impersonation.confirm.title
- core.users.impersonation.confirm.body
- core.users.impersonation.banner
- core.users.impersonation.return
- core.users.impersonation.readonly
- core.users.impersonation.denied
- core.users.impersonation.expired

Banner در تمام صفحات Admin هنگام Impersonation باید دائماً قابل مشاهده باشد.

Banner باید حداقل نشان دهد:

- کاربر هدف
- حالت مشاهده به جای کاربر
- Read-only بودن
- Action بازگشت به حساب مدیر

---

## 18. User Management UI

Action «ورود به حساب کاربر» فقط زمانی نمایش داده شود که:

- Actor Permission لازم را دارد.
- Target Eligible است.
- Actor در Impersonation نیست.

نمایش UI به تنهایی Authorization محسوب نمی‌شود.

Route باید تمام شروط را مجدداً Server-side بررسی کند.

---

## 19. عدم استفاده از Password/MFA کاربر هدف

Impersonation نباید:

- Password کاربر را بخواند.
- Password را Reset کند.
- OTP تولید کند.
- MFA Challenge کاربر را دور بزند به عنوان Login واقعی.
- Login Token کاربر ایجاد کند.

این قابلیت یک Privileged Administrative Session Mode است.

---

## 20. SSO و Moduleها

Module SSO باید Effective User را به Module منتقل کند، اما Context مربوط به Actor نیز باید برای Enforcement/Audit قابل بازیابی باشد.

تا قبل از تکمیل Actor propagation در Module:

- Module در حالت Impersonation Read-only باقی می‌ماند.

Impersonation نباید باعث ایجاد Login History جعلی برای Target در Module یا Core شود.

---

## 21. Fail-Closed Rules

موارد زیر باید Session را End یا Action را Deny کنند:

- Context ناقص
- Actor حذف/غیرفعال
- Target حذف/غیرفعال
- Permission Actor حذف‌شده
- TTL منقضی
- Nonce نامعتبر
- Actor Credential تغییرکرده
- Target Credential تغییرکرده
- Role Assignment نامعتبر
- Nested Impersonation
- Target Priority نامجاز
- Sensitive Route ناشناخته
- Mutation Route ناشناخته

---

## 22. Logging Privacy

Audit مجاز است موارد زیر را ذخیره کند:

- شناسه داخلی Actor
- شناسه داخلی Effective User
- IP
- User-Agent محدودشده
- Request ID
- Correlation ID
- زمان
- Reason Code
- Route Identifier

Audit نباید ذخیره کند:

- Password
- Password Hash
- OTP
- MFA Secret
- Token
- Cookie
- Raw Session Identifier
- Secret File Content

---

## 23. Rollout Stages

### S3 — Foundation

- Permission
- Migration
- Repository
- Impersonation Context Service
- Dedicated Audit Store

### S4 — Session Lifecycle

- Start
- Stop
- Expiry
- Logout interaction
- Password fingerprint interaction
- Active assignment restore

### S5 — Central Read-only Guard

- Mutation blocking
- Allow-list
- Fail-closed tests

### S6 — Dynamic UI

- User Management action
- Confirmation
- Global banner
- Return action
- Dynamic UI content

### S7 — SSO / Module Propagation

- Core
- Ticketing
- Work
- Automation
- Actor/Effective context

### S8 — Browser E2E + Regression

- Start
- Target view
- blocked mutation
- module navigation
- stop
- logout
- expiry
- nested denial
- inactive target denial
- higher-role denial

---

## 24. Production Acceptance Gates

Production فقط زمانی مجاز است که همه موارد زیر PASS باشند:

- Dedicated permission PASS
- sensitive permission PASS
- only authorized actor PASS
- target eligibility PASS
- role priority guard PASS
- nested impersonation denial PASS
- session regeneration PASS
- actor snapshot PASS
- target password fingerprint PASS
- actor restore fingerprint PASS
- TTL expiry PASS
- dedicated audit PASS
- platform audit PASS
- mutation block PASS
- logout terminal PASS
- dynamic UI PASS
- no hardcoded UI copy PASS
- Core SSO PASS
- Ticketing SSO PASS
- Work SSO PASS
- Automation SSO PASS
- existing Auth regression PASS
- existing Access regression PASS
- existing Password/session invalidation regression PASS

---

## 25. Non-Goals V1

نسخه اول شامل موارد زیر نیست:

- Impersonation تو در تو
- Impersonation حساب هم‌سطح یا بالاتر
- Impersonation حساب Protected/System
- Impersonation بدون Audit
- Write unrestricted
- تغییر Security Setting کاربر
- تغییر Access کاربر
- مخفی کردن Banner
- Session دائمی یا بدون Expiry

---

## 26. اصل نهایی

Impersonation یک قابلیت پشتیبانی است، نه راه دور زدن Authentication.

در هر لحظه باید بتوان پاسخ داد:

- چه مدیری وارد این حالت شد؟
- به جای کدام کاربر؟
- چه زمانی؟
- از کدام Request؟
- با چه مجوزی؟
- آیا Session هنوز معتبر است؟
- چه زمانی و چرا خاتمه یافت؟

اگر پاسخ هر یک از این پرسش‌ها قابل اثبات نباشد، Impersonation باید Fail Closed باشد.
