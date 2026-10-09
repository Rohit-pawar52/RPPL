@extends('layouts.admin')

@section('title', 'Finance — Committee')

@section('subtitle', $edition ? 'Committee members of '.$edition->name.' and what each has paid towards their target.' : 'Committee members and their dues.')

@section('actions')
    @if($edition && $previousEdition)
        <form method="POST" action="{{ route('admin.finance.committee.copy-previous') }}">
            @csrf
            <input type="hidden" name="edition_id" value="{{ $edition->id }}" />
            <button type="submit" class="btn btn-secondary btn-sm">Copy Previous Edition Committee</button>
        </form>
    @endif
@endsection

@section('content')
    @include('admin.finance._tabs')

    <form method="GET" action="{{ route('admin.finance.committee') }}" class="mb-4 flex items-center gap-2">
        <label for="committee-edition" class="crud-chip-label">Edition</label>
        <select id="committee-edition" name="edition_id" onchange="this.form.submit()" class="crud-field crud-select">
            @foreach($editions as $option)
                <option value="{{ $option->id }}" @selected($edition && $edition->id === $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    </form>

    @if(! $edition)
        <div class="crud-card">
            <x-admin.empty icon="users" class="py-12!">No editions exist yet.</x-admin.empty>
        </div>
    @else
        @if($summary)
            <div class="crud-kpis">
                <x-crud.kpi label="Members" :value="$summary['total_members']" icon="users" tone="brand" />
                <x-crud.kpi label="Paid in Full" :value="$summary['paid_in_full']" icon="users" tone="in" />
                <x-crud.kpi label="Partially Paid" :value="$summary['partially_paid']" icon="users" />
                <x-crud.kpi label="Not Paid" :value="$summary['not_paid']" icon="users" tone="out" />
            </div>
        @endif

        <div class="crud-card mb-4 p-3 sm:p-4">
            <form method="POST" action="{{ route('admin.finance.committee.store') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <input type="hidden" name="edition_id" value="{{ $edition->id }}" />

                <div class="min-w-[14rem] flex-1">
                    <label for="committee-contributor" class="mb-1 block text-xs font-medium text-slate-700">Add committee member</label>
                    <select id="committee-contributor" name="contributor_id" required class="crud-field w-full">
                        <option value="" disabled selected>Select a contributor</option>
                        @forelse($addableContributors as $contributor)
                            <option value="{{ $contributor->id }}">{{ $contributor->label() }}</option>
                        @empty
                            <option value="" disabled>No addable contributors — every active contributor is already on this committee</option>
                        @endforelse
                    </select>
                </div>

                <x-admin.button class="min-h-10">Add</x-admin.button>

                @can('create', \App\Models\Contributor::class)
                    <a href="{{ route('admin.contributors.create') }}" class="crud-link inline-flex min-h-10 items-center text-[13px]">+ New contributor</a>
                @endcan
            </form>
        </div>

        <div class="crud-table-wrap">
            <div class="crud-table-scroll">
                <table class="crud-table crud-stack">
                    <thead>
                        <tr>
                            <th>Contributor</th>
                            <th class="text-right">Target</th>
                            <th class="text-right">Paid</th>
                            <th class="text-right">Remaining</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dues as $row)
                            <tr class="crud-row">
                                <td class="c-title">
                                    @can('view', $row['contributor'])
                                        <a href="{{ route('admin.contributors.show', $row['contributor']) }}" class="crud-row-link">{{ $row['contributor']->name }}</a>
                                    @else
                                        {{ $row['contributor']->name }}
                                    @endcan
                                    @if(filled($row['contributor']->village))
                                        <span class="crud-meta">{{ $row['contributor']->village }}</span>
                                    @endif
                                    <span class="crud-meta md:hidden">Paid {{ money($row['paid']) }} of {{ money($row['target']) }}</span>
                                </td>
                                <td class="hidden text-right tabular-nums md:table-cell">{{ money($row['target']) }}</td>
                                <td class="hidden text-right font-medium tabular-nums text-green-600 md:table-cell">{{ money($row['paid']) }}</td>
                                <td class="c-amount c-num whitespace-nowrap">
                                    <span class="text-[11px] uppercase tracking-wide text-slate-400 md:hidden">Left</span>
                                    <span class="font-semibold tabular-nums {{ $row['remaining'] > 0 ? 'text-slate-900' : 'text-green-600' }}">{{ money($row['remaining']) }}</span>
                                </td>
                                <td class="c-sub"><x-status-badge :status="$row['status']" /></td>
                                <td class="c-actions">
                                    <div class="crud-actions">
                                        @can('create', \App\Models\EditionContribution::class)
                                            <a
                                                href="{{ route('admin.edition-contributions.create', ['edition_id' => $edition->id, 'contributor_id' => $row['contributor']->id]) }}"
                                                title="Record contribution"
                                                aria-label="Record contribution for {{ $row['contributor']->name }}"
                                                class="crud-icon-btn crud-icon-btn-brand"
                                            >
                                                <x-icon name="currency" class="h-4 w-4" />
                                            </a>
                                        @endcan
                                        <form
                                            method="POST"
                                            action="{{ route('admin.finance.committee.destroy', $row['membership']) }}"
                                            class="inline"
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
                                                class="crud-icon-btn crud-icon-btn-danger"
                                            >
                                                <x-icon name="trash" class="h-4 w-4" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty table colspan="6" icon="users">No committee members for this edition yet.</x-admin.empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
