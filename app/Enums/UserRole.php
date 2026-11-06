<?php

namespace App\Enums;

/**
 * Peran (role) pengguna pada sistem inventaris StarDust.
 *
 * Menentukan tingkatan akses mulai dari Super Admin lintas tenant (Admin Platform),
 * Admin Perusahaan (pengelola tenant via pivot company_admins),
 * Admin Gudang (pengelola gudang via pivot warehouse_staff),
 * hingga Staf Gudang (operasional harian transaksi barang).
 */
enum UserRole: string
{
    case AdminPlatform = 'admin_platform';
    case AdminPerusahaan = 'admin_perusahaan';
    case AdminGudang = 'admin_gudang';
    case StaffGudang = 'staff_gudang';

    /**
     * Dapatkan label yang ramah pengguna untuk antarmuka.
     */
    public function label(): string
    {
        return match ($this) {
            self::AdminPlatform => 'Admin Platform',
            self::AdminPerusahaan => 'Admin Perusahaan',
            self::AdminGudang => 'Admin Gudang',
            self::StaffGudang => 'Staff Gudang',
        };
    }

    /**
     * Dapatkan warna hex untuk lencana (badge) antarmuka.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::AdminPlatform => '#4f46e5',
            self::AdminPerusahaan => '#7c3aed',
            self::AdminGudang => '#0284c7',
            self::StaffGudang => '#6b7280',
        };
    }

    /**
     * Cek apakah peran ini memiliki otoritas administratif (platform, perusahaan, atau gudang).
     */
    public function isAdmin(): bool
    {
        return $this === self::AdminPlatform
            || $this === self::AdminPerusahaan
            || $this === self::AdminGudang;
    }

    /**
     * Cek apakah peran ini adalah staf operasional murni.
     */
    public function isStaff(): bool
    {
        return $this === self::StaffGudang;
    }

    /**
     * Cek apakah peran ini dibatasi lingkup gudang/perusahaan (bukan Admin Platform).
     */
    public function isWarehouseScoped(): bool
    {
        return $this !== self::AdminPlatform;
    }

    /**
     * Dapatkan seluruh nilai backing string enum.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Konversi nilai string menjadi enum dengan toleransi terhadap alias lama ('admin' atau 'staff').
     */
    public static function fromLegacy(string $value): self
    {
        return match ($value) {
            'admin', 'admin_platform' => self::AdminPlatform,
            'admin_perusahaan' => self::AdminPerusahaan,
            'admin_gudang' => self::AdminGudang,
            'staff', 'staff_gudang' => self::StaffGudang,
            default => self::from($value),
        };
    }

    /**
     * Coba parsing string menjadi enum dengan toleransi terhadap alias lama.
     */
    public static function tryFromLegacy(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return match ($value) {
            'admin', 'admin_platform' => self::AdminPlatform,
            'admin_perusahaan' => self::AdminPerusahaan,
            'admin_gudang' => self::AdminGudang,
            'staff', 'staff_gudang' => self::StaffGudang,
            default => self::tryFrom($value),
        };
    }
}

