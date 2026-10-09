@extends('layouts.admin')

@section('title', __('Finance — Committee'))

@section('subtitle', $edition ? __('Committee members of :edition and what each has paid towards their target.', ['edition' => $edition->name]) : __('Committee members and their dues.'))

@section('actions')
    @if($edition && $previousEdition)
        <form method="POST" action="{{ route('admin.finance.committee.copy-previous') }}">
            @csrf
            <input type="hidden" name="edition_id" value="{{ $edition->id }}" />
            <button type="submit" class="btn btn-secondary btn-sm">{{ __('Copy Previous Edition Committee') }}</button>
        </form>
    @endif
@endsection

@section('content')
    @include('admin.finance._tabs')

    <form method="GET" action="{{ route('admin.finance.committee') }}" class="mb-4 flex items-center gap-2">
        <label for="committee-edition" class="crud-chip-label">{{ __('Edition') }}</label>
        <select id="committee-edition" name="edition_id" onchange="this.form.submit()" class="crud-field crud-select">
            @foreach($editions as $option)
                <option value="{{ $option->id }}" @selected($edition && $edition->id === $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    </form>

    @if(! $edition)
        <div class="crud-card">
            <x-admin.empty icon="users" class="py-12!">{{ __('No editions exist yet.') }}</x-admin.empty>
        </div>
    @else
        @if($summary)
            <div class="crud-kpis">
                <x-crud.kpi :label="__('Members')" :value="$summary['total_members']" icon="users" tone="brand" />
                <x-crud.kpi :label="__('Paid in Full')" :value="$summary['paid_in_full']" icon="users" tone="in" />
                <x-crud.kpi :label="__('Partially Paid')" :value="$summary['partially_paid']" icon="users" />
                <x-crud.kpi :label="__('Not Paid')" :value="$summary['not_paid']" icon="users" tone="out" />
            </div>
        @endif

        <div class="crud-card mb-4 p-3 sm:p-4">
            <form method="POST" action="{{ route('admin.finance.committee.store') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <input type="hidden" name="edition_id" value="{{ $edition->id }}" />

                <div class="min-w-[14rem] flex-1">
                    <label for="committee-contributor" class="mb-1 block text-xs font-medium text-slate-700">{{ __('Add committee member') }}</label>
                    <select id="committee-contributor" name="contributor_id" required class="crud-field w-full">
                        <option value="" disabled selected>{{ __('Select a contributor') }}</option>
                        @forelse($addableContributors as $contributor)
                            <option value="{{ $contributor->id }}">{{ $contributor->label() }}</option>
                        @empty
                            <option value="" disabled>{{ __('No addable contributors — every active contributor is already on this committee') }}</option>
                        @endforelse
                    </select>
                </div>

                <x-admin.button class="min-h-10">{{ __('Add') }}</x-admin.button>

                @can('create', \App\Models\Contributor::class)
                    <a href="{{ route('admin.contributors.create') }}" class="crud-link inline-flex min-h-10 items-center text-[13px]">{{ __('+ New contributor') }}</a>
                @endcan
            </form>
        </div>

        <div class="crud-table-wrap">
            <div class="crud-table-scroll">
                <table class="crud-table crud-stack">
                    <thead>
                        <tr>
                            <th>{{ __('Contributor') }}</th>
                            <th class="text-right">{{ __('Target') }}</th>
                            <th class="text-right">{{ __('Paid') }}</th>
                            <th class="text-right">{{ __('Remaining') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-right">{{ __('Actions') }}</th>
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
                                    <span class="crud-meta md:hidden">{{ __('Paid :paid of :target', ['paid' => money($row['paid']), 'target' => money($row['target'])]) }}</span>
                                </td>
                                <td class="hidden text-right tabular-nums md:table-cell">{{ money($row['target']) }}</td>
                                <td class="hidden text-right font-medium tabular-nums text-green-600 md:table-cell">{{ money($row['paid']) }}</td>
                                <td class="c-amount c-num whitespace-nowrap">
                                    <span class="text-[11px] uppercase tracking-wide text-slate-400 md:hidden">{{ __('Left') }}</span>
                                    <span class="font-semibold tabular-nums {{ $row['remaining'] > 0 ? 'text-slate-900' : 'text-green-600' }}">{{ money($row['remaining']) }}</span>
                                </td>
                                <td class="c-sub"><x-status-badge :status="$row['status']" /></td>
                                <td class="c-actions">
                                    <div class="crud-actions">
                                        @can('create', \App\Models\EditionContribution::class)
                                            <a
                                                href="{{ route('admin.edition-contributions.create', ['edition_id' => $edition->id, 'contributor_id' => $row['contributor']->id]) }}"
                                                title="{{ __('Record contribution') }}"
                                                aria-label="{{ __('Record contribution for :name', ['name' => $row['contributor']->name]) }}"
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
                                            data-confirm-title="{{ __('Remove :name from this committee?', ['name' => $row['contributor']->name]) }}"
                                            data-confirm-text="{{ __('This only removes this edition\'s membership — the contributor and their contribution history are never touched. Blocked if they already have recorded contribution history for this edition.') }}"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                title="{{ __('Remove from committee') }}"
                                                aria-label="{{ __('Remove :name from committee', ['name' => $row['contributor']->name]) }}"
                                                class="crud-icon-btn crud-icon-btn-danger"
                                            >
                                                <x-icon name="trash" class="h-4 w-4" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty table colspan="6" icon="users">{{ __('No committee members for this edition yet.') }}</x-admin.empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
