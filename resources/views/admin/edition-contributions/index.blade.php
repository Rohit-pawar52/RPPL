@extends('layouts.admin')

@section('title', __('Finance — Contributions'))

@php
    $total = $contributions->total();
    $count = number_format($total);
    $subtitle = array_filter($filters)
        ? ($total === 1 ? __(':count contribution match your filters.', ['count' => $count]) : __(':count contributions match your filters.', ['count' => $count]))
        : ($total === 1 ? __(':count contribution recorded from committee members and supporters.', ['count' => $count]) : __(':count contributions recorded from committee members and supporters.', ['count' => $count]));
@endphp

@section('subtitle', $subtitle)

@section('actions')
    <a href="{{ route('admin.edition-contributions.export', $filters) }}" class="btn btn-secondary btn-sm max-sm:hidden">
        <x-icon name="document-chart" class="h-4 w-4" /> {{ __('Export') }}
    </a>
    <x-admin.button :href="route('admin.edition-contributions.create', array_filter(['edition_id' => $filters['edition_id'] ?? null, 'contributor_id' => $filters['contributor_id'] ?? null]))" variant="primary" size="sm">
        <x-crud.glyph name="plus" class="h-4 w-4" /> {{ __('Record contribution') }}
    </x-admin.button>
@endsection

