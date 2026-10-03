{{-- Match Details sidebar card — existing match fields only. Expects $match. --}}
<x-public.card :title="__('matches.info.details')">
    <dl class="space-y-3 text-[13px]">
        <div>
            <dt class="pub-eyebrow">{{ __('matches.info.edition') }}</dt>
            <dd class="mt-0.5 font-medium text-slate-800">{{ $match->edition->name }}</dd>
        </div>
        @if($match->match_stage)
            <div>
                <dt class="pub-eyebrow">{{ __('matches.info.stage') }}</dt>
                <dd class="mt-0.5 font-medium text-slate-800">{{ ucwords(str_replace('_', ' ', $match->match_stage)) }}</dd>
            </div>
        @endif
        <div>
            <dt class="pub-eyebrow">{{ __('matches.info.format') }}</dt>
            <dd class="mt-0.5 font-medium text-slate-800">{{ __('matches.common.overs_count', ['overs' => $match->overs_per_innings]) }}</dd>
        </div>
        <div>
            <dt class="pub-eyebrow">{{ __('matches.info.venue') }}</dt>
            <dd class="mt-0.5 font-medium text-slate-800">{{ $match->venue->name ?? __('matches.info.tbd') }}</dd>
            @if($match->venue && $match->venue->locationLabel() !== '')
                <dd class="mt-0.5 text-xs text-slate-500">{{ $match->venue->locationLabel() }}</dd>
            @endif
            @if($match->venue)
                <div class="mt-2">
                    @include('public.venues._map', ['venue' => $match->venue])
                </div>
            @endif
        </div>
        <div>
            <dt class="pub-eyebrow">{{ __('matches.info.date') }}</dt>
            <dd class="mt-0.5 font-medium text-slate-800">{{ display_datetime($match->scheduled_at, 'd M Y, h:i A') }}</dd>
        </div>
        @if($match->tossWinner)
            <div>
                <dt class="pub-eyebrow">{{ __('matches.info.toss') }}</dt>
                <dd class="mt-0.5 font-medium text-slate-800">
                    {{ __('matches.info.toss_result', ['team' => $match->tossWinner->team->name, 'decision' => __('matches.info.toss_decision.'.$match->toss_decision)]) }}
                </dd>
            </div>
        @endif
    </dl>
</x-public.card>
