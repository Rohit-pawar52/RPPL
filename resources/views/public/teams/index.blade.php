@extends('layouts.public')

@section('title', __('directory.teams.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('directory.teams.title')" />

    <div class="pc-toolbar">
        @include('public._directory-search', [
            'action' => route('public.teams.index'),
            'search' => $search,
            'placeholder' => __('directory.common.search_by_name'),
            'target' => '#pc-team-grid',
            'empty' => '#pc-team-nomatch',
        ])
        <p class="pc-count">{{ trans_choice('ux_public_content.count.teams', $teams->total(), ['count' => $teams->total()]) }}</p>
    </div>

    @if($teams->isEmpty())
        <div class="pc-empty">
            <span class="pc-empty-icon"><x-icon name="shield" class="h-7 w-7" /></span>
            @if($search !== '')
                <p class="pc-empty-title">{{ __('ux_public_content.search.none_title', ['term' => $search]) }}</p>
                <p class="pc-empty-hint">{{ __('ux_public_content.search.none_hint') }}</p>
                <a href="{{ route('public.teams.index') }}" class="btn btn-primary mt-2">{{ __('ux_public_content.search.clear') }}</a>
            @else
                <p class="pc-empty-title">{{ __('directory.teams.empty') }}</p>
            @endif
        </div>
    @else
        <div id="pc-team-grid" class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
            @foreach($teams as $team)
                <a
                    href="{{ route('public.teams.show', $team) }}"
                    class="group pc-card-link flex flex-col items-center gap-3 p-4 text-center sm:p-5"
                    data-filter-text="{{ mb_strtolower($team->name.' '.$team->short_name) }}"
                >
                    <span class="pc-avatar h-16 w-16 sm:h-20 sm:w-20">
                        <x-media-image :path="$team->logo_path" kind="image" alt="" loading="lazy" />
                    </span>
                    <span class="block w-full min-w-0">
                        <span class="block truncate text-sm font-semibold text-slate-900 group-hover:text-brand">{{ $team->name }}</span>
                        @if($team->short_name)
                            <span class="pc-role-pill mt-1.5">{{ $team->short_name }}</span>
                        @endif
                    </span>
                </a>
            @endforeach
        </div>

        {{-- Shown by the search box when what was typed matches nothing on this page. --}}
        <div id="pc-team-nomatch" class="pc-empty" hidden>
            <span class="pc-empty-icon"><x-icon name="shield" class="h-7 w-7" /></span>
            <p class="pc-empty-title">{{ __('ux_public_content.search.no_match_title') }}</p>
            <p class="pc-empty-hint">{{ __('ux_public_content.search.no_match_hint') }}</p>
            <button type="button" class="btn btn-primary mt-2" data-pc-submit="#pc-team-nomatch">{{ __('ux_public_content.search.search_all') }}</button>
        </div>
    @endif

    <div class="mt-6">
        {{ $teams->links() }}
    </div>
@endsection
