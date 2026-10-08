<?php

namespace Tests\Feature\Permissions;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A site that was deployed BEFORE roles became editable already has its scorer and auctioneer logins and
 * no role_permissions table. Deploying this change must leave those logins able to do exactly what they
 * could before - a scorer with no permissions would not even be able to sign in.
 */
class RolePermissionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Put the database back into the state of a site that has not run the role_permissions migration yet.
     * Roles are inserted straight into the table: the Role model's events would try to store the built-in
     * rights in the table that does not exist yet, exactly as it did not exist back then.
     */
    private function olderSite(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);

        $this->assertFalse(Schema::hasTable('role_permissions'), 'the role_permissions migration should have been rolled back');

        foreach ([['Admin', 'admin'], ['Scorer', 'scorer']] as [$name, $slug]) {
            DB::table('roles')->insert(['name' => $name, 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_existing_scorers_and_auctioneers_keep_exactly_the_access_they_had_after_the_upgrade(): void
    {
        $this->olderSite();

        Artisan::call('migrate', ['--force' => true]);

        $this->assertEqualsCanonicalizing(
            ['panel.access', 'dashboard.tournament', 'matches.view', 'matches.run', 'scoring.score', 'matches.finalize'],
            Role::where('slug', 'scorer')->firstOrFail()->permissionKeys()
        );
        $this->assertEqualsCanonicalizing(
            ['panel.access', 'auction.run'],
            Role::where('slug', 'auctioneer')->firstOrFail()->permissionKeys()
        );

        // The admin role needs no stored rows: it always holds everything.
        $this->assertSame([], Role::where('slug', 'admin')->firstOrFail()->permissionKeys());
    }

    public function test_an_existing_scorer_login_can_still_sign_in_and_score_after_the_upgrade(): void
    {
        $this->olderSite();

        $scorerRoleId = (int) DB::table('roles')->where('slug', 'scorer')->value('id');
        $scorer = User::factory()->create(['role_id' => $scorerRoleId, 'password' => 'a-scorer-password']);

        Artisan::call('migrate', ['--force' => true]);

        $this->assertTrue($scorer->fresh()->hasPermission('panel.access'));
        $this->assertTrue($scorer->fresh()->hasPermission('scoring.score'));
        $this->assertFalse($scorer->fresh()->hasPermission('settings.manage'));
        $this->assertFalse($scorer->fresh()->hasPermission('matches.manage'));

        $this->post(route('admin.login.store'), ['email' => $scorer->email, 'password' => 'a-scorer-password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.matches.index'))->assertOk();
        $this->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_running_the_migration_again_never_duplicates_or_resets_permissions(): void
    {
        $this->olderSite();
        Artisan::call('migrate', ['--force' => true]);

        // An admin edits the scorer role after the upgrade...
        Role::where('slug', 'scorer')->firstOrFail()->syncPermissions(['panel.access', 'matches.view']);

        // ...and a redeploy (migrate again, nothing pending) must not undo that.
        Artisan::call('migrate', ['--force' => true]);

        $this->assertEqualsCanonicalizing(
            ['panel.access', 'matches.view'],
            Role::where('slug', 'scorer')->firstOrFail()->permissionKeys()
        );
    }
}
