@extends('layouts.app')

@section('title', 'Ledger Barang - ' . ($activeWarehouse->name ?? 'Gudang') . ' • StarDust Engine')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Ledger Inventory & Logistik</h1>
        <p class="page-subtitle">Sistem manajemen barang & stok gudang berbasis StarDust Engine</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        @if (Auth::user()?->isAdmin())
            <details style="display: inline-block;">
                <summary class="btn btn-secondary" id="btn-bulk-import" style="cursor: pointer; list-style: none;">
                    Bulk Write
                </summary>
                <form action="{{ route('inventory.bulk-import') }}" method="POST" class="panel" style="position: absolute; z-index: 20; margin-top: 0.5rem; padding: 1rem; min-width: 260px;">
                    @csrf
                    <div style="margin-bottom: 0.75rem;">
                        <label style="display:block; font-size:0.75rem; color: var(--text-muted); margin-bottom:0.25rem;">Jumlah Barang</label>
                        <input type="number" name="count" class="form-control" value="100" min="1" max="5000" style="width:100%;" required>
                    </div>
                    <div style="margin-bottom: 0.75rem;">
                        <label style="display:block; font-size:0.75rem; color: var(--text-muted); margin-bottom:0.25rem;">Ukuran Chunk</label>
                        <input type="number" name="chunk_size" class="form-control" value="500" min="1" max="1000" style="width:100%;">
                    </div>
                    <div style="margin-bottom: 0.75rem;">
                        <label style="display:block; font-size:0.75rem; color: var(--text-muted); margin-bottom:0.25rem;">Delay Antar-Chunk (ms)</label>
                        <input type="number" name="delay_ms" class="form-control" value="0" min="0" max="5000" style="width:100%;">
                    </div>
                    <div style="margin-bottom: 0.75rem;">
                        <label style="display:block; font-size:0.75rem; color: var(--text-muted); margin-bottom:0.25rem;">Mode</label>
                        <select name="mode" class="form-control" style="width:100%;">
                            <option value="sync">Sync (langsung, maks 1000)</option>
                            <option value="async">Async (antrian, diproses Reconciler)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Jalankan</button>
                </form>
            </details>
        @endif
        <a href="{{ route('inventory.create') }}" class="btn btn-primary" id="btn-add-item">
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
        <div class="stat-value">Rp {{ number_format($stats['total_value'], 0, ',', '.') }}</div>
    </div>
    <div class="stat-card" id="stat-low-stock">
        <div class="stat-label">Peringatan Stok Rendah</div>
        <div class="stat-value" style="{{ $stats['low_stock_count'] > 0 ? 'color: #ef4444;' : '' }}">
            {{ number_format($stats['low_stock_count']) }}
        </div>
    </div>
</div>

