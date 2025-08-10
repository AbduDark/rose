<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحقق من بريدك الإلكتروني</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            direction: rtl;
            text-align: right;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .pin-code {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            color: #2d3748;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>التحقق من البريد الإلكتروني</h1>
        </div>
        <p>مرحباً،</p>
        <p>شكراً لتسجيلك في منصة Rose Academy. يرجى الضغط على الرابط التالي لتفعيل حسابك:</p>
        <div class="pin-code">
            <a href="{{ $verificationUrl }}" style="color: #4a5568; text-decoration: none;">اضغط هنا لتفعيل حسابك</a>
        </div>
        <p>هذا الرابط صالح لمدة ساعة واحدة. إذا لم تقم بالتسجيل، يرجى تجاهل هذا البريد الإلكتروني.</p>
        <p>مع تحيات،<br>فريق Rose Academy</p>
    </div>
</body>
</html>
