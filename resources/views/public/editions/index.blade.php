@extends('layouts.public')

@section('title', __('directory.editions.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('directory.editions.heading')" />

    @if($editions->isEmpty())
        <x-public.card>
            <x-public.empty icon="trophy">{{ __('directory.editions.empty') }}</x-public.empty>
        </x-public.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($editions as $edition)
                <a href="{{ route('public.editions.show', $edition) }}" @class(['mx-season', 'mx-season-active' => $edition->status === 'active'])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="pub-eyebrow">{{ $edition->year }}</p>
                            <h2 class="mt-1 break-words text-xl font-bold tracking-tight text-slate-900">{{ $edition->name }}</h2>
                        </div>
                        <x-public.status-pill :status="$edition->status" />
                    </div>

                    <dl class="grid grid-cols-2 gap-3 border-t border-line pt-4">
                        <div>
                            <dt class="pub-eyebrow">{{ __('directory.editions.teams') }}</dt>
                            <dd class="mt-0.5 text-2xl font-bold tabular-nums text-slate-900">{{ $edition->edition_teams_count }}</dd>
                        </div>
                        <div>
                            <dt class="pub-eyebrow">{{ __('directory.editions.matches') }}</dt>
                            <dd class="mt-0.5 text-2xl font-bold tabular-nums text-slate-900">{{ $edition->matches_count }}</dd>
                        </div>
                    </dl>

                    <span class="mt-auto text-xs font-semibold text-brand">{{ __('directory.editions.view') }} &rarr;</span>
                </a>
            @endforeach
        </div>
    @endif
@endsection
