@extends('layouts.public')

@section('title', 'Teams &middot; RPPL')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-base font-semibold text-neutral-900">Teams</h1>

        <form method="GET" action="{{ route('public.teams.index') }}" class="flex items-center gap-2">
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
            @forelse($teams as $team)
                <a
                    href="{{ route('public.teams.show', $team) }}"
                    class="flex items-center gap-3 rounded-md border border-neutral-200 p-3 hover:bg-neutral-50"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                        @if($team->logo_path)
                            <img
                                src="{{ Illuminate\Support\Facades\Storage::url($team->logo_path) }}"
                                alt="{{ $team->name }}"
                                class="h-full w-full object-cover"
                            />
                        @else
                            <x-icon name="shield" class="h-4 w-4" />
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-neutral-900">{{ $team->name }}</p>
                        @if($team->short_name)
                            <p class="truncate text-xs text-neutral-500">{{ $team->short_name }}</p>
                        @endif
                    </div>
                </a>
            @empty
                <p class="col-span-full py-4 text-center text-xs text-neutral-400">No teams available yet.</p>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $teams->links() }}
        </div>
    </div>
@endsection
