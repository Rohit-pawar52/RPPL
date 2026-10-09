@extends('layouts.public')

@section('title', __('directory.players.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('directory.players.title')" />

    <div class="pc-toolbar">
        @include('public._directory-search', [
            'action' => route('public.players.index'),
            'search' => $search,
            'placeholder' => __('directory.common.search_by_name'),
            'target' => '#pc-player-grid',
            'empty' => '#pc-player-nomatch',
        ])
        <p class="pc-count">{{ trans_choice('ux_public_content.count.players', $players->total(), ['count' => $players->total()]) }}</p>
    </div>

    @if($players->isEmpty())
        <div class="pc-empty">
            <span class="pc-empty-icon"><x-icon name="users" class="h-7 w-7" /></span>
            @if($search !== '')
                <p class="pc-empty-title">{{ __('ux_public_content.search.none_title', ['term' => $search]) }}</p>
                <p class="pc-empty-hint">{{ __('ux_public_content.search.none_hint') }}</p>
                <a href="{{ route('public.players.index') }}" class="btn btn-primary mt-2">{{ __('ux_public_content.search.clear') }}</a>
            @else
                <p class="pc-empty-title">{{ __('directory.players.empty') }}</p>
            @endif
        </div>
    @else
        <div id="pc-player-grid" class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
            @foreach($players as $player)
                @php
                    $team = $player->latestRegistration?->teamPlayer?->editionTeam?->team;
                    $roleLabel = $player->primary_role
                        ? (\Illuminate\Support\Facades\Lang::has('directory.roles.'.$player->primary_role) ? __('directory.roles.'.$player->primary_role) : str_replace('_', ' ', ucfirst($player->primary_role)))
                        : null;
                @endphp
                <a
                    href="{{ route('public.players.show', $player) }}"
                    class="group pc-card-link overflow-hidden"
                    data-filter-text="{{ mb_strtolower($player->name.' '.($team?->name ?? '')) }}"
                >
                    <span class="relative block aspect-square w-full overflow-hidden bg-slate-100">
                        <x-media-image :path="$player->photo_path" kind="user" alt="" loading="lazy" class="h-full w-full object-cover object-top transition duration-300 motion-safe:group-hover:scale-105" />
                        @if($roleLabel)
                            <span class="pc-role-pill absolute bottom-2 left-2 bg-white/90 shadow-sm backdrop-blur">{{ $roleLabel }}</span>
                        @endif
                    </span>
                    <span class="block min-w-0 px-3 py-2.5 sm:px-4 sm:py-3">
                        <span class="block truncate text-sm font-semibold text-slate-900 group-hover:text-brand">{{ $player->name }}</span>
                        <span class="block truncate text-xs text-slate-500">{{ $team?->name ?? __('directory.players.no_team') }}</span>
                    </span>
                </a>
            @endforeach
        </div>

        <div id="pc-player-nomatch" class="pc-empty" hidden>
            <span class="pc-empty-icon"><x-icon name="users" class="h-7 w-7" /></span>
            <p class="pc-empty-title">{{ __('ux_public_content.search.no_match_title') }}</p>
            <p class="pc-empty-hint">{{ __('ux_public_content.search.no_match_hint') }}</p>
            <button type="button" class="btn btn-primary mt-2" data-pc-submit="#pc-player-nomatch">{{ __('ux_public_content.search.search_all') }}</button>
        </div>
    @endif

    <div class="mt-6">
        {{ $players->links() }}
    </div>
@endsection
