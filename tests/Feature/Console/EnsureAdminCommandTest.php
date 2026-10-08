<?php

namespace Tests\Feature\Console;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\Demo\DemoUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * rppl:ensure-admin - the secure replacement for a built-in default admin login. docker/start.sh runs
 * it on every start, so besides creating the first admin it must be harmless to repeat, and it must
 * never create, promote or re-password anybody by accident. The demo dataset and the old default admin
 * have published passwords, so they never count as a real ("usable") admin and are switched off once a
 * real one exists.
 */
class EnsureAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    /** Exactly the minimum length (12). */
    private const PASSWORD = 'Twelve-chars';

    private function environment(?string $email = 'Owner@Example.com', ?string $password = self::PASSWORD, bool $reset = false, string $name = 'Site Owner'): void
    {
        config([
            'admin.email' => $email,
            'admin.password' => $password,
            'admin.name' => $name,
            'admin.reset_password' => $reset,
        ]);
    }

    /**
     * Runs the command and returns [exit code, everything it printed on stdout and stderr]. Whatever
     * happened, the password from the environment must not be in that output.
     *
     * @return array{0: int, 1: string}
     */
    private function ensureAdmin(bool $lockDemoAccounts = false): array
    {
        $exitCode = Artisan::call('rppl:ensure-admin', $lockDemoAccounts ? ['--lock-demo-accounts' => true] : []);
        $output = Artisan::output();

        if (filled(config('admin.password'))) {
            $this->assertStringNotContainsString((string) config('admin.password'), $output, 'The command printed the password.');
        }

        return [$exitCode, $output];
    }

    private function user(string $roleSlug, array $attributes = []): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['name' => ucfirst($roleSlug)]);

        return User::factory()->create(['role_id' => $role->id] + $attributes);
    }

    /**
     * The demo dataset's two logins, made by the real seeder so that this test notices if the
     * seeder's emails or passwords ever stop matching EnsureAdminCommand::PUBLIC_LOGINS.
     */
    private function seedDemoLogins(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(DemoUserSeeder::class);
    }

    private function account(string $email): User
    {
        return User::where('email', $email)->sole();
    }

    private function adminCount(): int
    {
        return User::whereHas('role', fn ($query) => $query->where('slug', Role::ADMIN_SLUG))->count();
    }

    public function test_creates_an_active_admin_from_the_environment_when_the_site_has_none(): void
    {
        $this->environment();

        [$exitCode, $output] = $this->ensureAdmin();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Created the admin user owner@example.com', $output);

        $admin = User::sole();
        $this->assertSame('owner@example.com', $admin->email);
        $this->assertSame('Site Owner', $admin->name);
        $this->assertSame(Role::ADMIN_SLUG, $admin->role->slug);
        $this->assertTrue($admin->is_active);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check(self::PASSWORD, $admin->password));

        // Not just a row: it really is a login into the admin panel.
        $this->post(route('admin.login.store'), ['email' => 'owner@example.com', 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_the_admin_name_defaults_to_administrator(): void
    {
        $this->environment(name: '');

        [$exitCode] = $this->ensureAdmin();

        $this->assertSame(0, $exitCode);
        $this->assertSame('Administrator', User::sole()->name);
    }

    public function test_running_it_again_never_touches_the_admin_it_created(): void
    {
        $this->environment();
        $this->ensureAdmin();

        // The admin has since picked a password of their own (the Change password page).
        User::sole()->update(['password' => 'chosen-by-the-admin-1']);

        [$exitCode, $output] = $this->ensureAdmin();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('A usable admin user already exists', $output);
        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('chosen-by-the-admin-1', User::sole()->password));
        $this->assertFalse(Hash::check(self::PASSWORD, User::sole()->password));
    }

    public function test_an_existing_admin_is_left_alone_and_the_environment_is_not_applied(): void
    {
        $existing = $this->user('admin', ['email' => 'founder@example.com', 'password' => 'founders-own-password']);
        $this->environment(email: 'someone-else@example.com');

        [$exitCode, $output] = $this->ensureAdmin();

        $this->assertSame(0, $exitCode);
        // Says why the ADMIN_* login does not exist, instead of leaving it a mystery.
        $this->assertStringContainsString('someone-else@example.com) is not an active admin here and was NOT created', $output);
        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('founders-own-password', $existing->fresh()->password));
    }

    public function test_an_inactive_admin_is_not_a_usable_admin(): void
    {
        $former = $this->user('admin', ['email' => 'former@example.com', 'password' => 'formers-own-password', 'is_active' => false]);
        $this->environment();

        [$exitCode] = $this->ensureAdmin();

        $this->assertSame(0, $exitCode);
        $this->assertSame(['owner@example.com'], User::where('is_active', true)->pluck('email')->all());
        // The inactive admin is neither reactivated nor changed.
        $this->assertFalse($former->fresh()->is_active);
        $this->assertTrue(Hash::check('formers-own-password', $former->fresh()->password));
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function unusableCredentials(): array
    {
        return [
            'no email' => [null, self::PASSWORD],
            'not an email address' => ['not-an-email', self::PASSWORD],
            'no password' => ['owner@example.com', null],
            'one character too short' => ['owner@example.com', 'Eleven-char'],
        ];
    }

    #[DataProvider('unusableCredentials')]
    public function test_refuses_and_creates_nothing_when_the_environment_is_unusable(?string $email, ?string $password): void
    {
        $this->environment($email, $password);

        [$exitCode, $output] = $this->ensureAdmin();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('nothing was created', $output);
        $this->assertSame(0, User::count());
    }

    public function test_never_creates_an_admin_from_a_missing_environment_even_when_other_users_exist(): void
    {
        $scorer = $this->user('scorer', ['email' => 'owner@example.com', 'password' => 'scorers-own-password']);
        $this->environment(password: null);

        [$exitCode] = $this->ensureAdmin();

        $this->assertSame(1, $exitCode);
        $this->assertSame(0, $this->adminCount());
        $this->assertSame('scorer', $scorer->fresh()->role->slug);
        $this->assertTrue(Hash::check('scorers-own-password', $scorer->fresh()->password));
    }

    public function test_an_admin_email_that_belongs_to_an_existing_user_is_an_error_and_that_user_is_left_alone(): void
    {
        $scorer = $this->user('scorer', ['email' => 'owner@example.com', 'password' => 'scorers-own-password']);
        $this->environment(email: 'OWNER@example.com');

        [$exitCode, $output] = $this->ensureAdmin();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('already exists, so no admin was created and that user was not changed', $output);
        $this->assertStringContainsString('Use another ADMIN_EMAIL, or set RESET_ADMIN_PASSWORD=true', $output);
        $this->assertSame(0, $this->adminCount());
        $this->assertSame(1, User::count());
        $this->assertSame('scorer', $scorer->fresh()->role->slug);
        $this->assertTrue(Hash::check('scorers-own-password', $scorer->fresh()->password));
    }

    // ----- Published logins (the demo dataset, the old default admin) -----

    public function test_a_demo_admin_is_not_a_usable_admin_so_a_real_one_is_created_and_the_demo_logins_stay_on_without_the_flag(): void
    {
        $this->seedDemoLogins();
        $this->environment();

        [$exitCode, $output] = $this->ensureAdmin();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Created the admin user owner@example.com', $output);
        $this->assertSame(3, User::count());
        $this->assertTrue($this->account('admin@rppl.test')->is_active);
        $this->assertTrue($this->account('scorer@rppl.test')->is_active);
    }

    public function test_the_flag_deactivates_the_demo_logins_once_a_real_admin_exists(): void
    {
        $this->seedDemoLogins();
        $this->environment();

        [$exitCode, $output] = $this->ensureAdmin(lockDemoAccounts: true);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Created the admin user owner@example.com', $output);
        $this->assertStringContainsString('Deactivated admin@rppl.test: it still used a publicly known password', $output);
        $this->assertStringContainsString('Deactivated scorer@rppl.test: it still used a publicly known password', $output);

        // Deactivated, never deleted (the demo data refers to them) - and really locked out.
        $this->assertSame(3, User::count());
        $this->assertFalse($this->account('admin@rppl.test')->is_active);
        $this->assertFalse($this->account('scorer@rppl.test')->is_active);
        $this->post(route('admin.login.store'), ['email' => 'admin@rppl.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post(route('admin.login.store'), ['email' => 'owner@example.com', 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.dashboard'));

        // Every later start finds nothing left to do.
        [$exitCode, $output] = $this->ensureAdmin(lockDemoAccounts: true);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No active account uses a publicly known password', $output);
        $this->assertStringNotContainsString('Deactivated', $output);
    }

    public function test_the_old_default_admin_login_is_switched_off_too(): void
    {
        // As an earlier version of docker/start.sh created it - here stored with capitals.
        $this->user('admin', ['email' => 'Admin@Gmail.com', 'password' => '12345678']);
        $this->environment();

        [$exitCode, $output] = $this->ensureAdmin(lockDemoAccounts: true);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Deactivated Admin@Gmail.com: it still used a publicly known password', $output);
        $this->assertStringNotContainsString('12345678', $output);
        $this->assertSame(['owner@example.com'], User::where('is_active', true)->pluck('email')->all());
    }

    #[DataProvider('unusableCredentials')]
    public function test_the_flag_deactivates_nothing_and_warns_when_no_real_admin_can_be_created(?string $email, ?string $password): void
    {
        $this->seedDemoLogins();
        $this->environment($email, $password);

        [$exitCode, $output] = $this->ensureAdmin(lockDemoAccounts: true);

        $this->assertSame(1, $exitCode);
        // The site must not lock itself out, and it says its demo logins are still open.
        $this->assertSame(2, User::count());
        $this->assertSame(2, User::where('is_active', true)->count());
        $this->assertStringContainsString('still has logins with publicly known passwords', $output);
        $this->assertStringContainsString('admin@rppl.test, scorer@rppl.test', $output);
        $this->assertStringContainsString('Nothing was deactivated', $output);
        $this->assertStringNotContainsString('Deactivated', $output);
    }

    public function test_a_demo_login_whose_password_was_changed_is_a_usable_admin_and_is_kept(): void
    {
        $this->seedDemoLogins();
        // Whoever runs the site signed in as the demo admin and chose a password of their own.
        $this->account('admin@rppl.test')->update(['password' => 'chosen-by-the-owner-1']);
        $this->environment();

        [$exitCode, $output] = $this->ensureAdmin(lockDemoAccounts: true);

        $this->assertSame(0, $exitCode);
        // It is a real admin now, so the environment creates no second one...
        $this->assertStringContainsString('A usable admin user already exists', $output);
        $this->assertSame(2, User::count());
        $admin = $this->account('admin@rppl.test');
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('chosen-by-the-owner-1', $admin->password));
        // ...while the demo scorer, still on its published password, is switched off.
        $this->assertFalse($this->account('scorer@rppl.test')->is_active);
    }

    public function test_a_real_admin_is_untouched_while_the_demo_logins_are_deactivated(): void
    {
        $this->seedDemoLogins();
        $real = $this->user('admin', ['email' => 'founder@example.com', 'password' => 'founders-own-password']);
        $hashBefore = $real->fresh()->password;
        $this->environment(null, null);

        [$exitCode] = $this->ensureAdmin(lockDemoAccounts: true);

        $this->assertSame(0, $exitCode);
        $this->assertSame(3, User::count());
        $this->assertSame(['founder@example.com'], User::where('is_active', true)->pluck('email')->all());
        // Not even re-hashed.
        $this->assertSame($hashBefore, $real->fresh()->password);
    }

    public function test_an_admin_email_that_belongs_to_a_demo_login_is_an_error_and_that_account_is_left_alone(): void
    {
        $this->seedDemoLogins();
        $demoAdmin = $this->account('admin@rppl.test');
        $this->environment(email: 'Admin@RPPL.test');

        [$exitCode, $output] = $this->ensureAdmin(lockDemoAccounts: true);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('already exists, so no admin was created and that user was not changed', $output);
        // Left exactly as it was - and, with no real admin, nothing is switched off.
        $this->assertSame($demoAdmin->password, $demoAdmin->fresh()->password);
        $this->assertSame(2, User::where('is_active', true)->count());
        $this->assertStringContainsString('Nothing was deactivated', $output);
    }

    public function test_resetting_a_demo_login_to_a_private_password_makes_it_a_usable_admin(): void
    {
        $this->seedDemoLogins();
        $this->environment(email: 'admin@rppl.test', reset: true);

        [$exitCode] = $this->ensureAdmin(lockDemoAccounts: true);

        $this->assertSame(0, $exitCode);
        $this->assertSame(2, User::count());
        $admin = $this->account('admin@rppl.test');
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check(self::PASSWORD, $admin->password));
        $this->assertFalse($this->account('scorer@rppl.test')->is_active);
    }

    // ----- Break-glass (RESET_ADMIN_PASSWORD) -----

    public function test_the_reset_flag_resets_the_password_and_restores_the_user_as_an_active_admin(): void
    {
        $this->user('admin', ['email' => 'founder@example.com']);
        // Stored with capitals by hand: it must still be found, not duplicated.
        $locked = $this->user('scorer', [
            'email' => 'Owner@Example.com',
            'password' => 'forgotten-password-1',
            'is_active' => false,
            'remember_token' => 'old-remember-token',
        ]);
        $this->environment(reset: true);

        [$exitCode, $output] = $this->ensureAdmin();

        $this->assertSame(0, $exitCode);
        // The loud warning to remove the switch again.
        $this->assertStringContainsString('RESET_ADMIN_PASSWORD is ON', $output);
        $this->assertStringContainsString('REMOVE RESET_ADMIN_PASSWORD', $output);

        $locked = $locked->fresh();
        $this->assertSame(2, User::count());
        $this->assertSame(Role::ADMIN_SLUG, $locked->role->slug);
        $this->assertTrue($locked->is_active);
        $this->assertTrue(Hash::check(self::PASSWORD, $locked->password));
        $this->assertNotSame('old-remember-token', $locked->remember_token);
    }

    public function test_the_reset_flag_signs_the_user_out_everywhere(): void
    {
        $user = $this->user('admin', ['email' => 'owner@example.com']);
        $other = $this->user('admin');
        config(['session.driver' => 'database']);
        foreach ([[$user, 'phone'], [$user, 'laptop'], [$other, 'someone-elses']] as [$owner, $id]) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);
        }
        $this->environment(reset: true);

        [$exitCode] = $this->ensureAdmin();

        $this->assertSame(0, $exitCode);
        $this->assertSame(['someone-elses'], DB::table('sessions')->pluck('id')->all());
    }

    public function test_the_reset_flag_creates_the_admin_when_the_user_is_missing(): void
    {
        $this->user('admin', ['email' => 'founder@example.com']);
        $this->environment(reset: true);

        [$exitCode] = $this->ensureAdmin();

        $this->assertSame(0, $exitCode);
        $created = User::where('email', 'owner@example.com')->sole();
        $this->assertSame(Role::ADMIN_SLUG, $created->role->slug);
        $this->assertTrue(Hash::check(self::PASSWORD, $created->password));
    }

    public function test_the_reset_flag_with_an_unusable_environment_changes_nothing(): void
    {
        $locked = $this->user('scorer', ['email' => 'owner@example.com', 'password' => 'forgotten-password-1', 'is_active' => false]);
        $this->environment(password: 'too-short', reset: true);

        [$exitCode, $output] = $this->ensureAdmin();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('nothing was changed', $output);
        $locked = $locked->fresh();
        $this->assertSame('scorer', $locked->role->slug);
        $this->assertFalse($locked->is_active);
        $this->assertTrue(Hash::check('forgotten-password-1', $locked->password));
    }

    public function test_only_a_true_value_switches_the_reset_flag_on(): void
    {
        // The break-glass switch must not be flipped by a sloppy value such as RESET_ADMIN_PASSWORD=no
        // (a plain (bool) cast would read any non-empty string as true), so read the real config file.
        $reset = function ($raw) {
            $_SERVER['RESET_ADMIN_PASSWORD'] = $raw;

            try {
                return (require config_path('admin.php'))['reset_password'];
            } finally {
                unset($_SERVER['RESET_ADMIN_PASSWORD']);
            }
        };

        foreach (['true', '1', 'yes', 'on'] as $on) {
            $this->assertTrue($reset($on), "RESET_ADMIN_PASSWORD={$on}");
        }

        foreach (['false', '0', 'no', 'off', '', 'nonsense'] as $off) {
            $this->assertFalse($reset($off), "RESET_ADMIN_PASSWORD={$off}");
        }

        $this->assertFalse((require config_path('admin.php'))['reset_password'], 'unset');
    }
}
