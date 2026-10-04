<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Advertisement\StoreAdvertisementRequest;
use App\Http\Requests\Admin\Advertisement\UpdateAdvertisementRequest;
use App\Models\Advertisement;
use App\Services\Advertisement\AdvertisementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\ValidatedInput;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin CRUD for sponsor ads (images / short videos shown on public pages).
 * Files go through AdvertisementService — this controller never touches
 * Storage directly. Active/Inactive is the status on the form and a
 * one-click toggle from the list.
 */
class AdvertisementController extends Controller
{
    public function __construct(private readonly AdvertisementService $ads) {}

    public function index(): View
    {
        $this->authorize('viewAny', Advertisement::class);

        // Main first, then Auction, Normal and Mini; newest first inside a tier.
        $advertisements = Advertisement::query()
            ->orderByRaw("case tier when 'main' then 1 when 'auction' then 2 when 'normal' then 3 else 4 end")
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.advertisements.index', ['advertisements' => $advertisements]);
    }

    public function create(): View
    {
        $this->authorize('create', Advertisement::class);

        return view('admin.advertisements.create');
    }

    public function store(StoreAdvertisementRequest $request): RedirectResponse
    {
        $this->authorize('create', Advertisement::class);

        $this->ads->createAdvertisement(
            $this->data($request->safe()),
            $request->file('media'),
            $request->file('poster'),
        );

        return redirect()
            ->route('admin.advertisements.index')
            ->with('success', 'Advertisement added.');
    }

    public function edit(Advertisement $advertisement): View
    {
        $this->authorize('update', $advertisement);

        return view('admin.advertisements.edit', ['advertisement' => $advertisement]);
    }

    public function update(UpdateAdvertisementRequest $request, Advertisement $advertisement): RedirectResponse
    {
        $this->authorize('update', $advertisement);

        $this->ads->updateAdvertisement(
            $advertisement,
            $this->data($request->safe()),
            $request->file('media'),
            $request->file('poster'),
        );

        return redirect()
            ->route('admin.advertisements.index')
            ->with('success', 'Advertisement updated.');
    }

    /**
     * Flips the current value server-side, so no client-supplied status is
     * ever trusted. Turning a Main or Auction sponsor on can be refused (one
     * slot each).
     */
    public function toggleStatus(Advertisement $advertisement): RedirectResponse
    {
        $this->authorize('update', $advertisement);

        try {
            $this->ads->toggleStatus($advertisement);
        } catch (ValidationException $e) {
            return redirect()
                ->back(fallback: route('admin.advertisements.index'))
                ->with('error', $e->errors()['status'][0]);
        }

        return redirect()
            ->back(fallback: route('admin.advertisements.index'))
            ->with('success', $advertisement->status === 'active' ? 'Advertisement activated.' : 'Advertisement deactivated.');
    }

    public function destroy(Advertisement $advertisement): RedirectResponse
    {
        $this->authorize('delete', $advertisement);

        $this->ads->deleteAdvertisement($advertisement);

        return redirect()
            ->route('admin.advertisements.index')
            ->with('success', 'Advertisement deleted.');
    }

    /**
     * The row's own columns: the uploaded files are handled separately and
     * blank dates become null.
     *
     * @return array<string, mixed>
     */
    private function data(ValidatedInput $input): array
    {
        $data = $input->except(['media', 'poster']);
        $data['starts_on'] = ($data['starts_on'] ?? null) ?: null;
        $data['ends_on'] = ($data['ends_on'] ?? null) ?: null;

        return $data;
    }
}
