<?php

namespace Tests\Feature\Admin\I18n;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shared admin shell (sidebar, top bar, command palette text, status badges, table filters, flash messages,
 * validation messages) and the shell-owned screens read in Hindi for a user whose language is Hindi, and stay in
 * English for everyone else.
 */
class ShellHindiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $locale): User
    {
        return User::factory()->create([
            'locale' => $locale,
            'role_id' => Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id,
        ]);
    }

    public function test_the_sidebar_top_bar_and_a_list_page_are_in_hindi(): void
    {
        $response = $this->actingAs($this->admin('hi'))->get(route('admin.users.index'));

        $response->assertOk()
            // sidebar and groups come from config/admin_navigation.php, translated at display time
            ->assertSee('डैशबोर्ड')
            ->assertSee('सिस्टम प्रबंधन')
            ->assertSee('एडमिन कंसोल')
            // top bar and the Ctrl+K box
            ->assertSee('किसी पेज या काम पर जाएँ', false)
            ->assertSee('लॉग आउट')
            // the page itself and the shared filter bar
            ->assertSee('यूज़र')
            ->assertSee('फ़िल्टर')
            ->assertSee('नया यूज़र')
            // the status badge shows the Hindi status
            ->assertSee('चालू')
            ->assertDontSee('Admin console');
    }

    public function test_the_same_pages_stay_in_english_for_an_english_user(): void
    {
        $this->actingAs($this->admin('en'))->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Admin console')
            ->assertSee('New user')
            ->assertDontSee('एडमिन कंसोल');
    }

    public function test_the_javascript_phrases_reach_the_page_in_hindi(): void
    {
        $this->actingAs($this->admin('hi'))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('"Yes, delete":'.json_encode('हाँ, हटाएँ'), false);
    }

    public function test_a_flash_message_and_a_validation_message_come_out_in_hindi(): void
    {
        $admin = $this->admin('hi');

        $this->actingAs($admin)
            ->from(route('admin.account.password.edit'))
            ->put(route('admin.account.password.update'), [
                'current_password' => 'password',
                'password' => 'abc',
                'password_confirmation' => 'xyz',
            ])
            ->assertSessionHasErrors(['password']);

        $message = session('errors')->first('password');
        $this->assertStringContainsString('पासवर्ड', $message);
        $this->assertDoesNotMatchRegularExpression('/[A-Za-z]{4,}/', $message);

        $this->actingAs($admin)->post(route('admin.logout'))
            ->assertRedirect();
        $this->assertSame('आप लॉग आउट हो गए हैं।', session('success'));
    }

    public function test_pagination_reads_in_hindi_with_the_numbers_between_the_words(): void
    {
        User::factory()->count(25)->create();

        $this->actingAs($this->admin('hi'))->get(route('admin.users.index', ['per_page' => 10]))
            ->assertOk()
            ->assertSee('दिखाए गए')
            ->assertSee('नतीजे');
    }
}
