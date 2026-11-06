<?php

namespace App\Support;

use App\Models\Company;

class Tenant
{
    public const SESSION_KEY = 'active_company';

    /**
     * Admin gudang / staff gudang: tenant = company_id milik user.
     * Admin platform: tenant = perusahaan yang dipilih di halaman daftar perusahaan.
     * Fallback (console / belum memilih): STARDUST_TENANT_ID.
     */
    public static function id(): int
    {
        $user = auth()->user();

        if ($user) {
            if ($user->isAdminPerusahaan() && session(self::SESSION_KEY)) {
                return (int) session(self::SESSION_KEY);
            }

            if (! $user->isAdminPlatform() && $user->company_id) {
                return (int) $user->company_id;
            }

            if ($user->isAdminPlatform() && session(self::SESSION_KEY)) {
                return (int) session(self::SESSION_KEY);
            }
        }

        return (int) config('stardust.tenant_id', 1);
    }

    public static function company(): ?Company
    {
        return Company::find(self::id());
    }
}
