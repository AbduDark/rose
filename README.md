
# دليل Rose Academy API الشامل

## نظرة عامة

Rose Academy هو نظام إدارة تعلم (LMS) يوفر API متكامل لإدارة المستخدمين والكورسات والدروس والاشتراكات والمدفوعات.

## هيكل المشروع

```
├── app/
│   ├── Http/Controllers/Api/     # كونترولرز API
│   ├── Models/                   # نماذج قاعدة البيانات
│   ├── Mail/                     # قوالب البريد الإلكتروني
│   └── Middleware/              # الوسطاء
├── database/
│   ├── migrations/              # ملفات الهجرة
│   └── seeders/                 # ملفات البذر
├── resources/
│   ├── lang/                    # ملفات الترجمة
│   └── views/                   # قوالب العرض
├── routes/
│   ├── api.php                  # routes API
│   └── web.php                  # routes الويب
└── public/                      # الملفات العامة
```

## 🔐 نظام المصادقة (Authentication)

### الملفات المسؤولة:
- **Controller**: `app/Http/Controllers/Api/AuthController.php`
- **Model**: `app/Models/User.php`
- **Middleware**: `app/Http/Middleware/CheckSessionMiddleware.php`

### الـ APIs المتاحة:

#### 1. التسجيل (Register)
```
POST /api/auth/register
```
**المعاملات المطلوبة:**
- `name`: اسم المستخدم
- `email`: البريد الإلكتروني
- `password`: كلمة المرور (يجب أن تحتوي على أحرف كبيرة وصغيرة وأرقام ورموز)
- `password_confirmation`: تأكيد كلمة المرور
- `gender`: الجنس (male/female)

**العملية:**
1. التحقق من صحة البيانات
2. إنشاء المستخدم في قاعدة البيانات
3. **إرسال PIN تلقائياً** إلى البريد الإلكتروني
4. إنشاء token للمصادقة
5. إرجاع `email_verification_required: true`

#### 2. تسجيل الدخول (Login)
```
POST /api/auth/login
```
**المعاملات المطلوبة:**
- `email`: البريد الإلكتروني
- `password`: كلمة المرور

**العملية:**
1. التحقق من البيانات
2. **فحص تأكيد البريد الإلكتروني**
3. إذا لم يكن مؤكداً، إرجاع `email_verification_required: true`
4. التحقق من تسجيل الدخول على أجهزة أخرى
5. إنشاء token جديد

#### 3. تأكيد البريد الإلكتروني (Verify Email)
```
POST /api/auth/verify-email
```
**المعاملات المطلوبة:**
- `email`: البريد الإلكتروني
- `pin`: رمز التحقق (6 أرقام)

#### 4. إعادة إرسال PIN (Resend PIN)
```
POST /api/auth/resend-pin
```
**المعاملات المطلوبة:**
- `email`: البريد الإلكتروني

**ميزات الأمان:**
- Rate Limiting: 3 محاولات كل دقيقة
- انتهاء صلاحية PIN خلال 5 دقائق
- تسجيل جميع العمليات في الـ logs

#### 5. نسيان كلمة المرور (Forgot Password)
```
POST /api/auth/forgot-password
```

#### 6. إعادة تعيين كلمة المرور (Reset Password)
```
POST /api/auth/reset-password
```

#### 7. تغيير كلمة المرور (Change Password)
```
POST /api/auth/change-password
```
**يتطلب:** Token مصادقة

#### 8. تحديث الملف الشخصي (Update Profile)
```
POST /api/auth/update-profile
```
**يتطلب:** Token مصادقة

#### 9. تسجيل الخروج (Logout)
```
POST /api/auth/logout
```
**يتطلب:** Token مصادقة

#### 10. فرض تسجيل الخروج (Force Logout)
```
POST /api/auth/force-logout
```

## 📚 إدارة الكورسات (Courses)

### الملفات المسؤولة:
- **Controller**: `app/Http/Controllers/Api/CourseController.php`
- **Model**: `app/Models/Course.php`
- **Resource**: `app/Http/Resources/CourseResource.php`

### نظام الكورسات الجديد:
- **الكورسات عامة**: يمكن لجميع الطلاب (ذكور وإناث) الاشتراك في أي كورس
- **الدروس مقسمة حسب الجنس**: كل درس يستهدف جنساً محدداً أو كليهما
- **نظام الاشتراك المدفوع**: يجب الاشتراك والدفع لرؤية محتوى الكورس
- **الاشتراك الشهري**: ينتهي الاشتراك كل 30 يوماً ويحتاج تجديد

### الـ APIs المتاحة:

#### 1. عرض جميع الكورسات
```
GET /api/courses
```
**الملف المسؤول:** `CourseController.php` - دالة `index`
**الوصف:** يعرض جميع الكورسات النشطة، مع إخفاء الدروس للمستخدمين غير المشتركين

