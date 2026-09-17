@extends('layouts.admin')

@section('title', 'Player Registrations')

@section('content')
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <form method="GET" action="{{ route('admin.player-registrations.index') }}" class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search registration #, name, phone&hellip;"
                class="w-full max-w-[220px] rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100"
            />

            <select name="edition_id" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100">
                <option value="">All editions</option>
                @foreach($editions as $edition)
                    <option value="{{ $edition->id }}" @selected(($filters['edition_id'] ?? '') == $edition->id)>
                        {{ $edition->name }}
                    </option>
                @endforeach
            </select>

            <select name="payment_status" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100">
                <option value="">All payment statuses</option>
                @foreach(\App\Models\PlayerRegistration::PAYMENT_STATUSES as $status)
                    <option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>
                        {{ ucfirst($status) }}
                    </option>
                @endforeach
            </select>

            <button type="submit" class="rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                Filter
            </button>

            @if(array_filter($filters))
                <a href="{{ route('admin.player-registrations.index') }}" class="text-[13px] text-neutral-400 hover:text-neutral-600">
                    Clear filters
                </a>
            @endif
        </form>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('admin.player-registrations.export', $filters) }}"
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-neutral-200 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50"
            >
                <x-icon name="document-chart" class="h-4 w-4" />
                Export
            </a>
            <a
                href="{{ route('admin.player-registrations.import') }}"
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-neutral-200 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50"
            >
                <x-icon name="document-chart" class="h-4 w-4" />
                Import CSV
            </a>
            <a
                href="{{ route('admin.player-registrations.create') }}"
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md bg-blue-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-blue-500"
            >
                + New registration
            </a>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Registration #</th>
                    <th class="px-4 py-2 font-medium">Player</th>
                    <th class="px-4 py-2 font-medium">Edition</th>
                    <th class="px-4 py-2 font-medium">Payment Status</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Fee</th>
                    <th class="hidden px-4 py-2 font-medium lg:table-cell">Registered</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($registrations as $registration)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-2 font-mono text-[12px] text-neutral-600">
                            <a href="{{ route('admin.player-registrations.show', $registration) }}" class="hover:underline">
                                {{ $registration->registration_number }}
                            </a>
                        </td>
                        <td class="px-4 py-2 font-medium text-neutral-800">
                            <a href="{{ route('admin.player-registrations.show', $registration) }}" class="hover:underline">
                                {{ $registration->player->name }}
                            </a>
                            @unless($registration->player->is_active)
                                <span class="ml-1 text-[10px] font-normal text-neutral-400">(inactive)</span>
                            @endunless
                        </td>
                        <td class="px-4 py-2 text-neutral-600">{{ $registration->edition->name }}</td>
                        <td class="px-4 py-2"><x-status-badge :status="$registration->payment_status" /></td>
                        <td class="hidden px-4 py-2 text-neutral-600 md:table-cell">
                            {{ $registration->registration_fee !== null ? '₹'.number_format($registration->registration_fee, 2) : '—' }}
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-500 lg:table-cell">
                            {{ $registration->registered_at?->format('d M Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.player-registrations.show', $registration) }}"
                                    title="View"
                                    aria-label="View registration"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('admin.player-registrations.edit', $registration) }}"
                                    title="Edit"
                                    aria-label="Edit registration"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-blue-600"
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
                                        class="rounded p-1.5 text-neutral-500 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-neutral-400">
                            No registrations found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $registrations->links() }}
    </div>
@endsection
