@extends('layouts.admin')

@section('title', 'Player Registrations')

@section('subtitle', 'Registrations received for each edition, with payment status.')

@section('actions')
    @if(! empty($filters['edition_id']))
        <x-admin.button href="{{ route('admin.player-registrations.review-pending', ['edition_id' => $filters['edition_id']]) }}" variant="secondary">Review pending</x-admin.button>
    @endif
    <x-admin.button href="{{ route('admin.player-registrations.export', $filters) }}" variant="secondary" icon="document-chart">Export</x-admin.button>
    <x-admin.button href="{{ route('admin.player-registrations.import') }}" variant="secondary" icon="document-chart">Import Excel / CSV</x-admin.button>
    <x-admin.button href="{{ route('admin.player-registrations.create') }}" variant="primary">+ New registration</x-admin.button>
@endsection

@section('content')
    {{-- Left behind by a CSV import: rows that were skipped and values that were
         cleaned up. Only present on the page the import redirects to. --}}
    @php $importNotes = session('import_notes'); @endphp
    @if($importNotes && (($importNotes['info'] ?? []) || ($importNotes['skipped'] ?? []) || ($importNotes['adjustments'] ?? [])))
        <details open class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs">
            <summary class="cursor-pointer font-medium text-amber-900">Import notes &mdash; please review</summary>
            <div class="mt-3 rounded-md bg-white p-3">
                @include('admin.player-registrations._import_notes', ['notes' => $importNotes])
            </div>
        </details>
    @endif

    <div class="mb-4">
        <x-table-filters :action="route('admin.player-registrations.index')" :filters="$filters" :date-range="true" :per-page="$perPage">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search registration #, name, phone, village&hellip;"
                class="w-full max-w-[220px] rounded-md border border-slate-300 px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100"
            />

            <select name="edition_id" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100">
                <option value="">All editions</option>
                @foreach($editions as $edition)
                    <option value="{{ $edition->id }}" @selected(($filters['edition_id'] ?? '') == $edition->id)>
                        {{ $edition->name }}
                    </option>
                @endforeach
            </select>

            <select name="payment_status" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100">
                <option value="">All payment statuses</option>
                @foreach(\App\Models\PlayerRegistration::PAYMENT_STATUSES as $status)
                    <option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>
                        {{ ucfirst($status) }}
                    </option>
                @endforeach
            </select>
        </x-table-filters>

        <div class="flex items-center gap-2">
            <x-selected-report-action
                id="registrations-selected-export"
                :action="route('admin.player-registrations.export-selected')"
                label="Export Selected ({count})"
            />

        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white" data-row-selection="#registrations-selected-export-button">
        <table class="w-full min-w-[720px] text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="w-8 px-4 py-2">
                        <input type="checkbox" data-select-all aria-label="Select all registrations on this page" />
                    </th>
                    <th class="px-4 py-2 font-medium"><x-sortable-header column="registration_number" :sort="$sort" :direction="$direction">Registration #</x-sortable-header></th>
                    <th class="px-4 py-2 font-medium"><x-sortable-header column="player_name" :sort="$sort" :direction="$direction">Player</x-sortable-header></th>
                    <th class="px-4 py-2 font-medium">Edition</th>
                    <th class="px-4 py-2 font-medium">Payment Status</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell"><x-sortable-header column="registration_fee" :sort="$sort" :direction="$direction">Fee</x-sortable-header></th>
                    <th class="hidden px-4 py-2 font-medium lg:table-cell"><x-sortable-header column="registered_at" :sort="$sort" :direction="$direction">Registered</x-sortable-header></th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($registrations as $registration)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2">
                            <input
                                type="checkbox"
                                data-row-checkbox
                                form="registrations-selected-export"
                                name="selected_ids[]"
                                value="{{ $registration->id }}"
                                aria-label="Select registration {{ $registration->registration_number }}"
                            />
                        </td>
                        <td class="px-4 py-2 font-mono text-[12px] text-slate-600">
                            <a href="{{ route('admin.player-registrations.show', $registration) }}" class="hover:underline">
                                {{ $registration->registration_number }}
                            </a>
                        </td>
                        <td class="px-4 py-2 font-medium text-slate-800">
                            <a href="{{ route('admin.player-registrations.show', $registration) }}" class="hover:underline">
                                {{ $registration->player->name }}
                            </a>
                            @unless($registration->player->is_active)
                                <span class="ml-1 text-[10px] font-normal text-slate-400">(inactive)</span>
                            @endunless
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $registration->edition->name }}</td>
                        <td class="px-4 py-2"><x-status-badge :status="$registration->payment_status" /></td>
                        <td class="hidden px-4 py-2 text-slate-600 md:table-cell">
                            {{ $registration->registration_fee !== null ? money($registration->registration_fee) : '—' }}
                        </td>
                        <td class="hidden px-4 py-2 text-slate-500 lg:table-cell">
                            {{ display_datetime($registration->registered_at, 'd M Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.player-registrations.show', $registration) }}"
                                    title="View"
                                    aria-label="View registration"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('admin.player-registrations.edit', $registration) }}"
                                    title="Edit"
                                    aria-label="Edit registration"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-green-700"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.player-registrations.destroy', $registration) }}"
                                    data-confirm-delete
                                    data-confirm-title="Delete this registration?"
                                    data-confirm-text="This cannot be undone. Registrations already assigned to a squad cannot be deleted."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Delete"
                                        aria-label="Delete registration"
                                        class="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="8">No registrations found.</x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $registrations->links() }}
    </div>
@endsection
