@extends('layouts.app')

@section('title', 'Registrasi Pengguna Baru - StarDust Admin')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Registrasi User / Staff Baru</h1>
        <p class="page-subtitle">Pendaftaran akun pengguna dan pemberian akses gudang oleh Administrator System</p>
    </div>
</div>

<div class="panel" style="max-width: 680px; margin: 0 auto;">
    @if ($errors->any())
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('register.post') }}" method="POST" id="form-admin-register">
        @csrf

        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label for="name" class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Nama Lengkap</label>
            <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" required autofocus placeholder="Contoh: Budi Santoso" style="width: 100%;">
        </div>

        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label for="email" class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Email</label>
            <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" required placeholder="user@stardust.com" style="width: 100%;">
        </div>

        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label for="role" class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Peran Pengguna (Role)</label>
            <select id="role" name="role" class="form-control" required style="width: 100%; cursor: pointer;">
                <option value="staff_gudang" {{ old('role', 'staff_gudang') == 'staff_gudang' || old('role') == 'staff' ? 'selected' : '' }}>Staff Gudang (Staff)</option>
                <option value="admin_gudang" {{ old('role') == 'admin_gudang' ? 'selected' : '' }}>Admin Gudang</option>
                <option value="admin_platform" {{ old('role') == 'admin_platform' || old('role') == 'admin' ? 'selected' : '' }}>Administrator Platform (Admin Platform)</option>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label for="id_warehouse" class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Gudang Penugasan (Opsional)</label>
            <select id="id_warehouse" name="id_warehouse" class="form-control" style="width: 100%; cursor: pointer;">
                <option value="">-- Pilih Gudang (Jika Ada) --</option>
                @foreach ($warehouses as $w)
                    <option value="{{ $w->id }}" {{ old('id_warehouse') == $w->id ? 'selected' : '' }}>
                        {{ $w->name }} @if(!empty($w->code)) ({{ $w->code }}) @endif
                    </option>
                @endforeach
            </select>
        </div>
        <p style="margin-bottom: 1.5rem; color: #6b7280; font-size: 0.9rem;">
            Password tidak perlu diisi. Pengguna akan menerima email berisi tautan untuk mengatur password sendiri.
        </p>
        <div style="display: flex; gap: 1rem; align-items: center; justify-content: flex-end;">
            <a href="{{ route('staff.index') }}" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary" id="btn-submit-register">Daftarkan Akun Baru</button>
        </div>
    </form>
</div>
@endsection
