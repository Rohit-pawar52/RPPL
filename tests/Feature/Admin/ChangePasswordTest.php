<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The signed-in user changing their own password (admin/account/password). It is the only way a
 * password changes without an admin editing the account, so besides the happy path it must check the
 * current password, refuse weak or unchanged ones, and cut off anybody else still using the old one.
 */
class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT = 'current-password-1';

    private const NEW = 'a-brand-new-password-2';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    private function user(string $roleSlug = 'admin', array $attributes = []): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', $roleSlug)->value('id'),
            'password' => self::CURRENT,
        ] + $attributes);
    }

    private function change(User $user, array $data = []): TestResponse
    {
        return $this->actingAs($user)
            ->from(route('admin.account.password.edit'))
            ->put(route('admin.account.password.update'), $data + [
                'current_password' => self::CURRENT,
                'password' => self::NEW,
                'password_confirmation' => self::NEW,
            ]);
    }

    private function assertPasswordUnchanged(User $user): void
    {
        $this->assertTrue(Hash::check(self::CURRENT, $user->fresh()->password));
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('admin.account.password.edit'))->assertRedirect(route('admin.login'));
        $this->put(route('admin.account.password.update'), [
            'current_password' => self::CURRENT,
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertRedirect(route('admin.login'));
    }

    public function test_a_deactivated_account_cannot_use_it(): void
    {
        $user = $this->user('admin', ['is_active' => false]);

        $this->actingAs($user)->get(route('admin.account.password.edit'))->assertRedirect(route('admin.login'));
        $this->change($user)->assertRedirect(route('admin.login'));

        $this->assertPasswordUnchanged($user);
    }

    public function test_the_page_is_linked_from_the_account_menu(): void
    {
        $this->actingAs($this->user())
            ->get(route('admin.account.password.edit'))
            ->assertOk()
            ->assertSee('Update password')
            ->assertSee('Change password')
            ->assertSee(route('admin.account.password.edit'), false);
    }

    public function test_a_wrong_current_password_is_rejected_and_nothing_changes(): void
    {
        $user = $this->user();

        $this->change($user, ['current_password' => 'not-my-password'])
            ->assertRedirect(route('admin.account.password.edit'))
            ->assertSessionHasErrors('current_password');

        $this->assertPasswordUnchanged($user);
    }

    public function test_changing_the_password_works_and_the_new_one_logs_in(): void
    {
        $user = $this->user();

        $this->change($user)
            ->assertRedirect(route('admin.account.password.edit'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check(self::NEW, $user->fresh()->password));
        $this->assertFalse(Hash::check(self::CURRENT, $user->fresh()->password));

        // Sign out, then prove it with real logins: the old password is dead, the new one works.
        $this->post(route('admin.logout'));
        $this->assertGuest();

        $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => self::CURRENT])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => self::NEW])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_new_password_must_differ_from_the_current_one(): void
    {
        $user = $this->user();

        $this->change($user, ['password' => self::CURRENT, 'password_confirmation' => self::CURRENT])
            ->assertSessionHasErrors(['password' => 'The new password must be different from your current password.']);

        $this->assertPasswordUnchanged($user);
    }

    public function test_a_password_shorter_than_twelve_characters_is_rejected(): void
    {
        $user = $this->user();

        $this->change($user, ['password' => 'Eleven-char', 'password_confirmation' => 'Eleven-char'])
            ->assertSessionHasErrors('password');

        $this->assertPasswordUnchanged($user);
    }

    public function test_a_scorer_can_change_their_own_password(): void
    {
        $scorer = $this->user('scorer');

        $this->change($scorer)->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertTrue(Hash::check(self::NEW, $scorer->fresh()->password));
    }

    public function test_a_role_without_panel_access_can_still_change_the_password_and_sign_out(): void
    {
        $viewer = $this->user(Role::create(['name' => 'Viewer', 'slug' => 'viewer'])->slug);

        // Not allowed into the panel itself...
        $this->actingAs($viewer)->get(route('admin.dashboard'))->assertForbidden();

        // ...but it is their own account, so this page works, and they can leave.
        $this->actingAs($viewer)->get(route('admin.account.password.edit'))->assertOk();
        $this->change($viewer)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertTrue(Hash::check(self::NEW, $viewer->fresh()->password));

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_changing_it_signs_the_user_out_of_every_other_session_and_rotates_the_remember_token(): void
    {
        $user = $this->user();
        $user->forceFill(['remember_token' => 'old-remember-token'])->save();
        $other = $this->user('scorer');

        config(['session.driver' => 'database']);
        foreach ([[$user, 'old-session-on-a-phone'], [$user, 'old-session-on-a-laptop'], [$other, 'someone-elses-session']] as [$owner, $id]) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);
        }

        $this->change($user)->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'old-session-on-a-phone']);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session-on-a-laptop']);
        $this->assertDatabaseHas('sessions', ['id' => 'someone-elses-session']);
        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token);
    }

    public function test_the_current_password_cannot_be_guessed_by_hammering_the_form(): void
    {
        $user = $this->user();

        foreach (range(1, 6) as $attempt) {
            $this->change($user, ['current_password' => "wrong-guess-{$attempt}"])->assertSessionHasErrors('current_password');
        }

        // Locked out: even the right current password is refused for now.
        $this->change($user)->assertStatus(429);
        $this->assertPasswordUnchanged($user);
    }
}
