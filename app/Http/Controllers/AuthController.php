<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Tenant;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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
     * Tampilkan form registrasi user baru (khusus Admin).
     */
    public function showRegisterForm(StarDust $stardust)
    {
        $warehouses = $this->getWarehouseList($stardust);

        return view('auth.register', compact('warehouses'));
    }

    /**
     * Proses registrasi / simpan user baru oleh Admin.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'role' => 'required|string|in:admin_platform,admin_gudang,staff_gudang,admin,staff',
            'id_warehouse' => 'nullable|integer',
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'role.required' => 'Peran (role) pengguna wajib dipilih.',
            'role.in' => 'Pilihan peran pengguna tidak valid.',
        ]);

        $isPlatform = in_array($request->role, ['admin_platform', 'admin'], true);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Str::random(40),
            'role' => $request->role,
            'id_warehouse' => $request->id_warehouse ? (int) $request->id_warehouse : null,
            'company_id' => $isPlatform ? null : Tenant::id(),
        ]);

        $roleLabel = $user->role_label;
        $message = "Pengguna baru {$user->name} ({$roleLabel}) berhasil didaftarkan!";

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
        } elseif ($user->isWarehouseScoped()) {
            $message = "Pengguna {$user->name} ({$roleLabel}) berhasil didaftarkan tanpa penugasan gudang. Anda dapat memberikan akses gudang di bawah ini.";
        }

        // Kirim tautan atur password ke email user.
        try {
            $status = Password::sendResetLink(['email' => $user->email]);
        } catch (\Throwable $e) {
            report($e);
            $status = null;
        }

        $message .= $status === Password::RESET_LINK_SENT
            ? " Tautan untuk mengatur password telah dikirim ke {$user->email}."
            : ' Namun email atur password gagal dikirim; minta pengguna memakai "Lupa password?" di halaman login.';

        return redirect()->route('staff.index')
            ->with('success', $message);
    }

    /**
     * Ambil daftar gudang dari StarDust Engine untuk pilihan dropdown.
     */
    private function getWarehouseList(StarDust $stardust)
    {
        try {
            $tenantId = Tenant::id();
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
            if ($user->isWarehouseScoped()) {
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
     * Tampilkan form "Lupa password".
     */
    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Kirim tautan reset password ke email.
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ]);

        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            report($e);
        }

        // Respons selalu sama agar email terdaftar tidak bisa ditebak.
        return back()->with('success', 'Jika email terdaftar, tautan untuk mengatur password telah dikirim.');
    }

    /**
     * Tampilkan form atur password baru (dari tautan email).
     */
    public function showResetPasswordForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    /**
     * Simpan password baru.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'email.required' => 'Email wajib diisi.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password, // di-hash otomatis oleh cast 'hashed'
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Password berhasil diatur. Silakan login.');
        }

        return back()->withErrors([
            'email' => 'Tautan tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru lewat "Lupa password?".',
        ])->onlyInput('email');
    }

    /**
     * Switch Application Mode (Normal vs Testing) — Khusus Admin Platform
     */
    public function toggleAppMode(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->isAdminPlatform()) {
            abort(403, 'Akses ditolak. Hanya Admin Platform yang dapat mengubah mode aplikasi.');
        }

        $currentMode = session('app_mode', env('APP_MODE', 'normal'));
        $newMode = $currentMode === 'testing' ? 'normal' : 'testing';

        session(['app_mode' => $newMode]);

        return back()->with('success', 'Mode Aplikasi berhasil diubah menjadi: '.strtoupper($newMode));
    }
}
