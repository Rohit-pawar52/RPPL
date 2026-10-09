{{--
    One step of a long public form: a card with a numbered header and the
    fields as the slot.
        <x-public.form-section :number="1" :title="…" :hint="…"> …fields… </x-public.form-section>
    The number and the hint are optional; the hint sits beside the title.
--}}
@props(['title', 'number' => null, 'hint' => null])

<section {{ $attributes->class(['pub-card']) }}>
    <header class="flex items-center gap-3 border-b border-line px-4 py-3 sm:px-5">
        @if($number)
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-navy-900 text-xs font-bold text-white" aria-hidden="true">{{ $number }}</span>
        @endif
        <h2 class="shrink-0 text-[15px] font-semibold tracking-tight text-slate-900">{{ $title }}</h2>
        @if($hint)
            <p class="min-w-0 truncate text-xs text-slate-500">{{ $hint }}</p>
        @endif
    </header>

    {{-- Every field ends with its own bottom margin, so the card needs none. --}}
    <div class="px-4 pb-0 pt-4 sm:px-5">
        {{ $slot }}
    </div>
</section>
