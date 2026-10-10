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
 * server render (the score header public/matches/_header + _score-team,
 * _live-chase, _live-over, _live-delivery-row and components/public/
 * status-pill). They only format the /live-data payload already fetched —
 * never derive score data — and must be kept in sync with those partials so
 * a polled update never shows less (or different) information than a fresh
 * page load.
 *
 * Wording comes from the page itself: live.blade.php puts every sentence
 * (already translated, with :placeholders) into data-i18n on the root, so a
 * Hindi visitor keeps getting Hindi after an update.
 */
let I18N = {};

// ":runs from :balls" -> filled in, in a single pass.
function fill(template, values) {
    return String(template ?? '').replace(/:(\w+)/g, (match, key) => (key in values ? String(values[key]) : match));
}

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
    const label = (I18N.status && I18N.status[status]) || status;

    return `<span class="pub-pill pub-pill-${variant}">${dot}${escapeHtml(label)}</span>`;
}

function formatRate(value) {
    return Number(value ?? 0).toFixed(2);
}

function countLabel(count, one, many) {
    return fill(count === 1 ? one : many, { count });
}

// Each team of the score header: fill its number slots from that team's innings.
function applyInnings(innings) {
    document.querySelectorAll('[data-live-team]').forEach((teamEl) => {
        const row = innings.find((i) => i.batting_team === teamEl.dataset.liveTeam);

        if (!row) {
            return;
        }

        const batting = row.status === 'live';
        const slot = (name) => teamEl.querySelector(`[data-slot="${name}"]`);

        teamEl.querySelector('.mx-team-score')?.classList.remove('hidden');
        slot('score').textContent = `${row.total_runs}/${row.total_wickets}`;
        slot('overs').textContent = fill(I18N.overs, { overs: row.overs_display });

        const crrEl = slot('crr');
        const hasCrr = batting && row.crr !== undefined && row.crr !== null;
        crrEl.textContent = hasCrr ? fill(I18N.crr, { rate: formatRate(row.crr) }) : '';
        crrEl.classList.toggle('hidden', !hasCrr);

        slot('batting').classList.toggle('hidden', !batting);
        slot('yet')?.classList.add('hidden');

        if (batting) {
            teamEl.setAttribute('data-batting', '1');
        } else {
            teamEl.removeAttribute('data-batting');
        }
    });
}

function renderChase(chase, innings) {
    if (!chase) {
        return '';
    }

    const second = innings.find((i) => Number(i.innings_number) === 2);
    const team = second?.batting_team ?? I18N.chasingSide;
    const headline =
        chase.runs_needed > 0
            ? fill(I18N.need, {
                  team,
                  runs: countLabel(chase.runs_needed, I18N.runOne, I18N.runMany),
                  balls: countLabel(chase.balls_remaining, I18N.ballOne, I18N.ballMany),
              })
            : fill(I18N.reached, { team });

    const scored = Math.max(0, chase.target - chase.runs_needed);
    const percent = chase.target > 0 ? Math.min(100, Math.round((scored / chase.target) * 100)) : 0;

    const stat = (label, value) => `
        <div>
            <dt>${escapeHtml(label)}</dt>
            <dd>${escapeHtml(String(value))}</dd>
        </div>
    `;

    return `
        <div class="mx-chase">
            <p class="mx-chase-headline">${escapeHtml(headline)}</p>
            <div class="mx-chase-bar" aria-hidden="true"><span style="width: ${percent}%"></span></div>
            <dl class="mx-chase-grid">
                ${stat(I18N.target, chase.target)}
                ${stat(I18N.needLabel, chase.runs_needed)}
                ${stat(I18N.ballsLabel, chase.balls_remaining)}
                ${stat(I18N.rrr, formatRate(chase.required_run_rate))}
            </dl>
        </div>
    `;
}

// Same ladder as the Blade partials: wicket, six, four, any other extra, a plain run.
function outcomeKind(d) {
    const label = String(d.outcome_label ?? '');

    if (d.is_wicket) {
        return 'wicket';
    }

    if (label === '6') {
        return 'six';
    }

    if (label === '4') {
        return 'four';
    }

    // Anything non-numeric (Wd, Nb, B, Lb...) is an extra.
    if (!/^\d+$/.test(label)) {
        return 'extra';
    }

    return 'run';
}

const overOf = (d) => String(d.ball_label ?? '').split('.')[0];

// "This over": the balls of the over in progress, the last ball and the last wicket.
function renderThisOver(deliveries) {
    if (deliveries.length === 0) {
        return `<p class="pub-empty">${escapeHtml(I18N.noDeliveries)}</p>`;
    }

    const latest = deliveries[0];
    const overKey = overOf(latest);
    const thisOver = [];

    for (const d of deliveries) {
        if (overOf(d) !== overKey) {
            break;
        }

        thisOver.push(d);
    }

    thisOver.reverse();

    const balls = thisOver
        .map((d) => `<span class="mx-ball mx-ball-lg mx-ball-${outcomeKind(d)}">${escapeHtml(d.outcome_label)}</span>`)
        .join('');

    const lastBall = fill(I18N.bowlerToStriker, { bowler: latest.bowler, striker: latest.striker });

    return `
        <div class="mx-over-row">
            <p class="mx-over-label">${escapeHtml(I18N.thisOver)} <span>${escapeHtml(fill(I18N.overN, { n: Number(overKey) + 1 }))}</span></p>
            <div class="mx-over-balls">${balls}</div>
            <p class="mx-over-last"><b>${escapeHtml(I18N.lastBall)}</b> ${escapeHtml(lastBall)}</p>
        </div>
    `;
}

