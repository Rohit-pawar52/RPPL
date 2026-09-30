@extends('layouts.public')

@section('title', __('directory.editions.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('directory.editions.heading')" />

    <x-public.card flush>
        <div class="pub-table-wrap">
            <table class="pub-table min-w-[420px]">
                <thead>
                    <tr>
                        <th>{{ __('directory.editions.edition') }}</th>
                        <th>{{ __('directory.editions.status') }}</th>
                        <th class="hidden text-right sm:table-cell">{{ __('directory.editions.teams') }}</th>
                        <th class="hidden text-right sm:table-cell">{{ __('directory.editions.matches') }}</th>
                        <th><span class="sr-only">{{ __('directory.editions.view') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($editions as $edition)
                        <tr>
                            <td>
                                <a href="{{ route('public.editions.show', $edition) }}" class="font-semibold text-slate-900 hover:text-green-700">
                                    {{ $edition->name }}
                                </a>
                                <p class="pub-meta">{{ $edition->year }}</p>
                            </td>
                            <td><x-public.status-pill :status="$edition->status" /></td>
                            <td class="hidden text-right tabular-nums sm:table-cell">{{ $edition->edition_teams_count }}</td>
                            <td class="hidden text-right tabular-nums sm:table-cell">{{ $edition->matches_count }}</td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('public.editions.show', $edition) }}" class="pub-link text-xs">{{ __('directory.editions.view') }} &rarr;</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5"><x-public.empty>{{ __('directory.editions.empty') }}</x-public.empty></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-public.card>
@endsection