<!-- Main Table Panel -->
<div class="panel">
    <!-- Filter Bar -->
    <form action="{{ route('inventory.index') }}" method="GET" class="filter-bar" id="inventory-filter-form">
        <input type="hidden" name="warehouse" value="{{ $warehouseId }}">
        @if($currentSort)
            <input type="hidden" name="sort" value="{{ $currentSort }}">
        @endif
        @if($currentDir)
            <input type="hidden" name="dir" value="{{ $currentDir }}">
        @endif

        <div class="filter-group">
            <div class="search-input-wrapper">
                <button type="submit" class="search-icon-btn" id="btn-search-submit" title="Cari">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       placeholder="Cari nama barang..." 
                       value="{{ $currentSearch }}"
                       id="search-input"
                       style="width: 260px;">
            </div>

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
                Hanya stok rendah
            </label>
        </div>

        <div class="filter-group">
            <div style="display: flex; align-items: center; gap: 0.35rem;">
                <label for="per-page-select" style="font-size: 0.825rem; color: var(--text-muted);">Tampilkan:</label>
                <select name="per_page" class="form-control" id="per-page-select" onchange="this.form.submit()" style="width: auto;">
                    <option value="10" {{ $currentPerPage == 10 ? 'selected' : '' }}>10 / hal</option>
                    <option value="20" {{ $currentPerPage == 20 ? 'selected' : '' }}>20 / hal</option>
                    <option value="50" {{ $currentPerPage == 50 ? 'selected' : '' }}>50 / hal</option>
                    <option value="100" {{ $currentPerPage == 100 ? 'selected' : '' }}>100 / hal</option>
                    <option value="200" {{ $currentPerPage == 200 ? 'selected' : '' }}>200 / hal</option>
                    <option value="500" {{ $currentPerPage == 500 ? 'selected' : '' }}>500 / hal</option>
                </select>
            </div>

            @if ($currentSearch || $currentCategory || $currentLowStock || $currentSort || $currentPerPage != 20)
                <a href="{{ route('inventory.index', ['warehouse' => $warehouseId]) }}" class="btn btn-secondary" id="btn-reset-filter">Reset</a>
            @endif
        </div>
    </form>

    <!-- Table -->
    <div class="table-container">
        <table class="custom-table" id="inventory-table">
            <thead>
                <tr>
                    @php
                        $makeSortUrl = function($field) use ($currentSort, $currentDir, $warehouseId) {
                            $nextDir = ($currentSort === $field && $currentDir === 'asc') ? 'desc' : 'asc';
                            return route('inventory.index', array_merge(request()->except(['cursor']), [
                                'warehouse' => $warehouseId,
                                'sort' => $field,
                                'dir' => $nextDir,
                            ]));
                        };
                    @endphp
                    <th>
                        <a href="{{ $makeSortUrl('sku') }}" class="sort-header-link {{ $currentSort === 'sku' ? 'sort-header-active' : '' }}" title="Sort SKU">
                            SKU / Entry
                            <span class="sort-icon">{{ $currentSort === 'sku' ? ($currentDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ $makeSortUrl('name') }}" class="sort-header-link {{ $currentSort === 'name' ? 'sort-header-active' : '' }}" title="Sort Nama Barang">
                            Nama Barang
                            <span class="sort-icon">{{ $currentSort === 'name' ? ($currentDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ $makeSortUrl('category') }}" class="sort-header-link {{ $currentSort === 'category' ? 'sort-header-active' : '' }}" title="Sort Kategori">
                            Kategori
                            <span class="sort-icon">{{ $currentSort === 'category' ? ($currentDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ $makeSortUrl('quantity') }}" class="sort-header-link {{ $currentSort === 'quantity' ? 'sort-header-active' : '' }}" title="Sort Stok">
                            Stok Gudang
                            <span class="sort-icon">{{ $currentSort === 'quantity' ? ($currentDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                        </a>
                    </th>
                    <th>Penyesuaian</th>
                    <th>
                        <a href="{{ $makeSortUrl('price') }}" class="sort-header-link {{ $currentSort === 'price' ? 'sort-header-active' : '' }}" title="Sort Harga">
                            Harga Satuan
                            <span class="sort-icon">{{ $currentSort === 'price' ? ($currentDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ $makeSortUrl('location') }}" class="sort-header-link {{ $currentSort === 'location' ? 'sort-header-active' : '' }}" title="Sort Lokasi Rak">
                            Lokasi Rak
                            <span class="sort-icon">{{ $currentSort === 'location' ? ($currentDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ $makeSortUrl('weight_kg') }}" class="sort-header-link {{ $currentSort === 'weight_kg' ? 'sort-header-active' : '' }}" title="Sort Berat (Num)">
                            Berat / Vol
                            <span class="sort-icon">{{ $currentSort === 'weight_kg' ? ($currentDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ $makeSortUrl('expiry_date') }}" class="sort-header-link {{ $currentSort === 'expiry_date' ? 'sort-header-active' : '' }}" title="Sort Expired Date (DT)">
                            Exp / Inbound
                            <span class="sort-icon">{{ $currentSort === 'expiry_date' ? ($currentDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                        </a>
                    </th>
                    <th>
                        <a href="{{ $makeSortUrl('supplier') }}" class="sort-header-link {{ $currentSort === 'supplier' ? 'sort-header-active' : '' }}" title="Sort Supplier">
                            Supplier
                            <span class="sort-icon">{{ $currentSort === 'supplier' ? ($currentDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                        </a>
                    </th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr id="item-row-{{ $item->id }}">
                        <td>
                            <span class="sku-code">{{ $item->sku ?? 'NO-SKU' }}</span>
                            <div style="font-size: 0.725rem; color: var(--text-dim); margin-top: 2px;">Entry #{{ $item->id }}</div>
                        </td>
                        <td>
                            <div style="font-weight: 500;">{{ $item->name ?? '-' }}</div>
                            @if(!empty($item->description))
                                <div style="font-size: 0.775rem; color: var(--text-muted); max-width: 240px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                    {{ $item->description }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <span class="badge-category-text">{{ $item->category ?? 'Umum' }}</span>
                        </td>
                        <td>
                            @php
                                $qty = (int)($item->quantity ?? 0);
                                $min = (int)($item->min_stock ?? 0);
                                $isLow = $qty <= $min;
                            @endphp
                            @if($isLow)
                                <div class="stock-low-tag">
                                    {{ $qty }} {{ $item->unit ?? 'Pcs' }} (Stok Rendah)
                                </div>
                            @else
                                <span class="stock-normal-text">{{ $qty }} {{ $item->unit ?? 'Pcs' }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="stock-stepper">
                                <form action="{{ route('inventory.stock-out', $item->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="stepper-btn" title="Kurangi Stok (-1)" id="btn-stock-minus-{{ $item->id }}">-</button>
                                </form>
                                <form action="{{ route('inventory.stock-in', $item->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="stepper-btn" title="Tambah Stok (+1)" id="btn-stock-plus-{{ $item->id }}">+</button>
                                </form>
                            </div>
                        </td>
                        <td>
                            <span>Rp {{ number_format((int)($item->price ?? 0), 0, ',', '.') }}</span>
                        </td>
                        <td>
                            <span style="color: var(--text-muted);">{{ $item->location ?? '-' }}</span>
                        </td>
                        <td>
                            <div style="font-size: 0.85rem; color: #a5f3fc; font-family: var(--font-mono);">
                                {{ isset($item->weight_kg) ? number_format((float)$item->weight_kg, 2) . ' kg' : '-' }}
                            </div>
                            @if(isset($item->volume_cbm))
                                <div style="font-size: 0.75rem; color: var(--text-dim); font-family: var(--font-mono);">
                                    {{ number_format((float)$item->volume_cbm, 3) }} m³
                                </div>
                            @endif
                        </td>
                        <td>
                            @if(!empty($item->expiry_date))
                                <div style="font-size: 0.825rem; color: #fef08a; font-family: var(--font-mono);">
                                    Exp: {{ date('d M Y', strtotime($item->expiry_date)) }}
                                </div>
                            @elseif(!empty($item->received_at))
                                <div style="font-size: 0.825rem; color: var(--text-muted); font-family: var(--font-mono);">
                                    In: {{ date('d M Y', strtotime($item->received_at)) }}
                                </div>
                            @else
                                <span style="color: var(--text-dim); font-size: 0.8rem;">-</span>
                            @endif
                        </td>
                        <td>
                            <span style="color: var(--text-muted);">{{ $item->supplier ?? '-' }}</span>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 0.35rem;">
                                <a href="{{ route('inventory.show', $item->id) }}" class="btn btn-secondary btn-sm" id="btn-show-{{ $item->id }}">
                                    Detail
                                </a>
                                <a href="{{ route('inventory.edit', $item->id) }}" class="btn btn-secondary btn-sm" id="btn-edit-{{ $item->id }}">
                                    Edit
                                </a>
                                @if (Auth::user()?->isAdmin())
                                    <form action="{{ route('inventory.destroy', $item->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('Hapus barang ini dari StarDust Engine?');" id="form-delete-{{ $item->id }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                            Hapus
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            Tidak ada barang inventory yang ditemukan di {{ $activeWarehouse->name ?? 'Gudang' }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- StarDust Cursor Pagination -->
    @if ($nextCursor)
        <div style="margin-top: 1.5rem; text-align: center;">
            <a href="{{ route('inventory.index', array_merge(request()->all(), ['cursor' => $nextCursor, 'warehouse' => $warehouseId])) }}" class="btn btn-secondary" id="btn-next-cursor">
                Halaman Berikutnya →
            </a>
        </div>
    @endif
</div>
@endsection