#### 2. عرض كورس محدد
```
GET /api/courses/{id}
```
**الملف المسؤول:** `CourseController.php` - دالة `show`
**الوصف:** يعرض تفاصيل الكورس، والدروس المجانية فقط للمستخدمين غير المشتركين

#### 3. إنشاء كورس جديد (للأدمن فقط)
```
POST /api/courses
```
**يتطلب:** Admin role
**المعاملات:**
- `title`: عنوان الكورس
- `description`: وصف الكورس
- `price`: سعر الاشتراك الشهري
- `duration_hours`: مدة الكورس بالساعات
- `level`: مستوى الكورس (beginner/intermediate/advanced)
- `language`: لغة الكورس (ar/en)
- `instructor_name`: اسم المدرس

#### 4. تحديث كورس (للأدمن فقط)
```
PUT /api/courses/{id}
```
**يتطلب:** Admin role

#### 5. حذف كورس (للأدمن فقط)
```
DELETE /api/courses/{id}
```
**يتطلب:** Admin role

## 📖 إدارة الدروس (Lessons)

### الملفات المسؤولة:
- **Controller**: `app/Http/Controllers/Api/LessonController.php`
- **Model**: `app/Models/Lesson.php`
- **Resource**: `app/Http/Resources/LessonResource.php`

### نظام الدروس الجديد:
- **تقسيم حسب الجنس**: كل درس له `target_gender` (male/female/both)
- **فلترة تلقائية**: المستخدمون يرون الدروس المناسبة لجنسهم فقط
- **حماية المحتوى**: الدروس المدفوعة محمية للمشتركين فقط

### الـ APIs المتاحة:

#### 1. عرض دروس كورس معين
```
GET /api/courses/{course_id}/lessons
```
**الملف المسؤول:** `LessonController.php` - دالة `index`
**الوصف:** 
- يعرض الدروس المناسبة لجنس المستخدم
- للمشتركين: جميع الدروس
- لغير المشتركين: الدروس المجانية فقط

#### 2. عرض درس محدد
```
GET /api/lessons/{id}
```
**الملف المسؤول:** `LessonController.php` - دالة `show`
**الوصف:** يتطلب اشتراك نشط للدروس المدفوعة

#### 3. إنشاء درس جديد (للأدمن فقط)
```
POST /api/courses/{course_id}/lessons
```
**يتطلب:** Admin role
**المعاملات:**
- `title`: عنوان الدرس
- `description`: وصف الدرس
- `content`: محتوى الدرس
- `video_url`: رابط الفيديو
- `duration_minutes`: مدة الدرس بالدقائق
- `order`: ترتيب الدرس
- `is_free`: هل الدرس مجاني؟
- `target_gender`: الجنس المستهدف (male/female/both)

#### 4. تحديث درس (للأدمن فقط)
```
PUT /api/lessons/{id}
```
**يتطلب:** Admin role

#### 5. حذف درس (للأدمن فقط)
```
DELETE /api/lessons/{id}
```
**يتطلب:** Admin role

## 💰 نظام الاشتراكات (Subscriptions)

### الملفات المسؤولة:
- **Controller**: `app/Http/Controllers/Api/SubscriptionController.php`
- **Model**: `app/Models/Subscription.php`

### نظام الاشتراك الجديد:
- **اشتراك شهري**: كل اشتراك لمدة 30 يوماً
- **موافقة الأدمن**: يتطلب موافقة الأدمن بعد الدفع
- **تجديد تلقائي**: إشعار عند انتهاء الاشتراك

### الـ APIs المتاحة:

#### 1. عرض اشتراكات المستخدم
```
GET /api/my-subscriptions
```
**يتطلب:** Token مصادقة
**الملف المسؤول:** `SubscriptionController.php` - دالة `mySubscriptions`

#### 2. طلب الاشتراك في كورس
```
POST /api/subscribe
```
**يتطلب:** Token مصادقة
**المعاملات المطلوبة:**
- `course_id`: معرف الكورس

**العملية:**
1. التحقق من عدم وجود اشتراك نشط
2. إنشاء طلب اشتراك بحالة "pending"
3. إرسال إشعار للأدمن

#### 3. الموافقة على الاشتراك (للأدمن فقط)
```
POST /api/subscriptions/{id}/approve
```
**يتطلب:** Admin role
**الملف المسؤول:** `SubscriptionController.php` - دالة `approve`

#### 4. رفض الاشتراك (للأدمن فقط)
```
POST /api/subscriptions/{id}/reject
```
**يتطلب:** Admin role

