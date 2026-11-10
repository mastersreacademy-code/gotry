<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_is_created_only_through_interactive_console_with_a_hashed_password(): void
    {
        $this->artisan('gotry:create-admin')->expectsQuestion('Admin full name', 'Test Admin')
            ->expectsQuestion('Admin email', ' ADMIN@example.test ')
            ->expectsQuestion('Password (12-128 characters)', 'long admin password!')
            ->expectsQuestion('Confirm password', 'long admin password!')->assertExitCode(0);
        $admin = User::firstOrFail();
        $this->assertSame('admin@example.test', $admin->email);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('long admin password!', $admin->password));
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_command_does_not_promote_an_existing_account_or_accept_a_weak_password(): void
    {
        User::create(['name' => 'Parent', 'email' => 'existing@example.test', 'password' => 'existing password!', 'role' => 'parent']);
        foreach (['existing@example.test', 'new@example.test'] as $email) {
            $this->artisan('gotry:create-admin')->expectsQuestion('Admin full name', 'Test Admin')
                ->expectsQuestion('Admin email', $email)->expectsQuestion('Password (12-128 characters)', 'short')
                ->expectsQuestion('Confirm password', 'short')->assertExitCode(1);
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('parent', User::firstOrFail()->role);
    }
}
