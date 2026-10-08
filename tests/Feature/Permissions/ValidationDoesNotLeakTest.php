<?php

namespace Tests\Feature\Permissions;

use App\Models\Player;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A validation message such as "the phone has already been taken" tells the reader that a record
 * exists. Laravel resolves a FormRequest, and so validates, BEFORE the controller method runs, so the
 * controller's own authorize() call comes too late to stop that: the request itself must ask the policy.
 */
class ValidationDoesNotLeakTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        static $n = 0;
        $n++;

        $role = Role::create(['name' => "Staff {$n}", 'slug' => "staff-{$n}"]);
        $role->syncPermissions(['panel.access', ...$permissions]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_every_admin_request_with_a_unique_rule_asks_the_policy_first(): void
    {
        $offenders = [];

        foreach (File::allFiles(app_path('Http/Requests/Admin')) as $file) {
            $source = str_replace("\r\n", "\n", $file->getContents());

            $hasUniqueRule = (bool) preg_match('/unique:|Rule::unique\(/', $source);
            $authorizesNothing = (bool) preg_match('/function authorize\(\): bool\s*\{\s*return true;\s*\}/', $source);

            if ($hasUniqueRule && $authorizesNothing) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'These requests validate (and so reveal existing records) before anyone has been authorized: '.implode(', ', $offenders));
    }

    public function test_a_panel_user_without_player_permission_cannot_probe_for_a_phone_number(): void
    {
        Player::factory()->create(['phone' => '9876501234', 'email' => 'taken@example.test']);

        $outsider = $this->userWith(['news.manage']);

        $this->actingAs($outsider)
            ->post(route('admin.players.store'), ['name' => 'Probe', 'phone' => '9876501234', 'email' => 'taken@example.test'])
            ->assertForbidden();
    }

    public function test_a_player_manager_still_gets_the_normal_duplicate_message(): void
    {
        Player::factory()->create(['phone' => '9876501234']);

        $manager = $this->userWith(['players.manage']);

        $this->actingAs($manager)
            ->from(route('admin.players.create'))
            ->post(route('admin.players.store'), ['name' => 'Second', 'phone' => '9876501234'])
            ->assertRedirect(route('admin.players.create'))
            ->assertSessionHasErrors('phone');
    }

    public function test_the_same_goes_for_team_names_and_for_editing(): void
    {
        $team = Team::factory()->create(['name' => 'Secret Eleven']);
        $other = Team::factory()->create(['name' => 'Another XI']);

        $outsider = $this->userWith(['news.manage']);

        $this->actingAs($outsider)->post(route('admin.teams.store'), ['name' => 'Secret Eleven'])->assertForbidden();
        $this->actingAs($outsider)->put(route('admin.teams.update', $other), ['name' => 'Secret Eleven'])->assertForbidden();

        $teamManager = $this->userWith(['teams.manage']);

        $this->actingAs($teamManager)
            ->from(route('admin.teams.edit', $other))
            ->put(route('admin.teams.update', $other), ['name' => $team->name])
            ->assertSessionHasErrors('name');
    }
}
