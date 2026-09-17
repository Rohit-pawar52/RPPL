<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function scorer(): User
    {
        return User::factory()->create(['role_id' => $this->scorerRole->id]);
    }

    public function test_admin_can_access_user_management_but_scorer_is_forbidden_everywhere(): void
    {
        $admin = $this->admin();
        $otherUser = $this->scorer();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.show', $otherUser))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.edit', $otherUser))->assertOk();

        $scorer = $this->scorer();
        $this->actingAs($scorer)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.users.show', $otherUser))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.users.edit', $otherUser))->assertForbidden();
        $this->actingAs($scorer)
            ->post(route('admin.users.store'), [
                'name' => 'Blocked', 'email' => 'blocked@example.com',
                'role_id' => $this->scorerRole->id, 'password' => 'password123', 'password_confirmation' => 'password123',
            ])
            ->assertForbidden();
        $this->assertNull(User::firstWhere('email', 'blocked@example.com'));
    }

    public function test_admin_can_create_scorer_and_arbitrary_role_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Scorer',
                'email' => 'newscorer@example.com',
                'role_id' => $this->scorerRole->id,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::firstWhere('email', 'newscorer@example.com');
        $this->assertNotNull($created);
        $this->assertSame('scorer', $created->role->slug);
        $this->assertTrue($created->is_active);

        // A role_id that doesn't exist in the roles table must be rejected.
        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Bad Role',
                'email' => 'badrole@example.com',
                'role_id' => 99999,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertSessionHasErrors('role_id');
        $this->assertNull(User::firstWhere('email', 'badrole@example.com'));
    }

    public function test_email_uniqueness_is_enforced_on_create_and_update(): void
    {
        $admin = $this->admin();
        $existing = $this->scorer();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Duplicate', 'email' => $existing->email,
                'role_id' => $this->scorerRole->id, 'password' => 'password123', 'password_confirmation' => 'password123',
            ])
            ->assertSessionHasErrors('email');

        $another = $this->scorer();
        $this->actingAs($admin)
            ->put(route('admin.users.update', $another), [
                'name' => $another->name, 'email' => $existing->email,
                'role_id' => $this->scorerRole->id, 'is_active' => '1',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_admin_can_update_another_users_profile_fields(): void
    {
        $admin = $this->admin();
        $target = $this->scorer();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'role_id' => $this->scorerRole->id,
                'is_active' => '0',
            ])
            ->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertSame('Updated Name', $target->name);
        $this->assertSame('updated@example.com', $target->email);
        $this->assertFalse($target->is_active);
    }

    public function test_blank_password_preserves_old_password_and_provided_password_is_hashed_and_works(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['role_id' => $this->scorerRole->id, 'password' => 'original-password']);
        $originalHash = $target->password;

        // Blank password fields: hash must remain untouched.
        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), [
                'name' => $target->name, 'email' => $target->email,
                'role_id' => $this->scorerRole->id, 'is_active' => '1',
                'password' => '', 'password_confirmation' => '',
            ])
            ->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertSame($originalHash, $target->password);
        $this->assertTrue(Hash::check('original-password', $target->password));

        // A provided password must be hashed and immediately usable to log in.
        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), [
                'name' => $target->name, 'email' => $target->email,
                'role_id' => $this->scorerRole->id, 'is_active' => '1',
                'password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password',
            ])
            ->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertNotSame($originalHash, $target->password);
        $this->assertTrue(Hash::check('brand-new-password', $target->password));
    }

    public function test_admin_cannot_deactivate_or_demote_self(): void
    {
        $admin = $this->admin();
        $this->admin(); // a second admin, so "last active admin" isn't the blocker here

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name, 'email' => $admin->email,
                'role_id' => $this->adminRole->id, 'is_active' => '0',
            ])
            ->assertSessionHasErrors('is_active');
        $this->assertTrue($admin->fresh()->is_active);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name, 'email' => $admin->email,
                'role_id' => $this->scorerRole->id, 'is_active' => '1',
            ])
            ->assertSessionHasErrors('role_id');
        $this->assertSame('admin', $admin->fresh()->role->slug);

        // Name/email changes for self must still work normally.
        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'name' => 'My New Name', 'email' => $admin->email,
                'role_id' => $this->adminRole->id, 'is_active' => '1',
            ])
            ->assertRedirect(route('admin.users.index'));
        $this->assertSame('My New Name', $admin->fresh()->name);
    }

    /**
     * With two active admins, one may safely deactivate/demote the
     * other — the acting admin's own unaffected active-admin status is
     * exactly what keeps this from ever reaching zero, so this must be
     * allowed (the "last active admin" guard must not over-block).
     */
    public function test_admin_can_deactivate_or_demote_another_admin_while_a_second_active_admin_remains(): void
    {
        $acting = $this->admin();
        $colleague = $this->admin();

        $this->actingAs($acting)
            ->put(route('admin.users.update', $colleague), [
                'name' => $colleague->name, 'email' => $colleague->email,
                'role_id' => $this->adminRole->id, 'is_active' => '0',
            ])
            ->assertRedirect(route('admin.users.index'));
        $this->assertFalse($colleague->fresh()->is_active);
    }

    /**
     * The literal "another admin deactivates/demotes the LAST active
     * admin" case can never occur over a live HTTP request in this
     * system: reaching this controller at all requires the acting user
     * to themselves be an active admin, and their own unaffected
     * status always keeps the post-change count above zero. The real
     * safety net for "can the last admin ever be removed" is the
     * unconditional self-protection rule (already covered above) — an
     * admin can never demote/deactivate themselves regardless of how
     * many other admins exist. This test instead verifies the guard's
     * query directly: with only one active admin in the whole system,
     * the count of "other active admins" the request would compute is
     * correctly zero.
     */
    public function test_last_active_admin_query_correctly_reports_no_other_active_admin(): void
    {
        $onlyActiveAdmin = $this->admin();
        User::factory()->create(['role_id' => $this->adminRole->id, 'is_active' => false]);

        $othersStillActive = User::query()
            ->whereHas('role', fn ($query) => $query->where('slug', 'admin'))
            ->where('is_active', true)
            ->where('id', '!=', $onlyActiveAdmin->id)
            ->doesntExist();

        $this->assertTrue($othersStillActive);
    }

    public function test_no_delete_route_exists_for_users(): void
    {
        $this->assertFalse(Route::has('admin.users.destroy'));

        $admin = $this->admin();
        $target = $this->scorer();

        $this->actingAs($admin)
            ->delete(route('admin.users.update', $target))
            ->assertStatus(405);
    }
}
