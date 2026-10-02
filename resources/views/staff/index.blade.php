@extends('layouts.app')

@section('title', 'Akses Staff Gudang - StarDust')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Akses Staff Gudang</h1>
        <p class="page-subtitle">Kelola penugasan staff yang berhak mengelola setiap gudang secara langsung</p>
    </div>
</div>

@if (isset($unassignedStaff) && $unassignedStaff->isNotEmpty())
    <div class="panel" style="margin-bottom: 1.5rem; border-left: 4px solid #f59e0b;" id="unassigned-staff-panel">
        <h3 style="margin-top: 0; margin-bottom: 0.5rem; font-size: 1rem; color: #b45309; display: flex; align-items: center; gap: 0.5rem;">
            <span>⚠️</span> Staff Belum Memiliki Penugasan Gudang ({{ $unassignedStaff->count() }})
        </h3>
        <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.75rem;">
            Staff di bawah ini telah terdaftar tetapi belum memiliki gudang penugasan. Pilih nama mereka pada dropdown <strong>Kelola Akses Staff</strong> pada gudang yang sesuai di bawah lalu simpan.
        </p>
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
            @foreach ($unassignedStaff as $us)
                <span style="background: #fffbeb; color: #b45309; border: 1px solid #fcd34d; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.85rem; font-weight: 500;">
                    👤 {{ $us->name }} &lt;{{ $us->email }}&gt;
                </span>
            @endforeach
        </div>
    </div>
@endif

<div class="panel">
    <div class="table-container" style="overflow-x: visible;">
        <table class="custom-table" id="staff-assignment-table">
            <thead>
                <tr>
                    <th style="width: 22%;">Nama Gudang</th>
                    <th style="width: 12%;">Kode</th>
                    <th style="width: 16%;">Lokasi</th>
                    <th>Kelola Akses Staff</th>
                </tr>
            </thead>
            <tbody>
                @forelse($warehouses as $warehouse)
                <tr>
                    <td style="vertical-align: middle;">
                        <strong style="color: var(--text-main);">{{ $warehouse->name ?? $warehouse->fields['name'] ?? '-' }}</strong>
                    </td>
                    <td style="vertical-align: middle;">
                        <span class="badge badge-secondary" style="background:#f1f5f9;color:#475569;padding:0.25rem 0.6rem;border-radius:4px;font-family:monospace;font-size:0.8rem;">
                            {{ $warehouse->code ?? $warehouse->fields['code'] ?? '-' }}
                        </span>
                    </td>
                    <td style="vertical-align: middle;">{{ $warehouse->location ?? $warehouse->fields['location'] ?? '-' }}</td>
                    <td style="vertical-align: middle;">
                        <form action="{{ route('staff.update', $warehouse->id) }}" method="POST" style="display:flex; align-items:center; gap:0.75rem; width: 100%;">
                            @csrf
                            @method('PUT')

                            <div class="ms-dropdown" style="position:relative; flex:1;">
                                <button type="button" class="ms-toggle" onclick="msToggle(this)"
                                    style="width:100%; text-align:left; padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; background:#fff; cursor:pointer; display:flex; justify-content:space-between; align-items:center; font-size: 0.875rem;">
                                    <span class="ms-label">
                                        @php
                                            $assignedIds = $warehouse->assigned_staff_ids ?? [];
                                            $selectedNames = $staffList->whereIn('id', $assignedIds)->pluck('name')->toArray();
                                        @endphp
                                        @if(count($selectedNames) === 1)
                                            {{ $selectedNames[0] }}
                                        @elseif(count($selectedNames) > 1)
                                            {{ count($selectedNames) }} staff ({{ implode(', ', $selectedNames) }})
                                        @else
                                            -- Pilih Staff --
                                        @endif
                                    </span>
                                    <span style="color:#9ca3af; font-size: 0.75rem;">▾</span>
                                </button>

                                <div class="ms-panel" style="display:none; position:absolute; top:100%; left:0; right:0; margin-top:4px; background:#fff; border:1px solid #cbd5e1; border-radius:6px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.15); max-height:260px; overflow:hidden; z-index:100;">

                                    <input
                                        type="text"
                                        placeholder="Cari staff..."
                                        class="ms-search"
                                        oninput="msFilter(this)"
                                        style="width:100%; padding: 0.55rem 0.75rem; border:none; border-bottom:1px solid #e2e8f0; outline:none; box-sizing:border-box; font-size: 0.85rem;"
                                    >

                                    <div class="ms-options" style="max-height:200px; overflow-y:auto;">
                                        @forelse($staffList as $staff)
                                            <label style="display:flex; align-items:center; gap:0.5rem; padding: 0.5rem 0.75rem; cursor:pointer; font-weight:400; font-size: 0.85rem; text-align: left;">
                                                <input
                                                    type="checkbox"
                                                    name="staff_ids[]"
                                                    value="{{ $staff->id }}"
                                                    onchange="msUpdateLabel(this)"
                                                    {{ in_array($staff->id, $assignedIds) ? 'checked' : '' }}
                                                    style="width:16px; height:16px;"
                                                >
                                                {{ $staff->name }}
                                            </label>
                                        @empty
                                            <p style="color:#9ca3af; margin:0; padding:0.75rem; font-size:0.85rem;" class="ms-empty">Belum ada staff terdaftar.</p>
                                        @endforelse
                                    </div>
                                    <p class="ms-no-result" style="display:none; color:#9ca3af; margin:0; padding:0.75rem; font-size:0.85rem;">Staff tidak ditemukan.</p>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-sm btn-primary" style="white-space: nowrap;">
                                Simpan Akses
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                        Belum ada data gudang terdaftar.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
function msToggle(btn) {
    const panel = btn.parentElement.querySelector('.ms-panel');
    const isOpen = panel.style.display === 'block';

    document.querySelectorAll('.ms-panel').forEach(p => {
        if (p !== panel) p.style.display = 'none';
    });

    panel.style.display = isOpen ? 'none' : 'block';

    if (!isOpen) {
        const search = panel.querySelector('.ms-search');
        if (search) setTimeout(() => search.focus(), 50);
    }
}

function msUpdateLabel(checkbox) {
    const dropdown = checkbox.closest('.ms-dropdown');
    const label = dropdown.querySelector('.ms-label');
    const checked = dropdown.querySelectorAll('input[type="checkbox"]:checked');

    if (checked.length === 0) {
        label.textContent = '-- Pilih Staff --';
    } else if (checked.length === 1) {
        label.textContent = checked[0].parentElement.textContent.trim();
    } else {
        const names = Array.from(checked).map(cb => cb.parentElement.textContent.trim());
        label.textContent = checked.length + ' staff (' + names.join(', ') + ')';
    }
}

function msFilter(input) {
    const panel = input.closest('.ms-panel');
    const keyword = input.value.toLowerCase().trim();
    const labels = panel.querySelectorAll('.ms-options label');
    let visibleCount = 0;

    labels.forEach(label => {
        const name = label.textContent.toLowerCase();
        const match = name.includes(keyword);
        label.style.display = match ? 'flex' : 'none';
        if (match) visibleCount++;
    });

    const noResult = panel.querySelector('.ms-no-result');
    if (noResult) {
        noResult.style.display = visibleCount === 0 ? 'block' : 'none';
    }
}

document.addEventListener('click', function (e) {
    if (!e.target.closest('.ms-dropdown')) {
        document.querySelectorAll('.ms-panel').forEach(panel => {
            panel.style.display = 'none';
        });
    }
});
</script>
@endsection