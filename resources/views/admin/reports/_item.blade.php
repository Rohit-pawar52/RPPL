{{-- One downloadable / printable report as a row: icon, name, what is in it, its format and the action.
     Expects $icon, $name, $text (may hold HTML, already escaped by the caller), $format (PDF / CSV / HTML),
     $href and $cta (the button label). --}}
<div class="flex flex-col gap-3 rounded-xl border border-line bg-white p-4 shadow-card transition duration-150 hover:border-brand/40 hover:shadow-raised sm:flex-row sm:items-center sm:gap-4 motion-reduce:transition-none">
    <div class="flex min-w-0 flex-1 items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand"><x-admin.icon :name="$icon" class="h-5 w-5" /></span>
        <div class="min-w-0">
            <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-900">
                {{ $name }}
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $format }}</span>
            </p>
            <p class="mt-0.5 text-xs leading-5 text-slate-500">{!! $text !!}</p>
        </div>
    </div>
    <a href="{{ $href }}" class="btn btn-secondary btn-sm shrink-0 max-sm:w-full">
        <x-admin.icon :name="$format === 'HTML' ? 'eye' : 'download'" class="h-4 w-4" />
        {{ $cta }}
    </a>
</div>
