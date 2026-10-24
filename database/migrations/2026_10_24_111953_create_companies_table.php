<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->timestamps();
        });

        // Perusahaan default = tenant lama (STARDUST_TENANT_ID), supaya data yang ada tidak hilang.
        $defaultId = (int) config('stardust.tenant_id', 1);
        DB::table('companies')->insert([
            'id' => $defaultId,
            'name' => 'Perusahaan Utama',
            'code' => 'PERUSAHAAN-UTAMA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id_warehouse')
                ->constrained('companies')->nullOnDelete();
        });

        DB::table('users')
            ->whereNotIn('role', ['admin_platform', 'admin'])
            ->update(['company_id' => $defaultId]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::dropIfExists('companies');
    }
};
