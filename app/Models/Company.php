<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = ['name', 'code'];

    // -------------------------------------------------------------------------
    // Relasi HasMany
    // -------------------------------------------------------------------------

    /**
     * Semua user yang ber-company_id = perusahaan ini
     * (admin_gudang + staff_gudang yang ter-assign langsung).
     *
     * @return HasMany<User>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // -------------------------------------------------------------------------
    // Relasi Pivot: company_admins
    // -------------------------------------------------------------------------

    /**
     * Admin Perusahaan yang mengelola perusahaan ini.
     * Relasi Many-to-Many melalui tabel pivot company_admins.
     *
     * @return BelongsToMany<User>
     */
    public function companyAdmins(): BelongsToMany
    {
        return $this->belongsToMany(
            related: User::class,
            table: 'company_admins',
            foreignPivotKey: 'company_id',
            relatedPivotKey: 'user_id',
        )->withPivot('access_level')->withTimestamps();
    }

    /**
     * Shortcut: hanya admin dengan access_level = owner.
     *
     * @return BelongsToMany<User>
     */
    public function ownerAdmins(): BelongsToMany
    {
        return $this->companyAdmins()->wherePivot('access_level', 'owner');
    }

    /**
     * Shortcut: hanya admin dengan access_level = manager.
     *
     * @return BelongsToMany<User>
     */
    public function managerAdmins(): BelongsToMany
    {
        return $this->companyAdmins()->wherePivot('access_level', 'manager');
    }
}
