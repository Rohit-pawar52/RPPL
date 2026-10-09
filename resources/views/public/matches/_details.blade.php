{{-- Match Details card - existing match fields only. Expects $match. The toss sits in the score header. --}}
<x-public.card :title="__('matches.info.details')">
    <dl class="mx-facts">
        <div class="mx-fact">
            <span class="mx-fact-icon"><x-icon name="trophy" class="size-4" /></span>
            <div class="min-w-0">
                <dt>{{ __('matches.info.edition') }}</dt>
                <dd>
                    <a href="{{ route('public.editions.show', $match->edition) }}" class="hover:text-brand hover:underline">{{ $match->edition->name }}</a>
                </dd>
            </div>
        </div>
        @if($match->match_stage)
            <div class="mx-fact">
                <span class="mx-fact-icon"><x-icon name="clipboard" class="size-4" /></span>
                <div class="min-w-0">
                    <dt>{{ __('matches.info.stage') }}</dt>
                    <dd>{{ ucwords(str_replace('_', ' ', $match->match_stage)) }}</dd>
                </div>
            </div>
        @endif
        <div class="mx-fact">
            <span class="mx-fact-icon"><x-icon name="chart-bar" class="size-4" /></span>
            <div class="min-w-0">
                <dt>{{ __('matches.info.format') }}</dt>
                <dd>{{ __('matches.common.overs_count', ['overs' => $match->overs_per_innings]) }}</dd>
            </div>
        </div>
        <div class="mx-fact">
            <span class="mx-fact-icon"><x-icon name="calendar" class="size-4" /></span>
            <div class="min-w-0">
                <dt>{{ __('matches.info.date') }}</dt>
                <dd>{{ display_datetime($match->scheduled_at, 'd M Y, h:i A') }}</dd>
            </div>
        </div>
        <div class="mx-fact">
            <span class="mx-fact-icon"><x-icon name="map-pin" class="size-4" /></span>
            <div class="min-w-0">
                <dt>{{ __('matches.info.venue') }}</dt>
                <dd>
                    @if($match->venue)
                        <a href="{{ route('public.venues.show', $match->venue) }}" class="hover:text-brand hover:underline">{{ $match->venue->name }}</a>
                    @else
                        {{ __('matches.info.tbd') }}
                    @endif
                </dd>
                @if($match->venue && $match->venue->locationLabel() !== '')
                    <dd class="mx-fact-sub">{{ $match->venue->locationLabel() }}</dd>
                @endif
            </div>
        </div>
    </dl>

    @if($match->venue)
        <div class="mt-4">
            @include('public.venues._map', ['venue' => $match->venue])
        </div>
    @endif
</x-public.card>
