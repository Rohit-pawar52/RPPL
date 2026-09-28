import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Public Live Match Center: progressive enhancement over a server-
 * rendered page. `refreshLiveMatch()` is the single fetch/render path —
 * both the ~10-second polling fallback (Phase 3.19) and the optional
 * WebSocket "this match changed" signal (Phase 3.37) call it. Neither
 * ever reads score data from anywhere but this endpoint's JSON response;
 * a WebSocket event is only ever a trigger to re-fetch, never a source
 * of score data itself, so overlapping/duplicate triggers are harmless.
 */
const POLL_INTERVAL_MS = 10000;

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';

    return div.innerHTML;
}

/*
 * Rendering helpers below mirror the Blade partials used for the initial
 * server render (public/matches/_live-innings-row, _live-chase,
 * _live-delivery-row and components/status-badge). They only format the
 * /live-data payload already fetched — never derive score data — and must
 * be kept in sync with those partials so a polled update never shows
 * less (or different) information than a fresh page load.
 */

// Subset of components/status-badge.blade.php's map covering every match
// and innings status this page can show.
const STATUS_BADGE_STYLES = {
    scheduled: 'bg-blue-50 text-blue-700 ring-blue-200',
    toss: 'bg-amber-50 text-amber-700 ring-amber-200',
    live: 'bg-green-50 text-green-700 ring-green-200',
    completed: 'bg-neutral-100 text-neutral-600 ring-neutral-200',
    abandoned: 'bg-red-50 text-red-700 ring-red-200',
    cancelled: 'bg-red-50 text-red-700 ring-red-200',
};

function renderStatusBadge(status) {
    const style = STATUS_BADGE_STYLES[status] ?? 'bg-neutral-100 text-neutral-600 ring-neutral-200';

    return `<span class="inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium capitalize ring-1 ring-inset ${style}">${escapeHtml(status)}</span>`;
}

function formatRate(value) {
    return Number(value ?? 0).toFixed(2);
}

function plural(word, count) {
    return count === 1 ? word : `${word}s`;
}

function renderInnings(innings) {
    return innings
        .map((i) => {
            const borderClass = i.status === 'live' ? 'theme-primary-border' : 'border-neutral-200';
            const crr =
                i.crr !== undefined && i.crr !== null
                    ? ` &middot; CRR <span class="font-semibold tabular-nums text-neutral-700">${formatRate(i.crr)}</span>`
                    : '';

            return `
                <div class="rounded-lg border bg-white p-3 ${borderClass}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Innings ${escapeHtml(String(i.innings_number))}</p>
                            <p class="truncate text-[13px] font-semibold text-neutral-900">${escapeHtml(i.batting_team)}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-lg font-bold leading-tight tabular-nums text-neutral-900">${escapeHtml(String(i.total_runs))}/${escapeHtml(String(i.total_wickets))}</p>
                            <p class="text-[11px] text-neutral-500">(${escapeHtml(i.overs_display)} overs)</p>
                        </div>
                    </div>
                    <div class="mt-1.5 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-[11px] text-neutral-500">
                        <span class="min-w-0">vs ${escapeHtml(i.bowling_team)}${crr}</span>
                        ${renderStatusBadge(i.status)}
                    </div>
                </div>
            `;
        })
        .join('');
}

function renderChase(chase, innings) {
    if (!chase) {
        return '';
    }

    const second = innings.find((i) => Number(i.innings_number) === 2);
    const team = escapeHtml(second?.batting_team ?? 'Chasing side');
    const headline =
        chase.runs_needed > 0
            ? `${team} need ${chase.runs_needed} ${plural('run', chase.runs_needed)} from ${chase.balls_remaining} ${plural('ball', chase.balls_remaining)}`
            : `${team} have reached the target`;

    const stat = (label, value) => `
        <div>
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">${label}</dt>
            <dd class="text-[13px] font-semibold tabular-nums text-neutral-900">${escapeHtml(String(value))}</dd>
        </div>
    `;

    return `
        <p class="text-[13px] font-semibold text-neutral-900">${headline}</p>
        <dl class="mt-1.5 grid grid-cols-4 gap-2 text-center">
            ${stat('Target', chase.target)}
            ${stat('Need', chase.runs_needed)}
            ${stat('Balls', chase.balls_remaining)}
            ${stat('RRR', formatRate(chase.required_run_rate))}
        </dl>
    `;
}

