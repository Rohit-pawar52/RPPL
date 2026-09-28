<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Rule;
use App\Models\RuleType;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin CRUD for Rules & Regulations entries. Proves authorization
 * (RulePolicy), the core validation (rule type must exist, status must be
 * one of Rule::STATUSES), that status/sort_order/is_important round-trip,
 * and that image store/replace/remove/delete through RuleService keeps
 * the public disk in sync with the rules table.
 */
class RuleManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $scorerRole;

    private RuleType $ruleType;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->scorerRole = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);
        $this->ruleType = RuleType::factory()->create(['name' => 'Cricket Rules']);

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

    private function fakeImage(string $name = 'rule.jpg'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'image/jpeg');
    }

    /**
     * A Rule row whose image_path points at a real file on the fake disk,
     * so replace/remove/delete cleanup can be asserted.
     */
    private function ruleWithImage(array $attributes = []): Rule
    {
        return Rule::factory()->for($this->ruleType)->create([
            'image_path' => $this->fakeImage('old.jpg')->store('rules', 'public'),
            ...$attributes,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            'rule_type_id' => $this->ruleType->id,
            'title' => 'Each innings is 8 overs',
            'content' => "Each side bats for 8 overs.\nA bowler may bowl at most 2 overs.",
            'sort_order' => 10,
            'status' => 'active',
            ...$overrides,
        ];
    }

    // ----- Authorization -----

    public function test_non_admin_cannot_access_any_rule_management_route(): void
    {
        $scorer = $this->scorer();
        $rule = $this->ruleWithImage();

        $this->actingAs($scorer)->get(route('admin.rules.index'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.rules.create'))->assertForbidden();
        $this->actingAs($scorer)->post(route('admin.rules.store'), $this->validPayload())->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.rules.edit', $rule))->assertForbidden();
        $this->actingAs($scorer)->put(route('admin.rules.update', $rule), $this->validPayload())->assertForbidden();
        $this->actingAs($scorer)->delete(route('admin.rules.destroy', $rule))->assertForbidden();

        $this->assertDatabaseCount('rules', 1);
        Storage::disk('public')->assertExists($rule->image_path);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.rules.index'))->assertRedirect(route('admin.login'));
    }

    // ----- Create / validation -----

    public function test_admin_can_create_a_rule(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.rules.create'))->assertOk()->assertSee('Cricket Rules');

        $this->actingAs($admin)
            ->post(route('admin.rules.store'), $this->validPayload([
                'sort_order' => 3,
                'status' => 'inactive',
                'is_important' => '1',
            ]))
            ->assertRedirect(route('admin.rules.index'))
            ->assertSessionHas('success');

        $rule = Rule::sole();
        $this->assertSame($this->ruleType->id, $rule->rule_type_id);
        $this->assertSame('Each innings is 8 overs', $rule->title);
        $this->assertSame(3, $rule->sort_order);
        $this->assertSame('inactive', $rule->status);
        $this->assertTrue($rule->is_important);
        $this->assertNull($rule->image_path);

        $this->actingAs($admin)
            ->get(route('admin.rules.index'))
            ->assertOk()
            ->assertSee('Each innings is 8 overs');
    }

    public function test_validation_rejects_a_missing_rule_type_or_an_invalid_status(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.rules.store'), $this->validPayload(['rule_type_id' => null]))
            ->assertSessionHasErrors('rule_type_id');

        $this->actingAs($admin)
            ->post(route('admin.rules.store'), $this->validPayload(['rule_type_id' => 999999]))
            ->assertSessionHasErrors('rule_type_id');

        $this->actingAs($admin)
            ->post(route('admin.rules.store'), $this->validPayload(['status' => 'archived']))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('rules', 0);
    }

    // ----- Edit: status / sort_order / is_important -----

    public function test_editing_persists_status_sort_order_and_can_untick_is_important(): void
    {
        $rule = Rule::factory()->for($this->ruleType)->important()->create([
            'status' => 'active',
            'sort_order' => 50,
        ]);

        // No image yet, so the remove-image checkbox isn't offered.
        $this->actingAs($this->admin())
            ->get(route('admin.rules.edit', $rule))
            ->assertOk()
            ->assertDontSee('Remove image');

        // is_important omitted entirely = checkbox unticked.
        $this->actingAs($this->admin())
            ->put(route('admin.rules.update', $rule), $this->validPayload([
                'status' => 'inactive',
                'sort_order' => 4,
            ]))
            ->assertRedirect(route('admin.rules.index'));

        $rule->refresh();
        $this->assertSame('inactive', $rule->status);
        $this->assertSame(4, $rule->sort_order);
        $this->assertFalse($rule->is_important);
    }

    public function test_index_filters_by_rule_type_status_and_title_search(): void
    {
        $otherType = RuleType::factory()->create();
        Rule::factory()->for($this->ruleType)->create(['title' => 'Wide ball rule']);
        Rule::factory()->for($this->ruleType)->inactive()->create(['title' => 'No ball rule']);
        Rule::factory()->for($otherType)->create(['title' => 'Dress code rule']);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.rules.index', ['rule_type_id' => $otherType->id]))
            ->assertSee('Dress code rule')
            ->assertDontSee('Wide ball rule');

        $this->actingAs($admin)
            ->get(route('admin.rules.index', ['status' => 'inactive']))
            ->assertSee('No ball rule')
            ->assertDontSee('Wide ball rule');

        $this->actingAs($admin)
            ->get(route('admin.rules.index', ['search' => 'wide']))
            ->assertSee('Wide ball rule')
            ->assertDontSee('Dress code rule');
    }

    // ----- Image handling -----

    public function test_uploading_an_image_on_create_stores_it(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.rules.store'), $this->validPayload(['image' => $this->fakeImage()]))
            ->assertRedirect(route('admin.rules.index'));

        $rule = Rule::sole();
        $this->assertNotNull($rule->image_path);
        $this->assertStringStartsWith('rules/', $rule->image_path);
        Storage::disk('public')->assertExists($rule->image_path);
    }

    public function test_editing_metadata_without_a_new_image_keeps_the_existing_image(): void
    {
        $rule = $this->ruleWithImage();
        $originalPath = $rule->image_path;

        $this->actingAs($this->admin())
            ->get(route('admin.rules.edit', $rule))
            ->assertOk()
            ->assertSee('Remove image');

        $this->actingAs($this->admin())
            ->put(route('admin.rules.update', $rule), $this->validPayload(['title' => 'Renamed rule']))
            ->assertRedirect(route('admin.rules.index'));

        $rule->refresh();
        $this->assertSame('Renamed rule', $rule->title);
        $this->assertSame($originalPath, $rule->image_path);
        Storage::disk('public')->assertExists($originalPath);
    }

    public function test_replacing_the_image_deletes_the_old_file_and_stores_the_new_one(): void
    {
        $rule = $this->ruleWithImage();
        $oldPath = $rule->image_path;

        $this->actingAs($this->admin())
            ->put(route('admin.rules.update', $rule), $this->validPayload(['image' => $this->fakeImage('new.png')]))
            ->assertRedirect(route('admin.rules.index'));

        $rule->refresh();
        $this->assertNotSame($oldPath, $rule->image_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($rule->image_path);
    }

    public function test_remove_image_flag_clears_the_image_without_a_replacement(): void
    {
        $rule = $this->ruleWithImage();
        $oldPath = $rule->image_path;

        $this->actingAs($this->admin())
            ->put(route('admin.rules.update', $rule), $this->validPayload(['remove_image' => '1']))
            ->assertRedirect(route('admin.rules.index'));

        $this->assertNull($rule->refresh()->image_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_deleting_a_rule_removes_the_row_and_its_image(): void
    {
        $rule = $this->ruleWithImage();
        $path = $rule->image_path;

        $this->actingAs($this->admin())
            ->delete(route('admin.rules.destroy', $rule))
            ->assertRedirect(route('admin.rules.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('rules', ['id' => $rule->id]);
        Storage::disk('public')->assertMissing($path);
    }
}
