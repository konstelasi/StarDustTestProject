@extends('layouts.app')

@section('title', 'Detail Barang: ' . ($item->name ?? '') . ' • StarDust')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">{{ $item->name }}</h1>
        <p class="page-subtitle">Gudang: {{ $activeWarehouse['name'] ?? 'Gudang' }} | SKU: <span class="sku-code">{{ $item->sku }}</span> | Entry #{{ $item->id }}</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="{{ route('inventory.index', ['warehouse' => $tenantId]) }}" class="btn btn-secondary" id="btn-back-to-list">
            ← Kembali ke Ledger
        </a>
        <a href="{{ route('inventory.edit', ['inventory' => $item->id, 'warehouse' => $tenantId]) }}" class="btn btn-primary" id="btn-edit-current">
            Edit Barang
        </a>
    </div>
</div>

<div class="panel">
    <h3 style="font-size: 1.05rem; font-weight: 600; margin-bottom: 1.5rem; color: var(--text-main); padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
        Detail Spesifikasi Barang
    </h3>
    
    <div class="detail-grid" style="margin-bottom: 2rem;">
        <div class="detail-item">
            <div class="detail-label">Nama Barang</div>
            <div class="detail-val">{{ $item->name ?? '-' }}</div>
        </div>

        <div class="detail-item">
            <div class="detail-label">Kode SKU</div>
            <div class="detail-val"><span class="sku-code">{{ $item->sku ?? '-' }}</span></div>
        </div>

        <div class="detail-item">
            <div class="detail-label">Kategori</div>
            <div class="detail-val">{{ $item->category ?? 'Umum' }}</div>
        </div>

        <div class="detail-item">
            <div class="detail-label">Stok Fisik</div>
            <div class="detail-val">
                @php
                    $qty = (int)($item->quantity ?? 0);
                    $min = (int)($item->min_stock ?? 0);
                    $isLow = $qty <= $min;
                @endphp
                @if($isLow)
                    <span class="stock-low-tag">{{ $qty }} {{ $item->unit ?? 'Pcs' }} (Stok Rendah)</span>
                @else
                    <span class="stock-normal-text">{{ $qty }} {{ $item->unit ?? 'Pcs' }}</span>
                @endif
            </div>
        </div>

        <div class="detail-item">
            <div class="detail-label">Harga Satuan</div>
            <div class="detail-val">Rp {{ number_format((int)($item->price ?? 0), 0, ',', '.') }}</div>
        </div>

        <div class="detail-item">
            <div class="detail-label">Estimasi Total Nilai Stok</div>
            <div class="detail-val">Rp {{ number_format(((int)($item->price ?? 0)) * ((int)($item->quantity ?? 0)), 0, ',', '.') }}</div>
        </div>

        <div class="detail-item">
            <div class="detail-label">Supplier / Vendor</div>
            <div class="detail-val">{{ $item->supplier ?? '-' }}</div>
        </div>

        <div class="detail-item">
            <div class="detail-label">Lokasi Rak</div>
            <div class="detail-val">{{ $item->location ?? '-' }}</div>
        </div>

        <div class="detail-item">
            <div class="detail-label">Minimum Stok Threshold</div>
            <div class="detail-val">{{ $item->min_stock ?? 0 }} {{ $item->unit ?? 'Pcs' }}</div>
        </div>
    </div>

    <!-- Additional Custom Attributes -->
    <h4 style="font-size: 0.95rem; font-weight: 600; color: var(--text-main); margin-top: 1.5rem; margin-bottom: 1rem; padding-bottom: 0.4rem; border-bottom: 1px solid var(--border-color);">
        Atribut Tambahan
    </h4>

    <div class="detail-grid" style="margin-bottom: 1.5rem;">
        <div class="detail-item">
            <div class="detail-label">Batch Number</div>
            <div class="detail-val">{{ $item->batch_number ?? '-' }}</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Tanggal Kadaluarsa</div>
            <div class="detail-val">{{ $item->expiry_date ?? '-' }}</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Masa Garansi</div>
            <div class="detail-val">{{ !empty($item->warranty_months) ? $item->warranty_months . ' Bulan' : '-' }}</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Nomor Seri Utama</div>
            <div class="detail-val">{{ $item->serial_number ?? '-' }}</div>
        </div>
    </div>

    @if (!empty($item->description))
        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
            <div class="detail-label" style="margin-bottom: 0.4rem;">Deskripsi / Catatan</div>
            <p style="color: var(--text-muted); font-size: 0.9rem; line-height: 1.6; white-space: pre-line;">{{ $item->description }}</p>
        </div>
    @endif
</div>
@endsection