#### 5. تجديد الاشتراك
```
POST /api/subscriptions/{id}/renew
```
**يتطلب:** Token مصادقة
**الوصف:** إنشاء طلب تجديد جديد يتطلب موافقة

#### 6. عرض جميع الاشتراكات (للأدمن فقط)
```
GET /api/admin/subscriptions
```
**يتطلب:** Admin role

## 💳 نظام المدفوعات (Payments)

### الملفات المسؤولة:
- **Controller**: `app/Http/Controllers/Api/PaymentController.php`
- **Model**: `app/Models/Payment.php`

### الـ APIs المتاحة:

#### 1. عرض مدفوعات المستخدم
```
GET /api/payments
```
**يتطلب:** Token مصادقة

#### 2. إنشاء عملية دفع فودافون كاش
```
POST /api/payments/vodafone
```
**يتطلب:** Token مصادقة
**المعاملات:**
- `course_id`: معرف الكورس
- `phone`: رقم الهاتف
- `amount`: المبلغ

#### 3. عرض تفاصيل دفعة محددة
```
GET /api/payments/{id}
```
**يتطلب:** Token مصادقة

## 📝 نظام التعليقات (Comments)

### الملفات المسؤولة:
- **Controller**: `app/Http/Controllers/Api/CommentController.php`
- **Model**: `app/Models/Comment.php`

### التحديثات الجديدة:
- **عرض معلومات المعلق**: اسم وصورة المستخدم مع كل تعليق
- **حماية المحتوى**: التعليق على الدروس المدفوعة للمشتركين فقط

### الـ APIs المتاحة:

#### 1. عرض تعليقات درس معين
```
GET /api/lessons/{lesson_id}/comments
```
**الملف المسؤول:** `CommentController.php` - دالة `index`
**الوصف:** يعرض التعليقات مع اسم وصورة كل معلق

#### 2. إضافة تعليق على درس
```
POST /api/lessons/{lesson_id}/comments
```
**يتطلب:** Token مصادقة + اشتراك نشط (للدروس المدفوعة)
**المعاملات:** `comment`

#### 3. تحديث تعليق
```
PUT /api/comments/{id}
```
**يتطلب:** Token مصادقة (صاحب التعليق فقط)

#### 4. حذف تعليق
```
DELETE /api/comments/{id}
```
**يتطلب:** Token مصادقة (صاحب التعليق أو أدمن)

## ⭐ نظام التقييمات (Ratings)

### الملفات المسؤولة:
- **Controller**: `app/Http/Controllers/Api/RatingController.php`
- **Model**: `app/Models/Rating.php`

### الـ APIs المتاحة:

#### 1. عرض تقييمات كورس معين
```
GET /api/courses/{course_id}/ratings
```

#### 2. إضافة/تحديث تقييم لكورس
```
POST /api/courses/{course_id}/ratings
```
**يتطلب:** Token مصادقة + اشتراك نشط
**المعاملات:** `rating` (1-5), `review` (اختياري)

#### 3. حذف تقييم
```
DELETE /api/ratings/{id}
```
**يتطلب:** Token مصادقة (صاحب التقييم فقط)

## ❤️ نظام المفضلة (Favorites)

### الملفات المسؤولة:
- **Controller**: `app/Http/Controllers/Api/FavoriteController.php`
- **Model**: `app/Models/Favorite.php`

### الـ APIs المتاحة:

#### 1. عرض مفضلة المستخدم
```
GET /api/favorites
```
**يتطلب:** Token مصادقة

#### 2. إضافة كورس للمفضلة
```
POST /api/courses/{course_id}/favorite
```
**يتطلب:** Token مصادقة

#### 3. إزالة كورس من المفضلة
```
DELETE /api/courses/{course_id}/favorite
```
**يتطلب:** Token مصادقة

## 📊 إدارة المستخدمين (User Management)

### الملفات المسؤولة:
- **Controller**: `app/Http/Controllers/Api/UserController.php`
- **Model**: `app/Models/User.php`

### الـ APIs المتاحة:

#### 1. عرض جميع المستخدمين (للأدمن فقط)
```
GET /api/users
```
**يتطلب:** Admin role

#### 2. عرض مستخدم محدد (للأدمن فقط)
```
GET /api/users/{id}
```
**يتطلب:** Admin role

#### 3. تحديث دور المستخدم (للأدمن فقط)
```
PUT /api/users/{id}/role
```
**يتطلب:** Admin role

#### 4. حذف مستخدم (للأدمن فقط)
```
DELETE /api/users/{id}
```
**يتطلب:** Admin role

## 🔒 الوسطاء (Middleware)

### الملفات والوظائف:

1. **AdminMiddleware** (`app/Http/Middleware/AdminMiddleware.php`)
   - يتحقق من صلاحيات الأدمن

