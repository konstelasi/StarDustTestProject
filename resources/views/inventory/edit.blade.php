@extends('layouts.app')

@section('title', 'Edit Barang - ' . ($item->name ?? 'Item') . ' • StarDust')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Edit Barang: {{ $item->name }}</h1>
        <p class="page-subtitle">Pembaruan payload StarDust (`$stardust->updateEntry()`) di {{ $activeWarehouse->name ?? 'Gudang' }}</p>
    </div>
    <a href="{{ route('inventory.show', ['inventory' => $item->id, 'warehouse' => $warehouseId]) }}" class="btn btn-secondary" id="btn-back-from-edit">
        ← Kembali ke Detail
    </a>
</div>

<div class="panel" style="max-width: 850px;">
    <form action="{{ route('inventory.update', $item->id) }}" method="POST" id="form-edit-inventory">
        @csrf
        @method('PUT')
        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass); margin-bottom: 0.4rem;" for="warehouse">Gudang (id_warehouse) *</label>
            <select name="warehouse" id="warehouse" class="form-control" required style="width: 100%;">
                @foreach ($warehouses as $w)
                    <option value="{{ $w->id }}" {{ (old('warehouse', $item->id_warehouse ?? $warehouseId) == $w->id) ? 'selected' : '' }}>
                        {{ $w->name }} ({{ $w->code ?? '' }})
                    </option>
                @endforeach
            </select>
        </div>

        <div style="font-family: var(--font-heading); font-size: 1rem; color: var(--brass-light); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            1. Atribut Standar Barang (Standard Slotted Fields)
        </div>

        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass); margin-bottom: 0.4rem;" for="name">Nama Barang *</label>
                <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $item->name ?? '') }}" required style="width: 100%;">
                @error('name') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass); margin-bottom: 0.4rem;" for="sku">Kode SKU *</label>
                <input type="text" name="sku" id="sku" class="form-control" value="{{ old('sku', $item->sku ?? '') }}" required style="width: 100%;">
                @error('sku') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass); margin-bottom: 0.4rem;" for="category">Kategori *</label>
                <input type="text" name="category" id="category" class="form-control" value="{{ old('category', $item->category ?? '') }}" required style="width: 100%;">
                @error('category') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass); margin-bottom: 0.4rem;" for="quantity">Jumlah Stok Fisik *</label>
                <input type="number" name="quantity" id="quantity" class="form-control" value="{{ old('quantity', $item->quantity ?? 0) }}" min="0" required style="width: 100%;">
                @error('quantity') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass); margin-bottom: 0.4rem;" for="unit">Satuan Stok *</label>
                <input type="text" name="unit" id="unit" class="form-control" value="{{ old('unit', $item->unit ?? 'Pcs') }}" required style="width: 100%;">
                @error('unit') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass); margin-bottom: 0.4rem;" for="price">Harga Satuan (Rp) *</label>
                <input type="number" name="price" id="price" class="form-control" value="{{ old('price', $item->price ?? 0) }}" min="0" required style="width: 100%;">
                @error('price') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass); margin-bottom: 0.4rem;" for="supplier">Supplier / Vendor *</label>
                <input type="text" name="supplier" id="supplier" class="form-control" value="{{ old('supplier', $item->supplier ?? '') }}" required style="width: 100%;">
                @error('supplier') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass); margin-bottom: 0.4rem;" for="location">Lokasi Rak / Sektor *</label>
                <input type="text" name="location" id="location" class="form-control" value="{{ old('location', $item->location ?? '') }}" required style="width: 100%;">
                @error('location') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass); margin-bottom: 0.4rem;" for="min_stock">Minimum Stok Alert *</label>
                <input type="number" name="min_stock" id="min_stock" class="form-control" value="{{ old('min_stock', $item->min_stock ?? 5) }}" min="0" required style="width: 100%;">
                @error('min_stock') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>
        </div>

        <div style="font-family: var(--font-heading); font-size: 1rem; color: var(--brass-light); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            2. Dynamic Schemaless Custom Attributes (StarDust JSON Payload)
        </div>

        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass); margin-bottom: 0.4rem;" for="batch_number">Batch Number (Konsumsi/Obat)</label>
                <input type="text" name="batch_number" id="batch_number" class="form-control" value="{{ old('batch_number', $item->batch_number ?? '') }}" placeholder="Opsional: BATCH-2026-X" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass); margin-bottom: 0.4rem;" for="expiry_date">Tanggal Kadaluarsa</label>
                <input type="date" name="expiry_date" id="expiry_date" class="form-control" value="{{ old('expiry_date', isset($item->expiry_date) ? date('Y-m-d', strtotime($item->expiry_date)) : '') }}" style="width: 100%;">
                <span style="color: var(--text-dim); font-size: 0.7rem;">Tersimpan sebagai tipe `datetime` yang terindeks.</span>
            </div>

            <div>
                <label style="display: block; font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass); margin-bottom: 0.4rem;" for="warranty_months">Masa Garansi (Bulan)</label>
                <input type="number" name="warranty_months" id="warranty_months" class="form-control" value="{{ old('warranty_months', $item->warranty_months ?? '') }}" placeholder="Opsional: 12, 24" min="0" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass); margin-bottom: 0.4rem;" for="serial_number">Nomor Seri Utama</label>
                <input type="text" name="serial_number" id="serial_number" class="form-control" value="{{ old('serial_number', $item->serial_number ?? '') }}" placeholder="Opsional: SN-9921-X" style="width: 100%;">
            </div>

            <div style="grid-column: span 2;">
                <label style="display: block; font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass); margin-bottom: 0.4rem;" for="description">Deskripsi & Catatan Spesifikasi</label>
                <textarea name="description" id="description" class="form-control" rows="3" style="width: 100%;">{{ old('description', $item->description ?? '') }}</textarea>
            </div>
        </div>

        <div style="display: flex; gap: 1rem; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid var(--border-color);">
            <a href="{{ route('inventory.show', ['inventory' => $item->id, 'warehouse' => $warehouseId]) }}" class="btn btn-secondary">Batal</a>            <button type="submit" class="btn btn-primary" id="btn-submit-update">
                Simpan Perubahan ke StarDust Engine
            </button>
        </div>
    </form>
</div>
@endsection
