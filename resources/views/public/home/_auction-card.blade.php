{{--
    A slim card at the top of the homepage while the player auction is on
    (live or paused) and for a week after it ends. Expects $auctionCard from
    HomeController (null = nothing to show).
--}}
@if($auctionCard)
    @php
        $over = $auctionCard['status'] === 'completed';
        $paused = $auctionCard['status'] === 'paused';
    @endphp

    <a
        href="{{ route('public.auction.show') }}"
        class="pub-card mt-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-l-4 {{ $over ? 'border-l-slate-400' : 'border-l-red-500' }} px-4 py-3 transition hover:border-green-300"
    >
        <span class="flex min-w-0 items-center gap-2.5">
            @unless($over)
                <span class="inline-flex items-center gap-1.5 rounded-full {{ $paused ? 'bg-amber-100 text-amber-800' : 'bg-red-600 text-white' }} px-2 py-0.5 text-[11px] font-bold tracking-wide">
                    @unless($paused)<span class="live-dot" aria-hidden="true"></span>@endunless
                    {{ $paused ? __('auction.paused') : __('auction.live') }}
                </span>
            @endunless
            <span class="min-w-0">
                <span class="block truncate text-[13px] font-semibold text-slate-900">
                    {{ $over ? __('auction.home_results') : ($paused ? __('auction.home_paused') : __('auction.home_live')) }}
                </span>
                @unless($over)
                    <span class="block truncate text-xs text-slate-500">
                        {{ $auctionCard['name'] ? __('auction.home_now', ['name' => $auctionCard['name']]) : __('auction.home_waiting') }}
                    </span>
                @endunless
            </span>
        </span>
        <span class="shrink-0 text-xs font-semibold text-green-700">{{ $over ? __('auction.home_results_link') : __('auction.home_watch') }} &rarr;</span>
    </a>
@endif
