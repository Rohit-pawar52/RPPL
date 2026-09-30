{{--
    Standard public card. Usage:
        <x-public.card title="Points Table" :href="route(...)" link-label="View all">…body…</x-public.card>
    `flush` removes the body padding (for tables / full-bleed media).
    With no title the card is just a padded surface.
--}}
@props(['title' => null, 'href' => null, 'linkLabel' => null, 'flush' => false])

<section {{ $attributes->class(['pub-card overflow-hidden']) }}>
    @if($title)
        <header class="pub-card-head">
            <h2 class="pub-card-title">{{ $title }}</h2>
            @if($href)
                <a href="{{ $href }}" class="pub-link text-xs">{{ $linkLabel }}</a>
            @endif
        </header>
    @endif

    <div @class(['pub-card-body' => ! $flush])>
        {{ $slot }}
    </div>
</section>
