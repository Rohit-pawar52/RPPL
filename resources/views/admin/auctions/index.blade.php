@extends('layouts.admin')

@section('title', 'Auction')

@section('subtitle', 'Players are bought with points. Pick a season to set up or run its auction.')

@section('content')
    <x-admin.card flush>
        <table class="w-full text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Season</th>
                    <th class="px-4 py-2 font-medium">Teams</th>
                    <th class="px-4 py-2 font-medium">Auction</th>
                    <th class="hidden px-4 py-2 text-right font-medium sm:table-cell">Players in the pool</th>
                    <th class="px-4 py-2 text-right font-medium"><span class="sr-only">Open</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($editions as $edition)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 font-medium text-slate-800">
                            <a href="{{ route('admin.auctions.show', $edition) }}" class="hover:underline">{{ $edition->name }}</a>
                            <span class="ml-1 text-xs font-normal text-slate-400">{{ $edition->year }}</span>
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $edition->edition_teams_count }}</td>
                        <td class="px-4 py-2">
                            @if($edition->auction)
                                <x-status-badge :status="$edition->auction->status" />
                            @else
                                <span class="text-xs text-slate-400">Not set up</span>
                            @endif
                        </td>
                        <td class="hidden px-4 py-2 text-right text-slate-600 sm:table-cell">{{ $edition->auction ? $edition->auction->lots_count : '—' }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.auctions.show', $edition) }}" class="text-[11px] font-medium text-green-700 hover:underline">
                                {{ $edition->auction ? 'Open' : 'Set up' }} &rarr;
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="5">No seasons yet.</x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </x-admin.card>
@endsection
