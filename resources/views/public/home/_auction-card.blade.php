{{--
    A slim banner under the hero while the player auction is on (live or
    paused) and for a week after it ends. Expects $auctionCard from
    HomeController (null = nothing to show). The whole banner is the link.
--}}
@if($auctionCard)
    @php
        $over = $auctionCard['status'] === 'completed';
        $paused = $auctionCard['status'] === 'paused';
    @endphp

    <a
        href="{{ route('public.auction.show') }}"
        class="group flex items-center gap-3 rounded-xl border bg-white px-4 py-3 shadow-card transition duration-150 hover:shadow-raised focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand {{ $over ? 'border-line' : ($paused ? 'border-amber-200 bg-gradient-to-r from-amber-50/70 to-white' : 'border-red-200 bg-gradient-to-r from-red-50/70 to-white') }}"
    >
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $over ? 'bg-slate-100 text-slate-500' : ($paused ? 'bg-amber-100 text-amber-700' : 'bg-red-600 text-white') }}">
            <x-icon name="gavel" class="h-5 w-5" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                @unless($over)
                    <span class="inline-flex items-center gap-1.5 rounded-full {{ $paused ? 'bg-amber-100 text-amber-800' : 'bg-red-600 text-white' }} px-2 py-0.5 text-[11px] font-bold tracking-wide">
                        @unless($paused)<span class="live-dot" aria-hidden="true"></span>@endunless
                        {{ $paused ? __('auction.paused') : __('auction.live') }}
                    </span>
                @endunless
                <span class="min-w-0 truncate text-[13px] font-semibold text-slate-900">
                    {{ $over ? __('auction.home_results') : ($paused ? __('auction.home_paused') : __('auction.home_live')) }}
                </span>
            </span>
            @unless($over)
                <span class="mt-0.5 block truncate text-xs text-slate-500">
                    {{ $auctionCard['name'] ? __('auction.home_now', ['name' => $auctionCard['name']]) : __('auction.home_waiting') }}
                </span>
            @endunless
        </span>
        <span class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-link group-hover:text-link-hover">
            <span class="hidden sm:inline">{{ $over ? __('auction.home_results_link') : __('auction.home_watch') }}</span>
            <x-icon name="arrow-right" class="h-4 w-4" />
        </span>
    </a>
@endif
