<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scheduler foundation phase — the Admin-facing half of match reminders
 * (reminder_enabled/_minutes_before/_dispatched_at). See
 * DispatchMatchRemindersTest for the scheduler-scan/duplicate-protection/
 * reschedule/cancellation half of this feature.
 */
class MatchReminderSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    /**
     * @return array{0: Edition, 1: EditionTeam, 2: EditionTeam}
     */
    private function eligibleTeams(): array
    {
        $edition = Edition::factory()->create(['status' => 'upcoming']);
        $teamA = EditionTeam::factory()->create([
            'edition_id' => $edition->id,
            'team_id' => Team::factory()->create(['is_active' => true])->id,
        ]);
        $teamB = EditionTeam::factory()->create([
            'edition_id' => $edition->id,
            'team_id' => Team::factory()->create(['is_active' => true])->id,
        ]);

        return [$edition, $teamA, $teamB];
    }

    private function validPayload(Edition $edition, EditionTeam $teamA, EditionTeam $teamB, array $overrides = []): array
    {
        return array_merge([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ], $overrides);
    }

    public function test_reminders_are_disabled_by_default(): void
    {
        [$edition, $teamA, $teamB] = $this->eligibleTeams();

        $this->actingAs($this->admin())->post(route('admin.matches.store'), $this->validPayload($edition, $teamA, $teamB));

        $match = GameMatch::firstOrFail();
        $this->assertFalse($match->reminder_enabled);
        $this->assertNull($match->reminder_dispatched_at);
    }

    public function test_admin_can_enable_a_reminder_with_minutes_before(): void
    {
        [$edition, $teamA, $teamB] = $this->eligibleTeams();

        $this->actingAs($this->admin())->post(route('admin.matches.store'), $this->validPayload($edition, $teamA, $teamB, [
            'reminder_enabled' => '1',
            'reminder_minutes_before' => 45,
        ]));

        $match = GameMatch::firstOrFail();
        $this->assertTrue($match->reminder_enabled);
        $this->assertSame(45, $match->reminder_minutes_before);
    }

    public function test_minutes_before_is_required_when_reminder_is_enabled(): void
    {
        [$edition, $teamA, $teamB] = $this->eligibleTeams();

        $response = $this->actingAs($this->admin())->post(route('admin.matches.store'), $this->validPayload($edition, $teamA, $teamB, [
            'reminder_enabled' => '1',
            'reminder_minutes_before' => '',
        ]));

        $response->assertSessionHasErrors('reminder_minutes_before');
    }

    public function test_admin_can_disable_an_enabled_reminder_on_edit(): void
    {
        [$edition, $teamA, $teamB] = $this->eligibleTeams();
        $match = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'reminder_enabled' => true,
            'reminder_minutes_before' => 30,
        ]);

        $this->actingAs($this->admin())->put(route('admin.matches.update', $match), $this->validPayload($edition, $teamA, $teamB, [
            // reminder_enabled omitted — an unchecked checkbox.
        ]));

        $match->refresh();
        $this->assertFalse($match->reminder_enabled);
    }

    public function test_existing_match_creation_still_works_without_any_reminder_fields(): void
    {
        [$edition, $teamA, $teamB] = $this->eligibleTeams();

        $response = $this->actingAs($this->admin())->post(route('admin.matches.store'), $this->validPayload($edition, $teamA, $teamB));

        $response->assertRedirect(route('admin.matches.index'));
        $this->assertSame(1, GameMatch::count());
    }
}
