<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إعادة تعيين كلمة المرور</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 450px;
            margin: 60px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
            animation: fadeIn 0.5s ease-in-out;
        }
        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #e74c3c;
        }
        label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
            color: #333;
        }
        input[type="password"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 8px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        input[type="password"]:focus {
            border-color: #e74c3c;
            outline: none;
            box-shadow: 0 0 5px rgba(231, 76, 60, 0.3);
        }
        .error {
            color: #e74c3c;
            font-size: 13px;
            margin-bottom: 10px;
            display: none;
        }
        button {
            background-color: #e74c3c;
            color: #fff;
            border: none;
            padding: 14px;
            border-radius: 8px;
            width: 100%;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background-color: #c0392b;
        }
        @keyframes fadeIn {
            from {opacity: 0; transform: translateY(15px);}
            to {opacity: 1; transform: translateY(0);}
        }
    </style>
</head>
<body>

<div class="container">
    <h2>إعادة تعيين كلمة المرور</h2>

    <form id="resetForm" method="POST" action="{{ url('/api/auth/reset-password') }}">
        @csrf
        <input type="hidden" name="token" value="{{ request()->query('token') }}">
        <input type="hidden" name="email" value="{{ request()->query('email') }}">

        <label for="password">كلمة المرور الجديدة</label>
        <input type="password" id="password" name="password" required>
        <div id="passwordError" class="error">كلمة المرور يجب أن تكون على الأقل 8 أحرف وتحتوي على رقم وحرف كبير.</div>

        <label for="password_confirmation">تأكيد كلمة المرور</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required>
        <div id="confirmError" class="error">كلمة المرور غير متطابقة.</div>

        <button type="submit">تغيير كلمة المرور</button>
    </form>
</div>

<script>
document.getElementById('resetForm').addEventListener('submit', function(e) {
    let valid = true;

    const password = document.getElementById('password').value.trim();
    const confirm = document.getElementById('password_confirmation').value.trim();
    const passwordError = document.getElementById('passwordError');
    const confirmError = document.getElementById('confirmError');

    // تحقق من قوة كلمة المرور
    const passwordRegex = /^(?=.*[A-Z])(?=.*\d).{8,}$/;
    if (!passwordRegex.test(password)) {
        passwordError.style.display = 'block';
        valid = false;
    } else {
        passwordError.style.display = 'none';
    }

    // تحقق من التطابق
    if (password !== confirm) {
        confirmError.style.display = 'block';
        valid = false;
    } else {
        confirmError.style.display = 'none';
    }

    if (!valid) {
        e.preventDefault();
    }
});
</script>

</body>
</html>