@section('content')
    @include('admin.finance._tabs')

    <div class="crud-kpis grid-cols-1! sm:grid-cols-3!">
        <x-crud.kpi :label="__('Total Contributions')" :value="money($totalContributions)" tone="in" icon="income" :sub="array_filter($filters) ? __('With the filters applied') : __('All editions')" />
    </div>

    <div class="crud-toolbar">
        <x-table-filters :action="route('admin.edition-contributions.index')" :filters="$filters" :date-range="true" :per-page="$perPage">
            <x-crud.search :value="$filters['search'] ?? ''" :placeholder="__('Search contributor name or village&hellip;')" />
            <x-crud.select name="edition_id" :all="__('All editions')" :value="$filters['edition_id'] ?? ''" :options="$editions->pluck('name', 'id')->all()" />
            <x-crud.select name="contributor_id" :all="__('All contributors')" :value="$filters['contributor_id'] ?? ''" :options="$contributors->mapWithKeys(fn ($contributor) => [$contributor->id => $contributor->label()])->all()" />
        </x-table-filters>
    </div>

    <div class="mb-3 flex flex-wrap items-center justify-end gap-2">
        <x-selected-report-action
            id="contributions-selected-export"
            :action="route('admin.edition-contributions.export-selected')"
            :label="__('Export Selected ({count})')"
        />
        <x-selected-report-action
            id="contributions-selected-receipts"
            :action="route('admin.edition-contributions.receipts.selected')"
            :label="__('Print Receipts ({count})')"
        />
    </div>

    <div class="crud-table-wrap" data-row-selection="#contributions-selected-export-button">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th class="w-10">
                            <input type="checkbox" data-select-all aria-label="{{ __('Select all contributions on this page') }}" class="rounded border-slate-300" />
                        </th>
                        <th><x-sortable-header column="contributed_at" :sort="$sort" :direction="$direction">{{ __('Date') }}</x-sortable-header></th>
                        <th>{{ __('Edition') }}</th>
                        <th>{{ __('Contributor') }}</th>
                        <th class="hidden md:table-cell">{{ __('Source') }}</th>
                        <th class="text-right"><x-sortable-header column="amount" :sort="$sort" :direction="$direction">{{ __('Amount') }}</x-sortable-header></th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contributions as $contribution)
                        <tr class="crud-row">
                            <td class="c-check w-10">
                                <input
                                    type="checkbox"
                                    data-row-checkbox
                                    form="contributions-selected-export"
                                    name="selected_ids[]"
                                    value="{{ $contribution->id }}"
                                    aria-label="{{ __('Select contribution :reference', ['reference' => $contribution->receiptReference()]) }}"
                                    class="rounded border-slate-300"
                                />
                            </td>
                            <td class="whitespace-nowrap text-slate-600 max-md:hidden">{{ $contribution->contributed_at->format('d M Y') }}</td>
                            <td class="text-slate-700 max-md:hidden">{{ $contribution->edition->name }}</td>
                            <td class="c-title">
                                <a href="{{ route('admin.edition-contributions.show', $contribution) }}" class="crud-row-link" aria-label="{{ __('View contribution') }}"></a>
                                @can('view', $contribution->contributor)
                                    <a href="{{ route('admin.contributors.show', $contribution->contributor) }}" class="relative z-1 font-medium hover:text-brand hover:underline">{{ $contribution->contributorName() }}</a>
                                @else
                                    {{ $contribution->contributorName() }}
                                @endcan
                                @if($contribution->contributorVillage())
                                    <span class="crud-meta">{{ $contribution->contributorVillage() }}</span>
                                @endif
                                <span class="crud-meta md:hidden">{{ $contribution->contributed_at->format('d M Y') }} &middot; {{ $contribution->edition->name }}</span>
                            </td>
                            <td class="hidden md:table-cell">{{ __($contribution->sourceLabel()) }}</td>
                            <td class="c-amount c-num whitespace-nowrap">
                                <span class="crud-money crud-money-in">+{{ money($contribution->amount) }}</span>
                            </td>
                            <td class="c-actions">
                                <x-crud.row-actions
                                    :view="route('admin.edition-contributions.show', $contribution)"
                                    :name="__('contribution')"
                                    :delete="route('admin.edition-contributions.destroy', $contribution)"
                                    :confirm-title="__('Delete this contribution?')"
                                    :confirm-text="__('This also removes its linked finance transaction. This cannot be undone.')"
                                >
                                    <a
                                        href="{{ route('admin.edition-contributions.receipt', $contribution) }}"
                                        target="_blank"
                                        title="{{ __('Receipt') }}"
                                        aria-label="{{ __('View receipt') }}"
                                        class="crud-icon-btn crud-icon-btn-brand"
                                    >
                                        <x-crud.glyph name="receipt" class="h-4 w-4" />
                                    </a>
                                </x-crud.row-actions>
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="7" icon="currency">
                            {{ array_filter($filters) ? __('No contributions match these filters.') : __('No contributions found.') }}
                            <x-slot:action>
                                @if(array_filter($filters))
                                    <x-admin.button :href="route('admin.edition-contributions.index')" variant="secondary" size="sm">{{ __('Clear filters') }}</x-admin.button>
                                @else
                                    <x-admin.button :href="route('admin.edition-contributions.create')" size="sm">{{ __('+ Record contribution') }}</x-admin.button>
                                @endif
                            </x-slot:action>
                        </x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $contributions->links() }}
    </div>

    {{--
        table-selection.js (shared/off-limits) drives ONE action button
        per data-row-selection root via document.querySelector — it only
        wires up the "Export Selected" button above. The row checkboxes
        are natively associated (form="contributions-selected-export")
        with that same button/form, exactly like every other module.

        The second button ("Print Receipts") reads the SAME checkboxes
        but has no native HTML form association to them (an <input> can
        only declare one `form`), so this page-local script drives it
        directly: mirrors table-selection.js's show/hide-with-count
        logic for visibility, and — since the checkboxes aren't wired to
        its form — copies the currently-checked ids into hidden inputs
        on that form immediately before it submits.
    --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const receiptsButton = document.getElementById('contributions-selected-receipts-button');
            const receiptsForm = document.getElementById('contributions-selected-receipts');
            const rowCheckboxes = () => document.querySelectorAll('[data-row-checkbox]');

            const updateReceiptsButton = () => {
                if (! receiptsButton) return;

                const checkedCount = Array.from(rowCheckboxes()).filter((checkbox) => checkbox.checked).length;

                if (checkedCount > 0) {
                    receiptsButton.hidden = false;
                    receiptsButton.textContent = receiptsButton.dataset.label.replace('{count}', checkedCount);
                } else {
                    receiptsButton.hidden = true;
                }
            };

            rowCheckboxes().forEach((checkbox) => checkbox.addEventListener('change', updateReceiptsButton));

            const selectAll = document.querySelector('[data-select-all]');
            if (selectAll) {
                selectAll.addEventListener('change', updateReceiptsButton);
            }

            updateReceiptsButton();

            if (receiptsForm) {
                receiptsForm.addEventListener('submit', () => {
                    receiptsForm.querySelectorAll('input[name="selected_ids[]"]').forEach((input) => input.remove());

                    rowCheckboxes().forEach((checkbox) => {
                        if (! checkbox.checked) return;

                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'selected_ids[]';
                        hidden.value = checkbox.value;
                        receiptsForm.appendChild(hidden);
                    });
                });
            }
        });
    </script>
@endsection
