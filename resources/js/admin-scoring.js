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

    function renderBatterFigure(player) {
        return player ? `${escapeHtml(player.name)} ${player.runs} (${player.balls})` : '—';
    }

    function renderBowlerFigure(player) {
        return player ? `${escapeHtml(player.name)} ${player.overs_display}-${player.runs_conceded}-${player.wickets}` : '—';
    }

    function renderOverStrip(over) {
        if (!over) {
            return '<span class="text-xs text-neutral-400">No deliveries yet.</span>';
        }

        return over.balls
            .map((ball) => {
                const classes = ball.is_wicket ? 'bg-red-50 text-red-600' : 'bg-neutral-100 text-neutral-700';

                if (ball.is_correctable) {
                    return `<button type="button" class="scorer-over-ball scorer-over-ball-correctable rounded px-1.5 py-0.5 text-[11px] font-semibold ${classes} ring-1 ring-inset ring-blue-300 hover:ring-blue-500" data-delivery-id="${ball.id}" title="Click to correct this delivery">${escapeHtml(ball.label)}</button>`;
                }

                return `<span class="rounded px-1.5 py-0.5 text-[11px] font-semibold ${classes}">${escapeHtml(ball.label)}</span>`;
            })
            .join('');
    }

    function applyState(state) {
        latestState = state;

        const crrEl = document.getElementById('scorer-crr');
        if (crrEl) crrEl.textContent = `CRR ${Number(state.innings.crr).toFixed(2)}`;

        renderChase(state.chase);

        const strikerEl = document.getElementById('scorer-striker');
        if (strikerEl) strikerEl.innerHTML = renderBatterFigure(state.striker);

        const nonStrikerEl = document.getElementById('scorer-non-striker');
        if (nonStrikerEl) nonStrikerEl.innerHTML = renderBatterFigure(state.non_striker);

        const bowlerEl = document.getElementById('scorer-bowler');
        if (bowlerEl) bowlerEl.innerHTML = renderBowlerFigure(state.bowler);

        const partnershipEl = document.getElementById('scorer-partnership');
        if (partnershipEl) {
            partnershipEl.innerHTML = `Partnership: <span class="font-medium text-neutral-800">${state.partnership.runs} runs (${state.partnership.balls} balls)</span>`;
        }

        const lastWicketEl = document.getElementById('scorer-last-wicket');
        if (lastWicketEl) {
            lastWicketEl.innerHTML = state.last_wicket
                ? `Last Wicket: <span class="font-medium text-neutral-800">${escapeHtml(state.last_wicket.player)} ${state.last_wicket.runs} (${state.last_wicket.balls}) &mdash; ${state.last_wicket.team_score}, ${state.last_wicket.over_notation} ov</span>`
                : 'Last Wicket: <span class="font-medium text-neutral-800">—</span>';
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

    function openExtraRunsPanel(kind) {
        const label = kind === 'wide' ? 'Runs physically run on the wide' : 'Bat runs off this no ball';
        const field = kind === 'wide' ? 'wide_running_runs' : 'runs_off_bat';

        situationalPanel.hidden = false;
        situationalPanel.innerHTML = `
            <label class="mb-2 block text-xs font-medium text-neutral-700">${label}</label>
            <input type="number" min="0" max="6" value="0" id="scorer-extra-runs-input" class="mb-2 w-24 rounded-md border border-neutral-300 px-2 py-1 text-[13px]" />
            <div class="flex gap-2">
                <button type="button" id="scorer-extra-runs-confirm" class="rounded-md theme-button px-3 py-1.5 text-[13px] font-medium">Confirm</button>
                <button type="button" id="scorer-extra-runs-cancel" class="rounded-md border border-neutral-200 px-3 py-1.5 text-[13px] font-medium text-neutral-600">Cancel</button>
            </div>
        `;

        document.getElementById('scorer-extra-runs-cancel').addEventListener('click', () => {
            situationalPanel.hidden = true;
            situationalPanel.innerHTML = '';
        });

        document.getElementById('scorer-extra-runs-confirm').addEventListener('click', () => {
            const value = Number(document.getElementById('scorer-extra-runs-input').value || 0);
            const payload = kind === 'wide' ? { is_wide: true, [field]: value } : { is_no_ball: true, [field]: value };
            submitDelivery(payload);
        });
    }

    // ----- Wicket follow-up (frozen rule 50: situational, not on the primary pad) -----

    document.getElementById('scorer-quick-wicket')?.addEventListener('click', () => {
        if (!latestState) return;

        const striker = latestState.striker;
        const nonStriker = latestState.non_striker;
        const isFreeHit = latestState.is_free_hit;

        const allowedTypes = isFreeHit ? ['run_out', 'obstructing_field'] : Object.keys(wicketTypes);

        const dismissedOptions = [striker, nonStriker]
            .filter(Boolean)
            .map((p) => `<option value="${p.id}">${escapeHtml(p.name)}</option>`)
            .join('');

        const typeOptions = allowedTypes.map((type) => `<option value="${type}">${escapeHtml(wicketTypes[type] ?? type)}</option>`).join('');

        const fielderOptions = Object.entries(bowlingOptions)
            .map(([id, name]) => `<option value="${id}">${escapeHtml(name)}</option>`)
            .join('');

        situationalPanel.hidden = false;
        situationalPanel.innerHTML = `
            ${isFreeHit ? '<p class="mb-2 text-[11px] font-semibold text-amber-700">Free Hit — only Run Out or Obstructing the Field is valid.</p>' : ''}
            <div class="grid gap-2 sm:grid-cols-2">
                <label class="text-xs font-medium text-neutral-700">Dismissed
                    <select id="scorer-wicket-dismissed" class="mt-1 w-full rounded-md border border-neutral-300 px-2 py-1.5 text-[13px]">${dismissedOptions}</select>
                </label>
                <label class="text-xs font-medium text-neutral-700">Type
                    <select id="scorer-wicket-type" class="mt-1 w-full rounded-md border border-neutral-300 px-2 py-1.5 text-[13px]">${typeOptions}</select>
                </label>
                <label class="text-xs font-medium text-neutral-700">Fielder (optional)
                    <select id="scorer-wicket-fielder" class="mt-1 w-full rounded-md border border-neutral-300 px-2 py-1.5 text-[13px]"><option value="">None</option>${fielderOptions}</select>
                </label>
                <label class="text-xs font-medium text-neutral-700">Runs completed (if any)
                    <input type="number" min="0" max="11" value="0" id="scorer-wicket-runs" class="mt-1 w-full rounded-md border border-neutral-300 px-2 py-1.5 text-[13px]" />
                </label>
            </div>
            <div class="mt-3 flex gap-2">
                <button type="button" id="scorer-wicket-confirm" class="rounded-md theme-button px-3 py-1.5 text-[13px] font-medium">Confirm Wicket</button>
                <button type="button" id="scorer-wicket-cancel" class="rounded-md border border-neutral-200 px-3 py-1.5 text-[13px] font-medium text-neutral-600">Cancel</button>
            </div>
        `;

        document.getElementById('scorer-wicket-cancel').addEventListener('click', () => {
            situationalPanel.hidden = true;
            situationalPanel.innerHTML = '';
        });

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
            <h4 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-amber-700">Correct Delivery ${escapeHtml(delivery.label)}</h4>
            <div class="grid gap-2 sm:grid-cols-3">
                <label class="text-xs font-medium text-neutral-700">Runs off bat
                    <input type="number" min="0" max="11" id="scorer-correct-runs" value="${raw.runs_off_bat}" class="mt-1 w-full rounded-md border border-neutral-300 px-2 py-1.5 text-[13px]" ${raw.is_wide ? 'disabled' : ''} />
                </label>
                <label class="flex items-center gap-2 text-xs font-medium text-neutral-700">
                    <input type="checkbox" id="scorer-correct-is-wicket" ${raw.is_wicket ? 'checked' : ''} /> Wicket
                </label>
                <div></div>
                <label class="text-xs font-medium text-neutral-700">Dismissed
                    <select id="scorer-correct-dismissed" class="mt-1 w-full rounded-md border border-neutral-300 px-2 py-1.5 text-[13px]">${dismissedOptions}</select>
                </label>
                <label class="text-xs font-medium text-neutral-700">Wicket type
                    <select id="scorer-correct-type" class="mt-1 w-full rounded-md border border-neutral-300 px-2 py-1.5 text-[13px]">${typeOptions}</select>
                </label>
                <label class="text-xs font-medium text-neutral-700">Commentary
                    <input type="text" id="scorer-correct-commentary" value="${escapeHtml(raw.commentary ?? '')}" class="mt-1 w-full rounded-md border border-neutral-300 px-2 py-1.5 text-[13px]" />
                </label>
            </div>
            <label class="mt-2 block text-xs font-medium text-neutral-700">Reason (optional)
                <input type="text" id="scorer-correct-reason" class="mt-1 w-full rounded-md border border-neutral-300 px-2 py-1.5 text-[13px]" placeholder="e.g. Miscounted runs" />
            </label>
            <div class="mt-3 flex gap-2">
                <button type="button" id="scorer-correct-confirm" class="rounded-md theme-button px-3 py-1.5 text-[13px] font-medium">Save Correction</button>
                <button type="button" id="scorer-correct-cancel" class="rounded-md border border-neutral-200 px-3 py-1.5 text-[13px] font-medium text-neutral-600">Cancel</button>
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
