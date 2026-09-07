<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'StarDust Warehouse & Inventory System')</title>
    <link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
</head>
<body>

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
            </nav>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="app-container">

        <!-- Top Warehouse Context Bar -->
        <div class="warehouse-context-bar" id="warehouse-context-bar">
            <div class="warehouse-info">
                <div class="warehouse-name">{{ $navCurrentWarehouse->name ?? 'Gudang' }}</div>
                <div class="warehouse-meta">Kode: {{ $navCurrentWarehouse->code ?? '-' }} • Manajer: {{ $navCurrentWarehouse->manager ?? '-' }} • {{ $navCurrentWarehouse->location ?? '' }}</div>
            </div>

            <form action="{{ route('inventory.index') }}" method="GET" class="warehouse-selector-form" id="warehouse-switch-form">
                <label for="warehouse-select" style="font-size: 0.85rem; color: var(--text-muted);">Pilih Gudang:</label>
                <select name="warehouse" id="warehouse-select" class="form-control" onchange="this.form.submit()" style="width: 200px;">
                    @foreach ($navWarehouses as $w)
                        <option value="{{ $w->id }}" {{ ($navCurrentWarehouse->id ?? null) == $w->id ? 'selected' : '' }}>
                            {{ $w->name }}
                        </option>
                    @endforeach
                </select>
            </form>
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