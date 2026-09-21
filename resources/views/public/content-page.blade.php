@extends('layouts.public')

@section('title', $title.' &middot; '.$branding->applicationName)

@section('content')
    <div class="mx-auto max-w-2xl rounded-lg border border-neutral-200 bg-white p-4 lg:p-6">
        <h1 class="mb-4 text-base font-semibold text-neutral-900">{{ $title }}</h1>

        @if($content)
            <div class="content-page-body">
                {!! $content !!}
            </div>
        @else
            <p class="text-xs text-neutral-400">This page has no content yet.</p>
        @endif
    </div>
@endsection
