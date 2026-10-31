<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        $allowed = false;
        foreach ($roles as $role) {
            if ($role === 'admin_platform' && $user->isAdminPlatform()) {
                $allowed = true;
                break;
            }
            if ($role === 'admin_perusahaan' && $user->isAdminPerusahaan()) {
                $allowed = true;
                break;
            }
            if ($role === 'admin_gudang' && $user->isAdminGudang()) {
                $allowed = true;
                break;
            }
            if ($role === 'staff_gudang' && $user->isStaffGudang()) {
                $allowed = true;
                break;
            }
            if ($role === 'admin' && $user->isAdmin()) {
                $allowed = true;
                break;
            }
            if ($role === 'staff' && $user->isStaff()) {
                $allowed = true;
                break;
            }
            if ($user->role === $role) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden. Anda tidak memiliki izin untuk fitur ini.'], 403);
            }
            abort(403, 'Akses ditolak. Fitur ini hanya dapat diakses oleh peran: '.implode(', ', $roles));
        }

        return $next($request);
    }
}
