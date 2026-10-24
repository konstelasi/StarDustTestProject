@extends('layouts.app')

@section('title', 'Perusahaan • StarDust')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Perusahaan</h1>
        <p class="page-subtitle">Setiap perusahaan adalah satu tenant StarDust dan punya gudang sendiri</p>
    </div>
</div>

<div class="panel" style="margin-bottom: 1.5rem;">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="text-align: left; border-bottom: 1px solid var(--border-color);">
                <th style="padding: 0.6rem;">ID (Tenant)</th>
                <th style="padding: 0.6rem;">Nama</th>
                <th style="padding: 0.6rem;">Kode</th>
                <th style="padding: 0.6rem;">Pengguna</th>
                <th style="padding: 0.6rem;"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($companies as $c)
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 0.6rem;">{{ $c->id }}</td>
                    <td style="padding: 0.6rem;">{{ $c->name }}</td>
                    <td style="padding: 0.6rem;">{{ $c->code }}</td>
                    <td style="padding: 0.6rem;">{{ $c->users_count }}</td>
                    <td style="padding: 0.6rem; text-align: right;">
                        @if ($c->id === $activeId)
                            <span class="btn btn-secondary btn-sm" style="opacity: .7;">Aktif</span>
                        @else
                            <form action="{{ route('companies.select', $c) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">Pilih</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="panel" style="max-width: 650px;">
    <form action="{{ route('companies.store') }}" method="POST">
        @csrf
        <div style="font-weight: 600; margin-bottom: 1rem;">Tambah Perusahaan</div>
        <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <label for="name" style="display:block; font-size:.85rem; color: var(--text-muted); margin-bottom:.4rem;">Nama Perusahaan *</label>
                <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required style="width:100%;">
                @error('name') <span style="color:#f87171; font-size:.8rem;">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="code" style="display:block; font-size:.85rem; color: var(--text-muted); margin-bottom:.4rem;">Kode *</label>
                <input type="text" name="code" id="code" class="form-control" value="{{ old('code') }}" required style="width:100%;">
                @error('code') <span style="color:#f87171; font-size:.8rem;">{{ $message }}</span> @enderror
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Simpan Perusahaan</button>
    </form>
</div>
@endsection