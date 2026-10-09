@extends('layouts.public')

@section('title', __('ux_public_contributors.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('ux_public_contributors.title')" :subtitle="__('ux_public_contributors.thanks', ['name' => $branding->shortName])" :back="route('public.home')" :back-label="__('ux_public_contributors.back')" />

    @if($editions->count() > 1)
        <nav class="mb-4 flex flex-wrap gap-2" aria-label="{{ __('ux_public_contributors.season') }}">
            @foreach($editions as $option)
                <a
                    href="{{ route('public.contributors.index', ['edition_id' => $option->id]) }}"
                    @class([
                        'inline-flex min-h-9 items-center rounded-full border px-3.5 text-sm font-semibold transition',
                        'border-brand bg-brand-soft text-brand' => $option->id === $edition?->id,
                        'border-line bg-white text-slate-600 hover:bg-hover' => $option->id !== $edition?->id,
                    ])
                    @if($option->id === $edition?->id) aria-current="page" @endif
                >{{ $option->name }}</a>
            @endforeach
        </nav>
    @endif

    @if(empty($contributors))
        <div class="pub-card px-4 py-10 text-center">
            <x-public.empty icon="star">{{ __('ux_public_contributors.empty') }}</x-public.empty>
        </div>
    @else
        <p class="mb-3 text-sm text-slate-500">{{ trans_choice('ux_public_contributors.count', count($contributors), ['count' => count($contributors)]) }}</p>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
            @foreach($contributors as $row)
                <div class="pub-card flex flex-col items-center gap-2 p-4 text-center">
                    <span class="relative block h-20 w-20 overflow-hidden rounded-full border border-line bg-white">
                        <x-media-image :path="$row['photo_path']" kind="user" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy" />
                    </span>
                    <span class="block w-full min-w-0">
                        <span class="block truncate text-sm font-semibold text-slate-900" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                        @if($row['village'])
                            <span class="mt-0.5 block truncate text-xs text-slate-500" title="{{ $row['village'] }}">{{ $row['village'] }}</span>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    @endif
@endsection
