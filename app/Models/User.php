<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'id_warehouse', 'company_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // -------------------------------------------------------------------------
    // Konstanta Role
    // -------------------------------------------------------------------------

    /** Super-admin: akses lintas semua perusahaan & gudang. */
    public const ROLE_ADMIN_PLATFORM = 'admin_platform';

    /** Admin Perusahaan: mengelola satu atau lebih perusahaan via pivot company_admins. */
    public const ROLE_ADMIN_PERUSAHAAN = 'admin_perusahaan';

    /** Admin Gudang: mengelola satu atau lebih gudang via pivot warehouse_staff. */
    public const ROLE_ADMIN_GUDANG = 'admin_gudang';

    /** Staf Gudang: operasional harian (stock in/out) pada gudang yang ditugaskan. */
    public const ROLE_STAFF_GUDANG = 'staff_gudang';

    // -------------------------------------------------------------------------
    // Role Checks
    // -------------------------------------------------------------------------

    /** Admin Platform — akses penuh lintas semua tenant. */
    public function isAdminPlatform(): bool
    {
        return in_array($this->role, ['admin_platform', 'admin'], true);
    }

    /** Admin Perusahaan — mengelola perusahaan tertentu. */
    public function isAdminPerusahaan(): bool
    {
        return $this->role === self::ROLE_ADMIN_PERUSAHAAN;
    }

    /** Admin Gudang — mengelola gudang tertentu di dalam suatu perusahaan. */
    public function isAdminGudang(): bool
    {
        return $this->role === self::ROLE_ADMIN_GUDANG;
    }

    /** Staf Gudang — akses operasional saja. */
    public function isStaffGudang(): bool
    {
        return in_array($this->role, ['staff_gudang', 'staff'], true);
    }

    /**
     * True jika user memiliki hak admin di level mana pun
     * (platform, perusahaan, atau gudang).
     */
    public function isAdmin(): bool
    {
        return $this->isAdminPlatform()
            || $this->isAdminPerusahaan()
            || $this->isAdminGudang();
    }

    /** True jika user adalah staf (bukan admin level mana pun). */
    public function isStaff(): bool
    {
        return $this->isStaffGudang();
    }

    /**
     * True jika user terbatas pada lingkup perusahaan tertentu
     * (bukan Admin Platform).
     */
    public function isWarehouseScoped(): bool
    {
        return ! $this->isAdminPlatform();
    }

    // -------------------------------------------------------------------------
    // Label Role
    // -------------------------------------------------------------------------

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin_platform', 'admin' => 'Admin Platform',
            'admin_perusahaan' => 'Admin Perusahaan',
            'admin_gudang' => 'Admin Gudang',
            'staff_gudang', 'staff' => 'Staff Gudang',
            default => ucfirst($this->role),
        };
    }

    // -------------------------------------------------------------------------
    // Relasi ke Company (BelongsTo — company_id di kolom users)
    // -------------------------------------------------------------------------

    /**
     * Perusahaan langsung user ini (untuk admin_gudang & staff_gudang).
     * Admin Perusahaan menggunakan relasi companyAdmins() di bawah.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // -------------------------------------------------------------------------
    // Relasi Pivot: company_admins (Admin Perusahaan ↔ Companies)
    // -------------------------------------------------------------------------

    /**
     * Semua perusahaan yang dikelola user ini sebagai Admin Perusahaan.
     * Diakses via tabel pivot `company_admins`.
     *
     * Contoh penggunaan:
     *   $user->managedCompanies()->attach($companyId, ['access_level' => 'owner']);
     *   $user->managedCompanies()->detach($companyId);
     *   $user->managedCompanies  // collection of Company
     *
     * @return BelongsToMany<Company>
     */
    public function managedCompanies(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Company::class,
            table: 'company_admins',
            foreignPivotKey: 'user_id',
            relatedPivotKey: 'company_id',
        )->withPivot('access_level')->withTimestamps();
    }

    /**
     * Hanya perusahaan yang dikelola sebagai owner.
     *
     * @return BelongsToMany<Company>
     */
    public function ownedCompanies(): BelongsToMany
    {
        return $this->managedCompanies()->wherePivot('access_level', 'owner');
    }

    // -------------------------------------------------------------------------
    // Relasi Pivot: warehouse_staff (Admin Gudang & Staf ↔ Warehouses)
    // -------------------------------------------------------------------------

    /**
     * Semua warehouse (StarDust entry ID) yang ditugaskan ke user ini,
     * baik sebagai admin_gudang maupun staff_gudang.
     *
     * Diakses via tabel pivot `warehouse_staff` (kolom: warehouse_id, user_id, role).
     *
     * Contoh penggunaan:
     *   $user->assignedWarehouses()->attach($warehouseId, ['role' => 'admin_gudang']);
     *   $user->assignedWarehouses()->sync([1 => ['role' => 'staff_gudang']]);
     *   $user->warehouseIds()  // array of int
     *
     * @return BelongsToMany<Warehouse>
     */
    public function assignedWarehouses(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Warehouse::class,
            table: 'warehouse_staff',
            foreignPivotKey: 'user_id',
            relatedPivotKey: 'warehouse_id',
        )->withPivot('role')->withTimestamps();
    }

    /**
     * Hanya gudang tempat user ini berperan sebagai admin_gudang.
     *
     * @return BelongsToMany<Warehouse>
     */
    public function adminWarehouses(): BelongsToMany
    {
        return $this->assignedWarehouses()->wherePivot('role', 'admin_gudang');
    }

    /**
     * Hanya gudang tempat user ini berperan sebagai staff_gudang.
     *
     * @return BelongsToMany<Warehouse>
     */
    public function staffWarehouses(): BelongsToMany
    {
        return $this->assignedWarehouses()->wherePivot('role', 'staff_gudang');
    }

    /**
     * Kembalikan daftar warehouse_id yang ditugaskan ke user ini.
     *
     * @return int[]
     */
    public function warehouseIds(): array
    {
        return \Illuminate\Support\Facades\DB::table('warehouse_staff')
            ->where('user_id', $this->id)
            ->pluck('warehouse_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }
}
