{{--
    Page title block used at the top of every public page.
        <x-public.page-header :title="…" :subtitle="…" :back="route(...)" :back-label="…">
            …optional right-aligned actions…
        </x-public.page-header>
    Optional: `eyebrow` (a small label above the title).
--}}
@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => null, 'eyebrow' => null])

<div class="mb-5 flex flex-wrap items-end justify-between gap-x-4 gap-y-3 sm:mb-6">
    <div class="min-w-0">
        @if($back)
            <a href="{{ $back }}" class="mb-2 inline-flex min-h-8 items-center gap-1.5 rounded-lg text-xs font-semibold text-slate-500 transition-colors hover:text-slate-900">
                <x-icon name="arrow-left" class="h-3.5 w-3.5" /> {{ $backLabel }}
            </a>
        @endif
        @if($eyebrow)
            <p class="pub-eyebrow mb-1">{{ $eyebrow }}</p>
        @endif
        <h1 class="break-words text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $title }}</h1>
        @if($subtitle)
            <p class="mt-1 max-w-2xl text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>

    @if(! $slot->isEmpty())
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
