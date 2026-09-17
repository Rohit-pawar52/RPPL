<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StatusLookupPlayerRegistrationRequest;
use App\Http\Requests\Public\StorePublicPlayerRegistrationRequest;
use App\Models\Edition;
use App\Services\Registration\GuestPlayerRegistrationService;
use App\Services\Registration\PlayerRegistrationStatusLookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
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
    ) {}

    public function create(): View
    {
        return view('public.player-registration.create', [
            'edition' => Edition::acceptingPublicRegistration()->first(),
        ]);
    }

    public function store(StorePublicPlayerRegistrationRequest $request): RedirectResponse
    {
        $edition = Edition::acceptingPublicRegistration()->first();

        if (! $edition) {
            return redirect()
                ->route('public.player-registration.create')
                ->with('error', GuestPlayerRegistrationService::CLOSED_MESSAGE);
        }

        $registration = $this->registrations->register(
            $edition,
            $request->validated(),
            $request->file('aadhaar_document'),
            $request->file('payment_proof'),
        );

        // Session-flashed, one-time success payload — deliberately NOT a
        // lookup-by-registration_number route/query string (that's
        // Phase 3.39E's separate, phone-guarded status lookup).
        session()->flash('registration_success', [
            'registration_number' => $registration->registration_number,
            'edition_name' => $edition->name,
            'player_name' => $registration->player->name,
            'registration_fee' => $registration->registration_fee,
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
        $result = $this->statusLookup->lookup(
            $request->validated('registration_number'),
            $request->validated('phone'),
        );

        return response()
            ->view('public.player-registration.status', ['result' => $result, 'searched' => true])
            ->header('Cache-Control', 'no-store, private');
    }
}
