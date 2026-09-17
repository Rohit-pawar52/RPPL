<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $scorerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->scorerRole = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);
    }

    public function test_guest_can_view_admin_login(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertViewIs('admin.auth.login');
    }

    public function test_guest_cannot_access_protected_dashboard(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_valid_admin_can_login(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => 'password',
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_valid_scorer_can_login_and_reach_dashboard_but_not_management_nav(): void
    {
        $scorer = User::factory()->create([
            'role_id' => $this->scorerRole->id,
            'password' => 'password',
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $scorer->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($scorer);

        // Scorer passes the broad admin-panel gate (can view the dashboard
        // itself, which has no per-role content of its own)...
        $this->actingAs($scorer)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee($scorer->name) // shared dashboard content, visible to both roles
            ->assertDontSee('Registrations'); // admin-only management nav section
    }

    public function test_admin_sees_management_navigation_scorer_does_not(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Registrations');
    }

    public function test_invalid_credentials_are_rejected_with_a_generic_message(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => 'password',
        ]);

        $response = $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        $errors = session('errors');
        $this->assertSame(
            'These credentials do not match our records.',
            $errors->first('email')
        );
    }

    public function test_authenticated_user_can_logout(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_authenticated_admin_hitting_login_page_is_redirected_to_dashboard(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)
            ->get(route('admin.login'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee($admin->name);
    }

    public function test_login_validation_requires_email_and_password(): void
    {
        $this->post(route('admin.login.store'), [])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited_after_repeated_failed_attempts(): void
    {
        RateLimiter::clear('login');

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => 'password',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.login.store'), [
                'email' => $admin->email,
                'password' => 'wrong-password',
            ]);
        }

        // The 6th attempt within the same window is throttled by the
        // "login" rate limiter (429), even with correct credentials.
        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertStatus(429);

        $this->assertGuest();
    }
}
