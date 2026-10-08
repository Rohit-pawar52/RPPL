<?php

namespace Tests\Feature\Permissions;

use App\Models\Role;
use App\Models\User;
use App\Support\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>|null  $permissions  null = keep what the role already has (built-in roles)
     * @return array{top: list<string>, groups: array<string, list<string>>}
     */
    private function sidebarFor(string $slug, ?array $permissions): array
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);

        if ($permissions !== null) {
            $role->syncPermissions($permissions);
        }

        $navigation = app(AdminNavigation::class)->forUser(User::factory()->create(['role_id' => $role->id])->load('role'));

        return [
            'top' => array_column($navigation['top'], 'label'),
            'groups' => collect($navigation['groups'])
                ->mapWithKeys(fn (array $group) => [$group['label'] => array_column($group['items'], 'label')])
                ->all(),
        ];
    }

    public function test_a_role_sees_exactly_the_links_its_permissions_open(): void
    {
        $sidebar = $this->sidebarFor('content-editor', ['panel.access', 'news.manage', 'photos.view', 'finance.view']);

        $this->assertSame(['Dashboard'], $sidebar['top']);
        $this->assertSame([
            'Content Management' => ['News', 'Photos'],
            'Finance Management' => ['Overview', 'Transactions', 'Contributions'],
        ], $sidebar['groups']);
    }

    public function test_a_role_with_only_panel_access_sees_just_the_dashboard(): void
    {
        $sidebar = $this->sidebarFor('visitor', ['panel.access']);

        $this->assertSame(['Dashboard'], $sidebar['top']);
        $this->assertSame([], $sidebar['groups']);
    }

    public function test_the_built_in_roles_keep_their_sidebar(): void
    {
        $scorer = $this->sidebarFor('scorer', null);
        $auctioneer = $this->sidebarFor('auctioneer', null);

        $this->assertSame(['Dashboard', 'Matches'], $scorer['top']);
        $this->assertSame([], $scorer['groups']);
        $this->assertSame(['Dashboard', 'Auction'], $auctioneer['top']);
        $this->assertSame([], $auctioneer['groups']);
    }

    public function test_the_admin_sees_every_section_including_access_control(): void
    {
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $navigation = app(AdminNavigation::class)->forUser(User::factory()->create(['role_id' => $role->id])->load('role'));

        $this->assertSame(['Dashboard', 'Matches', 'Auction', 'Reports', 'Analytics'], array_column($navigation['top'], 'label'));
        $this->assertSame(
            ['Tournament Management', 'Player Management', 'Content Management', 'Finance Management', 'Communication', 'System Management'],
            array_column($navigation['groups'], 'label')
        );
        $system = collect($navigation['groups'])->firstWhere('label', 'System Management');
        $this->assertSame(['Users', 'Roles', 'Settings', 'Data Cleanup'], array_column($system['items'], 'label'));
    }
}
