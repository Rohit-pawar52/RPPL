<?php

namespace Tests\Feature\Console;

use App\Models\DataCleanupLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * rppl:cleanup-failed-jobs — optional daily pruning of failed_jobs older
 * than the retention configured in Settings -> System. Synthetic rows only.
 */
class CleanupFailedJobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->flush();
        $this->travelTo(Carbon::parse('2026-10-31 12:00:00', 'UTC'));
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function failedJob(CarbonInterface $failedAt): string
    {
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\SendNotificationJob']),
            'exception' => 'RuntimeException: Something broke',
            'failed_at' => $failedAt,
        ]);

        return $uuid;
    }

    private function enable(int $retentionDays = 30): void
    {
        app(SettingsService::class)->setMany([
            'system.failed_jobs_auto_cleanup_enabled' => true,
            'system.failed_jobs_retention_days' => $retentionDays,
        ]);
    }

    private function systemPayload(array $overrides = []): array
    {
        return array_merge([
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'display_timezone' => 'Asia/Kolkata',
            'committee_minimum_contribution' => '1000',
        ], $overrides);
    }

    // ----- Command -----

    public function test_cleanup_is_disabled_by_default_and_deletes_nothing(): void
    {
        $this->assertFalse(app(SettingsService::class)->boolean('system.failed_jobs_auto_cleanup_enabled'));
        $this->assertSame(30, app(SettingsService::class)->integer('system.failed_jobs_retention_days'));

        $this->failedJob(now()->subYear());

        $this->artisan('rppl:cleanup-failed-jobs')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    public function test_enabled_cleanup_deletes_only_failed_jobs_older_than_retention(): void
    {
        $this->enable(30);
        $old = $this->failedJob(now()->subDays(45));
        $recent = $this->failedJob(now()->subDays(7));
        $today = $this->failedJob(now());

        $this->artisan('rppl:cleanup-failed-jobs')
            ->expectsOutputToContain('Deleted 1 failed job(s) older than 30 days.')
            ->assertSuccessful();

        $this->assertSame(
            [$recent, $today],
            DB::table('failed_jobs')->orderBy('failed_at')->pluck('uuid')->all(),
        );
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $old]);

        // Re-running is a no-op.
        $this->artisan('rppl:cleanup-failed-jobs')
            ->expectsOutputToContain('Deleted 0 failed job(s)')
            ->assertSuccessful();
        $this->assertSame(2, DB::table('failed_jobs')->count());
    }

    public function test_job_failed_exactly_at_the_retention_boundary_is_kept(): void
    {
        $this->enable(30);
        $boundary = $this->failedJob(now()->subDays(30));
        $justOlder = $this->failedJob(now()->subDays(30)->subSecond());

        Artisan::call('rppl:cleanup-failed-jobs');

        $this->assertDatabaseHas('failed_jobs', ['uuid' => $boundary]);
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $justOlder]);
    }

    public function test_pending_jobs_and_cleanup_audit_log_are_never_touched(): void
    {
        $this->enable(30);
        $this->failedJob(now()->subDays(60));
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => now()->subDays(60)->timestamp,
            'created_at' => now()->subDays(60)->timestamp,
        ]);

        Artisan::call('rppl:cleanup-failed-jobs');

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(0, DataCleanupLog::count());
    }

    public function test_out_of_range_stored_retention_deletes_nothing(): void
    {
        $this->enable(1);
        $this->failedJob(now()->subDays(60));

        $this->artisan('rppl:cleanup-failed-jobs')->assertFailed();

        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    // ----- Visibility after cleanup -----

    public function test_retained_jobs_stay_visible_and_deleted_jobs_return_404(): void
    {
        $this->enable(30);
        $admin = $this->admin();
        $old = $this->failedJob(now()->subDays(45));
        $recent = $this->failedJob(now()->subDays(2));

        Artisan::call('rppl:cleanup-failed-jobs');

        $this->actingAs($admin)
            ->get(route('admin.data-cleanup.index', ['tab' => 'system']))
            ->assertOk()
            ->assertSee('SendNotificationJob');
        $this->actingAs($admin)
            ->get(route('admin.data-cleanup.failed-jobs.show', $recent))
            ->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.data-cleanup.failed-jobs.show', $old))
            ->assertNotFound();
    }

    // ----- Settings -----

    public function test_admin_can_enable_cleanup_and_save_a_valid_retention(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.system.update'), $this->systemPayload([
                'failed_jobs_auto_cleanup_enabled' => '1',
                'failed_jobs_retention_days' => '60',
            ]))
            ->assertRedirect(route('admin.settings.index', ['tab' => 'system']));

        app(SettingsService::class)->flush();
        $this->assertTrue(app(SettingsService::class)->boolean('system.failed_jobs_auto_cleanup_enabled'));
        $this->assertSame(60, app(SettingsService::class)->integer('system.failed_jobs_retention_days'));

        $this->actingAs($this->admin())
            ->withoutVite()
            ->get(route('admin.settings.index', ['tab' => 'system']))
            ->assertOk()
            ->assertSee('Failed Job Cleanup');
    }

    public function test_retention_outside_7_to_365_days_is_rejected(): void
    {
        foreach (['0', '6', '366'] as $days) {
            $this->actingAs($this->admin())
                ->put(route('admin.settings.system.update'), $this->systemPayload([
                    'failed_jobs_auto_cleanup_enabled' => '1',
                    'failed_jobs_retention_days' => $days,
                ]))
                ->assertSessionHasErrors('failed_jobs_retention_days');
        }

        app(SettingsService::class)->flush();
        $this->assertFalse(app(SettingsService::class)->boolean('system.failed_jobs_auto_cleanup_enabled'));
    }

    public function test_system_settings_save_without_cleanup_fields_keeps_stored_values(): void
    {
        $this->enable(90);

        $this->actingAs($this->admin())
            ->put(route('admin.settings.system.update'), $this->systemPayload())
            ->assertSessionHasNoErrors();

        app(SettingsService::class)->flush();
        $this->assertTrue(app(SettingsService::class)->boolean('system.failed_jobs_auto_cleanup_enabled'));
        $this->assertSame(90, app(SettingsService::class)->integer('system.failed_jobs_retention_days'));
    }

    // ----- Scheduler -----

    public function test_cleanup_is_scheduled_exactly_once_and_daily(): void
    {
        Artisan::call('schedule:list');
        $lines = collect(explode("\n", Artisan::output()))
            ->filter(fn (string $line) => str_contains($line, 'rppl:cleanup-failed-jobs'));

        $this->assertCount(1, $lines);
        $this->assertMatchesRegularExpression('/0\s+3\s+\*\s+\*\s+\*/', $lines->first());
    }
}
