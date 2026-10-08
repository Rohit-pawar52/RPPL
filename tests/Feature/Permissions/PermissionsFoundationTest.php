<?php

namespace Tests\Feature\Permissions;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionsFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(Role $role): User
    {
        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_the_admin_role_holds_every_permission_but_never_an_unknown_one(): void
    {
        $admin = $this->userWith(Role::create(['name' => 'Admin', 'slug' => 'admin']));

        foreach (Permissions::keys() as $key) {
            $this->assertTrue($admin->hasPermission($key), "admin should hold {$key}");
        }

        // A typo in a policy must fail closed, even for the admin.
        $this->assertFalse($admin->hasPermission('matches.mange'));
    }

    public function test_built_in_roles_start_with_exactly_the_rights_they_always_had(): void
    {
        // Created now: the model event stores the built-in rights.
        $scorer = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);

        $this->assertEqualsCanonicalizing(
            ['panel.access', 'dashboard.tournament', 'matches.view', 'matches.run', 'scoring.score', 'matches.finalize'],
            $scorer->permissionKeys()
        );

        // The auctioneer role already exists after the migrations (an earlier migration inserts it);
        // the role_permissions migration must have given it its rights too, as for existing sites.
        $auctioneer = Role::where('slug', 'auctioneer')->firstOrFail();

        $this->assertEqualsCanonicalizing(['panel.access', 'auction.run'], $auctioneer->permissionKeys());
    }

    public function test_a_custom_role_starts_with_nothing_and_is_denied_everything(): void
    {
        $user = $this->userWith(Role::create(['name' => 'Helper', 'slug' => 'helper']));

        foreach (Permissions::keys() as $key) {
            $this->assertFalse($user->hasPermission($key), "a new role must not hold {$key}");
        }
    }

    public function test_manage_includes_view_but_view_does_not_include_manage(): void
    {
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
        $role->syncPermissions(['news.manage', 'photos.view']);
        $user = $this->userWith($role->fresh());

        $this->assertTrue($user->hasPermission('news.manage'));
        $this->assertTrue($user->hasPermission('news.view'));
        $this->assertTrue($user->hasPermission('photos.view'));
        $this->assertFalse($user->hasPermission('photos.manage'));
    }

    public function test_running_a_match_or_sending_a_notification_includes_looking_at_it(): void
    {
        $role = Role::create(['name' => 'Match helper', 'slug' => 'match-helper']);
        $role->syncPermissions(['scoring.score', 'notifications.send']);
        $user = $this->userWith($role->fresh());

        // These actions all happen on the match / notification page, so that page must open.
        $this->assertTrue($user->hasPermission('matches.view'));
        $this->assertTrue($user->hasPermission('notifications.view'));

        // ...but nothing stronger comes with them.
        $this->assertFalse($user->hasPermission('matches.manage'));
        $this->assertFalse($user->hasPermission('matches.run'));
        $this->assertFalse($user->hasPermission('notifications.manage'));

        // The implied keys are stored too, so the role editor shows them ticked.
        $this->assertEqualsCanonicalizing(
            ['scoring.score', 'matches.view', 'notifications.send', 'notifications.view'],
            $role->permissionKeys()
        );
    }

    public function test_every_permission_can_be_asked_as_a_gate_and_follows_the_role(): void
    {
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
        $role->syncPermissions(['news.manage']);
        $user = $this->userWith($role->fresh());

        $this->assertTrue($user->can('news.view'));
        $this->assertTrue($user->can('news.manage'));
        $this->assertFalse($user->can('photos.view'));
        $this->assertFalse($user->can('roles.manage'));

        $admin = $this->userWith(Role::create(['name' => 'Admin', 'slug' => 'admin']));

        foreach (Permissions::keys() as $key) {
            $this->assertTrue($admin->can($key), "admin should pass the {$key} gate");
        }
    }

    public function test_access_control_permissions_are_never_delegable(): void
    {
        $role = Role::create(['name' => 'Sneaky', 'slug' => 'sneaky']);

        // Not storable through the normal path...
        $role->syncPermissions(['roles.manage', 'users.manage', 'users.view', 'news.view', 'not.a.permission']);
        $this->assertSame(['news.view'], $role->permissionKeys());

        // ...and not honoured even if a row is forced into the database some other way.
        DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission' => 'roles.manage']);
        $user = $this->userWith($role->fresh());

        $this->assertFalse($user->hasPermission('roles.manage'));
        $this->assertFalse($user->hasPermission('users.manage'));
    }

    public function test_syncing_replaces_the_previous_permissions_and_the_admin_role_cannot_be_edited_down(): void
    {
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
        $role->syncPermissions(['news.manage']);
        $role->syncPermissions(['venues.view']);

        $this->assertEqualsCanonicalizing(['venues.view'], $role->permissionKeys());

        $admin = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $admin->syncPermissions([]);

        $this->assertTrue($this->userWith($admin->fresh())->hasPermission('settings.manage'));
    }

    public function test_deleting_a_role_removes_its_stored_permissions(): void
    {
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
        $role->syncPermissions(['news.manage']);

        $role->delete();

        $this->assertSame(0, DB::table('role_permissions')->where('role_id', $role->id)->count());
    }
}
