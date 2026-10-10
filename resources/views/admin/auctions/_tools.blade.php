{{--
    Under the rules on the auction page: the "ready for the day?" check, the downloads (result, bids, activity log),
    the practice reset and the activity log.
    Expects $edition, $auction, $counts, $readiness (null once completed) and $events.
--}}
@if($auction->isCompleted())
    <section class="ops-card mt-4 border-amber-300" aria-label="{{ __('Reopen the auction') }}">
        <div class="ops-card-head"><h3 class="ops-title">{{ __('Reopen the auction') }}</h3></div>
        <div class="ops-card-body">
            <p class="text-[13px] text-slate-600">{{ __('Pressed Complete too early, or a player turned up late? Reopening brings back the players who were left unsold, as waiting players. Every sale and every team stays exactly as it is. The auction comes back paused, so nothing happens until you press Resume.') }}</p>
            <form method="POST" action="{{ route('admin.auctions.reopen', $edition) }}" class="mt-3 flex flex-wrap items-end gap-2" onsubmit="return confirm({{ Js::from(__('Reopen the auction? The unsold players become waiting players again.')) }})">
                @csrf
                <label class="text-xs font-medium text-slate-600">{{ __('Type REOPEN to confirm') }}
                    <input type="text" name="confirm_reopen" autocomplete="off" required class="ops-input mt-1 block min-h-10 w-40" />
                </label>
                @error('confirm_reopen')
                    <p class="w-full text-xs text-red-600">{{ $message }}</p>
                @enderror
                <button type="submit" class="btn btn-secondary btn-sm min-h-10">{{ __('Reopen the auction') }}</button>
            </form>
        </div>
    </section>
@endif

@if($readiness)
    <section class="ops-card mt-4" aria-label="{{ __('Ready for the auction day?') }}">
        <div class="ops-card-head flex flex-wrap items-center justify-between gap-2">
            <h3 class="ops-title">{{ __('Ready for the auction day?') }}</h3>
            @if($readiness['errors'] > 0)
                <span class="pub-pill pub-pill-danger">{{ __(':count to fix', ['count' => $readiness['errors']]) }}</span>
            @elseif($readiness['warnings'] > 0)
                <span class="pub-pill pub-pill-warn">{{ __(':count to look at', ['count' => $readiness['warnings']]) }}</span>
            @else
                <span class="pub-pill pub-pill-success">{{ __('All good') }}</span>
            @endif
        </div>
        <ul class="divide-y divide-line">
            @foreach($readiness['items'] as $item)
                <li class="flex items-start gap-3 px-4 py-2.5 text-[13px]">
                    <span @class([
                        'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                        'bg-green-100 text-green-700' => $item['level'] === 'ok',
                        'bg-amber-100 text-amber-700' => $item['level'] === 'warn',
                        'bg-red-100 text-red-700' => $item['level'] === 'error',
                    ]) aria-hidden="true">{{ $item['level'] === 'ok' ? '✓' : ($item['level'] === 'warn' ? '!' : '×') }}</span>
                    <span class="min-w-0">
                        <span @class(['block font-medium', 'text-slate-700' => $item['level'] === 'ok', 'text-slate-900' => $item['level'] !== 'ok'])>{{ $item['title'] }}</span>
                        @if($item['detail'])
                            <span class="mt-0.5 block text-xs text-slate-500">{{ $item['detail'] }}</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </section>
@endif

<div class="mt-4 grid grid-cols-[minmax(0,1fr)] items-start gap-4 lg:grid-cols-2">
    <section class="ops-card">
        <div class="ops-card-head"><h3 class="ops-title">{{ __('Downloads and safety copy') }}</h3></div>
        <div class="ops-card-body">
            <p class="text-[13px] text-slate-600">{{ __('Keep a copy outside the website: the result, every bid and the activity log open in Excel. The PDF is the printable result by team.') }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('admin.auctions.results-pdf', $edition) }}" class="btn btn-primary btn-sm"><x-admin.icon name="download" class="h-4 w-4" /> {{ __('Result (PDF)') }}</a>
                <a href="{{ route('admin.auctions.export', [$edition, 'results']) }}" class="btn btn-secondary btn-sm">{{ __('Result (Excel)') }}</a>
                <a href="{{ route('admin.auctions.export', [$edition, 'bids']) }}" class="btn btn-secondary btn-sm">{{ __('All bids (Excel)') }}</a>
                <a href="{{ route('admin.auctions.export', [$edition, 'events']) }}" class="btn btn-secondary btn-sm">{{ __('Activity log (Excel)') }}</a>
            </div>
        </div>
    </section>

    @if($editable && ($counts['sold'] > 0 || $counts['live'] > 0 || $counts['hold'] > 0 || $counts['unsold'] > 0 || ! $auction->isDraft()))
        <section class="ops-card border-red-200">
            <div class="ops-card-head"><h3 class="ops-title text-red-700">{{ __('Start again (rehearsal)') }}</h3></div>
            <div class="ops-card-body">
                <p class="text-[13px] text-slate-600">{{ __('For a practice run on the real data: every sale and every bid is removed, every player waits again, every team is empty and the auction goes back to set-up. The activity log keeps the history. Not possible once a sold player has played a match.') }}</p>
                <form method="POST" action="{{ route('admin.auctions.reset', $edition) }}" class="mt-3 flex flex-wrap items-end gap-2" onsubmit="return confirm({{ Js::from(__('Remove every sale and bid and start the auction again?')) }})">
                    @csrf
                    <label class="text-xs font-medium text-slate-600">{{ __('Type RESET to confirm') }}
                        <input type="text" name="confirm" autocomplete="off" required class="ops-input mt-1 block min-h-10 w-40" />
                    </label>
                    @error('confirm')
                        <p class="w-full text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="btn btn-danger-soft btn-sm min-h-10">{{ __('Reset the auction') }}</button>
                </form>
            </div>
        </section>
    @endif
</div>

<section class="ops-card mt-4" aria-label="{{ __('Activity log') }}">
    <div class="ops-card-head flex flex-wrap items-center justify-between gap-2">
        <h3 class="ops-title">{{ __('Activity log') }}</h3>
        <span class="text-xs text-slate-400">{{ __('The latest :count entries, newest first', ['count' => $events->count()]) }}</span>
    </div>
    @if($events->isEmpty())
        <p class="px-4 py-6 text-center text-[13px] text-slate-500">{{ __('Nothing has happened in this auction yet.') }}</p>
    @else
        <ul class="divide-y divide-line">
            @foreach($events as $event)
                <li class="flex items-start justify-between gap-3 px-4 py-2 text-[13px]">
                    <span class="min-w-0 text-slate-800">{{ $event->describe() }}</span>
                    <span class="shrink-0 text-right text-[11px] leading-4 text-slate-400">
                        {{ display_datetime($event->created_at, 'h:i:s A') }}<br>
                        {{ $event->user?->name ?? '—' }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
