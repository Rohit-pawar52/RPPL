{{--
    Page title block used at the top of every public page.
        <x-public.page-header :title="…" :subtitle="…" :back="route(...)" :back-label="…">
            …optional right-aligned actions…
        </x-public.page-header>
--}}
@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => null])

<div class="mb-4 flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
        @if($back)
            <a href="{{ $back }}" class="mb-1 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-900">
                <span aria-hidden="true">&larr;</span> {{ $backLabel }}
            </a>
        @endif
        <h1 class="pub-h1 break-words">{{ $title }}</h1>
        @if($subtitle)
            <p class="pub-meta mt-0.5">{{ $subtitle }}</p>
        @endif
    </div>

    @if(! $slot->isEmpty())
        <div class="flex shrink-0 items-center gap-2">{{ $slot }}</div>
    @endif
</div>
