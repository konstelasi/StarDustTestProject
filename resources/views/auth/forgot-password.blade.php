<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - StarDust Warehouse & Inventory System</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset('css/konstelasi-bg.css') }}">
</head>
<body class="login-page">

    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">STARDUST</div>
            <div class="login-subtitle">Atur ulang password</div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('password.email') }}" method="POST">
            @csrf

            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-input" value="{{ old('email') }}" required autofocus placeholder="user@stardust.com">
            </div>

            <button type="submit" class="btn-login">Kirim Tautan</button>
        </form>

        <div style="text-align: center; margin-top: 1rem;">
            <a href="{{ route('login') }}">Kembali ke login</a>
        </div>
    </div>

</body>
</html>