function outcomeClasses(d) {
    if (d.is_wicket) {
        return 'bg-red-600 text-white';
    }

    if (d.outcome_label === '6') {
        return 'bg-emerald-600 text-white';
    }

    if (d.outcome_label === '4') {
        return 'bg-blue-600 text-white';
    }

    return 'bg-neutral-100 text-neutral-700';
}

function renderDeliveries(deliveries) {
    if (deliveries.length === 0) {
        return '<p class="py-4 text-center text-xs text-neutral-400">No deliveries recorded yet.</p>';
    }

    return deliveries
        .map(
            (d) => `
                <div class="flex items-start gap-2.5 border-b border-neutral-100 py-2 text-[13px] last:border-b-0">
                    <span class="mt-0.5 w-9 shrink-0 text-[11px] font-medium tabular-nums text-neutral-500">${escapeHtml(d.ball_label)}</span>
                    <span class="flex h-6 min-w-8 shrink-0 items-center justify-center rounded-full px-1.5 text-[11px] font-bold ${outcomeClasses(d)}">${escapeHtml(d.outcome_label)}</span>
                    <span class="min-w-0 leading-snug text-neutral-700">${escapeHtml(d.commentary)}</span>
                </div>
            `
        )
        .join('');
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('live-match-root');

    if (!root) {
        return;
    }

    const url = root.dataset.liveDataUrl;
    const matchId = root.dataset.matchId ? Number(root.dataset.matchId) : null;
    let shouldPoll = root.dataset.shouldPoll === '1';

    const inningsEl = document.getElementById('live-innings');
    const deliveriesEl = document.getElementById('live-deliveries');
    const resultEl = document.getElementById('live-match-result');
    const chaseEl = document.getElementById('live-chase');
    const statusBadgeEl = document.getElementById('live-status-badge');

    // Reassigned once initRealtimeUpdates() runs, below — declared here
    // so applyUpdate() can call whatever it currently is by reference.
    let stopRealtime = () => {};

    function applyUpdate(data) {
        if (inningsEl) {
            inningsEl.innerHTML = renderInnings(data.innings);
        }

        if (deliveriesEl) {
            deliveriesEl.innerHTML = renderDeliveries(data.recent_deliveries);
        }

        if (chaseEl) {
            const chaseHtml = renderChase(data.chase ?? null, data.innings);
            chaseEl.innerHTML = chaseHtml;
            chaseEl.classList.toggle('hidden', chaseHtml === '');
        }

        if (resultEl) {
            resultEl.textContent = data.match_result ?? '';
            resultEl.classList.toggle('hidden', !data.match_result);
        }

        if (statusBadgeEl && data.match_status) {
            statusBadgeEl.innerHTML = renderStatusBadge(data.match_status);
        }

        shouldPoll = data.should_poll;

        // should_poll only ever goes true -> false when match_status
        // leaves 'live' for a terminal state (completed/abandoned) —
        // this application never transitions a match back to live once
        // it has left that state — so once the final state has been
        // rendered above, the realtime subscription has nothing further
        // to ever receive. Cleanup runs after rendering, never before.
        if (!shouldPoll) {
            stopRealtime();
        }
    }

    // A minimal in-flight guard: if a WebSocket-triggered refresh and a
    // poll-triggered refresh land close together, the second one waits
    // for the first to finish and then runs once more, rather than two
    // overlapping fetches racing each other. Not strictly required for
    // correctness (every refresh renders full server state, never an
    // incremental delta), just avoids redundant concurrent requests.
    let refreshInProgress = false;
    let pendingRefresh = false;

    async function refreshLiveMatch() {
        if (refreshInProgress) {
            pendingRefresh = true;

            return;
        }

        refreshInProgress = true;

        try {
            const response = await window.fetch(url, { headers: { Accept: 'application/json' } });

            if (response.ok) {
                applyUpdate(await response.json());
            }
        } catch {
            // Transient failure — keep current content, next trigger tries again.
        } finally {
            refreshInProgress = false;

            if (pendingRefresh) {
                pendingRefresh = false;
                refreshLiveMatch();
            }
        }
    }

    function scheduleNext() {
        if (shouldPoll) {
            setTimeout(poll, POLL_INTERVAL_MS);
        }
    }

    function poll() {
        if (!shouldPoll) {
            return;
        }

        refreshLiveMatch().finally(scheduleNext);
    }

    scheduleNext();
    stopRealtime = initRealtimeUpdates(matchId, refreshLiveMatch);

    // pusher-js already reconnects its WebSocket automatically (including
    // on the browser's own 'online' event internally) — nothing custom is
    // needed for that. These two listeners are a separate, much simpler
    // concern: they just ask for one ordinary /live-data refresh (the
    // exact same one polling already performs) as soon as the tab
    // becomes visible or the network comes back, so a spectator who put
    // their phone to sleep isn't looking at stale data for up to another
    // 10 seconds while everything else catches back up. Both call the
    // same idempotent refreshLiveMatch() polling already relies on.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refreshLiveMatch();
        }
    });

    window.addEventListener('online', () => {
        refreshLiveMatch();
    });
});

