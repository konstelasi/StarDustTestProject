@extends('layouts.app')

@section('title', 'Ledger Barang - ' . ($activeWarehouse['name'] ?? 'Gudang') . ' • StarDust Engine')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Ledger Inventory & Logistik</h1>
        <p class="page-subtitle">Sistem Manajemen Barang & Stok Gudang berbasis <strong>StarDust Engine</strong> (SDDPG Multi-Tenant Engine)</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <form action="{{ route('inventory.bulk-import') }}" method="POST" style="display: inline;">
            @csrf
            <button type="submit" class="btn btn-secondary" id="btn-bulk-import" title="Simulasi Pengadaan Barang Skala Besar">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Simulasi bulkWrite()
            </button>
        </form>
        <a href="{{ route('inventory.create') }}" class="btn btn-primary" id="btn-add-item">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Registrasi Barang Baru
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card" id="stat-total-items">
        <div class="stat-label">Total Jenis Barang</div>
        <div class="stat-value">{{ number_format($stats['total_items']) }}</div>
    </div>
    <div class="stat-card" id="stat-total-stock">
        <div class="stat-label">Total Stok Fisik</div>
        <div class="stat-value">{{ number_format($stats['total_stock']) }}</div>
    </div>
    <div class="stat-card" id="stat-total-value">
        <div class="stat-label">Nilai Aset Gudang</div>
        <div class="stat-value" style="color: var(--brass-light);">Rp {{ number_format($stats['total_value'], 0, ',', '.') }}</div>
    </div>
    <div class="stat-card" id="stat-low-stock">
        <div class="stat-label">Peringatan Stok Rendah</div>
        <div class="stat-value" style="color: #f87171;">{{ number_format($stats['low_stock_count']) }}</div>
    </div>
</div>

<!-- Main Table Panel -->
<div class="panel">
    <!-- Filter & Search Bar -->
    <form action="{{ route('inventory.index') }}" method="GET" class="filter-bar" id="inventory-filter-form">
        <input type="hidden" name="warehouse" value="{{ $tenantId }}">
        
        <div class="filter-group">
            <input type="text" 
                   name="search" 
                   class="form-control" 
                   placeholder="Cari nama barang (Prefix StarDust)..." 
                   value="{{ $currentSearch }}"
                   id="search-input"
                   style="width: 280px;">

            <select name="category" class="form-control" id="category-select" onchange="this.form.submit()">
                <option value="">Semua Kategori</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat }}" {{ $currentCategory == $cat ? 'selected' : '' }}>
                        {{ $cat }}
                    </option>
                @endforeach
            </select>

            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-muted); cursor: pointer;">
                <input type="checkbox" 
                       name="low_stock" 
                       value="1" 
                       {{ $currentLowStock ? 'checked' : '' }} 
                       onchange="this.form.submit()"
                       id="low-stock-checkbox">
                Hanya Stok Rendah
            </label>
        </div>

        <div class="filter-group">
            <button type="submit" class="btn btn-secondary" id="btn-search-submit">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Filter
            </button>
            @if ($currentSearch || $currentCategory || $currentLowStock)
                <a href="{{ route('inventory.index', ['warehouse' => $tenantId]) }}" class="btn btn-secondary" style="color: var(--text-dim);" id="btn-reset-filter">Reset</a>
            @endif
        </div>
    </form>

    <!-- Table -->
    <div class="table-container">
        <table class="custom-table" id="inventory-table">
            <thead>
                <tr>
                    <th>SKU / Entry</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th>Stok Gudang</th>
                    <th>Penyesuaian Stok</th>
                    <th>Harga Satuan</th>
                    <th>Lokasi Rak</th>
                    <th>Supplier</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr id="item-row-{{ $item->id }}">
                        <td>
                            <span class="sku-code">{{ $item->sku ?? 'NO-SKU' }}</span>
                            <div style="font-family: var(--font-mono); font-size: 0.7rem; color: var(--text-dim); margin-top: 2px;">Entry #{{ $item->id }}</div>
                        </td>
                        <td>
                            <strong style="color: var(--parchment); font-family: var(--font-heading);">{{ $item->name ?? '-' }}</strong>
                            @if(!empty($item->description))
                                <div style="font-size: 0.8rem; color: var(--text-dim); max-width: 240px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                    {{ $item->description }}
                                </div>
                            @endif
                            @if(!empty($item->expiry_date) || !empty($item->warranty_months))
                                <div style="font-size: 0.72rem; color: var(--brass); margin-top: 2px;">
                                    @if(!empty($item->expiry_date)) Exp: {{ $item->expiry_date }} @endif
                                    @if(!empty($item->warranty_months)) Garansi: {{ $item->warranty_months }} bln @endif
                                </div>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-category">{{ $item->category ?? 'Umum' }}</span>
                        </td>
                        <td>
                            @php
                                $qty = (int)($item->quantity ?? 0);
                                $min = (int)($item->min_stock ?? 0);
                                $isLow = $qty <= $min;
                            @endphp
                            <span class="badge {{ $isLow ? 'badge-low' : 'badge-normal' }}">
                                {{ $qty }} {{ $item->unit ?? 'Pcs' }}
                            </span>
                            @if($isLow)
                                <div style="font-size: 0.7rem; color: #f87171; margin-top: 2px;">Min: {{ $min }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="stock-control">
                                <form action="{{ route('inventory.stock-out', $item->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="stock-btn" title="Kurangi Stok (-1)" id="btn-stock-minus-{{ $item->id }}">-</button>
                                </form>
                                <form action="{{ route('inventory.stock-in', $item->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="stock-btn" title="Tambah Stok (+1)" id="btn-stock-plus-{{ $item->id }}">+</button>
                                </form>
                            </div>
                        </td>
                        <td>
                            <span style="font-family: var(--font-mono); font-weight: 600;">Rp {{ number_format((int)($item->price ?? 0), 0, ',', '.') }}</span>
                        </td>
                        <td>
                            <span style="color: var(--text-muted);">{{ $item->location ?? '-' }}</span>
                        </td>
                        <td>
                            <span style="color: var(--text-muted);">{{ $item->supplier ?? '-' }}</span>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 0.4rem;">
                                <a href="{{ route('inventory.show', $item->id) }}" class="btn btn-secondary btn-sm" title="Detail" id="btn-show-{{ $item->id }}">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Detail
                                </a>
                                <a href="{{ route('inventory.edit', $item->id) }}" class="btn btn-secondary btn-sm" style="color: var(--brass-light);" title="Edit" id="btn-edit-{{ $item->id }}">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    Edit
                                </a>
                                <form action="{{ route('inventory.destroy', $item->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('Hapus barang ini dari StarDust Engine?');" id="form-delete-{{ $item->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 3rem; color: var(--text-dim);">
                            Tidak ada barang inventory yang ditemukan di {{ $activeWarehouse['name'] }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- StarDust Cursor Pagination -->
    @if ($nextCursor)
        <div style="margin-top: 1.5rem; text-align: center;">
            <a href="{{ route('inventory.index', array_merge(request()->all(), ['cursor' => $nextCursor, 'warehouse' => $tenantId])) }}" class="btn btn-secondary" id="btn-next-cursor">
                Muat Halaman Berikutnya (StarDust Cursor Pagination) →
            </a>
        </div>
    @endif
</div>
@endsection
