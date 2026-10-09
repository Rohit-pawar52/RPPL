@extends('layouts.public')

@section('title', __('directory.videos.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('directory.videos.title')">
        @if($videos->total() > 0)
            <span class="pc-count">{{ trans_choice('ux_public_content.count.videos', $videos->total(), ['count' => $videos->total()]) }}</span>
        @endif
    </x-public.page-header>

    @if($videos->isEmpty())
        <div class="pc-empty">
            <span class="pc-empty-icon"><x-icon name="play" class="h-7 w-7" /></span>
            <p class="pc-empty-title">{{ __('directory.videos.empty') }}</p>
            <p class="pc-empty-hint">{{ __('ux_public_content.videos.empty_hint') }}</p>
            <a href="{{ route('public.home') }}" class="btn btn-primary mt-2">{{ __('ux_public_content.news.go_home') }}</a>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3">
            @foreach($videos as $video)
                @include('public._video-card', ['video' => $video])
            @endforeach
        </div>
    @endif

    <div class="mt-6">
        {{ $videos->links() }}
    </div>
@endsection
