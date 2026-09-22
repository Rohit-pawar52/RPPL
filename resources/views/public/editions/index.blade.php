@extends('layouts.public')

@section('title', 'Editions · '.$branding->shortName)

@section('content')
    <div class="mb-4">
        <h1 class="text-base font-semibold text-neutral-900">Tournament Editions</h1>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[480px] text-left text-[13px]">
                <thead class="border-b border-neutral-200 text-[11px] uppercase tracking-wide text-neutral-400">
                    <tr>
                        <th class="px-2 py-1.5 font-medium">Edition</th>
                        <th class="px-2 py-1.5 font-medium">Status</th>
                        <th class="hidden px-2 py-1.5 text-right font-medium sm:table-cell">Teams</th>
                        <th class="hidden px-2 py-1.5 text-right font-medium sm:table-cell">Matches</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($editions as $edition)
                        <tr>
                            <td class="px-2 py-2">
                                <a href="{{ route('public.editions.show', $edition) }}" class="font-medium text-neutral-800 hover:underline">
                                    {{ $edition->name }}
                                </a>
                                <p class="text-[11px] text-neutral-500">{{ $edition->year }}</p>
                            </td>
                            <td class="px-2 py-2"><x-status-badge :status="$edition->status" /></td>
                            <td class="hidden px-2 py-2 text-right text-neutral-600 sm:table-cell">{{ $edition->edition_teams_count }}</td>
                            <td class="hidden px-2 py-2 text-right text-neutral-600 sm:table-cell">{{ $edition->matches_count }}</td>
                            <td class="px-2 py-2 text-right">
                                <a href="{{ route('public.editions.show', $edition) }}" class="text-xs theme-link hover:underline">View &rarr;</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-2 py-8 text-center text-neutral-400">No tournament editions available yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
