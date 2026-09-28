@extends('layouts.public')

@section('title', 'Videos · '.$branding->shortName)

@section('content')
    <div class="mb-4">
        <h1 class="text-base font-semibold text-neutral-900">Videos</h1>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($videos as $video)
                @include('public._video-card', ['video' => $video])
            @empty
                <p class="col-span-full py-4 text-center text-xs text-neutral-400">No videos available yet.</p>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $videos->links() }}
        </div>
    </div>
@endsection
