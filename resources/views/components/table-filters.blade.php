{{--
    Thin layout wrapper around an admin index filter form: the caller's
    own search/select inputs go in the default slot (they differ per
    module), this component only standardizes the surrounding <form>,
    the optional date-range pair, and the Filter/Clear buttons so the
    markup stays identical across tables instead of being hand-copied.
--}}
@props(['action', 'filters' => [], 'dateRange' => false, 'perPage' => null])

<form method="GET" action="{{ $action }}" class="flex flex-wrap items-end gap-2">
    {{ $slot }}

    @if($perPage !== null)
        <div class="flex flex-col gap-0.5">
            <label class="text-[11px] font-medium text-neutral-500">Rows</label>
            <select
                name="per_page"
                onchange="this.form.submit()"
                aria-label="Rows per page"
                class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring"
            >
                @foreach([10, 20, 50, 100, 200] as $option)
                    <option value="{{ $option }}" @selected((int) $perPage === $option)>{{ $option }} / page</option>
                @endforeach
            </select>
        </div>
    @endif

    @if($dateRange)
        <div class="flex flex-col gap-0.5">
            <label class="text-[11px] font-medium text-neutral-500">From</label>
            <input
                type="date"
                name="from_date"
                value="{{ $filters['from_date'] ?? '' }}"
                class="rounded-md border border-neutral-300 px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring"
            />
        </div>
        <div class="flex flex-col gap-0.5">
            <label class="text-[11px] font-medium text-neutral-500">To</label>
            <input
                type="date"
                name="to_date"
                value="{{ $filters['to_date'] ?? '' }}"
                class="rounded-md border border-neutral-300 px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring"
            />
        </div>
    @endif

    <button type="submit" class="rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
        Filter
    </button>

    @if(array_filter($filters))
        <a href="{{ $action }}" class="text-[13px] text-neutral-400 hover:text-neutral-600">
            Clear filters
        </a>
    @endif
</form>
