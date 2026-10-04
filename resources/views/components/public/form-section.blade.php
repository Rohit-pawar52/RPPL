{{--
    One step of a long public form: a card with a numbered header and the
    fields as the slot.
        <x-public.form-section :number="1" :title="…" :hint="…"> …fields… </x-public.form-section>
    The number is optional (a plain titled card without it).
--}}
@props(['title', 'number' => null, 'hint' => null])

<section {{ $attributes->class(['rounded-2xl border border-line bg-white shadow-sm']) }}>
    <header class="flex items-start gap-3 border-b border-line px-4 py-3.5 sm:px-5">
        @if($number)
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-navy-900 text-xs font-bold text-white" aria-hidden="true">{{ $number }}</span>
        @endif
        <div class="min-w-0">
            <h2 class="text-[15px] font-semibold leading-7 tracking-tight text-slate-900">{{ $title }}</h2>
            @if($hint)
                <p class="-mt-1 text-xs text-slate-500">{{ $hint }}</p>
            @endif
        </div>
    </header>

    {{-- Every field ends with its own bottom margin, so the card needs none. --}}
    <div class="p-4 pb-0 sm:p-5 sm:pb-0">
        {{ $slot }}
    </div>
</section>
