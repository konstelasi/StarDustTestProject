<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahkan kolom `role` ke warehouse_staff agar satu tabel pivot
 * bisa melayani dua jenis relasi:
 *   admin_gudang  – mengelola gudang (CRUD inventory, kelola staf)
 *   staff_gudang  – hanya operasional (stock in/out)
 *
 * Data lama yang tidak punya role akan dianggap staff_gudang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_staff', function (Blueprint $table) {
            $table->string('role', 30)
                ->default('staff_gudang')
                ->after('user_id')
                ->comment('admin_gudang | staff_gudang');
        });

        // Tandai admin_gudang yang sudah ada berdasarkan kolom role di tabel users
        DB::statement("
            UPDATE warehouse_staff ws
            INNER JOIN users u ON u.id = ws.user_id
            SET ws.role = u.role
            WHERE u.role IN ('admin_gudang', 'admin')
        ");
    }

    public function down(): void
    {
        Schema::table('warehouse_staff', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
