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

function renderInnings(innings) {
    return innings
        .map(
            (i) => `
                <div class="rounded-lg border border-neutral-200 bg-white p-4">
                    <p class="text-sm font-semibold text-neutral-900">Innings ${i.innings_number} &mdash; ${escapeHtml(i.batting_team)}</p>
                    <p class="mt-1 text-lg font-semibold text-neutral-900">
                        ${i.total_runs}/${i.total_wickets}
                        <span class="text-xs font-normal text-neutral-500">(${escapeHtml(i.overs_display)} overs)</span>
                    </p>
                    <p class="text-xs text-neutral-500">${escapeHtml(i.batting_team)} batting &middot; ${escapeHtml(i.bowling_team)} bowling</p>
                </div>
            `
        )
        .join('');
}

function renderDeliveries(deliveries) {
    if (deliveries.length === 0) {
        return '<p class="py-4 text-center text-xs text-neutral-400">No deliveries recorded yet.</p>';
    }

    return deliveries
        .map((d) => {
            const outcomeClasses = d.is_wicket ? 'bg-red-50 text-red-600' : 'bg-neutral-100 text-neutral-700';

            return `
                <div class="flex items-start gap-3 border-b border-neutral-100 py-2 text-[13px] last:border-b-0">
                    <span class="mt-0.5 w-10 shrink-0 text-xs font-medium text-neutral-500">${escapeHtml(d.ball_label)}</span>
                    <span class="flex h-6 w-9 shrink-0 items-center justify-center rounded-md text-xs font-semibold ${outcomeClasses}">${escapeHtml(d.outcome_label)}</span>
                    <span class="min-w-0 text-neutral-700">${escapeHtml(d.commentary)}</span>
                </div>
            `;
        })
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

        if (resultEl) {
            resultEl.textContent = data.match_result ?? '';
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
