@extends('layouts.public')

@section('title', __('directory.players.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('directory.players.title')">
        <form method="GET" action="{{ route('public.players.index') }}" class="flex items-center gap-2" role="search">
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
        @forelse($players as $player)
            @php $team = $player->latestRegistration?->teamPlayer?->editionTeam?->team; @endphp
            <a href="{{ route('public.players.show', $player) }}" class="pub-card pub-card-link flex items-center gap-3 p-3.5">
                <div class="pub-media flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-line text-sm font-bold text-slate-500">
                    {{ mb_strtoupper(mb_substr($player->name, 0, 1)) }}
                    @if($player->photo_path)
                        <img
                            src="{{ Illuminate\Support\Facades\Storage::url($player->photo_path) }}"
                            alt="{{ $player->name }}"
                            class="absolute inset-0 h-full w-full bg-white object-cover"
                            onerror="this.style.visibility='hidden'"
                        />
                    @endif
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ $player->name }}</p>
                    <p class="pub-meta truncate">{{ $team?->name ?? __('directory.players.no_team') }}</p>
                </div>
            </a>
        @empty
            <x-public.card class="col-span-full">
                <x-public.empty>{{ __('directory.players.empty') }}</x-public.empty>
            </x-public.card>
        @endforelse
    </div>

    <div class="mt-5">
        {{ $players->links() }}
    </div>
@endsection