/**
 * Best-effort WebSocket enhancement: on the "this match changed" signal,
 * simply re-run the same refresh the polling fallback already uses.
 * Never renders anything from the event payload itself. If Reverb
 * configuration is unavailable, or Echo/Pusher fails to initialize for
 * any reason, this silently does nothing (returns a no-op stop
 * function) — the page keeps working exactly as it did before this
 * phase, via polling alone.
 *
 * Deliberately does NOT skip initialization based on the page's initial
 * should_poll value: a match can legitimately still be 'scheduled' with
 * Innings data already present (a known Phase 3.18 dev-data scenario)
 * and later go live via startMatch() without a page reload, so the only
 * safe gate here is whether a matchId/Reverb config exists at all — not
 * whether the match happens to be live at the moment the page loaded.
 *
 * @return {() => void} idempotent cleanup — leaves the channel and
 *   disconnects Echo; a no-op if realtime was never initialized.
 */
function initRealtimeUpdates(matchId, refreshLiveMatch) {
    const noop = () => {};

    if (!matchId) {
        return noop;
    }

    const key = import.meta.env.VITE_REVERB_APP_KEY;
    const host = import.meta.env.VITE_REVERB_HOST;
    const port = import.meta.env.VITE_REVERB_PORT;
    const scheme = import.meta.env.VITE_REVERB_SCHEME;

    if (!key || !host) {
        return noop;
    }

    try {
        const echo = new Echo({
            broadcaster: 'reverb',
            key,
            Pusher,
            wsHost: host,
            wsPort: port ?? 80,
            wssPort: port ?? 443,
            forceTLS: (scheme ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });

        const channelName = `public-match.${matchId}`;

        echo.channel(channelName).listen('.match.score.updated', (event) => {
            if (event && event.match_id !== undefined && event.match_id !== matchId) {
                return;
            }

            refreshLiveMatch();
        });

        let stopped = false;

        return () => {
            if (stopped) {
                return;
            }

            stopped = true;

            try {
                echo.leaveChannel(channelName);
                echo.disconnect();
            } catch {
                // Already torn down, or never fully connected — nothing more to do.
            }
        };
    } catch {
        // Realtime unavailable for any reason — polling remains the fallback.
        return noop;
    }
}
