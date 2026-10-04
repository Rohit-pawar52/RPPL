@extends('layouts.public')

@section('title', __('directory.teams.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('directory.teams.title')">
        <form method="GET" action="{{ route('public.teams.index') }}" class="flex items-center gap-2" role="search">
            <input
                type="search"
                name="search"
                value="{{ $search }}"
                placeholder="{{ __('directory.common.search_by_name') }}"
                aria-label="{{ __('directory.common.search_by_name') }}"
                class="h-10 w-44 rounded-lg border border-slate-300 bg-white px-3 text-[13px] focus:border-green-600 focus:outline-none focus:ring-2 focus:ring-green-600/20 sm:w-56"
            />
            <button type="submit" class="pub-btn h-10">{{ __('directory.common.search') }}</button>
        </form>
    </x-public.page-header>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($teams as $team)
            <a href="{{ route('public.teams.show', $team) }}" class="pub-card pub-card-link flex items-center gap-3 p-3.5">
                <div class="pub-media flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-line bg-slate-100 text-sm font-bold text-slate-500">
                    <x-media-image :path="$team->logo_path" kind="image" alt="" class="absolute inset-0 h-full w-full bg-white object-cover" />
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ $team->name }}</p>
                    @if($team->short_name)
                        <p class="pub-meta truncate">{{ $team->short_name }}</p>
                    @endif
                </div>
            </a>
        @empty
            <x-public.card class="col-span-full">
                <x-public.empty>{{ __('directory.teams.empty') }}</x-public.empty>
            </x-public.card>
        @endforelse
    </div>

    <div class="mt-5">
        {{ $teams->links() }}
    </div>
@endsection
