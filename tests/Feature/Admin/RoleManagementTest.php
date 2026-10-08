<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Support\AdminNavigation;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Roles module: admins create and edit roles and tick the permissions
 * each one holds. The security-critical part is that this stays admin only
 * (a role cannot be given roles.* / users.*, so nobody can hand themselves
 * more power) and that a role can never be stored with a permission that is
 * not delegable or not in the catalog.
 */
class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $scorerRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Creating a scorer role stores the scorer's built-in permissions (Role::booted()).
        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->scorerRole = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function userWith(Role $role): User
    {
        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function customRole(string $name, array $permissions = []): Role
    {
        $role = Role::create(['name' => $name, 'slug' => Str::slug($name)]);
        $role->syncPermissions($permissions);

        return $role->fresh();
    }

    // ----- Create / edit / delete -----

    public function test_admin_can_create_a_role_and_its_permissions_are_stored_with_view_implied_by_manage(): void
    {
        $admin = $this->admin();

        // The form offers what can be given, and never the access-control permissions.
        $this->actingAs($admin)
            ->get(route('admin.roles.create'))
            ->assertOk()
            ->assertSee('value="news.manage"', false)
            ->assertDontSee('value="roles.manage"', false)
            ->assertDontSee('value="users.manage"', false);

        $this->actingAs($admin)
            ->post(route('admin.roles.store'), [
                'name' => 'News Editor',
                'permissions' => ['panel.access', 'news.manage', 'photos.view'],
            ])
            ->assertRedirect(route('admin.roles.index'))
            ->assertSessionHas('success');

        $role = Role::where('name', 'News Editor')->firstOrFail();
        $this->assertSame('news-editor', $role->slug);
        $this->assertEqualsCanonicalizing(['panel.access', 'news.manage', 'news.view', 'photos.view'], $role->permissionKeys());

        $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk()->assertSee('News Editor');
        $this->actingAs($admin)->get(route('admin.roles.show', $role))->assertOk()->assertSee('Manage news')->assertSee('View photos');

        // A role created with nothing ticked starts with nothing.
        $this->actingAs($admin)
            ->post(route('admin.roles.store'), ['name' => 'Observer'])
            ->assertRedirect(route('admin.roles.index'));

        $this->assertSame([], Role::where('name', 'Observer')->firstOrFail()->permissionKeys());
    }

    public function test_admin_can_edit_a_role_and_its_permissions_are_replaced_but_its_slug_never_changes(): void
    {
        $admin = $this->admin();
        $role = $this->customRole('Helper', ['news.manage', 'venues.view']);

        // The form shows what the role holds now.
        $html = $this->actingAs($admin)->get(route('admin.roles.edit', $role))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/value="news\.manage"[^>]*\schecked/', $html);
        $this->assertMatchesRegularExpression('/value="news\.view"[^>]*\schecked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="photos\.manage"[^>]*\schecked/', $html);

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $role), ['name' => 'Match Helper', 'permissions' => ['photos.manage']])
            ->assertRedirect(route('admin.roles.index'))
            ->assertSessionHas('success');

        $role = $role->fresh();
        $this->assertSame('Match Helper', $role->name);
        $this->assertSame('helper', $role->slug);
        $this->assertEqualsCanonicalizing(['photos.manage', 'photos.view'], $role->permissionKeys());
    }

    public function test_a_built_in_role_can_be_renamed_and_changed_but_keeps_its_slug_and_no_new_role_can_take_a_built_in_slug(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $this->scorerRole), ['name' => 'Match Scorer', 'permissions' => ['panel.access', 'auction.run']])
            ->assertRedirect(route('admin.roles.index'));

        $scorer = $this->scorerRole->fresh();
        $this->assertSame('scorer', $scorer->slug);
        $this->assertSame('Match Scorer', $scorer->name);
        // Replaced wholesale: none of the built-in scoring rights are left.
        $this->assertEqualsCanonicalizing(['panel.access', 'auction.run'], $scorer->permissionKeys());

        // "Scorer" is a free name now, but its natural slug belongs to the built-in role, so the
        // new role gets another one: code treats the built-in slugs as special.
        $this->actingAs($admin)->post(route('admin.roles.store'), ['name' => 'Scorer'])->assertRedirect(route('admin.roles.index'));

        $new = Role::where('name', 'Scorer')->firstOrFail();
        $this->assertSame('scorer-2', $new->slug);
        $this->assertFalse($new->isSystem());
        $this->assertSame([], $new->permissionKeys());
    }

    public function test_saving_a_role_without_panel_access_warns_when_logins_hold_it(): void
    {
        $admin = $this->admin();
        $role = $this->customRole('Helper', ['panel.access', 'news.view']);
        $this->userWith($role);
        $this->userWith($role);

        $this->actingAs($admin)->get(route('admin.roles.edit', $role))->assertOk()->assertSee('2 users have this role');

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $role), ['name' => 'Helper', 'permissions' => ['news.view']])
            ->assertRedirect(route('admin.roles.index'))
            ->assertSessionHas('warning');

        // Nobody holds this one, so there is nobody to lock out.
        $unused = $this->customRole('Unused', ['panel.access']);

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $unused), ['name' => 'Unused'])
            ->assertSessionMissing('warning');
    }

    public function test_the_admin_role_can_be_looked_at_but_not_edited_or_deleted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.roles.show', $this->adminRole))
            ->assertOk()
            ->assertSee('Administrators always have every permission');

        $this->actingAs($admin)->get(route('admin.roles.edit', $this->adminRole))->assertForbidden();
        $this->actingAs($admin)
            ->put(route('admin.roles.update', $this->adminRole), ['name' => 'Nobody', 'permissions' => []])
            ->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.roles.destroy', $this->adminRole))->assertForbidden();

        $this->assertSame('Admin', $this->adminRole->fresh()->name);
        $this->assertTrue($admin->fresh()->hasPermission('settings.manage'));
    }

    public function test_built_in_roles_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $auctioneer = Role::where('slug', 'auctioneer')->firstOrFail();

        foreach ([$this->scorerRole, $auctioneer] as $role) {
            $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))->assertForbidden();
            $this->assertDatabaseHas('roles', ['id' => $role->id]);
        }

        $this->assertNotEmpty($auctioneer->fresh()->permissionKeys());
    }

    public function test_a_role_that_still_has_users_cannot_be_deleted_but_an_unused_one_can(): void
    {
        $admin = $this->admin();
        $role = $this->customRole('Helper', ['news.manage']);
        $this->userWith($role);
        $this->userWith($role);

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertRedirect(route('admin.roles.index'))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, '2 users'));

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
        $this->assertNotEmpty($role->fresh()->permissionKeys());

        User::where('role_id', $role->id)->update(['role_id' => $this->scorerRole->id]);

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertRedirect(route('admin.roles.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('role_permissions', ['role_id' => $role->id]);
    }

    public function test_a_role_name_must_be_unique_even_when_it_only_differs_in_case_or_punctuation(): void
    {
        $admin = $this->admin();
        $role = $this->customRole('News Editor');
        $roles = Role::count();

        foreach (['News Editor', 'news editor', 'News-Editor!', 'Admin', 'Admin!', '!!!'] as $name) {
            $this->actingAs($admin)
                ->post(route('admin.roles.store'), ['name' => $name])
                ->assertSessionHasErrors('name');
        }

        $this->assertSame($roles, Role::count());

        // A role may keep (or re-case) its own name, but not take another role's.
        $this->actingAs($admin)
            ->put(route('admin.roles.update', $role), ['name' => 'NEWS EDITOR'])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $this->customRole('Other')), ['name' => 'news editor'])
            ->assertSessionHasErrors('name');
    }

    // ----- Permissions that must never be stored -----

    public function test_permissions_that_cannot_be_given_to_a_role_are_rejected_and_never_stored(): void
    {
        $admin = $this->admin();
        $role = $this->customRole('Helper', ['news.view']);

        foreach (['roles.manage', 'roles.view', 'users.manage', 'users.view', 'made.up'] as $key) {
            $this->actingAs($admin)
                ->post(route('admin.roles.store'), ['name' => 'Sneaky', 'permissions' => ['news.view', $key]])
                ->assertSessionHasErrors('permissions.1');

            $this->actingAs($admin)
                ->put(route('admin.roles.update', $role), ['name' => 'Helper', 'permissions' => [$key]])
                ->assertSessionHasErrors('permissions.0');
        }

        $this->actingAs($admin)
            ->post(route('admin.roles.store'), ['name' => 'Sneaky', 'permissions' => 'roles.manage'])
            ->assertSessionHasErrors('permissions');

        $this->assertNull(Role::where('name', 'Sneaky')->first());
        $this->assertSame(['news.view'], $role->fresh()->permissionKeys());
        $this->assertDatabaseMissing('role_permissions', ['permission' => 'roles.manage']);
        $this->assertDatabaseMissing('role_permissions', ['permission' => 'users.manage']);
    }

    // ----- Access control: the privilege-escalation guard -----

    public function test_nobody_but_an_administrator_can_manage_roles_or_logins_even_with_every_delegable_permission(): void
    {
        $target = $this->customRole('Target', ['news.view']);
        $victim = $this->userWith($this->scorerRole);

        // Every permission a role can hold, panel access included: a login like this is as
        // powerful as a non-administrator can be made, and must still get nowhere near access control.
        $everything = $this->customRole('Everything', Permissions::keys(delegableOnly: true));

        // The same, but with the access-control permissions forced into the table behind the app's
        // back: they must not be honoured either.
        $tampered = $this->customRole('Tampered', Permissions::keys(delegableOnly: true));
        DB::table('role_permissions')->insert(array_map(
            fn (string $key) => ['role_id' => $tampered->id, 'permission' => $key],
            ['roles.view', 'roles.manage', 'users.view', 'users.manage'],
        ));

        $actors = [
            'scorer' => $this->userWith($this->scorerRole),
            'every delegable permission' => $this->userWith($everything),
            'access control forced into the database' => $this->userWith($tampered),
        ];

        foreach ($actors as $label => $actor) {
            // The 403s below come from access control, not from the panel gate.
            $this->assertTrue(Gate::forUser($actor)->allows('access-admin-panel'), $label);

            $this->actingAs($actor);

            $this->get(route('admin.roles.index'))->assertForbidden();
            $this->get(route('admin.roles.create'))->assertForbidden();
            $this->get(route('admin.roles.show', $target))->assertForbidden();
            $this->get(route('admin.roles.edit', $target))->assertForbidden();
            $this->post(route('admin.roles.store'), ['name' => 'Backdoor', 'permissions' => ['panel.access']])->assertForbidden();
            $this->post(route('admin.roles.store'), [])->assertForbidden();
            $this->put(route('admin.roles.update', $target), ['name' => 'Target', 'permissions' => ['news.manage']])->assertForbidden();
            $this->delete(route('admin.roles.destroy', $target))->assertForbidden();

            $this->get(route('admin.users.index'))->assertForbidden();
            $this->get(route('admin.users.create'))->assertForbidden();
            $this->get(route('admin.users.show', $victim))->assertForbidden();
            $this->get(route('admin.users.edit', $victim))->assertForbidden();
            $this->post(route('admin.users.store'), [
                'name' => 'Backdoor', 'email' => 'backdoor@example.com', 'role_id' => $this->adminRole->id,
                'password' => 'password123', 'password_confirmation' => 'password123',
            ])->assertForbidden();
            // Refused before validation: nothing is learned from the error messages, such as which
            // email addresses already have an account.
            $this->post(route('admin.users.store'), ['email' => $victim->email])->assertForbidden();
            $this->put(route('admin.users.update', $victim), ['email' => $actor->email])->assertForbidden();
            $this->put(route('admin.users.update', $victim), [
                'name' => $victim->name, 'email' => $victim->email, 'role_id' => $this->adminRole->id, 'is_active' => '1',
            ])->assertForbidden();
            // Promoting oneself is the obvious attack.
            $this->put(route('admin.users.update', $actor), [
                'name' => $actor->name, 'email' => $actor->email, 'role_id' => $this->adminRole->id, 'is_active' => '1',
            ])->assertForbidden();
        }

        $this->assertNull(Role::where('name', 'Backdoor')->first());
        $this->assertNull(User::where('email', 'backdoor@example.com')->first());
        $this->assertSame(['news.view'], $target->fresh()->permissionKeys());
        $this->assertSame($this->scorerRole->id, $victim->fresh()->role_id);

        foreach ($actors as $actor) {
            $this->assertNotSame($this->adminRole->id, $actor->fresh()->role_id);
            $this->assertFalse($actor->fresh()->hasPermission('roles.manage'));
            $this->assertFalse($actor->fresh()->hasPermission('users.manage'));
        }
    }

    public function test_the_sidebar_offers_users_and_roles_only_to_administrators(): void
    {
        $everything = $this->customRole('Everything', Permissions::keys(delegableOnly: true));

        $routesFor = function (User $user): array {
            $navigation = app(AdminNavigation::class)->forUser($user);

            return collect($navigation['top'])
                ->merge(collect($navigation['groups'])->flatMap(fn (array $group) => $group['items']))
                ->pluck('route')
                ->all();
        };

        $this->assertContains('admin.roles.index', $routesFor($this->admin()));
        $this->assertContains('admin.users.index', $routesFor($this->admin()));

        foreach ([$this->userWith($this->scorerRole), $this->userWith($everything)] as $user) {
            $this->assertNotContains('admin.roles.index', $routesFor($user));
            $this->assertNotContains('admin.users.index', $routesFor($user));
        }
    }

    // ----- A role in use -----

    public function test_a_custom_role_can_be_given_to_a_login_and_grants_exactly_its_permissions(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.roles.store'), ['name' => 'News Editor', 'permissions' => ['panel.access', 'news.manage']])
            ->assertRedirect(route('admin.roles.index'));

        $role = Role::where('slug', 'news-editor')->firstOrFail();

        // The Users form offers the new role by name...
        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()->assertSee('News Editor');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Nina', 'email' => 'nina@example.com', 'role_id' => $role->id,
                'password' => 'password123', 'password_confirmation' => 'password123',
            ])
            ->assertRedirect(route('admin.users.index'));

        $editor = User::where('email', 'nina@example.com')->firstOrFail();
        $this->assertSame($role->id, $editor->role_id);

        // The account pages show the role by its name, and the list can be filtered by it.
        $this->actingAs($admin)->get(route('admin.users.show', $editor))->assertOk()->assertSee('News Editor');
        $this->actingAs($admin)->get(route('admin.users.edit', $editor))->assertOk()->assertSee('News Editor');
        $this->actingAs($admin)->get(route('admin.users.index', ['role_id' => $role->id]))->assertOk()->assertSee('Nina');

        // ...and that login can do what the role allows, and nothing else.
        $this->actingAs($editor)->get(route('admin.news.index'))->assertOk();
        $this->actingAs($editor)->get(route('admin.news.create'))->assertOk();
        $this->actingAs($editor)->get(route('admin.venues.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('admin.roles.index'))->assertForbidden();

        // Taking a permission away takes effect for that login straight away.
        $this->actingAs($admin)->put(route('admin.roles.update', $role), ['name' => 'News Editor', 'permissions' => ['panel.access']]);

        $this->actingAs($editor->fresh())->get(route('admin.news.index'))->assertForbidden();
    }

    public function test_the_roles_list_shows_built_in_roles_first_with_their_user_and_permission_counts(): void
    {
        $custom = $this->customRole('Aaa Custom', ['news.manage']);
        $this->userWith($custom);

        $this->actingAs($this->admin())
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertViewHas('roles', fn ($roles) => $roles->pluck('slug')->all() === ['admin', 'auctioneer', 'scorer', 'aaa-custom'])
            ->assertViewHas('permissionCounts', fn (array $counts) => $counts[$custom->id] === 2);
    }
}
