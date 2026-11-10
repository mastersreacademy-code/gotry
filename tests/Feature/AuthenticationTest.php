<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_and_provider_can_register_and_reach_their_own_dashboard(): void
    {
        foreach (['parent', 'provider'] as $role) {
            $response = $this->post('/register', $this->credentials($role));
            $response->assertRedirect('/dashboard');
            $user = User::where('email', $role.'@example.test')->firstOrFail();
            $this->assertAuthenticatedAs($user);
            $this->assertTrue(Hash::check('a long password 123!', $user->password));
            $this->get('/dashboard')->assertRedirect('/'.$role);
            $this->get('/'.$role)->assertOk()->assertSee('Hello, Test User');
            $this->get('/'.($role === 'parent' ? 'provider' : 'parent'))->assertForbidden();
            $this->get('/admin')->assertForbidden();
            $this->post('/logout')->assertRedirect('/account');
            $this->assertGuest();
        }
    }

    public function test_admin_self_registration_and_weak_or_unconfirmed_passwords_are_rejected(): void
    {
        $this->post('/register', $this->credentials('admin'))->assertSessionHasErrors('role');
        $this->post('/register', [...$this->credentials(), 'password' => 'short'])->assertSessionHasErrors('password');
        $this->post('/register', [...$this->credentials(), 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_normalization_duplicate_rejection_login_and_logout(): void
    {
        $this->post('/register', [...$this->credentials(), 'email' => ' PARENT@Example.Test '])->assertRedirect('/dashboard');
        $this->post('/logout');
        $this->post('/register', $this->credentials())->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->post('/login', ['email' => 'parent@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => 'PARENT@example.test', 'password' => 'a long password 123!'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->post('/logout');
        $this->get('/parent')->assertRedirect('/account');
    }

    public function test_account_and_dashboard_pages_preserve_noindex_and_escape_names(): void
    {
        $this->get('/account')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('name="_token"', false);
        $this->post('/register', [...$this->credentials(), 'name' => '<script>alert(1)</script>']);
        $this->get('/parent')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_login_is_throttled(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_database_backed_sessions_and_throttling_work_together(): void
    {
        config(['session.driver' => 'database', 'cache.default' => 'database']);
        $this->get('/register')->assertOk();
        $before = $this->app['session']->getId();
        $this->post('/register', $this->credentials())->assertRedirect('/dashboard');
        $this->assertNotSame($before, $this->app['session']->getId());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('sessions', ['user_id' => User::firstOrFail()->id]);
        $this->get('/parent')->assertOk();
        $this->post('/logout')->assertRedirect('/account');
        $this->assertGuest();
        $this->get('/parent')->assertRedirect('/account');
    }

    public function test_signup_rejects_requests_without_a_csrf_token(): void
    {
        // Laravel normally skips CSRF checks in feature tests. Exercise the real middleware.
        $this->app->instance('env', 'local');
        $this->post('/register', $this->credentials())->assertStatus(419);
        $this->assertDatabaseCount('users', 0);
    }

    private function credentials(string $role = 'parent'): array
    {
        return ['name' => 'Test User', 'email' => $role.'@example.test', 'role' => $role,
            'password' => 'a long password 123!', 'password_confirmation' => 'a long password 123!'];
    }
}
