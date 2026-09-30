@extends('layouts.public')

@section('title', __('directory.videos.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('directory.videos.title')" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($videos as $video)
            @include('public._video-card', ['video' => $video])
        @empty
            <x-public.card class="col-span-full">
                <x-public.empty>{{ __('directory.videos.empty') }}</x-public.empty>
            </x-public.card>
        @endforelse
    </div>

    <div class="mt-5">
        {{ $videos->links() }}
    </div>
@endsection
