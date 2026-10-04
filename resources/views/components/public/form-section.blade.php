{{--
    One step of a long public form: a compact card with a numbered header and
    the fields as the slot.
        <x-public.form-section :number="1" :title="…" :hint="…"> …fields… </x-public.form-section>
    The number and the hint are optional; the hint sits beside the title.
--}}
@props(['title', 'number' => null, 'hint' => null])

<section {{ $attributes->class(['rounded-xl border border-line bg-white shadow-sm']) }}>
    <header class="flex items-center gap-2.5 border-b border-line px-4 py-2.5">
        @if($number)
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-navy-900 text-[11px] font-bold text-white" aria-hidden="true">{{ $number }}</span>
        @endif
        <h2 class="shrink-0 text-sm font-semibold tracking-tight text-slate-900">{{ $title }}</h2>
        @if($hint)
            <p class="min-w-0 truncate text-[11px] text-slate-500">{{ $hint }}</p>
        @endif
    </header>

    {{-- Every field ends with its own bottom margin, so the card needs none. --}}
    <div class="px-4 pb-0 pt-3.5">
        {{ $slot }}
    </div>
</section>
