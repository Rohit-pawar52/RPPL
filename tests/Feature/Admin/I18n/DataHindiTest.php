<?php

namespace Tests\Feature\Admin\I18n;

use App\Models\Contributor;
use App\Models\Edition;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The master-data, finance and data-cleanup screens in Hindi: labels come out in Devanagari, the English
 * pages are untouched, a flash message and a validation message (with the translated field name) are Hindi,
 * and the data-cleanup warning keeps its "cannot be undone" meaning.
 */
class DataHindiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $locale): User
    {
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id, 'locale' => $locale]);
    }

    public function test_the_main_lists_forms_and_profiles_render_in_hindi(): void
    {
        $admin = $this->admin('hi');
        $team = Team::factory()->create();
        Edition::factory()->create();

        $this->actingAs($admin)->get(route('admin.players.index'))
            ->assertOk()->assertSee('नया खिलाड़ी')->assertSee('कार्रवाई')->assertDontSee('+ New player');

        $this->actingAs($admin)->get(route('admin.players.create'))
            ->assertOk()->assertSee('जन्म तारीख')->assertSee('मुख्य भूमिका')->assertSee('बल्लेबाज़ी का हाथ');

        $this->actingAs($admin)->get(route('admin.teams.show', $team))
            ->assertOk()->assertSee('संस्करणों का इतिहास');

        $this->actingAs($admin)->get(route('admin.edition-transactions.index'))
            ->assertOk()->assertSee('खाता-बही')->assertSee('कुल खर्च');

        $this->actingAs($admin)->get(route('admin.finance.overview'))
            ->assertOk()->assertSee('सारांश');
    }

    public function test_english_stays_english(): void
    {
        $this->actingAs($this->admin('en'))->get(route('admin.players.index'))
            ->assertOk()->assertSee('+ New player')->assertDontSee('नया खिलाड़ी');
    }

    public function test_a_flash_message_and_a_validation_message_with_the_field_name_are_hindi(): void
    {
        $admin = $this->admin('hi');

        $this->actingAs($admin)->post(route('admin.teams.store'), ['name' => 'Hindi Warriors'])
            ->assertRedirect()
            ->assertSessionHas('success', 'टीम जोड़ दी गई।');

        $response = $this->actingAs($admin)->from(route('admin.players.create'))
            ->post(route('admin.players.store'), ['name' => '']);
        $response->assertSessionHasErrors('name');
        $this->assertStringContainsString('नाम', session('errors')->first('name'));
        $this->assertDoesNotMatchRegularExpression('/\bname\b/', session('errors')->first('name'));
    }

    public function test_the_data_cleanup_warnings_say_it_cannot_be_undone_in_hindi(): void
    {
        $this->actingAs($this->admin('hi'))->get(route('admin.data-cleanup.index', ['tab' => 'notifications']))
            ->assertOk()
            ->assertSee('इसे वापस नहीं किया जा सकता')
            ->assertSee('हमेशा के लिए')
            ->assertSee('हाँ, हटाएँ');
    }

    public function test_the_contribution_form_texts_and_the_duplicate_warning_are_hindi(): void
    {
        $admin = $this->admin('hi');
        Contributor::factory()->create(['name' => 'Ramesh Patil', 'village' => 'Shirur']);

        $this->actingAs($admin)->get(route('admin.edition-contributions.create'))
            ->assertOk()->assertSee('किसने दिया')->assertSee('योगदानकर्ता चुनें');

        $edition = Edition::factory()->create();
        $this->actingAs($admin)->from(route('admin.contributors.create'))
            ->post(route('admin.contributors.store'), ['name' => 'Ramesh Patil', 'village' => 'Shirur'])
            ->assertSessionHasErrors('confirm_duplicate');
        $this->assertStringContainsString('पहले से योगदानकर्ता सूची में है', session('errors')->first('confirm_duplicate'));
        $this->assertNotNull($edition);
    }
}
