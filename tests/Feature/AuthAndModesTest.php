<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
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

    public function test_login_page_does_not_contain_public_register_link(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('id="link-register"', false);
    }

    public function test_admin_can_access_register_page_and_see_roles(): void
    {
        $admin = User::whereIn('role', ['admin_platform', 'admin'])->first();

        $response = $this->actingAs($admin)->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Peran Pengguna (Role)');
        $response->assertSee('Staff Gudang (Staff)');
        $response->assertSee('Administrator Platform');
        $response->assertSee('Admin Gudang');
    }

    public function test_guest_or_staff_cannot_access_register_page(): void
    {
        $guestResponse = $this->get('/register');
        $guestResponse->assertRedirect('/login');

        $staff = User::whereIn('role', ['staff_gudang', 'staff'])->first();
        if ($staff) {
            $staffResponse = $this->actingAs($staff)->get('/register');
            $staffResponse->assertStatus(403);
        }
    }

    public function test_admin_can_register_new_user_with_role_and_warehouse(): void
    {
        $admin = User::whereIn('role', ['admin_platform', 'admin'])->first();

        $response = $this->actingAs($admin)->post('/register', [
            'name' => 'Bambang Subagyo',
            'email' => 'bambang@stardust.com',
            'role' => 'admin_gudang',
            'id_warehouse' => 1,
        ]);

        $response->assertRedirect(route('staff.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Bambang Subagyo',
            'email' => 'bambang@stardust.com',
            'role' => 'admin_gudang',
            'id_warehouse' => 1,
        ]);

        $user = User::where('email', 'bambang@stardust.com')->first();
        $this->assertDatabaseHas('warehouse_staff', [
            'user_id' => $user->id,
            'warehouse_id' => 1,
        ]);
    }

    public function test_admin_can_register_new_staff_without_warehouse_optional(): void
    {
        $admin = User::whereIn('role', ['admin_platform', 'admin'])->first();

        $response = $this->actingAs($admin)->post('/register', [
            'name' => 'Siti Aminah',
            'email' => 'siti@stardust.com',
            'role' => 'staff_gudang',
            'id_warehouse' => '',
        ]);

        $response->assertRedirect(route('staff.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Siti Aminah',
            'email' => 'siti@stardust.com',
            'role' => 'staff_gudang',
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
            'role' => 'staff_gudang',
            'id_warehouse' => null,
        ]);

        $this->actingAs($staffWithoutWh);

        $response = $this->get('/inventory');

        $response->assertStatus(200);
        $response->assertSee('Anda Belum Memiliki Gudang Penugasan');
        $response->assertSee('Belum Memiliki Gudang');
        $response->assertDontSee('Gudang Utama Jakarta');
    }

    public function test_admin_gudang_can_manage_staff_but_cannot_register_or_toggle_app_mode(): void
    {
        $adminGudang = User::where('role', 'admin_gudang')->first();
        $this->actingAs($adminGudang);

        // Can access staff index
        $staffResponse = $this->get('/staff');
        $staffResponse->assertStatus(200);

        // Cannot access register
        $registerResponse = $this->get('/register');
        $registerResponse->assertStatus(403);

        // Cannot toggle app mode
        $appModeResponse = $this->post('/app-mode/toggle');
        $appModeResponse->assertStatus(403);
    }

    public function test_register_sends_password_setup_email_without_password_field(): void
    {
        Notification::fake();
        $admin = User::where('role', 'admin_platform')->first();

        $this->actingAs($admin)->post('/register', [
            'name' => 'User Tanpa Password',
            'email' => 'tanpapass@stardust.com',
            'role' => 'staff_gudang',
        ])->assertRedirect(route('staff.index'));

        $user = User::where('email', 'tanpapass@stardust.com')->first();
        $this->assertNotNull($user);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_user_can_set_password_via_reset_link(): void
    {
        $user = User::factory()->create(['email' => 'reset@stardust.com']);
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'reset@stardust.com',
            'password' => 'passwordBaru1',
            'password_confirmation' => 'passwordBaru1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('passwordBaru1', $user->fresh()->password));
    }

    public function test_admin_can_register_admin_perusahaan_and_sync_company_admins(): void
    {
        $admin = User::whereIn('role', ['admin_platform', 'admin'])->first();

        $response = $this->actingAs($admin)->post('/register', [
            'name' => 'Pak Joko Direktur',
            'email' => 'joko@perusahaan.com',
            'role' => 'admin_perusahaan',
            'company_id' => 1,
        ]);

        $response->assertRedirect(route('staff.index'));
        $response->assertSessionHas('success');

        $user = User::where('email', 'joko@perusahaan.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isAdminPerusahaan());
        $this->assertEquals(UserRole::AdminPerusahaan, $user->role);

        $this->assertDatabaseHas('company_admins', [
            'user_id' => $user->id,
            'company_id' => 1,
        ]);
    }
}
