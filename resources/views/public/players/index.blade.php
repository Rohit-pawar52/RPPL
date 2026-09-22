@extends('layouts.public')

@section('title', 'Players &middot; '.$branding->shortName)

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-base font-semibold text-neutral-900">Players</h1>

        <form method="GET" action="{{ route('public.players.index') }}" class="flex items-center gap-2">
            <input
                type="text"
                name="search"
                value="{{ $search }}"
                placeholder="Search by name"
                class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring"
            />
            <button type="submit" class="rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                Search
            </button>
        </form>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($players as $player)
                @php $team = $player->latestRegistration?->teamPlayer?->editionTeam?->team; @endphp
                <a
                    href="{{ route('public.players.show', $player) }}"
                    class="flex items-center gap-3 rounded-md border border-neutral-200 p-3 hover:bg-neutral-50"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                        @if($player->photo_path)
                            <img
                                src="{{ Illuminate\Support\Facades\Storage::url($player->photo_path) }}"
                                alt="{{ $player->name }}"
                                class="h-full w-full object-cover"
                            />
                        @else
                            <x-icon name="user" class="h-4 w-4" />
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-neutral-900">{{ $player->name }}</p>
                        <p class="truncate text-xs text-neutral-500">{{ $team?->name ?? 'No team' }}</p>
                    </div>
                </a>
            @empty
                <p class="col-span-full py-4 text-center text-xs text-neutral-400">No players available yet.</p>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $players->links() }}
        </div>
    </div>
@endsection
