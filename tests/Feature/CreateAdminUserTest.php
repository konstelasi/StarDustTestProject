<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminUserTest extends TestCase
{
    use DatabaseTransactions;

    private const NEW_EMAIL = 'new-admin@create-admin-test.example';

    private const PASSWORD = 'a-long-unique-passphrase';

    public function test_creates_a_platform_admin_with_a_hashed_password(): void
    {
        $this->artisan('user:create-admin', ['--name' => 'New Admin', '--email' => self::NEW_EMAIL])
            ->expectsQuestion('Password (at least 12 characters)', self::PASSWORD)
            ->expectsQuestion('Confirm password', self::PASSWORD)
            ->expectsOutputToContain('created')
            ->assertSuccessful();

        $user = User::where('email', self::NEW_EMAIL)->first();

        $this->assertNotNull($user);
        $this->assertSame('New Admin', $user->name);
        $this->assertSame(UserRole::AdminPlatform, $user->role);
        $this->assertNotSame(self::PASSWORD, $user->password);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
    }

    public function test_asks_for_name_and_email_when_no_options_are_given(): void
    {
        $this->artisan('user:create-admin')
            ->expectsQuestion('Name', 'Asked Admin')
            ->expectsQuestion('Email', self::NEW_EMAIL)
            ->expectsQuestion('Password (at least 12 characters)', self::PASSWORD)
            ->expectsQuestion('Confirm password', self::PASSWORD)
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => self::NEW_EMAIL, 'name' => 'Asked Admin']);
    }

    public function test_rejects_an_email_that_is_already_registered(): void
    {
        User::factory()->create(['email' => self::NEW_EMAIL, 'name' => 'Existing User']);

        $this->artisan('user:create-admin', ['--name' => 'New Admin', '--email' => self::NEW_EMAIL])
            ->expectsQuestion('Password (at least 12 characters)', self::PASSWORD)
            ->expectsQuestion('Confirm password', self::PASSWORD)
            ->expectsOutputToContain('The email has already been taken.')
            ->assertFailed();

        $this->assertDatabaseHas('users', ['email' => self::NEW_EMAIL, 'name' => 'Existing User']);
        $this->assertDatabaseMissing('users', ['name' => 'New Admin']);
    }

    public function test_rejects_a_password_shorter_than_12_characters(): void
    {
        $this->artisan('user:create-admin', ['--name' => 'New Admin', '--email' => self::NEW_EMAIL])
            ->expectsQuestion('Password (at least 12 characters)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->expectsOutputToContain('The password field must be at least 12 characters.')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => self::NEW_EMAIL]);
    }

    public function test_rejects_a_password_confirmation_that_does_not_match(): void
    {
        $this->artisan('user:create-admin', ['--name' => 'New Admin', '--email' => self::NEW_EMAIL])
            ->expectsQuestion('Password (at least 12 characters)', self::PASSWORD)
            ->expectsQuestion('Confirm password', 'a-different-passphrase')
            ->expectsOutputToContain('The password field confirmation does not match.')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => self::NEW_EMAIL]);
    }

    public function test_rejects_an_invalid_email_address(): void
    {
        $this->artisan('user:create-admin', ['--name' => 'New Admin', '--email' => 'not-an-email'])
            ->expectsQuestion('Password (at least 12 characters)', self::PASSWORD)
            ->expectsQuestion('Confirm password', self::PASSWORD)
            ->expectsOutputToContain('The email field must be a valid email address.')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['name' => 'New Admin']);
    }
}
