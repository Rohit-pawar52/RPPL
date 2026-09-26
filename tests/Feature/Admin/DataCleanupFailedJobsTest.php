<?php

namespace Tests\Feature\Admin;

use App\Models\DataCleanupLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 3.49 — System tab. failed_jobs is a real, growing table (OCR
 * and notification-send jobs both genuinely fail sometimes); it is the
 * ONLY System cleanup implemented this phase — job_batches (never
 * populated) and log files (unsafe against the configured `single`
 * channel) were audited and rejected, see the Phase 3.49 report.
 */
class DataCleanupFailedJobsTest extends TestCase
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

    private function insertFailedJob(\DateTimeInterface $failedAt): int
    {
        return DB::table('failed_jobs')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Test exception',
            'failed_at' => $failedAt,
        ]);
    }

    public function test_deleting_failed_jobs_before_a_date_removes_only_older_ones(): void
    {
        $oldId = $this->insertFailedJob(now()->subDays(10));
        $recentId = $this->insertFailedJob(now()->subDay());

        $response = $this->actingAs($this->admin())->delete(route('admin.data-cleanup.failed-jobs.destroy'), [
            'before_date' => now()->subDays(5)->toDateString(),
        ]);

        $response->assertRedirect(route('admin.data-cleanup.index', ['tab' => 'system']));
        $this->assertDatabaseMissing('failed_jobs', ['id' => $oldId]);
        $this->assertDatabaseHas('failed_jobs', ['id' => $recentId]);
    }

    public function test_scorer_cannot_delete_failed_jobs(): void
    {
        $id = $this->insertFailedJob(now()->subDays(10));

        $this->actingAs($this->scorer())->delete(route('admin.data-cleanup.failed-jobs.destroy'), [
            'before_date' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertDatabaseHas('failed_jobs', ['id' => $id]);
    }

    public function test_future_date_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->delete(route('admin.data-cleanup.failed-jobs.destroy'), [
            'before_date' => now()->addDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors('before_date');
    }

    public function test_preview_matches_deletion(): void
    {
        $this->insertFailedJob(now()->subDays(10));
        $this->insertFailedJob(now()->subDays(8));
        $this->insertFailedJob(now()->subDay());

        $preview = $this->actingAs($this->admin())->getJson(route('admin.data-cleanup.preview.cutoff', [
            'category' => 'failed-jobs',
            'before_date' => now()->subDays(5)->toDateString(),
        ]));
        $preview->assertOk()->assertJson(['count' => 2]);

        $this->actingAs($this->admin())->delete(route('admin.data-cleanup.failed-jobs.destroy'), [
            'before_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    public function test_pending_jobs_table_is_never_touched(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => now()->subDays(10)->timestamp,
            'created_at' => now()->subDays(10)->timestamp,
        ]);

        $this->actingAs($this->admin())->delete(route('admin.data-cleanup.failed-jobs.destroy'), [
            'before_date' => now()->toDateString(),
        ]);

        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_audit_log_records_the_deletion(): void
    {
        $this->insertFailedJob(now()->subDays(10));

        $this->actingAs($this->admin())->delete(route('admin.data-cleanup.failed-jobs.destroy'), [
            'before_date' => now()->subDays(5)->toDateString(),
        ]);

        $log = DataCleanupLog::first();
        $this->assertSame('failed_jobs', $log->category);
        $this->assertSame('delete_failed_jobs_before', $log->action);
        $this->assertSame(1, $log->records_affected);
    }
}
