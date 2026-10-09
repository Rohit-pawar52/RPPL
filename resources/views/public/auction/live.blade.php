@extends($big ? 'layouts.auction-display' : 'layouts.public')

@section('title', __('auction.title').' · '.$state['auction']['edition'].' · '.$branding->shortName)

{{--
    The live auction. The page draws itself (resources/js/public-auction.js)
    from the public state embedded here and refreshes it every few seconds
    from the data endpoint, in the visitor's language. Nothing private is in
    the state. With `?display=big` it is the dark projector layout. The
    "Big screen" link sits in the header the script draws, so it is one tap
    away without scrolling.
--}}
@section('content')
    <script type="application/json" id="public-auction-data">{!! json_encode([
        'state' => $state,
        'texts' => $texts,
        'dataUrl' => $dataUrl,
        'saleUrl' => $saleUrl,
        'pollSeconds' => $pollSeconds,
        'big' => $big,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>

    <div id="public-auction" data-big="{{ $big ? '1' : '0' }}" class="flex min-h-0 flex-1 flex-col">
        {{-- Until the script has drawn the page (or when it cannot): the title and a plain note. --}}
        <noscript>
            <div class="pc-empty">
                <span class="pc-empty-icon"><x-icon name="trophy" class="h-7 w-7" /></span>
                <p class="pc-empty-title">{{ __('auction.title') }} · {{ $state['auction']['edition'] }}</p>
                <p class="pc-empty-hint">{{ __('auction.needs_js') }}</p>
            </div>
        </noscript>
    </div>

    {{-- The sponsor pop-up sits outside the part that redraws itself. --}}
    <x-ad-popup :big="$big" />

    @vite(['resources/js/public-auction.js'])
@endsection
