<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthAndModesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('users')->truncate();
        DB::table('warehouse_staff')->truncate();
        DB::table('entry_data')->truncate();
        DB::table('stardust_models')->truncate();
        DB::table('stardust_fields')->truncate();
        DB::table('stardust_pages')->truncate();
        DB::table('stardust_slot_assignments')->truncate();
        DB::table('stardust_import_jobs')->truncate();
        DB::table('stardust_sync_queue')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->artisan('inventory:setup', ['--seed' => true]);
        $this->seed(UserSeeder::class);
    }

    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('STARDUST');
        $response->assertSee('Inventory Management System');
    }

    public function test_admin_user_can_login_and_access_inventory(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@stardust.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/inventory');
        $this->assertAuthenticated();

        $inventoryResponse = $this->get('/inventory');
        $inventoryResponse->assertStatus(200);
        $inventoryResponse->assertSee('Admin System');
        $inventoryResponse->assertSee('Bulk Write');
    }

    public function test_staff_user_is_locked_to_assigned_warehouse(): void
    {
        $staff = User::where('email', 'staff@stardust.com')->first();
        $this->actingAs($staff);

        // Try to access warehouse 2
        $response = $this->get('/inventory?warehouse=2');

        $response->assertStatus(200);
        // Staff should be forced to warehouse 1 (Gudang Utama Jakarta)
        $response->assertSee('Gudang Tugas:');
        $response->assertSee('Gudang Utama Jakarta');
    }

    public function test_staff_user_cannot_access_delete_or_bulk_import(): void
    {
        $staff = User::where('email', 'staff@stardust.com')->first();
        $this->actingAs($staff);

        // Attempt bulk import
        $bulkResponse = $this->post('/inventory/bulk-import', [
            'count' => 10,
            'mode' => 'sync',
        ]);
        $bulkResponse->assertStatus(403);

        // Attempt delete
        $deleteResponse = $this->delete('/inventory/1');
        $deleteResponse->assertStatus(403);
    }

    public function test_invalid_credentials_returns_error(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@stardust.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $admin = User::where('email', 'admin@stardust.com')->first();
        $this->actingAs($admin);

        $response = $this->post('/logout');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_can_toggle_app_mode(): void
    {
        $admin = User::where('email', 'admin@stardust.com')->first();
        $this->actingAs($admin);

        $response = $this->post('/app-mode/toggle');
        $response->assertRedirect();

        $this->assertEquals('testing', session('app_mode'));
    }

    public function test_login_page_contains_register_link(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Daftar Akun Baru');
    }

    public function test_register_page_renders_with_roles(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Peran Pengguna (Role)');
        $response->assertSee('Staff Gudang (Staff)');
        $response->assertSee('Administrator System (Admin)');
    }

    public function test_guest_can_register_new_user_with_role_and_warehouse(): void
    {
        $response = $this->post('/register', [
            'name' => 'Bambang Subagyo',
            'email' => 'bambang@stardust.com',
            'role' => 'staff',
            'id_warehouse' => 1,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Bambang Subagyo',
            'email' => 'bambang@stardust.com',
            'role' => 'staff',
            'id_warehouse' => 1,
        ]);

        $user = User::where('email', 'bambang@stardust.com')->first();
        $this->assertDatabaseHas('warehouse_staff', [
            'user_id' => $user->id,
            'warehouse_id' => 1,
        ]);
    }

    public function test_guest_can_register_new_staff_without_warehouse_optional(): void
    {
        $response = $this->post('/register', [
            'name' => 'Siti Aminah',
            'email' => 'siti@stardust.com',
            'role' => 'staff',
            'id_warehouse' => '',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Siti Aminah',
            'email' => 'siti@stardust.com',
            'role' => 'staff',
            'id_warehouse' => null,
        ]);

        $user = User::where('email', 'siti@stardust.com')->first();
        $this->assertDatabaseMissing('warehouse_staff', [
            'user_id' => $user->id,
        ]);
    }

    public function test_staff_without_warehouse_sees_warning_and_no_default_gudang_jakarta(): void
    {
        $staffWithoutWh = User::create([
            'name' => 'Staff Tanpa Gudang',
            'email' => 'tanpagudang@stardust.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'id_warehouse' => null,
        ]);

        $this->actingAs($staffWithoutWh);

        $response = $this->get('/inventory');

        $response->assertStatus(200);
        $response->assertSee('Anda Belum Memiliki Gudang Penugasan');
        $response->assertSee('Belum Memiliki Gudang');
        $response->assertDontSee('Gudang Utama Jakarta');
    }
}
