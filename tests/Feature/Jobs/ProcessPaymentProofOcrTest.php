<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessPaymentProofOcr;
use App\Models\PlayerRegistration;
use App\Services\Registration\PaymentProofOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Phase B — PaymentProofOcrService is mocked throughout (via container
 * binding, matching the audited reference implementation's own test
 * pattern) so this suite never depends on a real Tesseract binary being
 * installed, consistent with phpunit.xml's QUEUE_CONNECTION=sync.
 */
class ProcessPaymentProofOcrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function registrationWithPaymentProof(array $overrides = []): PlayerRegistration
    {
        $path = UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')
            ->store('player-registrations/payment-proofs', 'local');

        return PlayerRegistration::factory()->create(array_merge([
            'payment_proof_path' => $path,
            'ocr_status' => PlayerRegistration::OCR_PENDING,
        ], $overrides))->assignRegistrationNumber();
    }

    private function fakeOcrResult(array $result): void
    {
        $mock = Mockery::mock(PaymentProofOcrService::class);
        $mock->shouldReceive('process')->once()->andReturn($result);
        $this->app->instance(PaymentProofOcrService::class, $mock);
    }

    public function test_extracted_result_is_persisted(): void
    {
        $this->fakeOcrResult(['status' => PlayerRegistration::OCR_EXTRACTED, 'transaction_id' => 'ABC123456']);
        $registration = $this->registrationWithPaymentProof();

        (new ProcessPaymentProofOcr($registration))->handle(app(PaymentProofOcrService::class));

        $registration->refresh();
        $this->assertSame(PlayerRegistration::OCR_EXTRACTED, $registration->ocr_status);
        $this->assertSame('ABC123456', $registration->ocr_transaction_id);
    }

    public function test_not_found_result_is_persisted_with_null_candidate(): void
    {
        $this->fakeOcrResult(['status' => PlayerRegistration::OCR_NOT_FOUND, 'transaction_id' => null]);
        $registration = $this->registrationWithPaymentProof();

        (new ProcessPaymentProofOcr($registration))->handle(app(PaymentProofOcrService::class));

        $registration->refresh();
        $this->assertSame(PlayerRegistration::OCR_NOT_FOUND, $registration->ocr_status);
        $this->assertNull($registration->ocr_transaction_id);
    }

    public function test_failed_result_is_persisted_without_throwing(): void
    {
        $this->fakeOcrResult(['status' => PlayerRegistration::OCR_FAILED, 'transaction_id' => null]);
        $registration = $this->registrationWithPaymentProof();

        (new ProcessPaymentProofOcr($registration))->handle(app(PaymentProofOcrService::class));

        $registration->refresh();
        $this->assertSame(PlayerRegistration::OCR_FAILED, $registration->ocr_status);
        $this->assertNull($registration->ocr_transaction_id);
    }

    /**
     * Reached only for a genuine infrastructure failure (handle() threw
     * on every retry) — PaymentProofOcrService never throws itself. Must
     * still leave the registration in a conclusive state.
     */
    public function test_failed_hook_marks_the_registration_ocr_status_as_failed(): void
    {
        $registration = $this->registrationWithPaymentProof();

        (new ProcessPaymentProofOcr($registration))->failed(new \RuntimeException('Simulated infrastructure failure.'));

        $registration->refresh();
        $this->assertSame(PlayerRegistration::OCR_FAILED, $registration->ocr_status);
        $this->assertNull($registration->ocr_transaction_id);
    }

    /**
     * Not reachable via the public guest flow (payment proof is
     * required there), but admin-created/CSV-imported registrations
     * legitimately have none — the job must still resolve to a
     * conclusive state rather than crash or leave it pending forever.
     */
    public function test_missing_payment_proof_resolves_to_failed_without_calling_the_ocr_service(): void
    {
        $registration = PlayerRegistration::factory()->create([
            'payment_proof_path' => null,
            'ocr_status' => PlayerRegistration::OCR_PENDING,
        ])->assignRegistrationNumber();

        $mock = Mockery::mock(PaymentProofOcrService::class);
        $mock->shouldNotReceive('process');
        $this->app->instance(PaymentProofOcrService::class, $mock);

        (new ProcessPaymentProofOcr($registration))->handle(app(PaymentProofOcrService::class));

        $registration->refresh();
        $this->assertSame(PlayerRegistration::OCR_FAILED, $registration->ocr_status);
        $this->assertNull($registration->ocr_transaction_id);
    }

    /**
     * The core Phase B safety invariant: OCR is advisory-only. Running
     * it (successfully or not) must never touch the admin-managed
     * payment_reference/payment_status fields, even when the admin has
     * already manually confirmed a reference for this registration.
     */
    public function test_manual_payment_reference_and_payment_status_are_never_touched(): void
    {
        $this->fakeOcrResult(['status' => PlayerRegistration::OCR_EXTRACTED, 'transaction_id' => 'XYZ999888']);
        $registration = $this->registrationWithPaymentProof([
            'payment_reference' => 'ADMIN-CONFIRMED-REF',
            'payment_status' => 'paid',
        ]);

        (new ProcessPaymentProofOcr($registration))->handle(app(PaymentProofOcrService::class));

        $registration->refresh();
        $this->assertSame('XYZ999888', $registration->ocr_transaction_id);
        $this->assertSame('ADMIN-CONFIRMED-REF', $registration->payment_reference);
        $this->assertSame('paid', $registration->payment_status);
    }
}
