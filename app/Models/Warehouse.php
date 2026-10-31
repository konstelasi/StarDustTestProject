<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Warehouse bukan tabel SQL biasa — data gudang disimpan di StarDust Engine.
 * Model ini hanya digunakan sebagai intermediary untuk relasi pivot
 * warehouse_staff agar Eloquent BelongsToMany dapat bekerja.
 *
 * Kolom `id` di sini adalah StarDust entry ID gudang (integer).
 */
class Warehouse extends Model
{
    /**
     * Gudang tidak punya tabel Eloquent sendiri, namun kita perlu
     * sebuah "virtual" primary key agar pivot bekerja.
     * Gunakan tabel warehouse_staff sebagai referensi.
     */
    protected $table = 'warehouse_staff';

    protected $primaryKey = 'warehouse_id';

    public $timestamps = false;

    /**
     * Semua user yang terdaftar di gudang ini (admin gudang + staf).
     *
     * @return BelongsToMany<User>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            related: User::class,
            table: 'warehouse_staff',
            foreignPivotKey: 'warehouse_id',
            relatedPivotKey: 'user_id',
        )->withPivot('role')->withTimestamps();
    }

    /**
     * Hanya admin gudang yang terdaftar di gudang ini.
     *
     * @return BelongsToMany<User>
     */
    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'admin_gudang');
    }

    /**
     * Hanya staf gudang yang terdaftar di gudang ini.
     *
     * @return BelongsToMany<User>
     */
    public function staff(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'staff_gudang');
    }
}
