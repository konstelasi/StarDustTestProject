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
                <div>
                    <div class="brand-title">STARDUST</div>
                    <span class="brand-badge">Warehouse Engine</span>
                </div>
            </div>

            <nav class="nav-menu">
                <a href="{{ route('inventory.index', ['warehouse' => session('active_warehouse', $navCurrentWarehouse->id ?? null)]) }}" class="nav-link {{ request()->routeIs('inventory.index') ? 'active' : '' }}" id="nav-inventory-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Ledger Barang
                </a>
                <a href="{{ route('inventory.create', ['warehouse' => session('active_warehouse', $navCurrentWarehouse->id ?? null)]) }}" class="nav-link {{ request()->routeIs('inventory.create') ? 'active' : '' }}" id="nav-create-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Registrasi Barang
                </a>
            </nav>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="app-container">

        <!-- Top Warehouse Context Selector -->
        {{--
            $navWarehouses / $navCurrentWarehouse are injected by
            App\Providers\AppServiceProvider's view composer, which reads
            the real 'gudang' entries from the StarDust engine (single
            tenant) — NOT a static config array.
        --}}
        <div class="warehouse-context-bar" id="warehouse-context-bar">
            <div class="warehouse-info">
                <div class="warehouse-icon-badge">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <div class="warehouse-name">{{ $navCurrentWarehouse->name ?? 'Gudang' }}</div>
                    <div class="warehouse-meta">Kode: {{ $navCurrentWarehouse->code ?? '-' }} • Manajer: {{ $navCurrentWarehouse->manager ?? '-' }} • {{ $navCurrentWarehouse->location ?? '' }}</div>
                </div>
            </div>

            <form action="{{ route('inventory.index') }}" method="GET" class="warehouse-selector-form" id="warehouse-switch-form">
                <label for="warehouse-select" style="font-family: var(--font-heading); font-size: 0.8rem; color: var(--brass);">Pilih Gudang:</label>
                <select name="warehouse" id="warehouse-select" class="form-control" onchange="this.form.submit()" style="width: 220px; font-weight: 600;">
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
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger" id="error-alert">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

</body>
</html>