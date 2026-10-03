<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fast one-by-one payment verification: mark paid / failed (with a reason)
 * and land on the next pending registration of the same edition.
 */
class PlayerRegistrationQuickVerifyTest extends TestCase
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

    private function registration(Edition $edition, string $status = 'pending', string $registeredAt = '2026-03-01 10:00:00', array $overrides = []): PlayerRegistration
    {
        return PlayerRegistration::factory()->create(array_merge([
            'edition_id' => $edition->id,
            'payment_status' => $status,
            'registered_at' => $registeredAt,
        ], $overrides))->assignRegistrationNumber();
    }

    // ----- Mark paid -----

    public function test_mark_paid_clears_the_reason_and_opens_the_next_pending_of_the_same_edition(): void
    {
        $edition = Edition::factory()->create();
        $otherEdition = Edition::factory()->create();
        $current = $this->registration($edition, 'failed', '2026-03-01 10:00:00', ['payment_failure_reason' => 'Wrong UTR', 'payment_reference' => 'UTR-KEEP']);
        $next = $this->registration($edition, 'pending', '2026-03-02 10:00:00');
        // Older than anything in $edition, but it belongs to another edition.
        $this->registration($otherEdition, 'pending', '2026-01-01 10:00:00');
        $numberBefore = $current->registration_number;

        $this->actingAs($this->admin())
            ->post(route('admin.player-registrations.mark-paid', $current))
            ->assertRedirect(route('admin.player-registrations.show', $next))
            ->assertSessionHas('success', "{$numberBefore} marked paid. Next pending registration:");

        $current->refresh();
        $this->assertSame('paid', $current->payment_status);
        $this->assertNull($current->payment_failure_reason);
        $this->assertSame($numberBefore, $current->registration_number);
        $this->assertSame('UTR-KEEP', $current->payment_reference);
    }

    public function test_marking_the_last_pending_registration_ends_on_the_pending_list_with_an_info_message(): void
    {
        $edition = Edition::factory()->create();
        $this->registration(Edition::factory()->create(), 'pending'); // another edition's pending must not keep the loop going
        $only = $this->registration($edition, 'pending');

        $this->actingAs($this->admin())
            ->post(route('admin.player-registrations.mark-paid', $only))
            ->assertRedirect(route('admin.player-registrations.index', ['edition_id' => $edition->id, 'payment_status' => 'pending']))
            ->assertSessionHas('info');
    }

    // ----- Mark failed -----

    public function test_mark_failed_requires_a_reason_and_stores_it(): void
    {
        $edition = Edition::factory()->create();
        $current = $this->registration($edition, 'pending', '2026-03-01 10:00:00');
        $next = $this->registration($edition, 'pending', '2026-03-02 10:00:00');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('admin.player-registrations.show', $current))
            ->post(route('admin.player-registrations.mark-failed', $current), ['reason' => '   '])
            ->assertSessionHasErrors('reason');
        $this->assertSame('pending', $current->fresh()->payment_status);

        $this->actingAs($admin)
            ->post(route('admin.player-registrations.mark-failed', $current), ['reason' => '  UTR not found in the bank statement  '])
            ->assertRedirect(route('admin.player-registrations.show', $next));

        $current->refresh();
        $this->assertSame('failed', $current->payment_status);
        $this->assertSame('UTR not found in the bank statement', $current->payment_failure_reason);
    }

    // ----- Next pending -----

    public function test_next_pending_skips_the_current_registration_and_wraps_around(): void
    {
        $edition = Edition::factory()->create();
        $first = $this->registration($edition, 'pending', '2026-03-01 10:00:00');
        $second = $this->registration($edition, 'pending', '2026-03-02 10:00:00');
        $undated = $this->registration($edition, 'pending', '2026-03-03 10:00:00');
        $undated->forceFill(['registered_at' => null])->save();
        $admin = $this->actingAs($this->admin());

        $admin->get(route('admin.player-registrations.next-pending', $first))
            ->assertRedirect(route('admin.player-registrations.show', $second));
        $admin->get(route('admin.player-registrations.next-pending', $second))
            ->assertRedirect(route('admin.player-registrations.show', $undated));
        // Last in the order: wraps to the first pending one, never itself.
        $admin->get(route('admin.player-registrations.next-pending', $undated))
            ->assertRedirect(route('admin.player-registrations.show', $first));
    }

    public function test_next_pending_with_only_the_current_one_pending_goes_back_to_the_list(): void
    {
        $edition = Edition::factory()->create();
        $only = $this->registration($edition, 'pending');

        $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.next-pending', $only))
            ->assertRedirect(route('admin.player-registrations.index', ['edition_id' => $edition->id, 'payment_status' => 'pending']))
            ->assertSessionHas('info');
    }

    public function test_review_pending_opens_the_oldest_pending_registration_of_the_edition(): void
    {
        $edition = Edition::factory()->create();
        $this->registration($edition, 'paid', '2026-02-01 10:00:00');
        $oldestPending = $this->registration($edition, 'pending', '2026-03-01 10:00:00');
        $this->registration($edition, 'pending', '2026-03-02 10:00:00');

        $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.review-pending', ['edition_id' => $edition->id]))
            ->assertRedirect(route('admin.player-registrations.show', $oldestPending));
    }

    // ----- Authorization -----

    public function test_only_an_admin_can_verify_payments(): void
    {
        $registration = $this->registration(Edition::factory()->create());
        $scorer = User::factory()->create(['role_id' => $this->scorerRole->id]);

        $this->post(route('admin.player-registrations.mark-paid', $registration))->assertRedirect(route('admin.login'));
        $this->post(route('admin.player-registrations.mark-failed', $registration), ['reason' => 'x'])->assertRedirect(route('admin.login'));
        $this->get(route('admin.player-registrations.next-pending', $registration))->assertRedirect(route('admin.login'));

        $this->actingAs($scorer)->post(route('admin.player-registrations.mark-paid', $registration))->assertForbidden();
        $this->actingAs($scorer)->post(route('admin.player-registrations.mark-failed', $registration), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.player-registrations.next-pending', $registration))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.player-registrations.review-pending'))->assertForbidden();

        $this->assertSame('pending', $registration->fresh()->payment_status);
    }

    // ----- Edit form -----

    public function test_edit_form_keeps_the_reason_only_while_the_status_is_failed(): void
    {
        $registration = $this->registration(Edition::factory()->create(), 'pending');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.player-registrations.update', $registration), ['payment_status' => 'failed', 'payment_failure_reason' => 'Screenshot unreadable'])
            ->assertRedirect(route('admin.player-registrations.index'));
        $this->assertSame('Screenshot unreadable', $registration->fresh()->payment_failure_reason);

        // Switching away from failed drops the reason even if the form still sends it.
        $this->actingAs($admin)
            ->put(route('admin.player-registrations.update', $registration), ['payment_status' => 'paid', 'payment_failure_reason' => 'Screenshot unreadable']);
        $this->assertNull($registration->fresh()->payment_failure_reason);
    }

    public function test_show_page_displays_the_failure_reason_only_for_a_failed_registration(): void
    {
        $edition = Edition::factory()->create();
        $failed = $this->registration($edition, 'failed', '2026-03-01 10:00:00', ['payment_failure_reason' => 'Amount does not match']);
        $paid = $this->registration($edition, 'paid', '2026-03-02 10:00:00', ['player_id' => Player::factory()]);
        $admin = $this->actingAs($this->admin());

        $admin->get(route('admin.player-registrations.show', $failed))->assertOk()->assertSee('Amount does not match');
        $admin->get(route('admin.player-registrations.show', $paid))->assertOk()->assertDontSee('Failure reason (shown to the player)');
    }
}
