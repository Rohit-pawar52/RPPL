@extends('layouts.public')

@section('title', $title.' · '.$branding->applicationName)

@section('content')
    <div class="pub-card mx-auto max-w-3xl p-5 sm:p-8">
        <h1 class="mb-4 border-b border-line pb-4 text-2xl font-bold tracking-tight text-slate-900">{{ $title }}</h1>

        @if($content)
            <div class="content-page-body text-[15px] leading-relaxed text-slate-700">
                {!! $content !!}
            </div>
        @else
            <p class="pub-meta">{{ __('directory.content.empty') }}</p>
        @endif
    </div>
@endsection
