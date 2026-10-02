<?php

namespace Tests\Feature\Public;

use App\Http\Requests\Public\StorePublicPlayerRegistrationRequest;
use App\Models\Edition;
use App\Models\PlayerRegistration;
use App\Services\Settings\SettingsService;
use App\Support\UploadLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What the public registration form asks (the questions of the Season 3
 * Google Form, minus its weaknesses) and how each answer is validated.
 * What happens to a valid submission — players, identity, files — is in
 * PlayerRegistrationTest.
 */
class PlayerRegistrationFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->flush();
        Queue::fake();
        Storage::fake('local');

        Edition::factory()->create([
            'name' => 'RPPL 2026',
            'status' => 'active',
            'registration_open' => true,
            'registration_fee' => 400.00,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function answers(array $overrides = []): array
    {
        return array_merge([
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
            // ->create() rather than ->image(): no GD extension needed.
            'photo' => UploadedFile::fake()->create('photo.jpg', 500, 'image/jpeg'),
            'payment_proof' => UploadedFile::fake()->create('proof.jpg', 300, 'image/jpeg'),
        ], $overrides);
    }

    private function submit(array $overrides = [])
    {
        return $this->post(route('public.player-registration.store'), $this->answers($overrides));
    }

    // ----- What the form shows -----

    public function test_the_form_asks_the_google_form_questions_and_no_longer_asks_for_aadhaar_or_date_of_birth(): void
    {
        $size = UploadLimits::megabytes(StorePublicPlayerRegistrationRequest::maxFileKilobytes());

        $response = $this->get(route('public.player-registration.create'))->assertOk();

        foreach (['name', 'age', 'phone', 'email', 'village', 'tehsil', 'district', 'submitted_utr'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        // Radio groups and the two uploads.
        foreach (['primary_role', 'batting_style', 'bowling_style'] as $group) {
            $response->assertSee('type="radio"', false)->assertSee('name="'.$group.'"', false);
        }
        $response->assertSee('name="photo"', false)->assertSee('name="payment_proof"', false);
        $response->assertSee("up to {$size} MB");

        $response->assertDontSee('aadhaar', false)->assertDontSee('date_of_birth', false);
    }

    public function test_the_form_shows_the_upi_qr_id_and_pay_link_the_admin_configured(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('payments/qr.png', 'fake-qr');
        app(SettingsService::class)->setMany([
            'payment.upi_id' => 'rppl@ybl',
            'payment.upi_qr_path' => 'payments/qr.png',
        ]);

        $this->get(route('public.player-registration.create'))
            ->assertOk()
            ->assertSee('Scan this QR code')
            ->assertSee('payments/qr.png')
            ->assertSee('rppl@ybl')
            // The amount comes from the edition, the payee from Settings.
            ->assertSee('upi://pay?pa=rppl%40ybl&amp;pn=RPPL&amp;am=400.00&amp;cu=INR', false)
            ->assertSee('Pay the registration fee using the QR code or UPI ID below');
    }

    public function test_without_upi_settings_only_the_general_payment_instructions_are_shown(): void
    {
        $this->get(route('public.player-registration.create'))
            ->assertOk()
            ->assertSee('Pay the registration fee by UPI/bank transfer')
            ->assertDontSee('upi://pay')
            ->assertDontSee('Scan this QR code');
    }

    // ----- What is required and what is accepted -----

    /**
     * @return array<string, array{string}>
     */
    public static function requiredAnswers(): array
    {
        return array_combine(
            $fields = ['name', 'age', 'phone', 'primary_role', 'batting_style', 'bowling_style', 'village', 'tehsil', 'district', 'submitted_utr', 'photo', 'payment_proof'],
            array_map(fn (string $field) => [$field], $fields),
        );
    }

    #[DataProvider('requiredAnswers')]
    public function test_every_answer_except_the_email_is_required(string $field): void
    {
        $payload = $this->answers();
        unset($payload[$field]);

        $this->post(route('public.player-registration.store'), $payload)->assertSessionHasErrors($field);

        $this->assertSame(0, PlayerRegistration::count());
        $this->assertEmpty(Storage::disk('local')->allFiles(), 'nothing is stored for a rejected form');
    }

    public function test_the_email_is_optional_but_must_look_like_one(): void
    {
        $this->submit(['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->submit(['email' => 'player@example.com'])->assertRedirect(route('public.player-registration.success'));
    }

    /**
     * @return array<string, array{string|int}>
     */
    public static function unrealisticAges(): array
    {
        return ['too young' => [4], 'too old' => [100], 'words' => ['abc'], 'a fraction' => ['22.5'], 'a birth year' => [2004]];
    }

    #[DataProvider('unrealisticAges')]
    public function test_the_age_must_be_a_whole_number_in_range(string|int $age): void
    {
        $this->submit(['age' => $age])->assertSessionHasErrors('age');

        $this->assertSame(0, PlayerRegistration::count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badUtrs(): array
    {
        return [
            'too short' => ['1234567'],
            'too long' => [str_repeat('A', 31)],
            'punctuation' => ['ABCD-1234-5678'],
            'a word' => ['paid'],
        ];
    }

    #[DataProvider('badUtrs')]
    public function test_the_utr_must_look_like_a_transaction_id(string $utr): void
    {
        $this->submit(['submitted_utr' => $utr])->assertSessionHasErrors('submitted_utr');

        $this->assertSame(0, PlayerRegistration::count());
    }

    public function test_uploads_must_be_images_of_the_right_kind(): void
    {
        // A photo may be JPEG, PNG or WebP — nothing else.
        $this->submit(['photo' => UploadedFile::fake()->create('photo.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('photo');

        // A payment screenshot is JPEG or PNG (it is read by the OCR).
        $this->submit(['payment_proof' => UploadedFile::fake()->create('proof.webp', 100, 'image/webp')])
            ->assertSessionHasErrors('payment_proof');

        $this->assertSame(0, PlayerRegistration::count());

        $this->submit(['photo' => UploadedFile::fake()->create('photo.webp', 100, 'image/webp')])
            ->assertRedirect(route('public.player-registration.success'));
    }

    public function test_a_file_over_the_limit_is_rejected_with_the_size_in_the_message_and_one_at_the_limit_is_accepted(): void
    {
        $limit = StorePublicPlayerRegistrationRequest::maxFileKilobytes();
        $size = UploadLimits::megabytes($limit);

        $this->submit(['photo' => UploadedFile::fake()->create('big.jpg', $limit + 1, 'image/jpeg')])
            ->assertSessionHasErrors(['photo' => __('registration.validation.file_max', ['size' => $size])]);

        $this->submit(['payment_proof' => UploadedFile::fake()->create('big.jpg', $limit + 1, 'image/jpeg')])
            ->assertSessionHasErrors(['payment_proof' => __('registration.validation.file_max', ['size' => $size])]);

        $this->assertSame(0, PlayerRegistration::count());

        $this->submit(['photo' => UploadedFile::fake()->create('exact.jpg', $limit, 'image/jpeg')])
            ->assertRedirect(route('public.player-registration.success'));
    }
}
