<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'StarDust Warehouse & Inventory System')</title>
    <link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
</head>
<body>

    @php
        $currentAppMode = session('app_mode', env('APP_MODE', 'normal'));
        $authUser = Auth::user();
    @endphp

    <!-- Sidebar Navigation -->
    <aside class="app-sidebar">
        <div>
            <div class="brand-header">
                <div class="brand-title">STARDUST</div>
                <div class="brand-badge">Warehouse Engine</div>
            </div>

            <nav class="nav-menu">
                <a href="{{ route('inventory.index', ['warehouse' => session('active_warehouse', $navCurrentWarehouse->id ?? null)]) }}" class="nav-link {{ request()->routeIs('inventory.index') ? 'active' : '' }}" id="nav-inventory-link">
                    Ledger Barang
                </a>
                <a href="{{ route('inventory.create', ['warehouse' => session('active_warehouse', $navCurrentWarehouse->id ?? null)]) }}" class="nav-link {{ request()->routeIs('inventory.create') ? 'active' : '' }}" id="nav-create-link">
                    Registrasi Barang
                </a>
                @if ($authUser && $authUser->isAdmin())
                    <a href="{{ route('warehouses.create') }}" class="nav-link {{ request()->routeIs('warehouses.create') ? 'active' : '' }}" id="nav-create-warehouse-link">
                        Tambah Gudang
                    </a>
                    <a href="{{ route('staff.index') }}" class="nav-link {{ request()->routeIs('staff.index') ? 'active' : '' }}" id="nav-staff-index-link">
                    Atur Staff
                </a>
                @endif
            </nav>
        </div>

        @if ($authUser)
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-name">{{ $authUser->name }}</div>
                    @if ($authUser->isAdmin())
                        <span class="role-badge role-badge-admin">Admin System</span>
                    @else
                        <span class="role-badge role-badge-staff">Staff Gudang</span>
                    @endif
                </div>

                <form action="{{ route('logout') }}" method="POST" id="logout-form">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm" style="width: 100%; text-align: center;">
                        Keluar (Logout)
                    </button>
                </form>
            </div>
        @endif
    </aside>

    <!-- Main Content Area -->
    <main class="app-container">

        <!-- Top Context & Mode Bar -->
        <div class="warehouse-context-bar" id="warehouse-context-bar">
            <div class="warehouse-info">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div class="warehouse-name">{{ $navCurrentWarehouse->name ?? 'Gudang' }}</div>
                </div>
                <div class="warehouse-meta">
                    Kode: {{ $navCurrentWarehouse->code ?? '-' }} • Manajer: {{ $navCurrentWarehouse->manager ?? '-' }} • {{ $navCurrentWarehouse->location ?? '' }}
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 1rem;">
                @if ($authUser && $authUser->isAdmin())
                    <form action="{{ route('inventory.index') }}" method="GET" class="warehouse-selector-form" id="warehouse-switch-form">
                        <label for="warehouse-select" style="font-size: 0.85rem; color: var(--text-muted);">Pilih Gudang:</label>
                        <select name="warehouse" id="warehouse-select" class="form-control" onchange="this.form.submit()" style="width: 200px;">
                            @foreach ($navWarehouses as $w)
                                <option value="{{ $w->id }}" {{ ($navCurrentWarehouse->id ?? null) == $w->id ? 'selected' : '' }}>
                                    {{ $w->name }} @if(!empty($w->code)) ({{ $w->code }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </form>
                    <a href="{{ route('warehouses.create') }}" class="btn btn-secondary btn-sm" id="btn-add-warehouse-top" title="Tambah Gudang Baru">
                        Tambah Gudang
                    </a>
                @elseif ($navStaffWarehouses->count() > 0)
                    <form action="{{ route('inventory.index') }}" method="GET" class="warehouse-selector-form" id="staff-warehouse-switch-form">
                        <label for="staff-warehouse-select" style="font-size: 0.85rem; color: var(--text-muted);">Gudang Tugas:</label>
                        <select name="warehouse" id="staff-warehouse-select" class="form-control" onchange="this.form.submit()" style="width: 200px;">
                            @foreach ($navStaffWarehouses as $w)
                                <option value="{{ $w->id }}" {{ ($navCurrentWarehouse->id ?? null) == $w->id ? 'selected' : '' }}>
                                    {{ $w->name }} @if(!empty($w->code)) ({{ $w->code }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </form>
                @else
                    <span style="font-size: 0.825rem; color: var(--text-muted); background: #f1f5f9; padding: 4px 10px; border-radius: 4px;">
                        Belum ada gudang yang ditugaskan
                    </span>
                @endif
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success" id="success-alert">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger" id="error-alert">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

</body>
</html>
