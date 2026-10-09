{{--
    Layout wrapper around an admin index filter form: the caller's own
    search/select inputs go in the default slot (they differ per module); this
    component standardizes the surrounding <form>, the optional date-range
    pair, the rows-per-page select and the Filter / Clear buttons, so the
    markup stays identical across tables.

    On a phone only the search box (an input named "search" or "q") stays
    visible; a "Filters" button unfolds the rest, with a count of the filters
    currently applied.

    Props
      action     the GET url of the list
      filters    the applied filters (used for "Clear filters" and the count)
      dateRange  true = From / To date inputs (from_date, to_date)
      perPage    the current page size; shows the "Rows" select
--}}
@props(['action', 'filters' => [], 'dateRange' => false, 'perPage' => null])

@php
    $applied = count(array_filter((array) $filters, fn ($value) => $value !== null && $value !== '' && $value !== []));
@endphp

<form method="GET" action="{{ $action }}" class="tf" @if($applied > 0) data-open @endif>
    <div class="tf-fields">
        {{ $slot }}

        @if($perPage !== null)
            <div class="flex flex-col gap-0.5">
                <label for="tf-per-page" class="text-[11px] font-semibold text-slate-500">Rows</label>
                <select
                    id="tf-per-page"
                    name="per_page"
                    onchange="this.form.submit()"
                    aria-label="Rows per page"
                >
                    @foreach([10, 20, 50, 100, 200] as $option)
                        <option value="{{ $option }}" @selected((int) $perPage === $option)>{{ $option }} / page</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if($dateRange)
            <div class="flex flex-col gap-0.5">
                <label for="tf-from-date" class="text-[11px] font-semibold text-slate-500">From</label>
                <input id="tf-from-date" type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" />
            </div>
            <div class="flex flex-col gap-0.5">
                <label for="tf-to-date" class="text-[11px] font-semibold text-slate-500">To</label>
                <input id="tf-to-date" type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" />
            </div>
        @endif
    </div>

    <button
        type="button"
        class="tf-toggle"
        aria-expanded="{{ $applied > 0 ? 'true' : 'false' }}"
        onclick="var f=this.closest('.tf');var o=f.toggleAttribute('data-open');this.setAttribute('aria-expanded',o)"
    >
        <x-admin.icon name="filter" class="h-4 w-4" />
        Filters
        @if($applied > 0)<span class="tf-count">{{ $applied }}</span>@endif
    </button>

    <div class="tf-actions">
        <button type="submit" class="btn btn-secondary">Filter</button>

        @if($applied > 0)
            <a href="{{ $action }}" class="btn btn-ghost">Clear filters</a>
        @endif
    </div>
</form>
