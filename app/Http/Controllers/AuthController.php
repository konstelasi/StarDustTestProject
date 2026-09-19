<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            if ($user->isStaff() && $user->id_warehouse) {
                session(['active_warehouse' => $user->id_warehouse]);
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
