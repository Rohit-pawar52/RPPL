{{--
    Standard public card. Usage:
        <x-public.card title="Points Table" :href="route(...)" link-label="View all →">…body…</x-public.card>
    `flush` removes the body padding (for tables / full-bleed media).
    With no title the card is just a padded surface. Optional: `icon` puts a
    small icon in front of the title.
--}}
@props(['title' => null, 'href' => null, 'linkLabel' => null, 'flush' => false, 'icon' => null])

<section {{ $attributes->class(['pub-card overflow-hidden']) }}>
    @if($title)
        <header class="flex items-center justify-between gap-3 border-b border-line px-4 py-3 sm:px-5">
            <h2 class="flex min-w-0 items-center gap-2 text-[15px] font-semibold tracking-tight text-slate-900">
                @if($icon)
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand"><x-icon :name="$icon" class="h-4 w-4" /></span>
                @endif
                <span class="min-w-0 truncate">{{ $title }}</span>
            </h2>
            @if($href)
                <a href="{{ $href }}" class="shrink-0 whitespace-nowrap text-xs font-semibold text-link transition-colors hover:text-link-hover hover:underline">{{ $linkLabel }}</a>
            @endif
        </header>
    @endif

    <div @class(['p-4 sm:p-5' => ! $flush])>
        {{ $slot }}
    </div>
</section>
