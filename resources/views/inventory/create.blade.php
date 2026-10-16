@extends('layouts.app')

@section('title', 'Registrasi Barang Baru - ' . ($activeWarehouse->name ?? 'Gudang') . ' • StarDust')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Registrasi Barang Baru</h1>
        <p class="page-subtitle">Pencatatan ke {{ $activeWarehouse->name ?? 'Gudang' }} via StarDust Engine</p>
    </div>
    <a href="{{ route('inventory.index', ['warehouse' => $warehouseId]) }}" class="btn btn-secondary" id="btn-back-to-index">
        ← Kembali ke Ledger
    </a>
</div>

<div class="panel" style="max-width: 800px;">
    <form action="{{ route('inventory.store') }}" method="POST" id="form-create-inventory">
        @csrf

        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="warehouse">Gudang (id_warehouse) *</label>
            <select name="warehouse" id="warehouse" class="form-control" required style="width: 100%;">
                @foreach ($warehouses as $w)
                    <option value="{{ $w->id }}" {{ (old('warehouse', $warehouseId) == $w->id) ? 'selected' : '' }}>
                        {{ $w->name }} ({{ $w->code ?? '' }})
                    </option>
                @endforeach
            </select>
        </div>

        <div style="font-size: 1rem; font-weight: 600; color: var(--text-main); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            1. Atribut Standar Barang
        </div>

        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="name">Nama Barang *</label>
                <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" placeholder="Contoh: Laptop Asus ROG" required style="width: 100%;">
                @error('name') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="sku">Kode SKU *</label>
                <input type="text" name="sku" id="sku" class="form-control" value="{{ old('sku') }}" placeholder="Contoh: LAP-ROG-G15" required style="width: 100%;">
                @error('sku') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="category">Kategori *</label>
                <input type="text" name="category" id="category" class="form-control" value="{{ old('category') }}" placeholder="Contoh: Elektronik, Aksesori, Konsumsi" required style="width: 100%;">
                @error('category') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="quantity">Jumlah Stok Fisik *</label>
                <input type="number" name="quantity" id="quantity" class="form-control" value="{{ old('quantity', 0) }}" min="0" required style="width: 100%;">
                @error('quantity') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="unit">Satuan Stok *</label>
                <input type="text" name="unit" id="unit" class="form-control" value="{{ old('unit', 'Pcs') }}" placeholder="Pcs, Unit, Box, Kg" required style="width: 100%;">
                @error('unit') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="price">Harga Satuan (Rp) *</label>
                <input type="number" name="price" id="price" class="form-control" value="{{ old('price', 0) }}" min="0" required style="width: 100%;">
                @error('price') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="supplier">Supplier / Vendor *</label>
                <input type="text" name="supplier" id="supplier" class="form-control" value="{{ old('supplier') }}" placeholder="Contoh: PT Distributor Jaya" required style="width: 100%;">
                @error('supplier') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="location">Lokasi Rak / Sektor *</label>
                <input type="text" name="location" id="location" class="form-control" value="{{ old('location') }}" placeholder="Contoh: Sektor A - Rak 01" required style="width: 100%;">
                @error('location') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="min_stock">Minimum Stok Alert *</label>
                <input type="number" name="min_stock" id="min_stock" class="form-control" value="{{ old('min_stock', 5) }}" min="0" required style="width: 100%;">
                @error('min_stock') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="weight_kg">Berat Barang (kg) [Num/Double]</label>
                <input type="number" step="0.01" name="weight_kg" id="weight_kg" class="form-control" value="{{ old('weight_kg') }}" placeholder="Opsional: 2.50" min="0" style="width: 100%;">
                @error('weight_kg') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="volume_cbm">Volume Dimensi (m³/CBM) [Num/Double]</label>
                <input type="number" step="0.001" name="volume_cbm" id="volume_cbm" class="form-control" value="{{ old('volume_cbm') }}" placeholder="Opsional: 0.025" min="0" style="width: 100%;">
                @error('volume_cbm') <span style="color: #f87171; font-size: 0.8rem;">{{ $message }}</span> @enderror
            </div>
        </div>

        <div style="font-size: 1rem; font-weight: 600; color: var(--text-main); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            2. Atribut Opsional & System
        </div>

        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="batch_number">Batch Number</label>
                <input type="text" name="batch_number" id="batch_number" class="form-control" value="{{ old('batch_number') }}" placeholder="Opsional: BATCH-2026-X" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="received_at">Waktu Penerimaan Inbound [DateTime]</label>
                <input type="datetime-local" name="received_at" id="received_at" class="form-control" value="{{ old('received_at') }}" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="expiry_date">Tanggal Kadaluarsa [DateTime]</label>
                <input type="date" name="expiry_date" id="expiry_date" class="form-control" value="{{ old('expiry_date') }}" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="warranty_months">Masa Garansi (Bulan)</label>
                <input type="number" name="warranty_months" id="warranty_months" class="form-control" value="{{ old('warranty_months') }}" placeholder="Opsional: 12, 24" min="0" style="width: 100%;">
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="serial_number">Nomor Seri Utama</label>
                <input type="text" name="serial_number" id="serial_number" class="form-control" value="{{ old('serial_number') }}" placeholder="Opsional: SN-9921-X" style="width: 100%;">
            </div>

            <div style="grid-column: span 2;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;" for="description">Deskripsi & Catatan</label>
                <textarea name="description" id="description" class="form-control" rows="3" placeholder="Catatan spesifikasi tambahan..." style="width: 100%;">{{ old('description') }}</textarea>
            </div>
        </div>

        @if(isset($customFieldDefs) && $customFieldDefs->isNotEmpty())
            <div style="font-size: 1rem; font-weight: 600; color: var(--text-main); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
                3. Atribut Custom Terdefinisi (Custom Field Definitions)
            </div>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; margin-bottom: 2rem; background: #f8fafc; padding: 1.25rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                @foreach($customFieldDefs as $cdef)
                    <div>
                        <label style="display: block; font-size: 0.85rem; color: var(--text-main); font-weight: 600; margin-bottom: 0.4rem;" for="cdef_{{ $cdef->name }}">
                            {{ $cdef->label }}
                            @if($cdef->is_required) <span style="color: #ef4444;">*</span> @endif
                            <span style="font-size: 0.75rem; color: #64748b; font-weight: 400;">({{ strtoupper($cdef->type) }})</span>
                        </label>

                        @if($cdef->type === 'select')
                            @php
                                $opts = array_filter(array_map('trim', explode(',', $cdef->options ?? '')));
                            @endphp
                            <select name="custom_defined[{{ $cdef->name }}]" id="cdef_{{ $cdef->name }}" class="form-control" style="width: 100%;" {{ $cdef->is_required ? 'required' : '' }}>
                                <option value="">-- Pilih {{ $cdef->label }} --</option>
                                @foreach($opts as $opt)
                                    <option value="{{ $opt }}" {{ old('custom_defined.' . $cdef->name) == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                        @elseif($cdef->type === 'number')
                            <input type="number" step="any" name="custom_defined[{{ $cdef->name }}]" id="cdef_{{ $cdef->name }}" class="form-control" value="{{ old('custom_defined.' . $cdef->name) }}" placeholder="Masukkan angka..." style="width: 100%;" {{ $cdef->is_required ? 'required' : '' }}>
                        @elseif($cdef->type === 'date')
                            <input type="date" name="custom_defined[{{ $cdef->name }}]" id="cdef_{{ $cdef->name }}" class="form-control" value="{{ old('custom_defined.' . $cdef->name) }}" style="width: 100%;" {{ $cdef->is_required ? 'required' : '' }}>
                        @else
                            <input type="text" name="custom_defined[{{ $cdef->name }}]" id="cdef_{{ $cdef->name }}" class="form-control" value="{{ old('custom_defined.' . $cdef->name) }}" placeholder="Masukkan nilai teks..." style="width: 100%;" {{ $cdef->is_required ? 'required' : '' }}>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div style="font-size: 1rem; font-weight: 600; color: var(--text-main); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
            <span>{{ isset($customFieldDefs) && $customFieldDefs->isNotEmpty() ? '4' : '3' }}. Atribut Custom Dinamis Bebas (Key - Tipe Data - Value)</span>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addCustomRow()" id="btn-add-custom-row">+ Tambah Field Custom</button>
        </div>

        <p style="font-size: 0.825rem; color: var(--text-muted); margin-bottom: 1rem;">
            Tambahkan field custom baru secara bebas tanpa batas untuk barang ini (misal: Warna, Ukuran, IMEI, Voltase, Brand) dengan tipe data yang sesuai.
        </p>

        <div id="custom-fields-container" style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem;">
            <!-- Dynamic rows inserted by JS -->
        </div>

        <div style="display: flex; gap: 1rem; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid var(--border-color);">
            <a href="{{ route('inventory.index', ['warehouse' => $warehouseId]) }}" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary" id="btn-submit-create">
                Simpan Barang
            </button>
        </div>
    </form>
</div>

<script>
function addCustomRow(key = '', val = '', type = 'text') {
    const container = document.getElementById('custom-fields-container');
    const row = document.createElement('div');
    row.style.cssText = 'display: flex; gap: 0.75rem; align-items: center; background: #f8fafc; padding: 0.6rem 0.8rem; border-radius: 6px; border: 1px solid #e2e8f0;';

    let valInputHtml = `<input type="text" name="custom_vals[]" class="form-control val-input" value="${val}" placeholder="Nilai Atribut" style="flex: 1.5;" required>`;
    if (type === 'number') {
        valInputHtml = `<input type="number" step="any" name="custom_vals[]" class="form-control val-input" value="${val}" placeholder="Nilai Angka" style="flex: 1.5;" required>`;
    } else if (type === 'date') {
        valInputHtml = `<input type="date" name="custom_vals[]" class="form-control val-input" value="${val}" style="flex: 1.5;" required>`;
    } else if (type === 'boolean') {
        valInputHtml = `
            <select name="custom_vals[]" class="form-control val-input" style="flex: 1.5;" required>
                <option value="1" ${val == '1' || val == 'true' || val === true ? 'selected' : ''}>True (Ya / Aktif)</option>
                <option value="0" ${val == '0' || val == 'false' || val === false ? 'selected' : ''}>False (Tidak / Non-aktif)</option>
            </select>
        `;
    }

    row.innerHTML = `
        <input type="text" name="custom_keys[]" class="form-control" value="${key}" placeholder="Nama Atribut (e.g. Warna, Garansi)" style="flex: 1;" required>
        <select name="custom_types[]" class="form-control type-select" style="width: 140px; cursor: pointer;" onchange="updateCustomValInput(this)">
            <option value="text" ${type === 'text' ? 'selected' : ''}>Teks (Text)</option>
            <option value="number" ${type === 'number' ? 'selected' : ''}>Angka (Number)</option>
            <option value="date" ${type === 'date' ? 'selected' : ''}>Tanggal (Date)</option>
            <option value="boolean" ${type === 'boolean' ? 'selected' : ''}>Boolean</option>
        </select>
        <span style="color: #94a3b8; font-weight: bold;">:</span>
        ${valInputHtml}
        <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentElement.remove()" style="color: #ef4444; border-color: #fca5a5; padding: 0.4rem 0.6rem;">&times;</button>
    `;
    container.appendChild(row);
}

function updateCustomValInput(typeSelect) {
    const row = typeSelect.parentElement;
    const type = typeSelect.value;
    const oldValInput = row.querySelector('.val-input');
    const currentVal = oldValInput ? oldValInput.value : '';

    let newValInput;
    if (type === 'number') {
        newValInput = document.createElement('input');
        newValInput.type = 'number';
        newValInput.step = 'any';
        newValInput.placeholder = 'Nilai Angka';
    } else if (type === 'date') {
        newValInput = document.createElement('input');
        newValInput.type = 'date';
    } else if (type === 'boolean') {
        newValInput = document.createElement('select');
        newValInput.innerHTML = `
            <option value="1">True (Ya / Aktif)</option>
            <option value="0">False (Tidak / Non-aktif)</option>
        `;
    } else {
        newValInput = document.createElement('input');
        newValInput.type = 'text';
        newValInput.placeholder = 'Nilai Atribut';
    }

    newValInput.name = 'custom_vals[]';
    newValInput.className = 'form-control val-input';
    newValInput.style.flex = '1.5';
    newValInput.required = true;
    if (type !== 'boolean' && currentVal) {
        newValInput.value = currentVal;
    }

    oldValInput.replaceWith(newValInput);
}
</script>
@endsection

