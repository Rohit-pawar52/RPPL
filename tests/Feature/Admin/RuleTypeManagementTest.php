<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Rule;
use App\Models\RuleType;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin CRUD for Rule Types. Proves authorization (RuleTypePolicy),
 * slug uniqueness, and — the key business rule — that a type which
 * still has Rules is never deleted (friendly error, row kept) while an
 * empty type can be.
 */
class RuleTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $scorerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->scorerRole = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);

        app(SettingsService::class)->flush();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function scorer(): User
    {
        return User::factory()->create(['role_id' => $this->scorerRole->id]);
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Cricket Rules',
            'slug' => 'cricket-rules',
            'description' => 'Standard on-field playing rules.',
            'sort_order' => 10,
            'is_active' => '1',
            ...$overrides,
        ];
    }

    // ----- Authorization -----

    public function test_non_admin_cannot_access_any_rule_type_management_route(): void
    {
        $scorer = $this->scorer();
        $ruleType = RuleType::factory()->create();

        $this->actingAs($scorer)->get(route('admin.rule-types.index'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.rule-types.create'))->assertForbidden();
        $this->actingAs($scorer)->post(route('admin.rule-types.store'), $this->validPayload())->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.rule-types.edit', $ruleType))->assertForbidden();
        $this->actingAs($scorer)->put(route('admin.rule-types.update', $ruleType), $this->validPayload())->assertForbidden();
        $this->actingAs($scorer)->delete(route('admin.rule-types.destroy', $ruleType))->assertForbidden();

        $this->assertDatabaseCount('rule_types', 1);
        $this->assertDatabaseMissing('rule_types', ['slug' => 'cricket-rules']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.rule-types.index'))->assertRedirect(route('admin.login'));
    }

    // ----- Create / edit -----

    public function test_admin_can_create_a_rule_type(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.rule-types.create'))->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.rule-types.store'), $this->validPayload(['sort_order' => 7]))
            ->assertRedirect(route('admin.rule-types.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('rule_types', [
            'name' => 'Cricket Rules',
            'slug' => 'cricket-rules',
            'sort_order' => 7,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.rule-types.index'))
            ->assertOk()
            ->assertSee('Cricket Rules');
    }

    public function test_admin_can_edit_a_rule_type_including_deactivating_it(): void
    {
        $admin = $this->admin();
        $ruleType = RuleType::factory()->create(['slug' => 'old-slug', 'sort_order' => 100]);

        $this->actingAs($admin)->get(route('admin.rule-types.edit', $ruleType))->assertOk();

        $this->actingAs($admin)
            ->put(route('admin.rule-types.update', $ruleType), $this->validPayload([
                'name' => 'RPPL Specific Rules',
                'slug' => 'rppl-specific-rules',
                'sort_order' => 2,
                'is_active' => '0',
            ]))
            ->assertRedirect(route('admin.rule-types.index'));

        $ruleType->refresh();
        $this->assertSame('RPPL Specific Rules', $ruleType->name);
        $this->assertSame('rppl-specific-rules', $ruleType->slug);
        $this->assertSame(2, $ruleType->sort_order);
        $this->assertFalse($ruleType->is_active);
    }

    public function test_slug_must_be_unique_and_well_formed_but_a_type_may_keep_its_own_slug(): void
    {
        $admin = $this->admin();
        RuleType::factory()->create(['slug' => 'cricket-rules']);
        $other = RuleType::factory()->create(['slug' => 'code-of-conduct']);

        $this->actingAs($admin)
            ->post(route('admin.rule-types.store'), $this->validPayload(['slug' => 'cricket-rules']))
            ->assertSessionHasErrors('slug');

        $this->actingAs($admin)
            ->post(route('admin.rule-types.store'), $this->validPayload(['slug' => 'Cricket Rules']))
            ->assertSessionHasErrors('slug');

        $this->actingAs($admin)
            ->put(route('admin.rule-types.update', $other), $this->validPayload(['slug' => 'cricket-rules']))
            ->assertSessionHasErrors('slug');

        // Re-saving a type with its own unchanged slug is not a conflict.
        $this->actingAs($admin)
            ->put(route('admin.rule-types.update', $other), $this->validPayload(['slug' => 'code-of-conduct']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('rule_types', 2);
    }

    public function test_index_lists_types_in_sort_order(): void
    {
        RuleType::factory()->create(['name' => 'Third Type', 'sort_order' => 30]);
        RuleType::factory()->create(['name' => 'First Type', 'sort_order' => 1]);
        RuleType::factory()->create(['name' => 'Second Type', 'sort_order' => 20]);

        $this->actingAs($this->admin())
            ->get(route('admin.rule-types.index'))
            ->assertOk()
            ->assertSeeInOrder(['First Type', 'Second Type', 'Third Type']);
    }

    // ----- Guarded delete -----

    public function test_a_rule_type_that_still_has_rules_cannot_be_deleted(): void
    {
        $ruleType = RuleType::factory()->create();
        Rule::factory()->for($ruleType)->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.rule-types.destroy', $ruleType))
            ->assertRedirect(route('admin.rule-types.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('rule_types', ['id' => $ruleType->id]);
        $this->assertDatabaseCount('rules', 1);
    }

    public function test_a_rule_type_with_no_rules_can_be_deleted(): void
    {
        $ruleType = RuleType::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.rule-types.destroy', $ruleType))
            ->assertRedirect(route('admin.rule-types.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('rule_types', ['id' => $ruleType->id]);
    }
}
