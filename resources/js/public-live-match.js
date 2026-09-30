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

// Mirrors components/public/status-pill.blade.php: variant per status.
const STATUS_PILL_VARIANTS = {
    scheduled: 'scheduled',
    upcoming: 'scheduled',
    live: 'live',
    completed: 'completed',
    toss: 'warn',
    pending: 'warn',
    abandoned: 'danger',
    cancelled: 'danger',
};

function renderStatusBadge(status) {
    const variant = STATUS_PILL_VARIANTS[status] ?? 'neutral';
    const dot = variant === 'live' ? '<span class="live-dot" aria-hidden="true"></span>' : '';

    return `<span class="pub-pill pub-pill-${variant}">${dot}${escapeHtml(status)}</span>`;
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
            const tint = i.status === 'live' ? 'bg-green-50/50' : '';
            const crr =
                i.crr !== undefined && i.crr !== null
                    ? ` &middot; CRR <span class="font-semibold tabular-nums text-slate-700">${formatRate(i.crr)}</span>`
                    : '';

            return `
                <div class="p-4 sm:p-5 ${tint}">
                    <div class="flex items-center justify-between gap-3">
                        <p class="pub-eyebrow">Innings ${escapeHtml(String(i.innings_number))}</p>
                        ${renderStatusBadge(i.status)}
                    </div>
                    <p class="mt-2 truncate text-sm font-semibold text-slate-800">${escapeHtml(i.batting_team)}</p>
                    <p class="mt-1 flex items-baseline gap-2">
                        <span class="score-figure">${escapeHtml(String(i.total_runs))}/${escapeHtml(String(i.total_wickets))}</span>
                        <span class="text-xs text-slate-500">(${escapeHtml(i.overs_display)} overs)</span>
                    </p>
                    <p class="mt-2 text-xs text-slate-500">vs ${escapeHtml(i.bowling_team)}${crr}</p>
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
        <div class="rounded-lg bg-white px-1 py-2 ring-1 ring-inset ring-green-200">
            <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">${label}</dt>
            <dd class="mt-0.5 text-sm font-bold tabular-nums text-slate-900">${escapeHtml(String(value))}</dd>
        </div>
    `;

    return `
        <p class="text-[13px] font-semibold text-green-800">${headline}</p>
        <dl class="mt-3 grid grid-cols-4 gap-2 text-center">
            ${stat('Target', chase.target)}
            ${stat('Need', chase.runs_needed)}
            ${stat('Balls', chase.balls_remaining)}
            ${stat('RRR', formatRate(chase.required_run_rate))}
        </dl>
    `;
}

function outcomeClasses(d) {
    const label = String(d.outcome_label ?? '');

    if (d.is_wicket) {
        return 'ball-badge-wicket';
    }

    if (label === '6') {
        return 'ball-badge-six';
    }

    if (label === '4') {
        return 'ball-badge-four';
    }

    // Anything non-numeric (Wd, Nb, B, Lb...) is an extra.
    if (!/^\d+$/.test(label)) {
        return 'ball-badge-extra';
    }

    return '';
}

function renderDeliveries(deliveries) {
    if (deliveries.length === 0) {
        return '<p class="pub-empty">No deliveries recorded yet.</p>';
    }

    return deliveries
        .map(
            (d) => `
                <div class="flex items-start gap-3 border-b border-line px-4 py-3 text-[13px] last:border-b-0">
                    <span class="ball-badge ${outcomeClasses(d)}">${escapeHtml(d.outcome_label)}</span>
                    <p class="min-w-0 pt-0.5 leading-snug text-slate-700"><span class="mr-1.5 font-semibold tabular-nums text-slate-900">${escapeHtml(d.ball_label)}</span>${escapeHtml(d.commentary)}</p>
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
