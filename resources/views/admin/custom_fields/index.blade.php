@extends('layouts.app')

@section('title', 'Atur Atribut Dinamis Custom - StarDust Admin')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Atur Atribut Dinamis Custom</h1>
        <p class="page-subtitle">Kelola bidang dinamis schemaless yang dapat diisi oleh pengguna (Global / Per User)</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: 360px 1fr; gap: 1.5rem; align-items: start;">
    <!-- Form Tambah Atribut Baru -->
    <div class="panel">
        <h3 style="font-size: 1.05rem; margin-top: 0; margin-bottom: 1.25rem; color: var(--text-main); font-weight: 600; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            + Tambah Atribut Dinamis
        </h3>

        <form action="{{ route('admin.custom-fields.store') }}" method="POST" id="form-create-custom-field">
            @csrf

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="label" class="form-label" style="display: block; margin-bottom: 0.4rem; font-weight: 600; font-size: 0.875rem;">Nama Atribut (Label) *</label>
                <input type="text" id="label" name="label" class="form-control" value="{{ old('label') }}" required placeholder="Contoh: Warna, Masa Garansi, IMEI" style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="type" class="form-label" style="display: block; margin-bottom: 0.4rem; font-weight: 600; font-size: 0.875rem;">Tipe Data Input *</label>
                <select id="type" name="type" class="form-control" required style="width: 100%; cursor: pointer;">
                    <option value="text">Teks Bebas (Text)</option>
                    <option value="number">Angka / Jumlah (Number)</option>
                    <option value="date">Tanggal (Date)</option>
                    <option value="select">Pilihan Datar (Dropdown Select)</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="options" class="form-label" style="display: block; margin-bottom: 0.4rem; font-weight: 600; font-size: 0.875rem;">Opsi Pilihan (Khusus Dropdown Select)</label>
                <input type="text" id="options" name="options" class="form-control" value="{{ old('options') }}" placeholder="Pisahkan dengan koma: Merah, Hitam, Biru" style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem; background: #f8fafc; padding: 0.85rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                <label for="user_id" class="form-label" style="display: block; margin-bottom: 0.4rem; font-weight: 600; font-size: 0.875rem; color: #1e293b;">🔒 Batasi Ruang Lingkup User (Scope)</label>
                <select id="user_id" name="user_id" class="form-control" style="width: 100%; cursor: pointer;">
                    <option value="">-- Semua User (Global Scope) --</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" {{ old('user_id') == $u->id ? 'selected' : '' }}>
                            👤 {{ $u->name }} ({{ ucfirst($u->role) }})
                        </option>
                    @endforeach
                </select>
                <p style="font-size: 0.775rem; color: #64748b; margin-top: 0.4rem; margin-bottom: 0;">
                    Jika memilih user spesifik, atribut ini hanya akan muncul saat user tersebut mengisi form.
                </p>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.875rem;">
                    <input type="checkbox" name="is_required" value="1" {{ old('is_required') ? 'checked' : '' }}>
                    <span>Wajib diisi (Required)</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;" id="btn-save-custom-field">Simpan Atribut Dinamis</button>
        </form>
    </div>

    <!-- Tabel Daftar Atribut Custom -->
    <div class="panel">
        <h3 style="font-size: 1.05rem; margin-top: 0; margin-bottom: 1.25rem; color: var(--text-main); font-weight: 600; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            Daftar Atribut Dinamis Terdaftar ({{ $fields->count() }})
        </h3>

        <div class="table-container">
            <table class="custom-table" id="custom-fields-table">
                <thead>
                    <tr>
                        <th>Nama Atribut</th>
                        <th>Identifier (Slug)</th>
                        <th>Tipe Data</th>
                        <th>Ruang Lingkup (Scope)</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fields as $f)
                    <tr>
                        <td><strong>{{ $f->label }}</strong> @if($f->is_required) <span style="color:#ef4444;">*</span> @endif</td>
                        <td><code style="background:#f1f5f9; padding: 0.2rem 0.5rem; border-radius:4px; font-size: 0.8rem; color:#475569;">{{ $f->name }}</code></td>
                        <td><span class="badge badge-secondary" style="text-transform: uppercase; font-size: 0.75rem;">{{ $f->type }}</span></td>
                        <td>
                            @if ($f->user)
                                <span style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: 500;">
                                    👤 Khusus: {{ $f->user->name }}
                                </span>
                            @else
                                <span style="background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: 500;">
                                    🌐 Global (Semua User)
                                </span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <form action="{{ route('admin.custom-fields.destroy', $f->id) }}" method="POST" onsubmit="return confirm('Hapus atribut dinamis ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-secondary btn-sm" style="color: #ef4444; border-color: #fca5a5;">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                            Belum ada atribut dinamis custom terdaftar. Tambahkan via form di samping.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
