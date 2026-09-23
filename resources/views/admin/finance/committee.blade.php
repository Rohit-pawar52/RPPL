@extends('layouts.admin')

@section('title', 'Finance — Committee')

@section('content')
    @include('admin.finance._tabs')

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <form method="GET" action="{{ route('admin.finance.committee') }}" class="flex items-center gap-2">
            <select name="edition_id" onchange="this.form.submit()" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring">
                @foreach($editions as $option)
                    <option value="{{ $option->id }}" @selected($edition && $edition->id === $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </form>

        @if($edition)
            <div class="flex items-center gap-2">
                @if($previousEdition)
                    <form method="POST" action="{{ route('admin.finance.committee.copy-previous') }}">
                        @csrf
                        <input type="hidden" name="edition_id" value="{{ $edition->id }}" />
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-neutral-200 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50"
                        >
                            Copy Previous Edition Committee
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    @if(! $edition)
        <div class="rounded-lg border border-neutral-200 bg-white p-6 text-center text-sm text-neutral-400">
            No editions exist yet.
        </div>
    @else
        @if($summary)
            <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <x-stat-card label="Members" :value="$summary['total_members']" icon="users" />
                <x-stat-card label="Paid in Full" :value="$summary['paid_in_full']" icon="users" />
                <x-stat-card label="Partially Paid" :value="$summary['partially_paid']" icon="users" />
                <x-stat-card label="Not Paid" :value="$summary['not_paid']" icon="users" />
            </div>
        @endif

        <div class="mb-4 rounded-lg border border-neutral-200 bg-white p-4">
            <form method="POST" action="{{ route('admin.finance.committee.store') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <input type="hidden" name="edition_id" value="{{ $edition->id }}" />

                <div class="min-w-[220px]">
                    <label class="mb-1 block text-xs font-medium text-neutral-700">Add committee member</label>
                    <select name="contributor_id" required class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring">
                        <option value="" disabled selected>Select a contributor</option>
                        @forelse($addableContributors as $contributor)
                            <option value="{{ $contributor->id }}">{{ $contributor->name }}</option>
                        @empty
                            <option value="" disabled>No addable contributors — every active contributor is already on this committee</option>
                        @endforelse
                    </select>
                </div>

                <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
                    Add
                </button>

                <a href="{{ route('admin.contributors.create') }}" class="text-xs theme-link hover:underline">
                    + New contributor
                </a>
            </form>
        </div>

        <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
            <table class="w-full min-w-[560px] text-left text-[13px]">
                <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">Contributor</th>
                        <th class="px-4 py-2 text-right font-medium">Target</th>
                        <th class="px-4 py-2 text-right font-medium">Paid</th>
                        <th class="px-4 py-2 text-right font-medium">Remaining</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($dues as $row)
                        <tr class="hover:bg-neutral-50">
                            <td class="px-4 py-2 font-medium text-neutral-800">
                                <a href="{{ route('admin.contributors.show', $row['contributor']) }}" class="hover:underline">{{ $row['contributor']->name }}</a>
                            </td>
                            <td class="px-4 py-2 text-right text-neutral-600">{{ money($row['target']) }}</td>
                            <td class="px-4 py-2 text-right text-neutral-600">{{ money($row['paid']) }}</td>
                            <td class="px-4 py-2 text-right text-neutral-600">{{ money($row['remaining']) }}</td>
                            <td class="px-4 py-2"><x-status-badge :status="$row['status']" /></td>
                            <td class="px-4 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <a
                                        href="{{ route('admin.edition-contributions.create', ['edition_id' => $edition->id, 'contributor_id' => $row['contributor']->id]) }}"
                                        title="Record contribution"
                                        aria-label="Record contribution for {{ $row['contributor']->name }}"
                                        class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 theme-hover-primary"
                                    >
                                        <x-icon name="currency" class="h-4 w-4" />
                                    </a>
                                    <form
                                            method="POST"
                                            action="{{ route('admin.finance.committee.destroy', $row['membership']) }}"
                                            data-confirm-delete
                                            data-confirm-title="Remove {{ $row['contributor']->name }} from this committee?"
                                            data-confirm-text="This only removes this edition's membership — the contributor and their contribution history are never touched. Blocked if they already have recorded contribution history for this edition."
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                title="Remove from committee"
                                                aria-label="Remove {{ $row['contributor']->name }} from committee"
                                                class="rounded p-1.5 text-neutral-500 hover:bg-red-50 hover:text-red-600"
                                            >
                                                <x-icon name="trash" class="h-4 w-4" />
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-neutral-400">
                                No committee members for this edition yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
@endsection
