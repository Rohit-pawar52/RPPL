@extends($big ? 'layouts.auction-display' : 'layouts.public')

@section('title', __('auction.title').' · '.$state['auction']['edition'].' · '.$branding->shortName)

{{--
    The live auction. The page draws itself (resources/js/public-auction.js)
    from the public state embedded here and refreshes it every few seconds
    from the data endpoint, in the visitor's language. Nothing private is in
    the state. With `?display=big` it is the dark projector layout.
--}}
@section('content')
    <script type="application/json" id="public-auction-data">{!! json_encode([
        'state' => $state,
        'texts' => $texts,
        'dataUrl' => $dataUrl,
        'pollSeconds' => $pollSeconds,
        'big' => $big,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>

    <div id="public-auction" data-big="{{ $big ? '1' : '0' }}" class="flex-1">
        <noscript>
            <p class="pub-card p-4 text-[13px] text-slate-600">{{ __('auction.needs_js') }}</p>
        </noscript>
    </div>

    @unless($big)
        <p class="mt-4 text-right text-xs">
            <a href="{{ route('public.auction.show', ['display' => 'big']) }}" class="pub-link">{{ __('auction.big_screen') }} &rarr;</a>
        </p>
    @endunless

    @vite(['resources/js/public-auction.js'])
@endsection
