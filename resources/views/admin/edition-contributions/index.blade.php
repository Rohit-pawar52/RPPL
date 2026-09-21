@extends('layouts.admin')

@section('title', 'Contributions')

@section('content')
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <form method="GET" action="{{ route('admin.edition-contributions.index') }}" class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search contributor name&hellip;"
                class="w-full max-w-[220px] rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring"
            />

            <select name="edition_id" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring">
                <option value="">All editions</option>
                @foreach($editions as $edition)
                    <option value="{{ $edition->id }}" @selected(($filters['edition_id'] ?? '') == $edition->id)>
                        {{ $edition->name }}
                    </option>
                @endforeach
            </select>

            <select name="committee_member_id" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring">
                <option value="">All members</option>
                @foreach($members as $member)
                    <option value="{{ $member->id }}" @selected(($filters['committee_member_id'] ?? '') == $member->id)>
                        {{ $member->name }}
                    </option>
                @endforeach
            </select>

            <button type="submit" class="rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                Filter
            </button>

            @if(array_filter($filters))
                <a href="{{ route('admin.edition-contributions.index') }}" class="text-[13px] text-neutral-400 hover:text-neutral-600">
                    Clear filters
                </a>
            @endif
        </form>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('admin.edition-contributions.export', $filters) }}"
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-neutral-200 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50"
            >
                <x-icon name="document-chart" class="h-4 w-4" />
                Export
            </a>
            <a
                href="{{ route('admin.edition-contributions.create') }}"
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md theme-button px-3 py-1.5 text-[13px] font-medium"
            >
                + Record contribution
            </a>
        </div>
    </div>

    <div class="mb-4">
        <x-stat-card label="Total Contributions" :value="'₹'.number_format($totalContributions, 2)" icon="currency" />
    </div>

    <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Date</th>
                    <th class="px-4 py-2 font-medium">Edition</th>
                    <th class="px-4 py-2 font-medium">Contributor</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Source</th>
                    <th class="px-4 py-2 text-right font-medium">Amount</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($contributions as $contribution)
                    <tr class="hover:bg-neutral-50">
                        <td class="whitespace-nowrap px-4 py-2 text-neutral-600">
                            {{ $contribution->contributed_at->format('d M Y') }}
                        </td>
                        <td class="px-4 py-2 text-neutral-700">{{ $contribution->edition->name }}</td>
                        <td class="px-4 py-2 font-medium text-neutral-800">
                            @if($contribution->committeeMember)
                                <a href="{{ route('admin.committee-members.show', $contribution->committeeMember) }}" class="hover:underline">
                                    {{ $contribution->contributorName() }}
                                </a>
                            @else
                                <a href="{{ route('admin.contributors.show', $contribution->contributor) }}" class="hover:underline">
                                    {{ $contribution->contributorName() }}
                                </a>
                            @endif
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-600 md:table-cell">
                            {{ $contribution->sourceLabel() }}
                        </td>
                        <td class="px-4 py-2 text-right font-medium text-neutral-800">
                            &#8377;{{ number_format($contribution->amount, 2) }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.edition-contributions.show', $contribution) }}"
                                    title="View"
                                    aria-label="View contribution"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('admin.edition-contributions.receipt', $contribution) }}"
                                    target="_blank"
                                    title="Receipt"
                                    aria-label="View receipt"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 theme-hover-primary"
                                >
                                    <x-icon name="document-chart" class="h-4 w-4" />
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.edition-contributions.destroy', $contribution) }}"
                                    data-confirm-delete
                                    data-confirm-title="Delete this contribution?"
                                    data-confirm-text="This also removes its linked finance transaction. This cannot be undone."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Delete"
                                        aria-label="Delete contribution"
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
                        <td colspan="6" class="px-4 py-8 text-center text-neutral-400">
                            No contributions found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $contributions->links() }}
    </div>
@endsection
