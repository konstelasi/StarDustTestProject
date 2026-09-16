<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - StarDust Warehouse & Inventory System</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>
<body class="login-page">

    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">STARDUST</div>
            <div class="login-subtitle">Warehouse & Inventory Management System</div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('login.post') }}" method="POST">
            @csrf

            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-input" value="{{ old('email') }}" required autofocus placeholder="admin@stardust.com">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-input" required placeholder="••••••••">
            </div>

            <div class="form-checkbox-group">
                <label class="form-checkbox-label">
                    <input type="checkbox" name="remember" value="1"> Ingat Sesi Saya
                </label>
            </div>
            <button type="submit" class="btn-login">Masuk</button>
        </form>
    </div>

</body>
</html>
