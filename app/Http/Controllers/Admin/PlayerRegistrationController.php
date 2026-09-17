<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlayerRegistration\ImportPlayerRegistrationsRequest;
use App\Http\Requests\Admin\PlayerRegistration\StorePlayerRegistrationRequest;
use App\Http\Requests\Admin\PlayerRegistration\UpdatePlayerRegistrationRequest;
use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Services\PlayerRegistration\PlayerRegistrationService;
use App\Services\Registration\PlayerRegistrationImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlayerRegistrationController extends Controller
{
    public function __construct(
        private readonly PlayerRegistrationService $registrations,
        private readonly PlayerRegistrationImportService $imports,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PlayerRegistration::class);

        $filters = $request->only(['search', 'edition_id', 'payment_status']);

        $registrations = $this->registrationQuery($filters)
            ->with(['player', 'edition'])
            ->latest('registered_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.player-registrations.index', [
            'registrations' => $registrations,
            'filters' => $filters,
            'editions' => Edition::orderByDesc('year')->get(['id', 'name']),
        ]);
    }

    /**
     * Streamed UTF-8 CSV of the same filtered set index() shows — no
     * spreadsheet package exists in this project, and one row per
     * registration at RPPL's scale doesn't justify adding one. Honors
     * exactly the same query string as the index (search/edition_id/
     * payment_status), so there is only ever one place filter rules are
     * defined. chunkById() (rather than loading everything at once)
     * keeps this safe if an edition's registration count ever grows.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', PlayerRegistration::class);

        $filters = $request->only(['search', 'edition_id', 'payment_status']);

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');

            // Excel-friendly UTF-8 BOM so accented player names render
            // correctly when the file is opened directly in Excel.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Registration ID', 'Edition', 'Player Name', 'Phone', 'Email',
                'Payment Status', 'Registration Fee', 'Registered At',
            ]);

            $this->registrationQuery($filters)
                ->with(['player', 'edition'])
                ->chunkById(200, function ($registrations) use ($handle) {
                    foreach ($registrations as $registration) {
                        fputcsv($handle, [
                            $registration->registration_number,
                            $registration->edition->name,
                            $registration->player->name,
                            $registration->player->phone ?? '',
                            $registration->player->email ?? '',
                            ucfirst($registration->payment_status),
                            $registration->registration_fee !== null
                                ? number_format($registration->registration_fee, 2, '.', '')
                                : '',
                            $registration->registered_at?->format('Y-m-d') ?? '',
                        ]);
                    }
                });

            fclose($handle);
        }, $this->exportFilename($filters), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(): View
    {
        $this->authorize('create', PlayerRegistration::class);

        return view('admin.player-registrations.import', [
            'editions' => Edition::openForParticipation()->orderByDesc('year')->get(),
        ]);
    }

    /**
     * Two-pass, create-only import (see PlayerRegistrationImportService)
     * — never overwrites an existing Player or PlayerRegistration, and
     * never touches the finance ledger. Any fatal row problem rejects
     * the whole file with zero writes.
     */
    public function importStore(ImportPlayerRegistrationsRequest $request): RedirectResponse
    {
        $this->authorize('create', PlayerRegistration::class);

        $edition = Edition::findOrFail($request->validated('edition_id'));

        $result = $this->imports->import($edition, $request->file('csv_file'));

        if (! $result['success']) {
            return redirect()
                ->route('admin.player-registrations.import')
                ->withErrors(['csv_file' => $result['errors']])
                ->withInput();
        }

        return redirect()
            ->route('admin.player-registrations.index', ['edition_id' => $edition->id])
            ->with('success', sprintf(
                'Import completed: %d registration%s created, %d skipped, %d new player%s created.',
                $result['created_registrations'],
                $result['created_registrations'] === 1 ? '' : 's',
                $result['skipped'],
                $result['created_players'],
                $result['created_players'] === 1 ? '' : 's',
            ));
    }

    public function create(): View
    {
        $this->authorize('create', PlayerRegistration::class);

        return view('admin.player-registrations.create', [
            'editions' => Edition::openForParticipation()->orderByDesc('year')->get(),
            'players' => Player::active()->orderBy('name')->get(),
            'paymentStatuses' => PlayerRegistration::PAYMENT_STATUSES,
        ]);
    }

    public function store(StorePlayerRegistrationRequest $request): RedirectResponse
    {
        $this->authorize('create', PlayerRegistration::class);

        $this->registrations->createRegistration($request->validated());

        return redirect()
            ->route('admin.player-registrations.index')
            ->with('success', 'Registration created successfully.');
    }

    public function show(PlayerRegistration $playerRegistration): View
    {
        $this->authorize('view', $playerRegistration);

        $playerRegistration->load(['player', 'edition', 'teamPlayer.editionTeam.team']);

        return view('admin.player-registrations.show', [
            'registration' => $playerRegistration,
        ]);
    }

    public function edit(PlayerRegistration $playerRegistration): View
    {
        $this->authorize('update', $playerRegistration);

        $playerRegistration->load(['player', 'edition']);

        return view('admin.player-registrations.edit', [
            'registration' => $playerRegistration,
            'paymentStatuses' => PlayerRegistration::PAYMENT_STATUSES,
        ]);
    }

    /**
     * Served inline through the app after policy authorization — never
     * Storage::url()/a public disk/a signed URL. The path is always read
     * from this route-bound registration's own column, never from the
     * request, so the endpoint cannot be pointed at an arbitrary file.
     */
    public function aadhaar(PlayerRegistration $playerRegistration): StreamedResponse
    {
        $this->authorize('view', $playerRegistration);

        return $this->privateDocumentResponse(
            $playerRegistration->aadhaar_document_path,
            $playerRegistration,
            'aadhaar'
        );
    }

    public function paymentProof(PlayerRegistration $playerRegistration): StreamedResponse
    {
        $this->authorize('view', $playerRegistration);

        return $this->privateDocumentResponse(
            $playerRegistration->payment_proof_path,
            $playerRegistration,
            'payment-proof'
        );
    }

    public function update(UpdatePlayerRegistrationRequest $request, PlayerRegistration $playerRegistration): RedirectResponse
    {
        $this->authorize('update', $playerRegistration);

        $this->registrations->updateRegistration($playerRegistration, $request->validated());

        return redirect()
            ->route('admin.player-registrations.index')
            ->with('success', 'Registration updated successfully.');
    }

    public function destroy(PlayerRegistration $playerRegistration): RedirectResponse
    {
        $this->authorize('delete', $playerRegistration);

        if (! $this->registrations->deleteRegistration($playerRegistration)) {
            return redirect()
                ->route('admin.player-registrations.index')
                ->with('error', 'This registration cannot be deleted because the player has already been assigned to a squad.');
        }

        return redirect()
            ->route('admin.player-registrations.index')
            ->with('success', 'Registration deleted successfully.');
    }

    /**
     * The single source of truth for registration filtering, shared by
     * index() and export() so the two can never quietly diverge.
     * $filters values are whitelisted exactly as before: payment_status
     * must be one of the real enum values, never an arbitrary column/
     * value from the request.
     *
     * @param  array<string, mixed>  $filters
     */
    private function registrationQuery(array $filters): Builder
    {
        return PlayerRegistration::query()
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where(function ($query) use ($search) {
                    $query->where('registration_number', 'like', '%'.$search.'%')
                        ->orWhereHas('player', function ($query) use ($search) {
                            $query->where('name', 'like', '%'.$search.'%')
                                ->orWhere('phone', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                })
            )
            ->when(
                $filters['edition_id'] ?? null,
                fn ($query, $editionId) => $query->where('edition_id', $editionId)
            )
            ->when(
                in_array($filters['payment_status'] ?? null, PlayerRegistration::PAYMENT_STATUSES, true),
                fn ($query) => $query->where('payment_status', $filters['payment_status'])
            );
    }

    /**
     * Returns a clean 404 (no filesystem path, no stack trace) both when
     * the registration never had this document and when the DB path
     * exists but the physical file is gone — the caller can't tell the
     * two apart, which is the point. Served inline (not force-downloaded):
     * both Aadhaar and payment-proof documents exist for the admin to
     * visually review in the browser. The download name is entirely
     * server-generated from the registration_number, never the
     * originally-uploaded filename.
     */
    private function privateDocumentResponse(?string $path, PlayerRegistration $playerRegistration, string $label): StreamedResponse
    {
        if ($path === null || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $downloadName = Str::slug($playerRegistration->registration_number).'-'.$label
            .($extension !== '' ? '.'.$extension : '');

        return Storage::disk('local')->response($path, $downloadName);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function exportFilename(array $filters): string
    {
        $editionId = $filters['edition_id'] ?? null;
        $edition = $editionId ? Edition::find($editionId) : null;

        return $edition
            ? "rppl-registrations-{$edition->year}.csv"
            : 'rppl-player-registrations.csv';
    }
}
