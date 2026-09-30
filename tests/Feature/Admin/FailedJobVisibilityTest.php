<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Read-only Failed Jobs list + detail on Data Cleanup → System tab.
 * Fixtures are synthetic failed_jobs rows inserted directly — no real
 * queued job is ever run or crashed.
 */
class FailedJobVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET_AADHAAR = '123456789012';

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

    private function insertFailedJob(array $overrides = []): string
    {
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert(array_merge([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => $this->appJobPayload(),
            'exception' => "RuntimeException: Something broke\n#0 /app/Jobs/SendNotificationJob.php(42): handle()\n#1 {main}",
            'failed_at' => now(),
        ], $overrides));

        return $uuid;
    }

    /** Realistic shape of a queued app job's payload, with sensitive data nested in the serialized command. */
    private function appJobPayload(): string
    {
        return json_encode([
            'uuid' => (string) Str::uuid(),
            'displayName' => 'App\\Jobs\\SendNotificationJob',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'maxTries' => null,
            'data' => [
                'commandName' => 'App\\Jobs\\SendNotificationJob',
                'command' => 'O:28:"App\\Jobs\\SendNotificationJob":1:{s:4:"meta";a:1:{s:14:"aadhaar_number";s:12:"'.self::SECRET_AADHAAR.'";}}',
            ],
            'extra' => ['aadhaar_number' => self::SECRET_AADHAAR],
        ]);
    }

    /** Laravel sets displayName to the wrapped event's class for a queued broadcast (BroadcastEvent::displayName()). */
    private function broadcastEventPayload(): string
    {
        return json_encode([
            'uuid' => (string) Str::uuid(),
            'displayName' => 'App\\Events\\MatchScoreUpdated',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => [
                'commandName' => 'Illuminate\\Broadcasting\\BroadcastEvent',
                'command' => 'O:38:"Illuminate\\Broadcasting\\BroadcastEvent":1:{s:5:"event";N;}',
            ],
        ]);
    }

    private function systemTab(array $query = [])
    {
        return $this->actingAs($this->admin())
            ->get(route('admin.data-cleanup.index', array_merge(['tab' => 'system'], $query)));
    }

    public function test_admin_sees_failed_jobs_section_and_existing_delete_form(): void
    {
        $this->insertFailedJob();

        $this->systemTab()
            ->assertOk()
            ->assertSee('Failed Jobs')
            ->assertSee('SendNotificationJob')
            ->assertSee(route('admin.data-cleanup.failed-jobs.destroy'), false);
    }

    public function test_empty_state_renders_without_failed_jobs(): void
    {
        $this->systemTab()->assertOk()->assertSee('No failed jobs.');
    }

    public function test_other_tabs_do_not_query_the_failed_jobs_list(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.data-cleanup.index', ['tab' => 'notifications']))
            ->assertOk()
            ->assertViewHas('failedJobList', null);
    }

    public function test_rows_render_newest_first(): void
    {
        $this->insertFailedJob(['exception' => 'RuntimeException: older failure', 'failed_at' => now()->subDays(2)]);
        $this->insertFailedJob(['exception' => 'RuntimeException: newer failure', 'failed_at' => now()->subHour()]);

        $this->systemTab()->assertSeeInOrder(['newer failure', 'older failure']);
    }

    public function test_pagination_reaches_a_second_page_with_different_rows(): void
    {
        for ($i = 1; $i <= 21; $i++) {
            $this->insertFailedJob([
                'exception' => sprintf('RuntimeException: failure-%02d', $i),
                'failed_at' => now()->subMinutes(100 - $i),
            ]);
        }

        // failure-21 is newest (page 1); failure-01 is oldest (only on page 2).
        $this->systemTab()->assertSee('failure-21')->assertDontSee('failure-01');
        $this->systemTab(['page' => 2])->assertOk()->assertSee('failure-01')->assertDontSee('failure-21');
    }

    public function test_known_app_job_gets_a_readable_name(): void
    {
        $this->insertFailedJob();

        $this->systemTab()->assertSee('SendNotificationJob')->assertDontSee('Unknown Job');
    }

    public function test_broadcast_event_row_shows_the_wrapped_event_name(): void
    {
        $this->insertFailedJob(['payload' => $this->broadcastEventPayload()]);

        $this->systemTab()->assertOk()->assertSee('MatchScoreUpdated (broadcast)');
    }

    public function test_malformed_payloads_fall_back_to_unknown_job(): void
    {
        $this->insertFailedJob(['payload' => 'this is {not json', 'exception' => 'RuntimeException: bad-json']);
        $this->insertFailedJob(['payload' => json_encode(['job' => 'x']), 'exception' => 'RuntimeException: no-name']);
        $this->insertFailedJob(['payload' => json_encode(['displayName' => ['nested']]), 'exception' => 'RuntimeException: wrong-type']);
        $uuid = $this->insertFailedJob(['payload' => '', 'exception' => 'RuntimeException: empty']);

        $this->systemTab()->assertOk()->assertSee('Unknown Job')->assertSee('bad-json')->assertSee('wrong-type');

        $this->actingAs($this->admin())
            ->get(route('admin.data-cleanup.failed-jobs.show', $uuid))
            ->assertOk()
            ->assertSee('Unknown Job');
    }

    public function test_list_shows_truncated_first_line_not_full_stack_trace(): void
    {
        $longMessage = 'RuntimeException: '.str_repeat('x', 300);
        $this->insertFailedJob(['exception' => $longMessage."\n#0 /app/Secret/StackFrame.php(12): boom()"]);

        $this->systemTab()
            ->assertSee(Str::limit($longMessage, 150))
            ->assertDontSee($longMessage)
            ->assertDontSee('StackFrame.php');
    }

    public function test_detail_page_shows_full_exception_but_never_the_payload(): void
    {
        $uuid = $this->insertFailedJob([
            'exception' => "RuntimeException: Something broke\n#0 /app/Jobs/SendNotificationJob.php(42): handle()\n#1 <script>alert(1)</script>",
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.data-cleanup.failed-jobs.show', $uuid));

        $response->assertOk()
            ->assertSee('SendNotificationJob')
            ->assertSee($uuid)
            ->assertSee('#0 /app/Jobs/SendNotificationJob.php(42): handle()')
            // Stack trace is escaped plain text, never interpreted as HTML.
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee(self::SECRET_AADHAAR)
            ->assertDontSee('aadhaar_number')
            ->assertDontSee('CallQueuedHandler')
            ->assertDontSee('commandName');

        $this->systemTab()->assertDontSee(self::SECRET_AADHAAR)->assertDontSee('aadhaar_number');
    }

    public function test_unknown_uuid_is_a_404_not_a_crash(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.data-cleanup.failed-jobs.show', (string) Str::uuid()))
            ->assertNotFound();
    }

    public function test_scorer_and_guest_cannot_view_list_or_detail(): void
    {
        $uuid = $this->insertFailedJob();

        $this->actingAs($this->scorer())->get(route('admin.data-cleanup.index', ['tab' => 'system']))->assertForbidden();
        $this->actingAs($this->scorer())->get(route('admin.data-cleanup.failed-jobs.show', $uuid))->assertForbidden();

        auth()->logout();

        $this->get(route('admin.data-cleanup.failed-jobs.show', $uuid))->assertRedirect(route('admin.login'));
    }

    public function test_no_retry_control_or_route_exists(): void
    {
        $uuid = $this->insertFailedJob();

        $this->systemTab()->assertDontSee('retry', false)->assertDontSee('Retry', false);
        $this->actingAs($this->admin())
            ->get(route('admin.data-cleanup.failed-jobs.show', $uuid))
            ->assertDontSee('retry', false)
            ->assertDontSee('Retry', false);

        $retryRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains(strtolower($route->uri().' '.($route->getName() ?? '')), 'retry'));

        $this->assertCount(0, $retryRoutes);
    }
}
