@extends('layouts.admin')

@section('title', __('Auction'))

@section('subtitle', __('Players are bought with points. Pick a season to set up or run its auction.'))

@section('content')
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse($editions as $edition)
            @php $auction = $edition->auction; @endphp
            <article class="group relative flex flex-col gap-4 rounded-xl border border-line bg-white p-4 shadow-card transition hover:-translate-y-px hover:shadow-raised sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="ops-kicker">{{ __('Season :year', ['year' => $edition->year]) }}</p>
                        <a href="{{ route('admin.auctions.show', $edition) }}" class="block truncate text-lg font-bold tracking-tight text-slate-900 after:absolute after:inset-0 after:content-[''] hover:underline">{{ $edition->name }}</a>
                    </div>
                    @if($auction)
                        <x-status-badge :status="$auction->status" />
                    @else
                        <span class="ops-pill ops-pill-slate">{{ __('Not set up') }}</span>
                    @endif
                </div>

                <dl class="grid grid-cols-2 gap-2 text-center">
                    <div class="rounded-lg bg-slate-50 py-2">
                        <dd class="text-xl font-bold tabular-nums text-slate-900">{{ $edition->edition_teams_count }}</dd>
                        <dt class="text-[11px] text-slate-500">{{ __('Teams') }}</dt>
                    </div>
                    <div class="rounded-lg bg-slate-50 py-2">
                        <dd class="text-xl font-bold tabular-nums text-slate-900">{{ $auction ? $auction->lots_count : '—' }}</dd>
                        <dt class="text-[11px] text-slate-500">{{ __('Players in the pool') }}</dt>
                    </div>
                </dl>

                <span class="mt-auto inline-flex items-center gap-1 text-[13px] font-semibold text-link group-hover:text-link-hover">
                    {{ $auction ? ($auction->status === 'live' || $auction->status === 'paused' ? __('Open the auction') : __('Open')) : __('Set up') }}
                    <x-ops.icon name="arrow-right" class="h-4 w-4 transition group-hover:translate-x-0.5" />
                </span>
            </article>
        @empty
            <div class="col-span-full">
                <x-admin.empty icon="gavel" class="ops-card py-14">{{ __('No seasons yet.') }}</x-admin.empty>
            </div>
        @endforelse
    </div>
@endsection
