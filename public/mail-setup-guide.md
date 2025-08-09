
# 📧 دليل إعداد البريد الإلكتروني - Rose Academy

## خطوات إعداد Gmail SMTP:

### 1. إنشاء App Password في Gmail:
1. اذهب إلى حسابك في Google
2. اختر "الأمان" > "المصادقة الثنائية"
3. في الأسفل اختر "كلمات مرور التطبيقات"
4. اختر "البريد" و "جهاز آخر"
5. انسخ كلمة المرور التي تظهر (16 رقم)

### 2. إعداد متغيرات البيئة:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-16-digit-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="Rose Academy"
MAIL_EHLO_DOMAIN=gmail.com
```

### 3. بدائل أخرى للـ SMTP:

#### Mailtrap (للتطوير):
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_ENCRYPTION=tls
```

#### SendGrid:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_ENCRYPTION=tls
```

### 4. للتطوير فقط - استخدام Log Driver:
```env
MAIL_MAILER=log
```
هذا سيحفظ الرسائل في ملف `storage/logs/laravel.log` بدلاً من إرسالها.

### ملاحظات مهمة:
- تأكد من تفعيل المصادقة الثنائية في Gmail قبل إنشاء App Password
- لا تستخدم كلمة مرور حسابك العادية
- احتفظ بـ App Password في مكان آمن