2. **CheckSessionMiddleware** (`app/Http/Middleware/CheckSessionMiddleware.php`)
   - يتحقق من صحة الجلسة (تم تحديثه للعمل مع token فقط)

3. **CheckSubscription** (`app/Http/Middleware/CheckSubscription.php`)
   - يتحقق من الاشتراك النشط للوصول للدروس المدفوعة

4. **GenderContentMiddleware** (`app/Http/Middleware/GenderContentMiddleware.php`)
   - يفلتر المحتوى حسب الجنس تلقائياً

5. **LocalizationMiddleware** (`app/Http/Middleware/LocalizationMiddleware.php`)
   - يدير اللغة

6. **RateLimitMiddleware** (`app/Http/Middleware/RateLimitMiddleware.php`)
   - يحدد معدل الطلبات

7. **SecurityLogMiddleware** (`app/Http/Middleware/SecurityLogMiddleware.php`)
   - يسجل العمليات الأمنية

## 📧 نظام البريد الإلكتروني

### الملفات المسؤولة:
- **Mail Class**: `app/Mail/SendPinMail.php`
- **Template**: `resources/views/emails/pin.blade.php`

### الوظائف:
- إرسال PIN للتحقق من البريد
- إرسال PIN لاستعادة كلمة المرور
- إشعارات الاشتراك والموافقة
- قوالب باللغة العربية والإنجليزية

## 🗃️ قاعدة البيانات

### الجداول الرئيسية:

1. **users** - معلومات المستخدمين (مع الجنس)
2. **courses** - الكورسات (عامة لجميع الأجناس)
3. **lessons** - الدروس (مع target_gender للتقسيم حسب الجنس)
4. **subscriptions** - الاشتراكات الشهرية (مع موافقة الأدمن)
5. **payments** - المدفوعات
6. **comments** - التعليقات (مع معلومات المعلق)
7. **ratings** - التقييمات
8. **favorites** - المفضلة
9. **email_verifications** - التحقق من البريد

### ملفات الهجرة الجديدة:
- `2025_01_01_000010_update_courses_and_lessons_gender.php` - تحديث نظام الدروس والجنس

## 🌐 صفحات الويب

### الصفحات المتاحة:

1. **الصفحة الرئيسية**: `/`
2. **خريطة الطريق**: `/roadmap`
3. **تأكيد البريد الإلكتروني**: `/verify-email`

### الملفات:
- `public/roadmap.html` - دليل كامل للـ APIs
- `public/verify-email.html` - صفحة تأكيد البريد الإلكتروني

## ⚙️ الإعدادات والتكوين

### ملفات التكوين المهمة:

1. **البريد الإلكتروني**: `config/mail.php`
2. **قاعدة البيانات**: `config/database.php`
3. **المصادقة**: `config/auth.php`
4. **Sanctum**: `config/sanctum.php`

### متغيرات البيئة المطلوبة:
```
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/database.sqlite

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
```

## 🚀 التشغيل والنشر

### أوامر Laravel المهمة:

```bash
# تنظيف التخزين المؤقت
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

# إنشاء مفتاح التطبيق
php artisan key:generate --force

# تشغيل الهجرات والبذور
php artisan migrate:fresh --seed --force

# تشغيل الخادم
php artisan serve --host=0.0.0.0 --port=8000
```

### Workflows المعدة مسبقاً:
- **Laravel Complete Setup**: إعداد كامل وتشغيل
- **Laravel Development**: تطوير مع حفظ البيانات
- **Laravel App**: إعداد سريع

## 🔍 استكشاف الأخطاء

### ملفات السجلات:
- `storage/logs/` - سجلات عامة
- Security logs - للعمليات الأمنية

### أخطاء شائعة وحلولها:

1. **خطأ قاعدة البيانات**: تأكد من وجود ملف `database.sqlite`
2. **خطأ البريد الإلكتروني**: تحقق من إعدادات SMTP
3. **خطأ المصادقة**: تأكد من صحة token في الـ header
4. **خطأ الاشتراك**: تأكد من وجود اشتراك نشط ومعتمد

## 📞 المساعدة والدعم

### الملفات المرجعية:
- `postman_collection.json` - مجموعة Postman للاختبار
- `public/roadmap.html` - دليل مفصل للـ APIs
- هذا الملف - دليل شامل للمطورين

### معلومات إضافية:
- جميع الـ APIs تدعم اللغة العربية والإنجليزية
- نظام أمان متقدم مع Rate Limiting
- تسجيل شامل لجميع العمليات
- دعم للأدوار المختلفة (student, admin)
- **نظام اشتراك شهري متكامل**
- **دروس مقسمة حسب الجنس**
- **تعليقات محسنة مع معلومات المستخدم**

---

**تم إنشاؤه بواسطة Rose Academy Team 🌹**
