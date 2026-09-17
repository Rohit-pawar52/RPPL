<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserActiveStatusTest extends TestCase
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

    public function test_new_user_defaults_active(): void
    {
        $user = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_active_admin_can_login(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id, 'password' => 'password']);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_active_scorer_can_login(): void
    {
        $scorer = User::factory()->create(['role_id' => $this->scorerRole->id, 'password' => 'password']);

        $this->post(route('admin.login.store'), [
            'email' => $scorer->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($scorer);
    }

    public function test_inactive_admin_cannot_login(): void
    {
        $admin = User::factory()->inactive()->create(['role_id' => $this->adminRole->id, 'password' => 'password']);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_inactive_scorer_cannot_login(): void
    {
        $scorer = User::factory()->inactive()->create(['role_id' => $this->scorerRole->id, 'password' => 'password']);

        $this->post(route('admin.login.store'), [
            'email' => $scorer->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_inactive_login_receives_the_same_generic_credentials_failure_message(): void
    {
        $admin = User::factory()->inactive()->create(['role_id' => $this->adminRole->id, 'password' => 'password']);

        $response = $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors('email');

        $errors = session('errors');
        $this->assertSame(
            'These credentials do not match our records.',
            $errors->first('email')
        );
    }

    public function test_deactivated_user_with_an_existing_session_is_logged_out_of_protected_routes(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        // Simulate: admin logged in earlier while still active.
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        // Another admin deactivates the account mid-session.
        $admin->update(['is_active' => false]);

        // The same (still nominally "authenticated") session must now be
        // rejected on its very next request to a protected route.
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_active_authenticated_user_continues_normally(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        // A second request in the same still-active session keeps working.
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }
}