// Who is batting and bowling: mirrors public/matches/_live-board.blade.php.
function renderBoard(board) {
    if (!board) {
        return '';
    }

    const num = (value) => escapeHtml(String(value ?? 0));
    const rate = (value) => escapeHtml(formatRate(value));
    const star = (on, title = '') => (on ? `<span class="mx-lb-star"${title ? ` title="${escapeHtml(title)}"` : ''}>*</span>` : '');
    const empty = (cols) => `<tr><td colspan="${cols}" class="mx-lb-empty">&mdash;</td></tr>`;

    const batters = board.batters.length
        ? board.batters
              .map(
                  (b) => `<tr class="${b.on_strike ? 'is-strike' : ''}"><td class="mx-lb-name">${escapeHtml(b.name)}${star(b.on_strike, I18N.onStrike)}</td><td class="mx-lb-strong">${num(b.runs)}</td><td>${num(b.balls)}</td><td>${num(b.fours)}</td><td>${num(b.sixes)}</td><td>${rate(b.strike_rate)}</td></tr>`,
              )
              .join('')
        : empty(6);

    const bowlers = board.bowlers.length
        ? board.bowlers
              .map(
                  (b) => `<tr class="${b.current ? 'is-strike' : ''}"><td class="mx-lb-name">${escapeHtml(b.name)}${star(b.current)}</td><td>${escapeHtml(String(b.overs))}</td><td>${num(b.runs)}</td><td class="mx-lb-strong">${num(b.wickets)}</td><td>${rate(b.economy)}</td></tr>`,
              )
              .join('')
        : empty(5);

    const wicket = board.last_wicket
        ? `<div><dt>${escapeHtml(I18N.lastWkt)}</dt><dd>${escapeHtml(board.last_wicket.player)} ${escapeHtml(
              fill(I18N.lastWktAt, {
                  runs: board.last_wicket.runs ?? 0,
                  balls: board.last_wicket.balls ?? 0,
                  score: `${board.last_wicket.team_score}/${board.last_wicket.wickets}`,
                  over: board.last_wicket.over,
              }),
          )}</dd></div>`
        : '';

    return `
        <table class="mx-lb">
            <thead><tr><th>${escapeHtml(I18N.batter)}</th><th>${escapeHtml(I18N.colRuns)}</th><th>${escapeHtml(I18N.colBalls)}</th><th>${escapeHtml(I18N.colFours)}</th><th>${escapeHtml(I18N.colSixes)}</th><th>${escapeHtml(I18N.colSr)}</th></tr></thead>
            <tbody>${batters}</tbody>
        </table>
        <table class="mx-lb">
            <thead><tr><th>${escapeHtml(I18N.bowler)}</th><th>${escapeHtml(I18N.colOvers)}</th><th>${escapeHtml(I18N.colConceded)}</th><th>${escapeHtml(I18N.colWickets)}</th><th>${escapeHtml(I18N.colEco)}</th></tr></thead>
            <tbody>${bowlers}</tbody>
        </table>
        <dl class="mx-lb-facts">
            <div><dt>${escapeHtml(I18N.partnership)}</dt><dd>${num(board.partnership.runs)} (${num(board.partnership.balls)})</dd></div>
            ${wicket}
        </dl>
    `;
}

function renderDeliveries(deliveries) {
    if (deliveries.length === 0) {
        return `<p class="pub-empty">${escapeHtml(I18N.noDeliveries)}</p>`;
    }

    return deliveries
        .map((d) => {
            const kind = outcomeKind(d);

            return `
                <div class="mx-feed-row" data-kind="${kind}">
                    <span class="mx-ball mx-ball-${kind}">${escapeHtml(d.outcome_label)}</span>
                    <p class="min-w-0 pt-0.5 leading-snug text-slate-700"><span class="mr-1.5 font-semibold tabular-nums text-slate-900">${escapeHtml(d.ball_label)}</span>${escapeHtml(d.commentary)}</p>
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

    try {
        I18N = JSON.parse(root.dataset.i18n || '{}');
    } catch {
        I18N = {};
    }

    // The score header sits above the root; everything is found by id / hook.
    const heroEl = document.getElementById('live-score-card') ?? document.querySelector('.mx-hero');
    const thisOverEl = document.getElementById('live-this-over');
    const deliveriesEl = document.getElementById('live-deliveries');
    const resultEl = document.getElementById('live-match-result');
    const chaseEl = document.getElementById('live-chase');
    const statusBadgeEl = document.getElementById('live-status-badge');
    const boardEl = document.getElementById('live-board');

    // Reassigned once initRealtimeUpdates() runs, below — declared here
    // so applyUpdate() can call whatever it currently is by reference.
    let stopRealtime = () => {};

    function applyUpdate(data) {
        applyInnings(data.innings);

        if (thisOverEl) {
            thisOverEl.innerHTML = renderThisOver(data.recent_deliveries);
        }

        if (deliveriesEl) {
            deliveriesEl.innerHTML = renderDeliveries(data.recent_deliveries);
        }

        if (boardEl) {
            const boardHtml = renderBoard(data.board ?? null);
            boardEl.innerHTML = boardHtml;
            boardEl.classList.toggle('hidden', boardHtml === '');
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

        if (heroEl && data.match_status) {
            heroEl.classList.toggle('mx-hero-live', data.match_status === 'live');
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
