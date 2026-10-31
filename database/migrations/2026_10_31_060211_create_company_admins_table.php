<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel pivot company_admins: relasi Admin Perusahaan ↔ Companies.
 *
 * Satu user dengan role admin_perusahaan dapat mengelola satu atau lebih
 * perusahaan, dan satu perusahaan dapat memiliki lebih dari satu admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_admins', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            /**
             * Tingkatan akses di dalam perusahaan:
             *   owner   - bisa menghapus perusahaan & mengelola semua admin
             *   manager - bisa mengelola gudang & staff tapi tidak bisa hapus perusahaan
             */
            $table->string('access_level', 20)->default('manager');

            $table->timestamps();

            // Satu user hanya bisa memiliki satu entri per perusahaan
            $table->unique(['user_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_admins');
    }
};
