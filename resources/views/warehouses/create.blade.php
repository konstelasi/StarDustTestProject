@extends('layouts.app')

@section('title', 'Tambah Gudang Baru • StarDust')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Tambah Gudang Baru</h1>
        <p class="page-subtitle">Registrasi Lokasi Warehouse Baru ke StarDust Engine</p>
    </div>
    <a href="{{ route('inventory.index') }}" class="btn btn-secondary" id="btn-back-to-index">
        ← Kembali ke Ledger
    </a>
</div>

<div class="panel" style="max-width: 650px;">
    <form action="{{ route('warehouses.store') }}" method="POST" id="form-create-warehouse">
        @csrf

        <div style="font-size: 1rem; font-weight: 600; color: var(--text-main); margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            Informasi Gudang Baru
        </div>

        <div style="display: flex; flex-direction: column; gap: 1.25rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="name">Nama Gudang *</label>
                <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" placeholder="Contoh: Gudang Hub Semarang" required style="width: 100%;">
                @error('name') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="code">Kode Gudang *</label>
                <input type="text" name="code" id="code" class="form-control" value="{{ old('code') }}" placeholder="Contoh: WH-SMG-04" required style="width: 100%;">
                @error('code') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="location">Lokasi / Alamat Lengkap *</label>
                <input type="text" name="location" id="location" class="form-control" value="{{ old('location') }}" placeholder="Contoh: Kawasan Industri Terboyo, Semarang" required style="width: 100%;">
                @error('location') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="manager">Manajer Penanggung Jawab *</label>
                <input type="text" name="manager" id="manager" class="form-control" value="{{ old('manager') }}" placeholder="Contoh: Hendra Wijaya" required style="width: 100%;">
                @error('manager') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>
        </div>

        <div style="display: flex; gap: 1rem; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid var(--border-color);">
            <a href="{{ route('inventory.index') }}" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary" id="btn-submit-warehouse">
                Simpan Gudang Baru
            </button>
        </div>
    </form>
</div>
@endsection
