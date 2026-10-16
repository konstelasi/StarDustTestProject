<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atur Password - StarDust Warehouse & Inventory System</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset('css/konstelasi-bg.css') }}">
</head>
<body class="login-page">

    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">STARDUST</div>
            <div class="login-subtitle">Atur password baru</div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('password.update') }}" method="POST">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-input" value="{{ old('email', $email) }}" required readonly>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password Baru</label>
                <input type="password" id="password" name="password" class="form-input" required autofocus placeholder="Minimal 6 karakter">
            </div>

            <div class="form-group">
                <label for="password_confirmation" class="form-label">Konfirmasi Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-input" required placeholder="Ulangi password">
            </div>

            <button type="submit" class="btn-login">Simpan Password</button>
        </form>
    </div>

</body>
</html>