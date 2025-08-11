<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إعادة تعيين كلمة المرور</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 450px;
            margin: 50px auto;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #e74c3c;
        }
        label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
        }
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            background-color: #e74c3c;
            color: #fff;
            border: none;
            padding: 12px;
            border-radius: 5px;
            width: 100%;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background-color: #c0392b;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>إعادة تعيين كلمة المرور</h2>

    <form method="POST" action="{{ url('/api/auth/reset-password') }}">
        @csrf
        <input type="hidden" name="token" value="{{ request()->query('token') }}">
        <input type="hidden" name="email" value="{{ request()->query('email') }}">

        <label for="password">كلمة المرور الجديدة</label>
        <input type="password" id="password" name="password" required>

        <label for="password_confirmation">تأكيد كلمة المرور</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required>

        <button type="submit">تغيير كلمة المرور</button>
    </form>
</div>

</body>
</html>
