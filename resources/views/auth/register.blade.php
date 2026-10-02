<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Akun - StarDust Warehouse & Inventory System</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>
<body class="login-page">

    <div class="login-card" style="max-width: 480px;">
        <div class="login-header">
            <div class="login-logo">STARDUST</div>
            <div class="login-subtitle">Registrasi Akun Pengguna Baru</div>
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

        <form action="{{ route('register.post') }}" method="POST" id="form-guest-register">
            @csrf

            <div class="form-group">
                <label for="name" class="form-label">Nama Lengkap</label>
                <input type="text" id="name" name="name" class="form-input" value="{{ old('name') }}" required autofocus placeholder="Contoh: Budi Santoso">
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-input" value="{{ old('email') }}" required placeholder="user@stardust.com">
            </div>

            <div class="form-group">
                <label for="role" class="form-label">Peran Pengguna (Role)</label>
                <select id="role" name="role" class="form-input" required style="cursor: pointer;">
                    <option value="staff" {{ old('role', 'staff') == 'staff' ? 'selected' : '' }}>Staff Gudang (Staff)</option>
                    <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Administrator System (Admin)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="id_warehouse" class="form-label">Gudang Penugasan (Opsional)</label>
                <select id="id_warehouse" name="id_warehouse" class="form-input" style="cursor: pointer;">
                    <option value="">-- Pilih Gudang (Jika Ada) --</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}" {{ old('id_warehouse') == $w->id ? 'selected' : '' }}>
                            {{ $w->name }} @if(!empty($w->code)) ({{ $w->code }}) @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-input" required placeholder="Minimal 6 karakter">
            </div>

            <div class="form-group">
                <label for="password_confirmation" class="form-label">Konfirmasi Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-input" required placeholder="Ulangi password">
            </div>

            <button type="submit" class="btn-login" style="margin-top: 0.5rem;">Daftar Akun Baru</button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.85rem; color: var(--auth-text-muted);">
            Sudah memiliki akun? <a href="{{ route('login') }}" id="link-login" style="color: var(--auth-primary); font-weight: 600; text-decoration: none;">Masuk ke Halaman Login</a>
        </div>
    </div>

</body>
</html>
