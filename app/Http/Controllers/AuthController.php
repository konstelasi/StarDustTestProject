<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use StarDust\Read\EntryQuery;
use StarDust\StarDust;

class AuthController extends Controller
{
    /**
     * Tampilkan halaman login.
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('inventory.index');
        }

        return view('auth.login');
    }

    /**
     * Tampilkan form registrasi user baru (khusus tamu / guest).
     */
    public function showRegisterForm(StarDust $stardust)
    {
        if (Auth::check()) {
            return redirect()->route('inventory.index');
        }

        $warehouses = $this->getWarehouseList($stardust);

        return view('auth.register', compact('warehouses'));
    }

    /**
     * Proses registrasi / simpan user baru.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|string|in:admin,staff',
            'id_warehouse' => 'nullable|integer',
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'role.required' => 'Peran (role) pengguna wajib dipilih.',
            'role.in' => 'Pilihan peran pengguna tidak valid.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'id_warehouse' => $request->id_warehouse ? (int) $request->id_warehouse : null,
        ]);

        $message = 'Registrasi akun berhasil! Silakan masuk dengan email dan password Anda.';

        if ($request->filled('id_warehouse')) {
            DB::table('warehouse_staff')->updateOrInsert(
                [
                    'warehouse_id' => (int) $request->id_warehouse,
                    'user_id' => $user->id,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        } elseif ($user->isStaff()) {
            $message = 'Registrasi akun berhasil! Akun Anda terdaftar sebagai staff tanpa penugasan gudang. Admin dapat memberikan akses gudang untuk Anda kapan saja.';
        }

        return redirect()->route('login')
            ->with('success', $message);
    }

    /**
     * Ambil daftar gudang dari StarDust Engine untuk pilihan dropdown.
     */
    private function getWarehouseList(StarDust $stardust)
    {
        try {
            $tenantId = (int) config('stardust.tenant_id', 1);
            $models = $stardust->listModels($tenantId);
            $gudangModel = collect($models)->firstWhere('name', config('stardust.warehouse_model_name', 'gudang'));

            if (! $gudangModel) {
                return collect();
            }

            $page = $stardust->read(new EntryQuery(
                tenantId: $tenantId,
                modelId: $gudangModel->modelId,
            ));

            return collect($page->rows)->map(function ($row) {
                $obj = (object) array_merge(['id' => $row->id], $row->fields);
                $obj->fields = $row->fields;

                return $obj;
            });
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Proses autentikasi login pengguna.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            $user = Auth::user();
            if ($user->isStaff()) {
                $staffWhId = DB::table('warehouse_staff')
                    ->where('user_id', $user->id)
                    ->value('warehouse_id');
                if ($staffWhId) {
                    session(['active_warehouse' => (int) $staffWhId]);
                } elseif ($user->id_warehouse) {
                    session(['active_warehouse' => (int) $user->id_warehouse]);
                } else {
                    session()->forget('active_warehouse');
                }
            }

            return redirect()->intended(route('inventory.index'))
                ->with('success', "Selamat datang kembali, {$user->name}!");
        }

        return back()->withErrors([
            'email' => 'Kredensial email atau password yang Anda masukkan salah.',
        ])->onlyInput('email');
    }

    /**
     * Proses logout pengguna.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar dari sistem.');
    }

    /**
     * Switch Application Mode (Normal vs Testing) — Khusus Admin
     */
    public function toggleAppMode(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Akses ditolak. Hanya Admin yang dapat mengubah mode aplikasi.');
        }

        $currentMode = session('app_mode', env('APP_MODE', 'normal'));
        $newMode = $currentMode === 'testing' ? 'normal' : 'testing';

        session(['app_mode' => $newMode]);

        return back()->with('success', 'Mode Aplikasi berhasil diubah menjadi: '.strtoupper($newMode));
    }
}
