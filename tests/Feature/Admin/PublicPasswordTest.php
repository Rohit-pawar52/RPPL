<?php

namespace Tests\Feature\Admin;

use App\Console\Commands\EnsureAdminCommand;
use App\Models\Role;
use App\Models\User;
use App\Support\PublicLogins;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A login that still has a PUBLISHED password (admin@gmail.com / 12345678, the demo logins) may do nothing
 * in the admin panel except change it - or sign out - until it has. Production-only by default: the
 * tests switch the rule on, as the Render deployment does.
 */
class PublicPasswordTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.force_private_password' => true]);
        $this->adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    }

    private function publicAdmin(string $email = 'admin@gmail.com', string $password = '12345678'): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id, 'email' => $email, 'password' => $password]);
    }

    public function test_signing_in_with_a_published_password_opens_only_the_change_password_page(): void
    {
        $this->publicAdmin();

        $this->post(route('admin.login.store'), ['email' => 'admin@gmail.com', 'password' => '12345678'])
            ->assertRedirect(route('admin.dashboard'));

        // The login itself works, but every panel page sends the user to the password page.
        foreach (['admin.dashboard', 'admin.settings.index', 'admin.users.index', 'admin.roles.index', 'admin.editions.index', 'admin.finance.overview'] as $route) {
            $this->get(route($route))
                ->assertRedirect(route('admin.account.password.edit'))
                ->assertSessionHas('warning');
        }

        // Writes are stopped the same way, before anything is validated or done.
        $this->post(route('admin.users.store'), ['name' => 'Sneaky', 'email' => 'sneaky@example.test'])
            ->assertRedirect(route('admin.account.password.edit'));
        $this->assertNull(User::firstWhere('email', 'sneaky@example.test'));

        // The page that fixes it opens and says why; signing out still works.
        $this->get(route('admin.account.password.edit'))
            ->assertOk()
            ->assertSee('publicly known password');

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_choosing_a_new_password_opens_the_panel_straight_away(): void
    {
        $user = $this->publicAdmin();

        $this->post(route('admin.login.store'), ['email' => 'admin@gmail.com', 'password' => '12345678']);
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.account.password.edit'));

        $this->put(route('admin.account.password.update'), [
            'current_password' => '12345678',
            'password' => 'a-long-private-password',
            'password_confirmation' => 'a-long-private-password',
        ])->assertRedirect(route('admin.account.password.edit'));

        $this->assertTrue(Hash::check('a-long-private-password', $user->fresh()->password));

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
    }

    public function test_the_same_email_with_a_private_password_is_not_held_back(): void
    {
        $user = $this->publicAdmin(password: 'a-long-private-password');

        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
        $this->assertFalse(session('public_password_verdict.public'));
    }

    public function test_only_the_published_pairs_count_and_other_logins_cost_nothing(): void
    {
        // 12345678 is only "published" for admin@gmail.com: another login that happens to use it is not touched
        // (and its password is never even hashed against the list).
        $other = $this->publicAdmin('boss@example.test', '12345678');

        $this->actingAs($other)->get(route('admin.dashboard'))->assertOk();
        $this->assertNull(session('public_password_verdict'));
    }

    public function test_a_login_that_skipped_the_sign_in_form_is_held_back_too(): void
    {
        // A "remember me" cookie or a session that was already open when the site was updated never
        // submits the sign-in form: the decision must come from the stored password, not from a flag set there.
        $user = $this->publicAdmin();

        $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('admin.account.password.edit'));

        // And a brand-new session for the same user, as after the browser was closed, is held back as well.
        $this->flushSession();
        $this->actingAs($user)->get(route('admin.settings.index'))->assertRedirect(route('admin.account.password.edit'));
    }

    public function test_resetting_a_password_to_a_published_one_holds_the_login_back_again(): void
    {
        $user = $this->publicAdmin(password: 'a-long-private-password');

        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();

        // An admin sets the account's password to the published one (say, from the Users form).
        $user->update(['password' => '12345678']);

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.account.password.edit'));
    }

    public function test_scripts_get_a_readable_refusal_instead_of_a_redirect(): void
    {
        $this->actingAs($this->publicAdmin())
            ->getJson(route('admin.dashboard'))
            ->assertForbidden()
            ->assertJsonPath('message', 'This login still has a publicly known password. Change it first.');
    }

    public function test_the_demo_logins_are_held_back_too_but_only_when_the_rule_is_on(): void
    {
        $scorer = User::factory()->create([
            'role_id' => Role::firstOrCreate(['slug' => 'scorer'], ['name' => 'Scorer'])->id,
            'email' => 'scorer@rppl.test',
            'password' => 'password',
        ]);

        $this->actingAs($scorer)->get(route('admin.dashboard'))->assertRedirect(route('admin.account.password.edit'));

        // Off, as on a development machine: the demo login keeps working.
        config(['admin.force_private_password' => false]);
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_the_rule_is_off_unless_the_site_runs_in_production_or_it_is_switched_on(): void
    {
        $config = require config_path('admin.php');

        // These tests run with APP_ENV=testing and no ADMIN_FORCE_PRIVATE_PASSWORD, so the shipped default is off.
        $this->assertFalse($config['force_private_password']);
    }

    public function test_the_published_list_is_what_the_demo_seeder_and_the_bootstrap_command_use(): void
    {
        $this->assertSame(PublicLogins::PAIRS, EnsureAdminCommand::PUBLIC_LOGINS);
        $this->assertTrue(PublicLogins::listsEmail('Admin@Gmail.com'));
        $this->assertFalse(PublicLogins::listsEmail('someone@example.test'));
    }
}
