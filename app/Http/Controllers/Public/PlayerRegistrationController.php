<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StatusLookupPlayerRegistrationRequest;
use App\Http\Requests\Public\StorePublicPlayerRegistrationRequest;
use App\Models\Edition;
use App\Services\Registration\GuestPlayerRegistrationService;
use App\Services\Registration\PlayerRegistrationStatusLookupService;
use App\Services\Settings\SettingsService;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Public/guest player registration submission (Phase 3.39C) and
 * read-only registration-status lookup (Phase 3.39E). No authentication,
 * no account created for the guest. Kept thin — identity resolution,
 * file storage, transactions, and registration_number generation all
 * live in GuestPlayerRegistrationService; status matching lives in
 * PlayerRegistrationStatusLookupService — never here.
 */
class PlayerRegistrationController extends Controller
{
    public function __construct(
        private readonly GuestPlayerRegistrationService $registrations,
        private readonly PlayerRegistrationStatusLookupService $statusLookup,
        private readonly SettingsService $settings,
    ) {}

    public function create(): View
    {
        $candidate = Edition::publicRegistrationEnabled()->first();
        $state = $candidate?->publicRegistrationState() ?? Edition::REGISTRATION_STATE_CLOSED;
        $edition = $state === Edition::REGISTRATION_STATE_OPEN ? $candidate : null;

        return view('public.player-registration.create', [
            'edition' => $edition,
            'upcomingEdition' => $state === Edition::REGISTRATION_STATE_NOT_YET_OPEN ? $candidate : null,
            'payment' => $edition ? $this->paymentDetails($edition) : null,
            'maxFileKb' => StorePublicPlayerRegistrationRequest::maxFileKilobytes(),
        ]);
    }

    /**
     * Where and how much to pay: the UPI ID / QR the admin configured in
     * Settings, plus — when there is a UPI ID — a "pay with a UPI app" link,
     * because a player filling this form on their phone cannot scan a QR
     * code shown on that same phone.
     *
     * @return array{upi_id: ?string, qr_url: ?string, pay_link: ?string}
     */
    private function paymentDetails(Edition $edition): array
    {
        $upiId = $this->settings->get('payment.upi_id');
        $qrPath = $this->settings->get('payment.upi_qr_path');

        return [
            'upi_id' => $upiId,
            'qr_url' => Media::existingUrl($qrPath),
            'pay_link' => $upiId ? 'upi://pay?'.http_build_query([
                'pa' => $upiId,
                'pn' => $this->settings->get('general.short_name'),
                'am' => number_format((float) $edition->registration_fee, 2, '.', ''),
                'cu' => 'INR',
            ], '', '&', PHP_QUERY_RFC3986) : null,
        ];
    }

    public function store(StorePublicPlayerRegistrationRequest $request): RedirectResponse
    {
        $edition = Edition::acceptingPublicRegistration()->first();

        if (! $edition) {
            return redirect()
                ->route('public.player-registration.create')
                ->with('error', __(GuestPlayerRegistrationService::CLOSED_MESSAGE_KEY));
        }

        $registration = $this->registrations->register(
            $edition,
            $request->validated(),
            $request->file('photo'),
            $request->file('payment_proof'),
        );

        // Kept in the session (not flashed), so refreshing the page or
        // coming back to it does not lose the registration number. Still
        // deliberately NOT a lookup-by-registration_number route/query
        // string (that's Phase 3.39E's separate, phone-guarded status
        // lookup).
        session()->put('registration_success', [
            'registration_number' => $registration->registration_number,
            'edition_name' => $edition->name,
            'player_name' => $registration->player->name,
            'registration_fee' => $registration->registration_fee,
            'registered_at' => $registration->registered_at?->toIso8601String(),
        ]);

        return redirect()->route('public.player-registration.success');
    }

    public function success(): View|RedirectResponse
    {
        $payload = session('registration_success');

        if (! $payload) {
            return redirect()->route('public.player-registration.create');
        }

        return view('public.player-registration.success', $payload);
    }

    /**
     * Deliberately available regardless of whether an edition currently
     * has registration_open — a past applicant's status is historical
     * data, not gated by the current registration window.
     */
    public function status(): View
    {
        return view('public.player-registration.status');
    }

    /**
     * Renders the result directly (no PRG) — the operation is pure
     * read, so a browser resubmitting the form on refresh is harmless,
     * and this avoids putting the phone number in a URL/query string or
     * building a token/lookup-by-registration_number endpoint. The
     * "not found" and "found" branches use the exact same view/response
     * shape; only the $result variable differs, so there is no way for
     * a caller to distinguish "wrong phone" from "no such registration"
     * by response shape/timing/headers.
     */
    public function statusLookup(StatusLookupPlayerRegistrationRequest $request): Response
    {
        $number = $request->validated('registration_number');

        $results = $this->statusLookup->lookup($number, $request->validated('phone'));

        return response()
            ->view('public.player-registration.status', [
                'results' => $results,
                'searched' => true,
            ])
            ->header('Cache-Control', 'no-store, private');
    }
}
