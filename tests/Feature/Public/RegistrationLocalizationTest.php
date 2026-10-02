<?php

namespace Tests\Feature\Public;

use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bilingual localization — public player registration, its success
 * page, and the registration-status lookup. Proves the static UI and
 * the scoped Hindi validation messages follow the rppl_locale cookie,
 * while submitted/stored values (name, registration number) render
 * identically in both languages. The switching mechanism itself is
 * covered by PublicLocaleTest.
 */
class RegistrationLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private function openEdition(): Edition
    {
        return Edition::factory()->create([
            'name' => 'RPPL 2026',
            'status' => 'active',
            'registration_open' => true,
            'registration_fee' => 400.00,
        ]);
    }

    /**
     * A complete, valid submission (the fake files use ->create() — no GD here).
     *
     * @return array<string, mixed>
     */
    private function submission(): array
    {
        return [
            'name' => 'Ramesh Joshi',
            'age' => 24,
            'phone' => '9876543210',
            'primary_role' => 'batter',
            'batting_style' => 'right_hand',
            'bowling_style' => 'none',
            'village' => 'Sendriya',
            'tehsil' => 'Multai',
            'district' => 'Betul',
            'submitted_utr' => '402912345678',
            'photo' => UploadedFile::fake()->create('photo.jpg', 500, 'image/jpeg'),
            'payment_proof' => UploadedFile::fake()->create('proof.jpg', 300, 'image/jpeg'),
        ];
    }

    private function registrationFor(string $name, string $phone, string $status = 'pending'): PlayerRegistration
    {
        $player = Player::factory()->create(['name' => $name, 'phone' => $phone]);

        return PlayerRegistration::factory()
            ->create(['player_id' => $player->id, 'payment_status' => $status, 'registration_fee' => 400])
            ->assignRegistrationNumber();
    }

    private function lookup(?string $locale, string $registrationNumber, string $phone)
    {
        $request = $locale ? $this->withCookie('rppl_locale', $locale) : $this;

        return $request->post(route('public.player-registration.status.lookup'), [
            'registration_number' => $registrationNumber,
            'phone' => $phone,
        ]);
    }

    public function test_registration_form_renders_in_english_by_default_and_hindi_with_the_cookie(): void
    {
        $this->openEdition();

        $this->get(route('public.player-registration.create'))
            ->assertOk()
            ->assertSee('Full Name')
            ->assertSee('Batting hand')
            ->assertSee('Village (Gram)')
            ->assertSee('Payment Screenshot')
            ->assertSee('Submit Registration')
            ->assertSee('Wicket Keeper')
            ->assertDontSee('पूरा नाम');

        $this->withCookie('rppl_locale', 'hi')->get(route('public.player-registration.create'))
            ->assertOk()
            ->assertSee('पूरा नाम')
            ->assertSee('बल्लेबाज़ी का हाथ')
            ->assertSee('गाँव (ग्राम)')
            ->assertSee('भुगतान का स्क्रीनशॉट')
            ->assertSee('पंजीकरण जमा करें')
            ->assertSee('विकेटकीपर')
            ->assertSee('value="wicket_keeper"', false) // option VALUE never translated
            ->assertSee('value="right_hand"', false)
            ->assertSee('RPPL 2026')                   // edition name as stored
            ->assertDontSee('Submit Registration');
    }

    public function test_closed_registration_state_is_translated(): void
    {
        $this->withCookie('rppl_locale', 'hi')->get(route('public.player-registration.create'))
            ->assertOk()
            ->assertSee('खिलाड़ी पंजीकरण अभी बंद है।')
            ->assertDontSee('currently closed');
    }

    public function test_status_lookup_form_and_result_labels_translate_while_stored_values_render_identically(): void
    {
        $registration = $this->registrationFor('Ramesh Joshi', '9876543210', 'paid');

        // English first: withCookie() persists for the rest of the test.
        $english = $this->lookup(null, $registration->registration_number, '9876543210')->assertOk();

        $this->withCookie('rppl_locale', 'hi')->get(route('public.player-registration.status'))
            ->assertOk()
            ->assertSee('पंजीकरण की स्थिति देखें')
            ->assertSee('स्थिति देखें');

        $hindi = $this->lookup('hi', $registration->registration_number, '9876543210')->assertOk();

        $english->assertSee('Payment Status')->assertSee('Paid');
        $hindi->assertSee('भुगतान की स्थिति')->assertSee('भुगतान हो गया')->assertDontSee('Payment Status');

        foreach ([$english, $hindi] as $response) {
            $response->assertSee($registration->registration_number)
                ->assertSee('Ramesh Joshi')
                ->assertSee($registration->edition->name);
        }
    }

    public function test_status_lookup_not_found_message_is_translated(): void
    {
        $this->lookup('hi', 'RPPL-2026-999999', '9876543210')
            ->assertOk()
            ->assertSee('कोई पंजीकरण नहीं मिला');
    }

    public function test_validation_messages_follow_the_public_locale(): void
    {
        $this->openEdition();

        // English default — Laravel's stock line and the form's own
        // phone-format override.
        $this->post(route('public.player-registration.store'), ['phone' => '12345'])
            ->assertSessionHasErrors([
                'name' => 'The name field is required.',
                'phone' => 'Enter a valid 10-digit Indian mobile number.',
            ]);

        // Hindi — generic rule + Hindi attribute name, a field-specific
        // custom line, a message built by the form request for the new
        // fields, and the translated form-level override.
        $this->withCookie('rppl_locale', 'hi')
            ->post(route('public.player-registration.store'), ['phone' => '12345', 'age' => '3', 'submitted_utr' => 'x'])
            ->assertSessionHasErrors([
                'name' => 'नाम भरना ज़रूरी है।',
                'photo' => 'अपनी फ़ोटो अपलोड करना ज़रूरी है।',
                'batting_style' => 'बल्लेबाज़ी का हाथ चुनना ज़रूरी है।',
                'village' => 'गाँव भरना ज़रूरी है।',
                'age' => 'कृपया 5 से 99 के बीच की उम्र डालें।',
                'submitted_utr' => 'अपने पेमेंट ऐप में दिखने वाला UTR / ट्रांज़ैक्शन ID डालें — सिर्फ़ अक्षर और अंक, 8 से 30 वर्ण।',
                'phone' => 'कृपया सही 10 अंकों का भारतीय मोबाइल नंबर डालें।',
            ]);

        $this->withCookie('rppl_locale', 'hi')
            ->post(route('public.player-registration.status.lookup'), ['registration_number' => 'ABC', 'phone' => '9876543210'])
            ->assertSessionHasErrors([
                'registration_number' => 'पंजीकरण नंबर ठीक वैसे ही डालें जैसा दिया गया था, जैसे RPPL-2026-000125।',
            ]);
    }

    public function test_successful_hindi_submission_shows_translated_success_page_with_values_as_submitted(): void
    {
        Storage::fake('local');
        $this->openEdition();

        $this->withCookie('rppl_locale', 'hi')
            ->post(route('public.player-registration.store'), $this->submission())
            ->assertRedirect(route('public.player-registration.success'));

        $registration = PlayerRegistration::firstOrFail();

        $this->withCookie('rppl_locale', 'hi')->get(route('public.player-registration.success'))
            ->assertOk()
            ->assertSee('पंजीकरण सफल')
            ->assertSee($registration->registration_number)
            ->assertSee('Ramesh Joshi')
            ->assertSee('RPPL 2026')
            ->assertDontSee('Registration Successful');
    }

    public function test_duplicate_registration_rejection_is_translated(): void
    {
        Storage::fake('local');
        $edition = $this->openEdition();
        $player = Player::factory()->create(['phone' => '9876543210']);
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'player_id' => $player->id]);

        $this->withCookie('rppl_locale', 'hi')
            ->post(route('public.player-registration.store'), $this->submission())
            ->assertSessionHasErrors(['phone' => __('registration.errors.duplicate', [], 'hi')]);
    }
}
