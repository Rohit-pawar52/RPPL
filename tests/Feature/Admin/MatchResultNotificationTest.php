<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendNotificationJob;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\MatchResult\MatchResultNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Automatic push notification after a match is successfully finalized —
 * reuses the exact same Notification/NotificationSend/SendNotificationJob
 * pipeline every other push feature uses. Never sends a real Firebase
 * message: SendNotificationJob is faked via Queue::fake() throughout,
 * exactly like NotificationSendTest/AnnouncementNotificationSchedulingTest/
 * DispatchMatchRemindersTest.
 */
class MatchResultNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        Role::create(['name' => 'Scorer', 'slug' => 'scorer']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function liveMatchWithSquads(array $matchAttributes = []): GameMatch
    {
        $match = GameMatch::factory()->create(array_merge([
            'match_status' => 'live',
            'started_at' => now(),
        ], $matchAttributes));

        $match->update(['toss_winner_team_id' => $match->edition_team_a_id, 'toss_decision' => 'bat']);

        foreach ([$match->edition_team_a_id, $match->edition_team_b_id] as $editionTeamId) {
            for ($i = 0; $i < 2; $i++) {
                MatchPlayer::factory()->create([
                    'match_id' => $match->id,
                    'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $editionTeamId])->id,
                ]);
            }
        }

        return $match->fresh();
    }

    private function addInnings(GameMatch $match, int $number, int $battingTeamId, int $bowlingTeamId, int $runs, int $wickets): Innings
    {
        return Innings::create([
            'match_id' => $match->id,
            'innings_number' => $number,
            'batting_team_id' => $battingTeamId,
            'bowling_team_id' => $bowlingTeamId,
            'status' => 'completed',
            'total_runs' => $runs,
            'total_wickets' => $wickets,
        ]);
    }

    private function matchReadyToFinalize(int $firstRuns, int $firstWickets, int $secondRuns, int $secondWickets, array $matchAttributes = []): GameMatch
    {
        $match = $this->liveMatchWithSquads($matchAttributes);

        $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, $firstRuns, $firstWickets);
        $this->addInnings($match, 2, $match->edition_team_b_id, $match->edition_team_a_id, $secondRuns, $secondWickets);

        return $match->fresh();
    }

    // ----- Basic dispatch on finalize -----

    public function test_finalizing_a_match_queues_exactly_one_result_notification(): void
    {
        Queue::fake();
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);

        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));

        $fresh = $match->fresh();
        $this->assertNotNull($fresh->result_notification_dispatched_at);
        $this->assertNotNull($fresh->result_notification_id);
        $this->assertSame(1, Notification::count());
        $this->assertSame(1, NotificationSend::count());
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_result_notification_uses_the_canonical_match_result_text(): void
    {
        Queue::fake();
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);
        $teamAName = $match->teamA->team->name;

        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));

        $notification = Notification::firstOrFail();
        $this->assertSame("{$teamAName} won by 10 runs", $notification->message);
        $this->assertSame($match->fresh()->match_result, $notification->message);
    }

    public function test_wicket_margin_result_produces_correct_content(): void
    {
        Queue::fake();
        $match = $this->matchReadyToFinalize(150, 8, 151, 6);
        $teamBName = $match->teamB->team->name;

        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));

        $this->assertSame("{$teamBName} won by 4 wickets", Notification::firstOrFail()->message);
    }

    public function test_super_over_result_uses_canonical_result_text(): void
    {
        Queue::fake();
        $match = $this->matchReadyToFinalize(150, 8, 150, 9);
        $winner = $match->teamA;

        $this->actingAs($this->admin())->post(route('admin.matches.super-over', $match), [
            'winner_team_id' => $winner->id,
            'reason' => 'Super Over: 8 runs to 5',
        ]);

        $fresh = $match->fresh();
        $this->assertSame('completed', $fresh->match_status);
        $this->assertNotNull($fresh->result_notification_dispatched_at);
        $this->assertSame($fresh->match_result, Notification::firstOrFail()->message);
        $this->assertStringContainsString('Super Over', $fresh->match_result);
    }

    public function test_tied_match_without_super_over_notifies_with_canonical_tied_text_and_no_fabricated_winner(): void
    {
        Queue::fake();
        $match = $this->matchReadyToFinalize(150, 8, 150, 9);

        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));

        $fresh = $match->fresh();
        $this->assertNull($fresh->winner_team_id);
        $this->assertSame('Match tied', Notification::firstOrFail()->message);
    }

    // ----- Firebase never called synchronously -----

    public function test_firebase_is_not_called_synchronously_the_job_is_only_queued(): void
    {
        Queue::fake();
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);

        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));

        // NotificationSend exists but completed_at is still null — proves
        // the actual Firebase send only happens inside the (faked, never
        // executed) queued job, never inline in the request.
        $send = NotificationSend::firstOrFail();
        $this->assertNull($send->completed_at);
    }

    // ----- Idempotency -----

    public function test_repeated_finalize_attempt_cannot_duplicate_the_notification(): void
    {
        Queue::fake();
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);

        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));
        // finalize() itself already fails safely on a second call (match
        // is no longer 'live'), but the controller unconditionally calls
        // dispatchIfDue() after every successful path — proving the
        // underlying claim is what actually prevents a duplicate, not
        // just finalize()'s own single-call guard.
        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));

        $this->assertSame(1, NotificationSend::count());
    }

    public function test_calling_dispatch_if_due_twice_directly_remains_idempotent(): void
    {
        Queue::fake();
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);
        $admin = $this->admin();
        $service = app(MatchResultNotificationService::class);

        $first = $service->dispatchIfDue($match->id, $admin);
        // Not yet finalized — nothing to claim.
        $this->assertFalse($first);

        $this->actingAs($admin)->post(route('admin.matches.finalize', $match));
        $this->assertSame(1, NotificationSend::count());

        // The manual recovery action, called again after the automatic
        // one already succeeded — must be a safe no-op.
        $second = $service->dispatchIfDue($match->id, $admin);
        $this->assertFalse($second);
        $this->assertSame(1, NotificationSend::count());
    }

    public function test_manual_resend_action_is_a_no_op_once_already_dispatched(): void
    {
        Queue::fake();
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.matches.finalize', $match));
        $this->actingAs($admin)
            ->post(route('admin.matches.resend-result-notification', $match))
            ->assertSessionHas('error');

        $this->assertSame(1, NotificationSend::count());
    }

    // ----- Only created after successful finalization -----

    public function test_a_failed_finalize_attempt_creates_no_notification(): void
    {
        Queue::fake();
        // Only innings 1 exists — finalize() itself will fail.
        $match = $this->liveMatchWithSquads();
        $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 150, 8);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.finalize', $match))
            ->assertSessionHas('error');

        $this->assertSame('live', $match->fresh()->match_status);
        $this->assertSame(0, Notification::count());
        $this->assertNull($match->fresh()->result_notification_dispatched_at);
    }

    // ----- Failure semantics -----

    public function test_a_notification_dispatch_failure_does_not_undo_the_finalized_match(): void
    {
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);

        config(['queue.default' => 'nonexistent-connection']);
        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));

        $fresh = $match->fresh();
        $this->assertSame('completed', $fresh->match_status);
        $this->assertNotNull($fresh->match_result);
    }

    public function test_a_failed_queue_dispatch_never_falsely_marks_the_notification_as_dispatched(): void
    {
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);

        config(['queue.default' => 'nonexistent-connection']);
        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));

        $this->assertNull($match->fresh()->result_notification_dispatched_at);

        // Recovery: fix the connection and use the manual resend action.
        config(['queue.default' => 'sync']);
        Queue::fake();
        $this->actingAs($this->admin())
            ->post(route('admin.matches.resend-result-notification', $match))
            ->assertSessionHas('success');

        $this->assertNotNull($match->fresh()->result_notification_dispatched_at);
    }

    // ----- Ineligible states -----

    public function test_cancelled_match_never_sends_a_result_notification(): void
    {
        Queue::fake();
        $match = GameMatch::factory()->create(['match_status' => 'scheduled']);

        $this->actingAs($this->admin())->post(route('admin.matches.cancel', $match));

        $fresh = $match->fresh();
        $this->assertSame('cancelled', $fresh->match_status);
        $this->assertSame(0, Notification::count());

        // Direct service-level proof too — even if something tried to
        // claim it, the scope excludes non-'completed' matches.
        $this->assertFalse(app(MatchResultNotificationService::class)->dispatchIfDue($fresh->id, $this->admin()));
    }

    public function test_abandoned_match_never_sends_a_winner_result_notification(): void
    {
        Queue::fake();
        $match = $this->liveMatchWithSquads();

        $this->actingAs($this->admin())->post(route('admin.matches.abandon', $match));

        $fresh = $match->fresh();
        $this->assertSame('abandoned', $fresh->match_status);
        $this->assertSame(0, Notification::count());
        $this->assertFalse(app(MatchResultNotificationService::class)->dispatchIfDue($fresh->id, $this->admin()));
    }

    // ----- Admin visibility -----

    public function test_admin_match_page_shows_result_notification_status(): void
    {
        Queue::fake();
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);

        $before = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));
        $before->assertDontSee('Result Notification:');

        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));

        $after = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));
        $after->assertSee('Result Notification:');
        $after->assertSee('Queued');
    }

    // ----- Reopen resets eligibility for a corrected re-finalize -----

    public function test_reopening_a_finalized_match_clears_result_notification_tracking(): void
    {
        Queue::fake();
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.matches.finalize', $match));
        $this->assertNotNull($match->fresh()->result_notification_dispatched_at);

        $this->actingAs($admin)->post(route('admin.matches.reopen', $match), ['reason' => 'Scoring correction needed']);

        $reopened = $match->fresh();
        $this->assertSame('live', $reopened->match_status);
        $this->assertNull($reopened->result_notification_dispatched_at);
        $this->assertNull($reopened->result_notification_id);
    }
}
