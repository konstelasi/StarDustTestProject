<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WarehouseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('users')->truncate();
        DB::table('entry_data')->truncate();
        DB::table('stardust_models')->truncate();
        DB::table('stardust_fields')->truncate();
        DB::table('stardust_slot_assignments')->truncate();
        DB::table('stardust_import_jobs')->truncate();
        DB::table('stardust_sync_queue')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->artisan('inventory:setup', ['--seed' => true]);
        $this->seed(\Database\Seeders\UserSeeder::class);
    }

    public function test_admin_can_access_create_warehouse_page(): void
    {
        $admin = User::where('role', 'admin')->first();
        $response = $this->actingAs($admin)->get('/warehouses/create');

        $response->assertStatus(200);
        $response->assertSee('Tambah Gudang Baru');
    }

    public function test_admin_can_create_new_warehouse_via_stardust(): void
    {
        $admin = User::where('role', 'admin')->first();

        $payload = [
            'name' => 'Gudang Hub Semarang',
            'code' => 'WH-SMG-04',
            'location' => 'Kawasan Industri Terboyo, Semarang',
            'manager' => 'Hendra Wijaya',
        ];

        $response = $this->actingAs($admin)->post('/warehouses', $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check StarDust entry creation
        $stardust = app(\StarDust\StarDust::class);
        $models = $stardust->listModels(1);
        $gudangModel = collect($models)->firstWhere('name', 'gudang');

        $page = $stardust->read(new \StarDust\Read\EntryQuery(
            tenantId: 1,
            modelId: $gudangModel->modelId,
            pageSize: 200,
        ));

        $names = collect($page->rows)->map(fn ($r) => $r->fields['name'] ?? null)->all();
        $this->assertContains('Gudang Hub Semarang', $names);
    }

    public function test_staff_cannot_access_create_warehouse_page(): void
    {
        $staff = User::where('role', 'user')->first() ?? User::where('role', 'staff')->first();
        $response = $this->actingAs($staff)->get('/warehouses/create');

        $response->assertStatus(403);
    }

    public function test_staff_cannot_store_new_warehouse(): void
    {
        $staff = User::where('role', 'user')->first() ?? User::where('role', 'staff')->first();

        $payload = [
            'name' => 'Gudang Unauthorized',
            'code' => 'WH-UNAUTH-99',
            'location' => 'Unknown',
            'manager' => 'Nobody',
        ];

        $response = $this->actingAs($staff)->post('/warehouses', $payload);

        $response->assertStatus(403);
    }
}
