{{--
    A white card with an optional header.

    Props
      title     the card heading (an <h2>)
      subtitle  a small line under the title
      flush     true = no padding inside, and the body scrolls sideways when wider than the card
                (use it for a table placed directly inside)
    Slots
      actions   buttons / links on the right of the header
      footer    a muted strip under the body
--}}
@props(['title' => null, 'subtitle' => null, 'flush' => false])

<div {{ $attributes->merge(['class' => 'adm-card']) }}>
    @if($title || isset($actions))
        <div class="adm-card-head">
            <div class="min-w-0">
                @if($title)<h2 class="adm-card-title">{{ $title }}</h2>@endif
                @if($subtitle)<p class="adm-card-sub">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
        </div>
    @endif
    <div @class([
        'adm-card-body' => ! $flush,
        'overflow-x-auto rounded-b-xl' => $flush,
        'rounded-t-xl' => $flush && ! ($title || isset($actions)),
    ])>
        {{ $slot }}
    </div>
    @isset($footer)<div class="adm-card-foot">{{ $footer }}</div>@endisset
</div>
