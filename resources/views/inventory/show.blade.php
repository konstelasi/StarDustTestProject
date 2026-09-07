@extends('layouts.app')

@section('title', 'Detail Barang: ' . ($item->name ?? '') . ' • StarDust')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">{{ $item->name }}</h1>
        <p class="page-subtitle">Gudang: {{ $activeWarehouse->name }} | StarDust Entry ID: #{{ $item->id }} | SKU: <span class="sku-code">{{ $item->sku }}</span></p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="{{ route('inventory.index', ['warehouse' => $warehouseId]) }}" class="btn btn-secondary" id="btn-back-to-list">
            ← Kembali ke Ledger
        </a>
        <a href="{{ route('inventory.edit', ['inventory' => $item->id, 'warehouse' => $warehouseId]) }}" class="btn btn-primary" id="btn-edit-current">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Edit Barang
        </a>
    </div>
</div>

<div class="panel">
    <h3 style="font-family: var(--font-heading); font-size: 1.1rem; margin-bottom: 1.5rem; color: var(--brass-light); padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
        Record Spesifikasi Barang (StarDust JSON Payload)
    </h3>
    
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
        <div>
            <div style="font-family: var(--font-heading); font-size: 0.75rem; color: var(--brass); text-transform: uppercase;">Nama Barang</div>
            <div style="font-size: 1.1rem; font-weight: 600; color: var(--parchment); margin-top: 0.25rem;">{{ $item->name ?? '-' }}</div>
        </div>

        <div>
            <div style="font-family: var(--font-heading); font-size: 0.75rem; color: var(--brass); text-transform: uppercase;">Kode SKU</div>
            <div style="margin-top: 0.25rem;"><span class="sku-code">{{ $item->sku ?? '-' }}</span></div>
        </div>

        <div>
            <div style="font-family: var(--font-heading); font-size: 0.75rem; color: var(--brass); text-transform: uppercase;">Kategori</div>
            <div style="margin-top: 0.25rem;"><span class="badge badge-category">{{ $item->category ?? 'Umum' }}</span></div>
        </div>

        <div>
            <div style="font-family: var(--font-heading); font-size: 0.75rem; color: var(--brass); text-transform: uppercase;">Stok Fisik Saat Ini</div>
            <div style="margin-top: 0.25rem;">
                @php
                    $qty = (int)($item->quantity ?? 0);
                    $min = (int)($item->min_stock ?? 0);
                    $isLow = $qty <= $min;
                @endphp
                <span class="badge {{ $isLow ? 'badge-low' : 'badge-normal' }}" style="font-size: 0.9rem;">
                    {{ $qty }} {{ $item->unit ?? 'Pcs' }}
                </span>
            </div>
        </div>

        <div>
            <div style="font-family: var(--font-heading); font-size: 0.75rem; color: var(--brass); text-transform: uppercase;">Harga Satuan</div>
            <div style="font-family: var(--font-mono); font-size: 1.1rem; color: var(--brass-light); margin-top: 0.25rem;">Rp {{ number_format((int)($item->price ?? 0), 0, ',', '.') }}</div>
        </div>

        <div>
            <div style="font-family: var(--font-heading); font-size: 0.75rem; color: var(--brass); text-transform: uppercase;">Estimasi Total Nilai Stock</div>
            <div style="font-family: var(--font-mono); font-size: 1.1rem; color: #72d99d; margin-top: 0.25rem;">Rp {{ number_format(((int)($item->price ?? 0)) * ((int)($item->quantity ?? 0)), 0, ',', '.') }}</div>
        </div>

        <div>
            <div style="font-family: var(--font-heading); font-size: 0.75rem; color: var(--brass); text-transform: uppercase;">Supplier / Vendor</div>
            <div style="font-size: 0.95rem; color: var(--text-main); margin-top: 0.25rem;">{{ $item->supplier ?? '-' }}</div>
        </div>

        <div>
            <div style="font-family: var(--font-heading); font-size: 0.75rem; color: var(--brass); text-transform: uppercase;">Lokasi Rak Gudang</div>
            <div style="font-size: 0.95rem; color: var(--text-main); margin-top: 0.25rem;">{{ $item->location ?? '-' }}</div>
        </div>

        <div>
            <div style="font-family: var(--font-heading); font-size: 0.75rem; color: var(--brass); text-transform: uppercase;">Minimum Stock Threshold</div>
            <div style="font-size: 0.95rem; color: var(--text-main); margin-top: 0.25rem;">{{ $item->min_stock ?? 0 }} {{ $item->unit ?? 'Pcs' }}</div>
        </div>
    </div>

    <!-- Schemaless Dynamic Custom Attributes -->
    <h4 style="font-family: var(--font-heading); font-size: 0.95rem; color: var(--brass); margin-top: 2rem; margin-bottom: 1rem; padding-bottom: 0.4rem; border-bottom: 1px dashed var(--border-color);">
        Atribut Dinamis Khusus (Dynamic Schemaless Payload)
    </h4>

    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; margin-bottom: 1.5rem;">
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">Batch Number</div>
            <div style="font-family: var(--font-mono); font-size: 0.9rem; color: var(--parchment);">{{ $item->batch_number ?? '-' }}</div>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">Tanggal Expiry</div>
            <div style="font-family: var(--font-mono); font-size: 0.9rem; color: var(--parchment);">{{ $item->expiry_date ?? '-' }}</div>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">Masa Garansi</div>
            <div style="font-family: var(--font-mono); font-size: 0.9rem; color: var(--parchment);">{{ !empty($item->warranty_months) ? $item->warranty_months . ' Bulan' : '-' }}</div>
        </div>
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">Nomor Seri Utama</div>
            <div style="font-family: var(--font-mono); font-size: 0.9rem; color: var(--parchment);">{{ $item->serial_number ?? '-' }}</div>
        </div>
    </div>

    @if (!empty($item->description))
        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
            <div style="font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass); margin-bottom: 0.4rem;">Deskripsi / Catatan Barang</div>
            <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.6; white-space: pre-line;">{{ $item->description }}</p>
        </div>
    @endif
</div>
@endsection
