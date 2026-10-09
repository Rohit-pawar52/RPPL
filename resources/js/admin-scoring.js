import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * One-click match-day scoring (frozen S02 rules 42/47-53): the quick-tap
 * pad submits directly with an idempotency token, retries automatically
 * on a transient failure, and re-renders the scorer panels from the
 * exact same canonical JSON the score-data endpoint and every other
 * client (polling, realtime, recovery-after-refresh) already share —
 * never a second, independently-computed source of truth.
 *
 * Deliberately reloads the page (rather than re-rendering in place)
 * after any action that changes the scoring "phase" — a wicket/over
 * boundary requiring a New Batter/New Over Bowler selection, or the
 * innings ending — so the server's own already-tested Start Innings/
 * New Batter/New Over Bowler Blade forms render exactly as they do
 * today; this file only owns the fast, common "next ball, same batters
 * and bowler" path, where one-click matters most.
 */
const POLL_INTERVAL_MS = 8000;
const MAX_AUTO_RETRIES = 2;

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';

    return div.innerHTML;
}

function uuid() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    // Fallback for a browser without crypto.randomUUID — still unique
    // enough for a client-generated idempotency token, never used for
    // anything security-sensitive.
    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('scorer-root');

    if (!root) {
        return;
    }

    const scoreDataUrl = root.dataset.scoreDataUrl;
    const storeUrl = root.dataset.storeUrl;
    const undoUrl = root.dataset.undoUrl;
    const correctUrlBase = root.dataset.correctUrlBase;
    const matchId = root.dataset.matchId ? Number(root.dataset.matchId) : null;
    const battingOptions = JSON.parse(root.dataset.battingOptions || '{}');
    const bowlingOptions = JSON.parse(root.dataset.bowlingOptions || '{}');
    const wicketTypes = JSON.parse(root.dataset.wicketTypes || '{}');

    const quickPad = document.getElementById('scorer-quick-pad');
    const savingIndicator = document.getElementById('scorer-saving-indicator');
    const situationalPanel = document.getElementById('scorer-situational-panel');
    const correctionPanel = document.getElementById('scorer-correction-panel');

    let latestState = null;
    let actionInFlight = false;

    // ----- Rendering: mirrors admin/scoring/_*.blade.php partials exactly -----

    function renderChase(chase) {
        const el = document.getElementById('scorer-chase');

        if (!el) return;

        if (!chase) {
            el.hidden = true;
            el.innerHTML = '';

            return;
        }

        el.hidden = false;
        el.innerHTML = `Target ${chase.target} &middot; Need ${chase.runs_needed} from ${chase.balls_remaining} &middot; RRR ${Number(chase.required_run_rate).toFixed(2)}`;
    }

    // KEEP IN SYNC with admin/scoring/_batter-figure, _bowler-figure and _over-strip.
    function renderBatterFigure(player) {
        return player
            ? `<span class="sc-name">${escapeHtml(player.name)}</span><span class="sc-fig">${player.runs}<i>(${player.balls})</i></span>`
            : '<span class="sc-name">—</span><span class="sc-fig">&nbsp;</span>';
    }

    function renderBowlerFigure(player) {
        return player
            ? `<span class="sc-name">${escapeHtml(player.name)}</span><span class="sc-fig">${player.overs_display}-${player.runs_conceded}-${player.wickets}</span>`
            : '<span class="sc-name">—</span><span class="sc-fig">&nbsp;</span>';
    }

    function ballKind(ball) {
        const label = String(ball.label);

        if (ball.is_wicket) return 'wicket';
        if (label === '6') return 'six';
        if (label === '4') return 'four';
        if (label === '0') return 'dot';
        if (!/^\d+$/.test(label)) return 'extra';

        return 'run';
    }

    function renderOverStrip(over) {
        if (!over) {
            return '<span class="text-xs text-white/60">No deliveries yet.</span>';
        }

        return over.balls
            .map((ball) => {
                const kind = ballKind(ball);

                if (ball.is_correctable) {
                    return `<button type="button" class="scorer-over-ball scorer-over-ball-correctable sc-ball sc-ball-correctable sc-ball-${kind}" data-delivery-id="${ball.id}" title="Tap to correct this delivery">${escapeHtml(ball.label)}</button>`;
                }

                return `<span class="sc-ball sc-ball-${kind}">${escapeHtml(ball.label)}</span>`;
            })
            .join('');
    }

    function applyState(state) {
        latestState = state;

        const crrEl = document.getElementById('scorer-crr');
        if (crrEl) crrEl.textContent = `CRR ${Number(state.innings.crr).toFixed(2)}`;

        const scoreEl = document.getElementById('scorer-score');
        if (scoreEl) scoreEl.textContent = `${state.innings.total_runs}/${state.innings.total_wickets}`;

        const oversEl = document.getElementById('scorer-overs');
        if (oversEl) oversEl.textContent = state.innings.overs_display;

        const freeHitEl = document.getElementById('scorer-free-hit');
        if (freeHitEl) freeHitEl.hidden = !state.is_free_hit;

        const undoButton = document.getElementById('scorer-undo-button');
        if (undoButton && !actionInFlight) undoButton.disabled = !state.can_undo;

        renderChase(state.chase);

        const strikerEl = document.getElementById('scorer-striker');
        if (strikerEl) strikerEl.innerHTML = renderBatterFigure(state.striker);

        const nonStrikerEl = document.getElementById('scorer-non-striker');
        if (nonStrikerEl) nonStrikerEl.innerHTML = renderBatterFigure(state.non_striker);

        const bowlerEl = document.getElementById('scorer-bowler');
        if (bowlerEl) bowlerEl.innerHTML = renderBowlerFigure(state.bowler);

        const partnershipEl = document.getElementById('scorer-partnership');
        if (partnershipEl) {
            partnershipEl.innerHTML = `Partnership: <span class="font-semibold text-slate-800">${state.partnership.runs} runs (${state.partnership.balls} balls)</span>`;
        }

        const lastWicketEl = document.getElementById('scorer-last-wicket');
        if (lastWicketEl) {
            lastWicketEl.innerHTML = state.last_wicket
                ? `Last Wicket: <span class="font-semibold text-slate-800">${escapeHtml(state.last_wicket.player)} ${state.last_wicket.runs} (${state.last_wicket.balls}) &mdash; ${state.last_wicket.team_score}, ${state.last_wicket.over_notation} ov</span>`
                : 'Last Wicket: <span class="font-semibold text-slate-800">—</span>';
        }

        const thisOverEl = document.getElementById('scorer-this-over');
        if (thisOverEl) thisOverEl.innerHTML = renderOverStrip(state.this_over);

        const previousOverEl = document.getElementById('scorer-previous-over');
        if (previousOverEl) previousOverEl.innerHTML = renderOverStrip(state.previous_over);
    }

    async function fetchAndApplyState() {
        try {
            const response = await axios.get(scoreDataUrl, { headers: { Accept: 'application/json' } });
            applyState(response.data);
        } catch {
            // Transient — the next poll/realtime signal tries again; the
            // page's server-rendered content stays as the last-known-good
            // display in the meantime.
        }
    }

    // ----- Quick-tap delivery submission (rules 50-53) -----

    function setBusy(busy) {
        actionInFlight = busy;

        if (savingIndicator) savingIndicator.hidden = !busy;

        quickPad?.querySelectorAll('button').forEach((button) => {
            button.disabled = busy;
        });

        // Coming out of "busy" the undo key follows what the server last said (nothing to undo = off).
        if (!busy && latestState) {
            const undoButton = document.getElementById('scorer-undo-button');
            if (undoButton) undoButton.disabled = !latestState.can_undo;
        }
    }

    function closeSituationalPanel() {
        situationalPanel.hidden = true;
        situationalPanel.innerHTML = '';
    }

    function requiresPhaseReload(state) {
        return (
            state.awaiting_setup ||
            state.expected_batting_state.requires_replacement ||
            state.expected_batting_state.awaiting_new_over_bowler ||
            !state.can_record_delivery
        );
    }

    async function submitDelivery(payload) {
        if (actionInFlight) return;

        setBusy(true);
        situationalPanel.hidden = true;
        situationalPanel.innerHTML = '';

        const idempotencyKey = uuid();
        const body = { ...payload, idempotency_key: idempotencyKey };

        let attempt = 0;
        // eslint-disable-next-line no-constant-condition
        while (true) {
            try {
                const response = await axios.post(storeUrl, body, { headers: { Accept: 'application/json' } });

                setBusy(false);

                if (requiresPhaseReload(response.data.state)) {
                    window.location.reload();

                    return;
                }

                applyState(response.data.state);

                return;
            } catch (error) {
                const isNetworkFailure = !error.response;

                if (isNetworkFailure && attempt < MAX_AUTO_RETRIES) {
                    // Rule 52: automatic, bounded retry with the SAME
                    // idempotency token — the server either never saw the
                    // first attempt, or already recorded it and will just
                    // return that same Delivery again; either way this
                    // can never create a duplicate ball.
                    attempt++;
                    await new Promise((resolve) => setTimeout(resolve, 500 * attempt));
                    continue;
                }

                setBusy(false);

                const message = error.response?.data?.message
                    ?? (isNetworkFailure
                        ? 'Could not confirm this ball was recorded — check your connection. Refreshing to show the current score.'
                        : 'This action could not be completed.');

                window.Swal?.fire({ icon: 'error', text: message, toast: true, position: 'top-end', timer: 4000, showConfirmButton: false });

                // Rule 53: never trust local assumptions after an
                // uncertain/failed submission — reconcile with whatever
                // the server actually holds.
                fetchAndApplyState();

                return;
            }
        }
    }

    quickPad?.querySelectorAll('.scorer-quick-run').forEach((button) => {
        button.addEventListener('click', () => submitDelivery({ runs_off_bat: Number(button.dataset.runs) }));
    });

    document.getElementById('scorer-quick-wide')?.addEventListener('click', (event) => {
        if (event.target.closest('[data-extra-runs-for]')) return;

        submitDelivery({ is_wide: true, wide_running_runs: 0 });
    });

    document.getElementById('scorer-quick-noball')?.addEventListener('click', (event) => {
        if (event.target.closest('[data-extra-runs-for]')) return;

        submitDelivery({ is_no_ball: true, runs_off_bat: 0 });
    });

    root.querySelector('[data-extra-runs-for="wide"]')?.addEventListener('click', (event) => {
        event.stopPropagation();
        openExtraRunsPanel('wide');
    });

    root.querySelector('[data-extra-runs-for="no_ball"]')?.addEventListener('click', (event) => {
        event.stopPropagation();
        openExtraRunsPanel('no_ball');
    });

    // One tap on a number sends the ball: the keys of a "how many?" panel.
    function renderCountPanel(title, values, onPick) {
        situationalPanel.hidden = false;
        situationalPanel.innerHTML = `
            <p class="mb-2 text-xs font-semibold text-slate-700">${title}</p>
            <div class="grid gap-2" style="grid-template-columns: repeat(${values.length}, minmax(0, 1fr));">
                ${values.map((value) => `<button type="button" class="sc-pick min-h-12 text-base" data-count="${value}">${value}</button>`).join('')}
            </div>
            <button type="button" id="scorer-panel-cancel" class="btn btn-ghost btn-sm mt-2">Cancel</button>
        `;

        document.getElementById('scorer-panel-cancel').addEventListener('click', closeSituationalPanel);
        situationalPanel.querySelectorAll('[data-count]').forEach((button) => {
            button.addEventListener('click', () => onPick(Number(button.dataset.count)));
        });
    }

    function openExtraRunsPanel(kind) {
        if (kind === 'wide') {
            renderCountPanel('Wide &mdash; runs physically run', [1, 2, 3, 4], (value) => submitDelivery({ is_wide: true, wide_running_runs: value }));

            return;
        }

        renderCountPanel('No ball &mdash; runs off the bat', [0, 1, 2, 3, 4, 5, 6], (value) => submitDelivery({ is_no_ball: true, runs_off_bat: value }));
    }

    document.getElementById('scorer-quick-bye')?.addEventListener('click', () => {
        renderCountPanel('Byes &mdash; how many?', [1, 2, 3, 4, 5], (value) => submitDelivery({ bye_runs: value }));
    });

    document.getElementById('scorer-quick-legbye')?.addEventListener('click', () => {
        renderCountPanel('Leg byes &mdash; how many?', [1, 2, 3, 4, 5], (value) => submitDelivery({ leg_bye_runs: value }));
    });

    // ----- Wicket follow-up (frozen rule 50: situational, not on the primary pad) -----

    document.getElementById('scorer-quick-wicket')?.addEventListener('click', () => {
        if (!latestState) return;

        const striker = latestState.striker;
        const nonStriker = latestState.non_striker;
        const isFreeHit = latestState.is_free_hit;

        const allowedTypes = isFreeHit ? ['run_out', 'obstructing_field'] : Object.keys(wicketTypes);

        const batters = [striker, nonStriker].filter(Boolean);

        const dismissedOptions = batters
            .map((p) => `<option value="${p.id}">${escapeHtml(p.name)}</option>`)
            .join('');

        const typeOptions = allowedTypes.map((type) => `<option value="${type}">${escapeHtml(wicketTypes[type] ?? type)}</option>`).join('');

        const fielderOptions = Object.entries(bowlingOptions)
            .map(([id, name]) => `<option value="${id}">${escapeHtml(name)}</option>`)
            .join('');

        // The two selects the confirm button reads stay in the page (hidden); the big
        // keys below only set them, so one tap picks who is out and how.
        situationalPanel.hidden = false;
        situationalPanel.innerHTML = `
            ${isFreeHit ? '<p class="mb-2 text-[11px] font-semibold text-amber-700">Free Hit — only Run Out or Obstructing the Field is valid.</p>' : ''}
            <p class="mb-1.5 text-xs font-semibold text-slate-700">Who is out?</p>
            <div class="grid grid-cols-2 gap-2" data-pick-for="scorer-wicket-dismissed">
                ${batters.map((p, index) => `<button type="button" class="sc-pick sc-pick-red min-h-12" data-value="${p.id}" aria-pressed="${index === 0 ? 'true' : 'false'}"><span class="truncate">${escapeHtml(p.name)}${index === 0 ? ' *' : ''}</span></button>`).join('')}
            </div>
            <p class="mb-1.5 mt-3 text-xs font-semibold text-slate-700">How?</p>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4" data-pick-for="scorer-wicket-type">
                ${allowedTypes.map((type, index) => `<button type="button" class="sc-pick sc-pick-red" data-value="${type}" aria-pressed="${index === 0 ? 'true' : 'false'}">${escapeHtml(wicketTypes[type] ?? type)}</button>`).join('')}
            </div>
            <select id="scorer-wicket-dismissed" class="hidden" aria-hidden="true" tabindex="-1">${dismissedOptions}</select>
            <select id="scorer-wicket-type" class="hidden" aria-hidden="true" tabindex="-1">${typeOptions}</select>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <label class="text-xs font-medium text-slate-600">Fielder (optional)
                    <select id="scorer-wicket-fielder" class="ops-input mt-1 min-h-11"><option value="">None</option>${fielderOptions}</select>
                </label>
                <label class="text-xs font-medium text-slate-600">Runs completed (if any)
                    <input type="number" inputmode="numeric" min="0" max="11" value="0" id="scorer-wicket-runs" class="ops-input mt-1 min-h-11" />
                </label>
            </div>
            <div class="mt-3 grid grid-cols-[1fr_auto] gap-2">
                <button type="button" id="scorer-wicket-confirm" class="btn btn-danger btn-lg min-h-12">Confirm Wicket</button>
                <button type="button" id="scorer-wicket-cancel" class="btn btn-secondary btn-lg min-h-12">Cancel</button>
            </div>
        `;

        situationalPanel.querySelectorAll('[data-pick-for]').forEach((group) => {
            const select = document.getElementById(group.dataset.pickFor);

            group.querySelectorAll('[data-value]').forEach((button) => {
                button.addEventListener('click', () => {
                    select.value = button.dataset.value;
                    group.querySelectorAll('[data-value]').forEach((other) => other.setAttribute('aria-pressed', other === button ? 'true' : 'false'));
                });
            });
        });

        document.getElementById('scorer-wicket-cancel').addEventListener('click', closeSituationalPanel);

        document.getElementById('scorer-wicket-confirm').addEventListener('click', () => {
            submitDelivery({
                is_wicket: true,
                dismissed_match_player_id: Number(document.getElementById('scorer-wicket-dismissed').value),
                wicket_type: document.getElementById('scorer-wicket-type').value,
                fielder_match_player_id: document.getElementById('scorer-wicket-fielder').value || null,
                runs_off_bat: Number(document.getElementById('scorer-wicket-runs').value || 0),
            });
        });
    });

    // ----- Universal Undo (rule 42) -----

    document.getElementById('scorer-undo-button')?.addEventListener('click', (event) => {
        event.preventDefault();

        window.confirmAction?.({ title: 'Undo the last action?', confirmButtonText: 'Yes, undo' }).then((result) => {
            if (!result.isConfirmed) return;

            axios
                .delete(undoUrl, { headers: { Accept: 'application/json' } })
                .then(() => window.location.reload())
                .catch((error) => {
                    window.Swal?.fire({
                        icon: 'error',
                        text: error.response?.data?.message ?? 'There is nothing to undo right now.',
                        toast: true,
                        position: 'top-end',
                        timer: 4000,
                        showConfirmButton: false,
                    });
                });
        });
    });

    // ----- Quick correction of the latest 3 deliveries (rules 43/44/46) -----

    root.addEventListener('click', (event) => {
        const trigger = event.target.closest('.scorer-over-ball-correctable');

        if (!trigger || !latestState) return;

        const deliveryId = Number(trigger.dataset.deliveryId);
        const delivery = latestState.correctable_deliveries.find((d) => d.id === deliveryId);

        if (delivery) openCorrectionPanel(delivery);
    });

    function openCorrectionPanel(delivery) {
        const raw = delivery.raw;
        const dismissedOptions = [raw.striker_match_player_id, raw.non_striker_match_player_id]
            .map((id) => `<option value="${id}" ${raw.dismissed_match_player_id === id ? 'selected' : ''}>${escapeHtml(battingOptions[id] ?? '—')}</option>`)
            .join('');

        const allowedTypes = raw.is_free_hit ? ['run_out', 'obstructing_field'] : Object.keys(wicketTypes);
        const typeOptions = allowedTypes
            .map((type) => `<option value="${type}" ${raw.wicket_type === type ? 'selected' : ''}>${escapeHtml(wicketTypes[type] ?? type)}</option>`)
            .join('');

        correctionPanel.classList.remove('hidden');
        correctionPanel.innerHTML = `
            <h4 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-amber-800">Correct Delivery ${escapeHtml(delivery.label)}</h4>
            <div class="grid gap-2 sm:grid-cols-3">
                <label class="text-xs font-medium text-slate-700">Runs off bat
                    <input type="number" min="0" max="11" id="scorer-correct-runs" value="${raw.runs_off_bat}" class="ops-input mt-1 min-h-11" ${raw.is_wide ? 'disabled' : ''} />
                </label>
                <label class="flex items-center gap-2 text-xs font-medium text-slate-700">
                    <input type="checkbox" id="scorer-correct-is-wicket" ${raw.is_wicket ? 'checked' : ''} /> Wicket
                </label>
                <div></div>
                <label class="text-xs font-medium text-slate-700">Dismissed
                    <select id="scorer-correct-dismissed" class="ops-input mt-1 min-h-11">${dismissedOptions}</select>
                </label>
                <label class="text-xs font-medium text-slate-700">Wicket type
                    <select id="scorer-correct-type" class="ops-input mt-1 min-h-11">${typeOptions}</select>
                </label>
                <label class="text-xs font-medium text-slate-700">Commentary
                    <input type="text" id="scorer-correct-commentary" value="${escapeHtml(raw.commentary ?? '')}" class="ops-input mt-1 min-h-11" />
                </label>
            </div>
            <label class="mt-2 block text-xs font-medium text-slate-700">Reason (optional)
                <input type="text" id="scorer-correct-reason" class="ops-input mt-1 min-h-11" placeholder="e.g. Miscounted runs" />
            </label>
            <div class="mt-3 flex gap-2">
                <button type="button" id="scorer-correct-confirm" class="btn btn-primary min-h-11">Save Correction</button>
                <button type="button" id="scorer-correct-cancel" class="btn btn-secondary min-h-11">Cancel</button>
            </div>
        `;

        document.getElementById('scorer-correct-cancel').addEventListener('click', () => {
            correctionPanel.classList.add('hidden');
            correctionPanel.innerHTML = '';
        });

        document.getElementById('scorer-correct-confirm').addEventListener('click', () => {
            const isWicket = document.getElementById('scorer-correct-is-wicket').checked;

            const payload = {
                runs_off_bat: Number(document.getElementById('scorer-correct-runs').value || 0),
                is_wicket: isWicket,
                commentary: document.getElementById('scorer-correct-commentary').value || null,
                reason: document.getElementById('scorer-correct-reason').value || null,
            };

            if (isWicket) {
                payload.dismissed_match_player_id = Number(document.getElementById('scorer-correct-dismissed').value);
                payload.wicket_type = document.getElementById('scorer-correct-type').value;
            }

            axios
                .patch(`${correctUrlBase}/${delivery.id}/correct`, payload, { headers: { Accept: 'application/json' } })
                .then(() => window.location.reload())
                .catch((error) => {
                    window.Swal?.fire({
                        icon: 'error',
                        text: error.response?.data?.message ?? 'This correction could not be saved.',
                        toast: true,
                        position: 'top-end',
                        timer: 4000,
                        showConfirmButton: false,
                    });
                });
        });
    }

    // ----- Recovery (rule 53) + polling fallback + realtime -----

    function poll() {
        if (!actionInFlight) fetchAndApplyState();

        setTimeout(poll, POLL_INTERVAL_MS);
    }

    // Read the state once straight away, not only after the first poll: the Wicket key and the
    // corrections work from it, so right after a page load they would otherwise do nothing for a few seconds.
    fetchAndApplyState();
    setTimeout(poll, POLL_INTERVAL_MS);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && !actionInFlight) fetchAndApplyState();
    });

    window.addEventListener('online', () => {
        if (!actionInFlight) fetchAndApplyState();
    });

    initRealtimeUpdates(matchId, () => {
        if (!actionInFlight) fetchAndApplyState();
    });
});

/**
 * Same best-effort WebSocket enhancement as public-live-match.js, reused
 * here rather than imported from it — this file is deliberately isolated
 * from the public bundle (separate Vite entry, separate audience). Falls
 * back to silent no-op if Reverb isn't configured; the polling fallback
 * above keeps working regardless.
 */
function initRealtimeUpdates(matchId, onUpdate) {
    if (!matchId) return;

    const key = import.meta.env.VITE_REVERB_APP_KEY;
    const host = import.meta.env.VITE_REVERB_HOST;
    const port = import.meta.env.VITE_REVERB_PORT;
    const scheme = import.meta.env.VITE_REVERB_SCHEME;

    if (!key || !host) return;

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

        echo.channel(`public-match.${matchId}`).listen('.match.score.updated', (event) => {
            if (event && event.match_id !== undefined && event.match_id !== matchId) return;

            onUpdate();
        });
    } catch {
        // Realtime unavailable for any reason — polling remains the fallback.
    }
}
