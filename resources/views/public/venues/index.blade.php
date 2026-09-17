@extends('layouts.public')

@section('title', 'Venues &middot; RPPL')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-base font-semibold text-neutral-900">Venues</h1>

        <form method="GET" action="{{ route('public.venues.index') }}" class="flex items-center gap-2">
            <input
                type="text"
                name="search"
                value="{{ $search }}"
                placeholder="Search by name or city"
                class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100"
            />
            <button type="submit" class="rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                Search
            </button>
        </form>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($venues as $venue)
                <a
                    href="{{ route('public.venues.show', $venue) }}"
                    class="flex items-center gap-3 rounded-md border border-neutral-200 p-3 hover:bg-neutral-50"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                        <x-icon name="map-pin" class="h-4 w-4" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-neutral-900">{{ $venue->name }}</p>
                        <p class="truncate text-xs text-neutral-500">
                            {{ collect([$venue->city, $venue->country])->filter()->implode(', ') ?: 'Location unavailable' }}
                        </p>
                    </div>
                </a>
            @empty
                <p class="col-span-full py-4 text-center text-xs text-neutral-400">No venues available yet.</p>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $venues->links() }}
        </div>
    </div>
@endsection
