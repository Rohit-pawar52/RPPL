@extends('layouts.admin')

@section('title', __('Player Registrations'))

@section('subtitle', __('Check each payment, mark it paid or failed, and move on to the next one.'))

@section('actions')
    <x-admin.button :href="route('admin.player-registrations.export', $filters)" variant="secondary" icon="document-chart">{{ __('Export') }}</x-admin.button>
    <x-admin.button href="{{ route('admin.player-registrations.import') }}" variant="secondary" icon="document-chart">{{ __('Import') }}</x-admin.button>
    <x-admin.button href="{{ route('admin.player-registrations.create') }}" variant="primary">{{ __('+ New registration') }}</x-admin.button>
@endsection

@section('content')
    @php
        // Counts for the status chips: the whole edition (or all editions), not just this page.
        $statusCounts = \App\Models\PlayerRegistration::query()
            ->when($filters['edition_id'] ?? null, fn ($query, $editionId) => $query->where('edition_id', $editionId))
            ->selectRaw('payment_status, count(*) as total')
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status');
        $pendingCount = (int) ($statusCounts['pending'] ?? 0);
        $reviewEditionId = $filters['edition_id'] ?? \App\Models\PlayerRegistration::query()
            ->where('payment_status', 'pending')->orderBy('registered_at')->value('edition_id');
        $activeStatus = $filters['payment_status'] ?? '';
        $statusTabs = ['' => __('All')] + collect(\App\Models\PlayerRegistration::PAYMENT_STATUSES)->mapWithKeys(fn ($s) => [$s => ucfirst(__($s))])->all();
        $failReasons = [__('UTR not found in the bank statement'), __('Payment screenshot is unclear'), __('Amount does not match the fee'), __('This UTR was already used')];
    @endphp

    {{-- Left behind by a CSV import: rows that were skipped and values that were
         cleaned up. Only present on the page the import redirects to. --}}
    @php $importNotes = session('import_notes'); @endphp
    @if($importNotes && (($importNotes['info'] ?? []) || ($importNotes['skipped'] ?? []) || ($importNotes['adjustments'] ?? [])))
        <details open class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs">
            <summary class="cursor-pointer font-medium text-amber-900">{!! str_replace('—', '&mdash;', e(__('Import notes — please review'))) !!}</summary>
            <div class="mt-3 rounded-lg bg-white p-3">
                @include('admin.player-registrations._import_notes', ['notes' => $importNotes])
            </div>
        </details>
    @endif

    @if($errors->has('reason'))
        <div class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-[13px] text-red-700" role="alert">
            <x-ops.icon name="warning" class="mt-0.5 h-4 w-4" />
            <p>{{ $errors->first('reason') }} {!! __('Open :link again and give a short reason — the player sees it.', ['link' => '<strong>'.e(__('Mark failed')).'</strong>']) !!}</p>
        </div>
    @endif

    <div class="space-y-4">
        {{-- The work queue: how many are waiting, one tap to start reviewing, and the
             payment status as the filter. --}}
        <section class="ops-card ops-card-body flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <span @class([
                    'flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-2xl font-bold tabular-nums',
                    'bg-amber-50 text-amber-700' => $pendingCount > 0,
                    'bg-green-50 text-green-700' => $pendingCount === 0,
                ])>{{ $pendingCount }}</span>
                <div>
                    <p class="ops-kicker">{{ __('Waiting for a decision') }}</p>
                    <p class="text-sm text-slate-700">
                        @if($pendingCount > 0)
                            @if(! empty($filters['edition_id']))
                                {{ $pendingCount === 1 ? __(':count payment to check in this edition.', ['count' => $pendingCount]) : __(':count payments to check in this edition.', ['count' => $pendingCount]) }}
                            @else
                                {{ $pendingCount === 1 ? __(':count payment to check.', ['count' => $pendingCount]) : __(':count payments to check.', ['count' => $pendingCount]) }}
                            @endif
                        @else
                            {{ __('Nothing is waiting — every payment has been checked.') }}
                        @endif
                    </p>
                </div>
            </div>
            @if($pendingCount > 0 && $reviewEditionId)
                <a href="{{ route('admin.player-registrations.review-pending', ['edition_id' => $reviewEditionId]) }}" class="btn btn-primary btn-lg w-full lg:w-auto">
                    {{ __('Review pending, one by one') }}
                    <x-ops.icon name="arrow-right" />
                </a>
            @endif
        </section>

        <nav class="ops-chips" aria-label="{{ __('Payment status') }}">
            @foreach($statusTabs as $value => $label)
                @php $count = $value === '' ? $statusCounts->sum() : (int) ($statusCounts[$value] ?? 0); @endphp
                <a
                    href="{{ request()->fullUrlWithQuery(['payment_status' => $value === '' ? null : $value, 'page' => null]) }}"
                    @class(['ops-chip', 'ops-chip-active' => $activeStatus === $value])
                    @if($activeStatus === $value) aria-current="page" @endif
                >
                    {{ $label }}
                    <span class="ops-chip-count">{{ $count }}</span>
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('admin.player-registrations.index') }}" class="space-y-2">
            @if($activeStatus !== '')
                <input type="hidden" name="payment_status" value="{{ $activeStatus }}" />
            @endif
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative min-w-0 flex-1 basis-56 sm:max-w-md">
                    <x-ops.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        enterkeyhint="search"
                        placeholder="{{ __('Search name, phone, reg. no. or UTR') }}"
                        aria-label="{{ __('Search registrations') }}"
                        class="ops-input pl-9"
                    />
                </div>
                <select name="edition_id" onchange="this.form.submit()" aria-label="{{ __('Edition') }}" class="ops-input w-auto max-w-44 sm:max-w-none">
                    <option value="">{{ __('All editions') }}</option>
                    @foreach($editions as $edition)
                        <option value="{{ $edition->id }}" @selected(($filters['edition_id'] ?? '') == $edition->id)>
                            {{ $edition->name }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-secondary min-h-10">{{ __('Search') }}</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.player-registrations.index') }}" class="ops-link text-[13px]">{{ __('Clear all') }}</a>
                @endif
            </div>
            <details class="group text-xs" @if(! empty($filters['from_date']) || ! empty($filters['to_date'])) open @endif>
                <summary class="inline-flex min-h-9 cursor-pointer list-none items-center gap-1 font-medium text-slate-500 hover:text-slate-800">
                    <x-ops.icon name="chevron-right" class="h-3.5 w-3.5 transition group-open:rotate-90" />
                    {{ __('Dates and rows per page') }}
                </summary>
                <div class="mt-2 flex flex-wrap items-end gap-3">
                    <div>
                        <label class="ops-label" for="rq-from">{{ __('Registered from') }}</label>
                        <input id="rq-from" type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" class="ops-input w-40" />
                    </div>
                    <div>
                        <label class="ops-label" for="rq-to">{{-- "to " (trailing space) is a separate key from the shared "to" (Hindi "से"): a date range needs "तक" --}}{{ __('to ') }}</label>
                        <input id="rq-to" type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" class="ops-input w-40" />
                    </div>
                    <div>
                        <label class="ops-label" for="rq-per">{{ __('Rows') }}</label>
                        <select id="rq-per" name="per_page" onchange="this.form.submit()" class="ops-input w-auto">
                            @foreach([10, 20, 50, 100, 200] as $option)
                                <option value="{{ $option }}" @selected((int) $perPage === $option)>{{ __(':count / page', ['count' => $option]) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary min-h-10">{{ __('Apply') }}</button>
                </div>
            </details>
        </form>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                <span class="ops-kicker">{{ __('Sort') }}</span>
                <x-sortable-header column="registered_at" :sort="$sort" :direction="$direction">{{ __('Newest') }}</x-sortable-header>
                <x-sortable-header column="player_name" :sort="$sort" :direction="$direction">{{ __('Name') }}</x-sortable-header>
                <x-sortable-header column="registration_number" :sort="$sort" :direction="$direction">{{ __('Reg. no.') }}</x-sortable-header>
                <x-sortable-header column="registration_fee" :sort="$sort" :direction="$direction">{{ __('Fee') }}</x-sortable-header>
            </p>
            <x-selected-report-action
                id="registrations-selected-export"
                :action="route('admin.player-registrations.export-selected')"
                :label="__('Export Selected ({count})')"
            />
        </div>

        <div class="ops-card" data-row-selection="#registrations-selected-export-button" data-rq-list>
            <div class="flex items-center gap-3 border-b border-line px-3 py-2 sm:px-4">
                <label class="flex min-h-9 cursor-pointer items-center gap-2 text-xs font-medium text-slate-500">
                    <input type="checkbox" data-select-all class="h-4 w-4 rounded border-slate-300" aria-label="{{ __('Select all registrations on this page') }}" />
                    {{ __('Select all on this page') }}
                </label>
                <span class="ml-auto text-xs tabular-nums text-slate-400">{{ $registrations->total() == 1 ? __(':count registration', ['count' => 1]) : __(':count registrations', ['count' => $registrations->total()]) }}</span>
            </div>

            <div class="divide-y divide-line">
                @forelse($registrations as $registration)
                    @php
                        $player = $registration->player;
                        $status = $registration->payment_status;
                        $photoUrl = $registration->photo_path
                            ? route('admin.player-registrations.photo', $registration)
                            : ($player->photo_path ? media_url($player->photo_path, 'user') : null);
                        $proofUrl = $registration->payment_proof_path
                            ? route('admin.player-registrations.payment-proof', $registration) : null;
                        $utr = $registration->submitted_utr ?: $registration->payment_reference;
                        $canVerify = auth()->user()->can('update', $registration);
                    @endphp
                    <div class="rq-row" data-rq-row>
                        <div class="relative z-10 self-start pt-1 xl:self-center xl:pt-0">
                            <input
                                type="checkbox"
                                data-row-checkbox
                                form="registrations-selected-export"
                                name="selected_ids[]"
                                value="{{ $registration->id }}"
                                class="h-4 w-4 rounded border-slate-300"
                                aria-label="{{ __('Select registration :number', ['number' => $registration->registration_number]) }}"
                            />
                        </div>

                        {{-- Who --}}
                        <div class="flex min-w-0 items-center gap-3">
                            @if($photoUrl)
                                <a href="{{ $photoUrl }}" data-lightbox data-caption="{{ $player->name }}" class="ops-thumb relative z-10 h-11 w-11 rounded-full" aria-label="{{ __('Enlarge the photo of :name', ['name' => $player->name]) }}">
                                    <x-media-image :url="$photoUrl" kind="user" alt="" loading="lazy" />
                                </a>
                            @else
                                <span class="ops-initials h-11 w-11" aria-hidden="true">{{ \Illuminate\Support\Str::of($player->name)->substr(0, 1) }}</span>
                            @endif
                            <div class="min-w-0">
                                <a href="{{ route('admin.player-registrations.show', $registration) }}" class="block truncate text-sm font-semibold text-slate-900 after:absolute after:inset-0 after:content-[''] hover:underline">{{ $player->name }}</a>
                                <p class="truncate font-mono text-[11px] text-slate-500">{{ $registration->registration_number }}</p>
                                @if($player->phone)
                                    <p class="truncate text-xs text-slate-500">{{ $player->phone }}@unless($player->is_active) &middot; <span class="text-slate-400">{{ __('inactive') }}</span>@endunless</p>
                                @endif
                            </div>
                        </div>

                        {{-- Proof of payment --}}
                        <div class="flex min-w-0 items-center gap-3">
                            @if($proofUrl)
                                <a href="{{ $proofUrl }}" data-lightbox data-caption="{{ __('Payment screenshot') }} &middot; {{ $player->name }}" class="ops-thumb relative z-10 h-11 w-11" aria-label="{{ __('Enlarge the payment screenshot of :name', ['name' => $player->name]) }}">
                                    <x-media-image :url="$proofUrl" kind="image" alt="" loading="lazy" />
                                </a>
                            @elseif($registration->payment_proof_url)
                                <a href="{{ $registration->payment_proof_url }}" target="_blank" rel="noopener noreferrer" class="ops-thumb relative z-10 flex h-11 w-11 items-center justify-center text-slate-400 hover:text-brand" title="{{ __('Screenshot on Google Drive') }}" aria-label="{{ __('Open the payment screenshot on Google Drive') }}">
                                    <x-ops.icon name="image" class="h-5 w-5" />
                                </a>
                            @else
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-dashed border-slate-300 text-slate-300" title="{{ __('No screenshot') }}" aria-label="{{ __('No screenshot') }}">
                                    <x-ops.icon name="image" class="h-5 w-5" />
                                </span>
                            @endif
                            <div class="min-w-0 text-xs">
                                <p class="ops-kicker">UTR</p>
                                <p class="truncate font-mono text-slate-700" title="{{ $utr }}">{{ $utr ?: '—' }}</p>
                            </div>
                        </div>

                        {{-- Status, fee, when --}}
                        <div class="min-w-0 space-y-1 text-xs text-slate-500">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <x-status-badge :status="$status" />
                                @if(empty($filters['edition_id']))
                                    <span class="truncate font-medium text-slate-600">{{ $registration->edition->name }}</span>
                                @endif
                            </div>
                            {{-- Fee, then the date on the same line when it fits and on the next when it does not. --}}
                            <p class="tabular-nums">
                                {{ $registration->registration_fee !== null ? money($registration->registration_fee) : '—' }}
                                <span class="whitespace-nowrap">&middot; {{ display_datetime($registration->registered_at, 'd M Y, h:i A') ?? '—' }}</span>
                            </p>
                            @if($status === 'failed' && $registration->payment_failure_reason)
                                <p class="line-clamp-2 leading-4 text-red-600">{{ $registration->payment_failure_reason }}</p>
                            @endif
                        </div>

                        {{-- One-tap decisions --}}
                        <div class="relative z-10 flex items-center gap-2 xl:justify-end">
                            @if($canVerify)
                                @if($status !== 'paid' && $status !== 'refunded')
                                    <form method="POST" action="{{ route('admin.player-registrations.mark-paid', $registration) }}" class="max-xl:flex-1">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm min-h-10 w-full xl:min-h-9">
                                            <x-ops.icon name="check" /> {{ __('Mark paid') }}
                                        </button>
                                    </form>
                                @endif
                                @if($status !== 'failed' && $status !== 'refunded')
                                    <button type="button" data-fail-toggle class="btn btn-danger-soft btn-sm min-h-10 max-xl:flex-1 xl:min-h-9" aria-expanded="false">
                                        <x-ops.icon name="x" /> {{ __('Mark failed') }}
                                    </button>
                                @endif
                            @endif
                            <details class="relative" data-more>
                                <summary class="btn btn-ghost btn-icon btn-sm min-h-10 w-10 cursor-pointer list-none xl:min-h-9 xl:w-9" aria-label="{{ __('More actions for :name', ['name' => $player->name]) }}">
                                    <x-ops.icon name="dots" class="h-5 w-5" />
                                </summary>
                                <div class="ops-menu">
                                    <a href="{{ route('admin.player-registrations.show', $registration) }}" class="ops-menu-item"><x-icon name="eye" class="h-4 w-4" /> {{ __('Open') }}</a>
                                    <a href="{{ route('admin.player-registrations.edit', $registration) }}" class="ops-menu-item"><x-icon name="pencil" class="h-4 w-4" /> {{ __('Edit') }}</a>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.player-registrations.destroy', $registration) }}"
                                        data-confirm-delete
                                        data-confirm-title="{{ __('Delete this registration?') }}"
                                        data-confirm-text="{{ __('This cannot be undone. Registrations already assigned to a squad cannot be deleted.') }}"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ops-menu-item w-full text-red-600 hover:!bg-red-50"><x-icon name="trash" class="h-4 w-4" /> {{ __('Delete') }}</button>
                                    </form>
                                </div>
                            </details>
                        </div>

                        {{-- Mark failed: a reason is required, so one tap on a usual reason sends it. --}}
                        @if($canVerify)
                            <form
                                method="POST"
                                action="{{ route('admin.player-registrations.mark-failed', $registration) }}"
                                data-fail-panel
                                hidden
                                class="rq-wide relative z-10 rounded-xl border border-red-200 bg-red-50/60 p-3"
                            >
                                @csrf
                                <p class="text-xs font-semibold text-red-800">{{ __('Why did it fail? The player sees this reason.') }}</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach($failReasons as $reason)
                                        <button type="button" data-fail-reason="{{ $reason }}" class="ops-chip min-h-10 border-red-200 text-red-700 hover:bg-red-50 sm:min-h-9">{{ $reason }}</button>
                                    @endforeach
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <input type="text" name="reason" maxlength="255" required placeholder="{{ __('Or type your own reason') }}" aria-label="{{ __('Reason the payment failed') }}" class="ops-input min-w-48 flex-1" />
                                    <button type="submit" class="btn btn-danger min-h-10 sm:min-h-9">{{ __('Save as failed') }}</button>
                                </div>
                            </form>
                        @endif
                    </div>
                @empty
                    <x-admin.empty icon="clipboard" class="py-14">
                        {{ __('No registrations match these filters.') }}
                        <x-slot:action>
                            <a href="{{ route('admin.player-registrations.index') }}" class="btn btn-secondary btn-sm">{{ __('Clear filters') }}</a>
                        </x-slot:action>
                    </x-admin.empty>
                @endforelse
            </div>
        </div>

        <div>
            {{ $registrations->links() }}
        </div>
    </div>

    @include('admin.player-registrations._lightbox')

    <script>
        (function () {
            var list = document.querySelector('[data-rq-list]');
            if (!list) { return; }
            document.addEventListener('click', function (event) {
                document.querySelectorAll('[data-more][open]').forEach(function (menu) {
                    if (!menu.contains(event.target)) { menu.removeAttribute('open'); }
                });
            });
            list.addEventListener('click', function (event) {
                var toggle = event.target.closest('[data-fail-toggle]');
                if (toggle) {
                    var panel = toggle.closest('[data-rq-row]').querySelector('[data-fail-panel]');
                    panel.hidden = !panel.hidden;
                    toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
                    if (!panel.hidden) { panel.querySelector('input[name="reason"]').focus(); }
                    return;
                }
                var quick = event.target.closest('[data-fail-reason]');
                if (quick) {
                    var form = quick.closest('form');
                    form.querySelector('input[name="reason"]').value = quick.dataset.failReason;
                    form.requestSubmit();
                }
            });
        })();
    </script>
@endsection
