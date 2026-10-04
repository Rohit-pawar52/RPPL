import axios from 'axios';

/**
 * The auction console (admin / auctioneer). Everything on screen is drawn
 * from one `state` object the server builds (AuctionStateService) — this
 * file never works out a rule: it shows what the server says each team may
 * do, sends the tap, and redraws from the fresh state the server answers
 * with. A tap carries the player's id and the version the screen was
 * showing, so a tap on an out-of-date screen is refused by the server; a
 * bid also carries a one-off key, so a double tap places one bid.
 *
 * Only the amount box, the search box and the walk-in form are static in the
 * page (they keep typed text); everything else is redrawn.
 */
const POLL_MS = 4000;
const CHIP_KEY = 'rppl.auction.chips';
const DEFAULT_CHIPS = [5000, 10000, 25000, 50000];

const dataEl = document.getElementById('auction-console-data');
const root = document.getElementById('auction-console');

if (dataEl && root) {
    const { state: initial, urls } = JSON.parse(dataEl.textContent);

    let state = initial;
    let busy = false;
    let chips = loadChips();
    const openSquads = new Set();

    const el = {
        toolbar: document.getElementById('ac-toolbar'),
        pool: document.getElementById('ac-pool'),
        notice: document.getElementById('ac-notice'),
        lot: document.getElementById('ac-lot'),
        teams: document.getElementById('ac-teams'),
        results: document.getElementById('ac-results'),
        bids: document.getElementById('ac-bids'),
        sold: document.getElementById('ac-sold'),
        soldSearch: document.getElementById('ac-sold-search'),
        soldCount: document.getElementById('ac-sold-count'),
        chips: document.getElementById('ac-chips'),
        amount: document.getElementById('ac-amount'),
        search: document.getElementById('ac-search'),
        walkIn: document.getElementById('ac-walkin'),
    };

    // ----- Small helpers ----------------------------------------------------

    /** Escapes text for HTML, including inside quoted attributes. */
    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        }[char]));
    }

    /** 600000 -> "6,00,000" (the same lakh grouping the server uses). */
    function pts(value) {
        const number = Math.round(Number(value) || 0);
        const negative = number < 0;
        let digits = String(Math.abs(number));

        if (digits.length > 3) {
            const head = digits.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',');
            digits = `${head},${digits.slice(-3)}`;
        }

        return (negative ? '-' : '') + digits;
    }

    function loadChips() {
        try {
            const saved = JSON.parse(window.localStorage.getItem(CHIP_KEY) || 'null');
            if (Array.isArray(saved) && saved.length && saved.every((n) => Number.isInteger(n) && n > 0)) {
                return saved;
            }
        } catch (e) {
            // No storage: use the defaults.
        }

        return DEFAULT_CHIPS;
    }

    function saveChips(list) {
        chips = list;
        try {
            window.localStorage.setItem(CHIP_KEY, JSON.stringify(list));
        } catch (e) {
            // Not remembered; still works for this visit.
        }
    }

    function uuid() {
        if (window.crypto && window.crypto.randomUUID) {
            return window.crypto.randomUUID();
        }

        return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }

    let noticeTimer = null;

    function notify(message, kind = 'ok') {
        if (!message) {
            return;
        }

        const styles = {
            ok: 'border-green-200 bg-green-50 text-green-800',
            error: 'border-red-200 bg-red-50 text-red-800',
        };

        el.notice.className = `rounded-md border px-3 py-2 text-[13px] ${styles[kind] || styles.ok}`;
        el.notice.textContent = message;

        window.clearTimeout(noticeTimer);
        noticeTimer = window.setTimeout(() => el.notice.classList.add('hidden'), kind === 'error' ? 7000 : 4000);
    }

    function typedAmount() {
        const value = parseInt(el.amount.value, 10);

        return Number.isFinite(value) && value > 0 ? value : null;
    }

    // ----- What a tap on a team would do -----------------------------------

    /**
     * The same checks the server makes, only to label the button; the
     * server still decides.
     */
    function teamAction(team) {
        const lot = state.lot;

        if (!lot) {
            return { kind: 'idle', label: 'Waiting for a player', enabled: false };
        }

        const amount = typedAmount() ?? lot.next_bid;

        if (state.auction.status !== 'live') {
            return { kind: 'paused', label: 'Auction paused', enabled: false };
        }
        if (lot.leading_team && lot.leading_team.id === team.id) {
            return { kind: 'leading', label: 'Leading', enabled: false };
        }
        if (team.full) {
            return { kind: 'full', label: 'Squad full', enabled: false };
        }
        if (amount < lot.next_bid) {
            return { kind: 'low', label: `Needs ${pts(lot.next_bid)}+`, enabled: false };
        }
        if ((amount - state.auction.min_bid) % state.auction.bid_step !== 0) {
            return { kind: 'step', label: `Steps of ${pts(state.auction.bid_step)}`, enabled: false };
        }
        if (amount > team.left) {
            return { kind: 'purse', label: 'Not enough points', enabled: false };
        }
        if (amount > team.max_bid) {
            return { kind: 'reserve', label: `Bid ${pts(amount)} · override`, enabled: true, amount };
        }

        return { kind: 'ok', label: `Bid ${pts(amount)}`, enabled: true, amount };
    }

    // ----- Drawing -----------------------------------------------------------

    function render() {
        renderPoolNotice();
        renderToolbar();
        renderLot();
        renderTeams();
        renderResults();
        renderBids();
        renderSold();
        renderChips();
        root.classList.toggle('opacity-70', busy);
    }

    function statusBadge(status) {
        const styles = {
            live: 'bg-green-50 text-green-700 ring-green-200',
            paused: 'bg-amber-50 text-amber-700 ring-amber-200',
            completed: 'bg-neutral-100 text-neutral-600 ring-neutral-200',
        };

        return `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium capitalize ring-1 ring-inset ${styles[status] || styles.completed}">${esc(status)}</span>`;
    }

    /**
     * Paid players missing from the pool, or waiting players who should no
     * longer be in it — one button puts it right.
     */
    function renderPoolNotice() {
        const { missing, stale } = state.pool_check;
        const parts = [];

        if (missing.length) {
            parts.push(`${missing.length} paid ${missing.length === 1 ? 'player is' : 'players are'} not in the pool: ${missing.slice(0, 5).map(esc).join(', ')}${missing.length > 5 ? '…' : ''}`);
        }
        if (stale.length) {
            parts.push(`${stale.length} waiting ${stale.length === 1 ? 'player is' : 'players are'} no longer eligible (not paid, or already in a team): ${stale.slice(0, 5).map(esc).join(', ')}${stale.length > 5 ? '…' : ''}`);
        }

        el.pool.innerHTML = parts.length
            ? `<div class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-[13px] text-amber-900"><span>${parts.join('. ')}.</span><button type="button" data-action="refresh-pool" class="rounded-md bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-600">Update the pool</button></div>`
            : '';
    }

    function renderToolbar() {
        const { auction, counts } = state;
        const live = auction.status === 'live';
        const paused = auction.status === 'paused';
        const done = auction.status === 'completed';

        const stat = (label, number) => `<span class="text-xs text-slate-500">${esc(label)} <b class="tabular-nums text-slate-800">${number}</b></span>`;

        el.toolbar.innerHTML = `
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                ${statusBadge(auction.status)}
                <span class="text-xs font-medium text-slate-700">Round ${auction.round}</span>
                ${stat('Waiting', counts.pending)}
                ${stat('Hold', counts.hold)}
                ${stat('Sold', counts.sold)}
                ${stat('Unsold', counts.unsold)}
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <label class="inline-flex cursor-pointer items-center gap-1.5 text-xs text-slate-600">
                    <input type="checkbox" data-action="toggle-live" ${auction.show_live_bids ? 'checked' : ''} class="h-4 w-4 rounded border-slate-300 text-green-600 focus:ring-green-500" ${done ? 'disabled' : ''} />
                    Show bids live on the website
                </label>
                ${counts.hold > 0 && !done ? `<button type="button" data-action="next-round" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Start round ${auction.round + 1} (${counts.hold} on hold)</button>` : ''}
                ${live ? '<button type="button" data-action="pause" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Pause</button>' : ''}
                ${paused ? '<button type="button" data-action="resume" class="rounded-md bg-green-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-green-700">Resume</button>' : ''}
                ${!done ? `<a href="${esc(urls.setup)}" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Finish the auction…</a>` : ''}
            </div>`;
    }

    function renderLot() {
        const { lot, auction, counts } = state;

        if (!lot) {
            const done = auction.status === 'completed';
            const waiting = counts.pending;

            el.lot.innerHTML = `
                <div class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-8 text-center">
                    <p class="text-sm font-semibold text-slate-800">${done ? 'The auction is completed' : 'No player on the block'}</p>
                    <p class="mt-1 text-xs text-slate-500">${done
                        ? 'Nothing more can be called.'
                        : waiting > 0
                            ? `${waiting} ${waiting === 1 ? 'player is' : 'players are'} waiting. Call one at random, or find a player with the search box.`
                            : counts.hold > 0
                                ? `Nobody is waiting, but ${counts.hold} ${counts.hold === 1 ? 'player is' : 'players are'} on hold — start the next round to bring ${counts.hold === 1 ? 'them' : 'them'} back.`
                                : 'Nobody is waiting. You can finish the auction.'}</p>
                    ${!done && waiting > 0 ? '<button type="button" data-action="random" class="mt-4 rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Call a random player</button>' : ''}
                </div>`;

            return;
        }

        const tags = [lot.role, lot.batting ? `${lot.batting} bat` : null, lot.bowling ? `${lot.bowling} bowler` : null]
            .filter(Boolean)
            .map((tag) => `<span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">${esc(tag)}</span>`)
            .join('');

        const where = [lot.village, lot.tehsil, lot.district].filter(Boolean).join(', ');
        const stats = lot.stats
            ? `<p class="mt-2 text-xs text-slate-600"><span class="font-medium text-slate-700">Before:</span> ${lot.stats.matches} ${lot.stats.matches === 1 ? 'match' : 'matches'} · ${lot.stats.runs} runs${lot.stats.highest !== null ? ` (best ${lot.stats.highest})` : ''} · ${lot.stats.wickets} ${lot.stats.wickets === 1 ? 'wicket' : 'wickets'}${lot.stats.best_bowling ? ` (best ${esc(lot.stats.best_bowling)})` : ''}</p>`
            : '<p class="mt-2 text-xs text-slate-400">First time in RPPL</p>';

        // The player's photo, or the default picture when there is none (or it fails to load).
        const photo = `<div class="relative h-24 w-24 overflow-hidden rounded-lg bg-slate-100"><img src="${esc(lot.photo || '')}" alt="" data-fallback="user" class="h-full w-full object-cover" /></div>`;

        const hasBid = lot.current_bid !== null;
        const live = auction.status === 'live';

        el.lot.innerHTML = `
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-start gap-4">
                    <div class="shrink-0">${photo}</div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">On the block${lot.round > 1 ? ` · round ${lot.round}` : ''}</p>
                        <h2 class="mt-0.5 break-words text-2xl font-bold tracking-tight text-slate-900">${esc(lot.name)}</h2>
                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">${tags}</div>
                        ${where || lot.age ? `<p class="mt-1.5 text-xs text-slate-500">${esc(where)}${where && lot.age ? ' · ' : ''}${lot.age ? `${lot.age} yrs` : ''}</p>` : ''}
                        ${stats}
                    </div>
                    <div class="min-w-[11rem] rounded-lg ${hasBid ? 'bg-green-50 ring-1 ring-green-200' : 'bg-slate-50 ring-1 ring-slate-200'} px-4 py-3 text-right">
                        <p class="text-[11px] font-semibold uppercase tracking-wide ${hasBid ? 'text-green-700' : 'text-slate-400'}">${hasBid ? 'Current bid' : 'Base price'}</p>
                        <p class="text-3xl font-bold tabular-nums ${hasBid ? 'text-green-800' : 'text-slate-700'}">${pts(hasBid ? lot.current_bid : auction.min_bid)}</p>
                        <p class="text-xs ${hasBid ? 'font-medium text-green-700' : 'text-slate-400'}">${hasBid ? esc(lot.leading_team.name) : 'No bid yet'}</p>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button type="button" data-action="sell" ${hasBid && live ? '' : 'disabled'} class="rounded-md bg-green-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400">
                        ${hasBid ? `SOLD to ${esc(lot.leading_team.name)} · ${pts(lot.current_bid)}` : 'SOLD'}
                    </button>
                    <button type="button" data-action="hold" ${live ? '' : 'disabled'} class="rounded-md border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-800 hover:bg-amber-100 disabled:opacity-50">Hold for later</button>
                    <button type="button" data-action="undo" ${hasBid && live ? '' : 'disabled'} class="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">Undo last bid</button>
                    <button type="button" data-action="release" ${live ? '' : 'disabled'} class="ml-auto rounded-md px-3 py-2.5 text-xs font-medium text-slate-500 hover:bg-slate-100 disabled:opacity-50">Wrong player? Put back</button>
                </div>
            </div>`;
    }

    function renderTeams() {
        const lot = state.lot;
        const tiles = state.teams.map((team) => {
            const action = teamAction(team);
            const usedPct = team.purse > 0 ? Math.min(100, Math.round(((team.purse - team.left) / team.purse) * 100)) : 0;
            const open = openSquads.has(team.id);

            const buttonStyle = {
                ok: 'bg-green-600 text-white hover:bg-green-700',
                reserve: 'bg-amber-500 text-white hover:bg-amber-600',
                leading: 'bg-green-50 text-green-700 ring-1 ring-green-200',
            }[action.kind] || 'bg-slate-100 text-slate-400';

            return `
                <div class="rounded-lg border ${lot && lot.leading_team && lot.leading_team.id === team.id ? 'border-green-400 ring-1 ring-green-300' : 'border-slate-200'} bg-white p-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="truncate text-[13px] font-semibold text-slate-900" title="${esc(team.name)}">${esc(team.name)}</p>
                        <span class="shrink-0 text-[11px] tabular-nums text-slate-500">${team.count}/${state.auction.max_squad}</span>
                    </div>
                    <p class="mt-1 text-xl font-bold tabular-nums text-slate-900">${pts(team.left)} <span class="text-[11px] font-medium text-slate-400">left</span></p>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true"><div class="h-full rounded-full bg-green-500" style="width: ${usedPct}%"></div></div>
                    <p class="mt-1.5 text-[11px] text-slate-500">
                        Max bid <b class="tabular-nums text-slate-700">${team.full ? '—' : pts(team.max_bid)}</b>
                        ${team.still_needed > 0 ? ` · needs ${team.still_needed} more` : ''}
                    </p>
                    <button type="button" data-action="bid" data-team="${team.id}" ${action.enabled ? '' : 'disabled'} class="mt-2 w-full rounded-md px-2 py-2 text-[13px] font-semibold ${buttonStyle} disabled:cursor-not-allowed">${esc(action.label)}</button>
                    <details data-squad="${team.id}" class="mt-2" ${open ? 'open' : ''}>
                        <summary class="cursor-pointer text-[11px] text-slate-500 hover:text-slate-800">Squad (${team.players.length})</summary>
                        <ul class="mt-1 space-y-0.5 text-[11px] text-slate-600">
                            ${team.players.length ? team.players.map((p) => `<li class="flex justify-between gap-2"><span class="truncate">${esc(p.name)}</span><span class="tabular-nums text-slate-500">${p.amount === null ? '—' : pts(p.amount)}</span></li>`).join('') : '<li class="text-slate-400">No players yet</li>'}
                        </ul>
                    </details>
                </div>`;
        });

        el.teams.innerHTML = `<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">${tiles.join('')}</div>`;
    }

    function renderResults() {
        const query = el.search.value.trim().toLowerCase();
        const live = state.auction.status === 'live';
        let list = state.waiting;

        if (query) {
            list = list.filter((row) => row.name.toLowerCase().includes(query) || (row.village || '').toLowerCase().includes(query));
        } else {
            // Nothing typed: the hold players first (they are the ones to bring back).
            list = list.filter((row) => row.status === 'hold');
        }

        const shown = list.slice(0, 8);
        const rows = shown.map((row) => `
            <li class="flex items-center justify-between gap-2 py-1.5">
                <span class="min-w-0">
                    <span class="block truncate text-[13px] font-medium text-slate-800">${esc(row.name)}</span>
                    <span class="block truncate text-[11px] text-slate-400">${esc([row.role, row.village].filter(Boolean).join(' · '))}${row.status === 'hold' ? ' · on hold' : ''}</span>
                </span>
                <button type="button" data-action="call" data-lot="${row.id}" ${live ? '' : 'disabled'} class="shrink-0 rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">Call</button>
            </li>`).join('');

        const note = query
            ? (list.length > shown.length ? `<p class="mt-1 text-[11px] text-slate-400">${list.length - shown.length} more — keep typing to narrow it down.</p>` : '')
            : `<p class="mt-1 text-[11px] text-slate-400">${state.counts.pending} waiting${state.counts.hold ? ` · ${state.counts.hold} on hold (listed above)` : ''}. Type to find anyone.</p>`;

        el.results.innerHTML = `
            ${rows ? `<ul class="divide-y divide-slate-100">${rows}</ul>` : `<p class="text-xs text-slate-400">${query ? 'Nobody matches.' : 'Nobody is on hold.'}</p>`}
            ${note}
            ${state.counts.pending > 0 ? '<button type="button" data-action="random" class="mt-2 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Call a random waiting player</button>' : ''}`;
    }

    function renderBids() {
        const bids = state.lot ? state.lot.bids : [];

        el.bids.innerHTML = `
            <h3 class="text-xs font-semibold text-slate-700">Bids on this player</h3>
            ${bids.length
                ? `<ol class="mt-1.5 space-y-1 text-[12px]">${bids.map((bid, index) => `<li class="flex justify-between gap-2 ${index === 0 ? 'font-semibold text-slate-900' : 'text-slate-500'}"><span class="truncate">${esc(bid.team)}${bid.override ? ' <span class="rounded bg-amber-100 px-1 text-[10px] font-medium text-amber-800">override</span>' : ''}</span><span class="shrink-0 tabular-nums">${pts(bid.amount)} <span class="text-[10px] font-normal text-slate-400">${esc(bid.at)}</span></span></li>`).join('')}</ol>`
                : '<p class="mt-1 text-xs text-slate-400">No bids yet.</p>'}`;
    }

    /**
     * Every sale, newest first, searchable by player, team or village. Any
     * of them can be reopened (back on the block with the last bid) or taken
     * back (out of the team, waiting again) — a player who has already played
     * a match can do neither.
     */
    function renderSold() {
        const live = state.auction.status === 'live';
        const running = live || state.auction.status === 'paused';
        const query = el.soldSearch.value.trim().toLowerCase();
        const all = state.sold;
        const list = query
            ? all.filter((sale) => [sale.name, sale.team, sale.village].some((text) => (text || '').toLowerCase().includes(query)))
            : all;

        const scroll = el.sold.querySelector('[data-sold-list]')?.scrollTop ?? 0;

        const rows = list.map((sale) => {
            let actions;

            if (sale.locked) {
                actions = '<span class="text-[11px] text-slate-400" title="This player has played a match, so the sale cannot be undone.">played a match</span>';
            } else {
                actions = `${sale.orphan ? '' : `<button type="button" data-action="reopen" data-lot="${sale.lot_id}" ${live ? '' : 'disabled'} title="Back on the block with the last bid" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-40">Reopen</button>`}
                    <button type="button" data-action="take-back" data-lot="${sale.lot_id}" ${running ? '' : 'disabled'} title="Out of the team, waiting again" class="rounded-md border border-red-200 bg-white px-2 py-1 text-[11px] font-medium text-red-700 hover:bg-red-50 disabled:opacity-40">Take back</button>`;
            }

            return `
                <li class="flex items-start justify-between gap-2 py-2">
                    <span class="min-w-0">
                        <span class="block truncate text-[13px] font-medium text-slate-800"><span class="mr-1 text-[11px] font-normal tabular-nums text-slate-400">#${sale.number}</span>${esc(sale.name)}</span>
                        <span class="block text-[11px] leading-snug text-slate-500">${esc(sale.team || 'no team')} · ${pts(sale.amount)}${sale.bids ? ` · ${sale.bids} ${sale.bids === 1 ? 'bid' : 'bids'}` : ''}${sale.at ? ` · ${esc(sale.at)}` : ''}</span>
                    </span>
                    <span class="flex shrink-0 gap-1">${actions}</span>
                </li>`;
        }).join('');

        el.soldCount.textContent = `(${all.length})`;
        el.sold.innerHTML = rows
            ? `<ul data-sold-list class="max-h-80 divide-y divide-slate-100 overflow-y-auto">${rows}</ul>`
            : `<p class="text-xs text-slate-400">${query ? 'No sale matches.' : 'Nobody is sold yet.'}</p>`;

        const fresh = el.sold.querySelector('[data-sold-list]');
        if (fresh) {
            fresh.scrollTop = scroll;
        }
    }

    function renderChips() {
        el.chips.innerHTML = `${chips.map((amount) => `<button type="button" data-action="chip" data-amount="${amount}" class="rounded-full border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium tabular-nums text-slate-700 hover:bg-slate-50">${pts(amount)}</button>`).join('')}
            <button type="button" data-action="edit-chips" class="rounded-md px-1.5 py-1 text-[11px] text-slate-400 hover:text-slate-700">edit</button>`;
    }

    // ----- Talking to the server ---------------------------------------------

    let lastState = JSON.stringify(initial);

    /**
     * Takes the server's state. A refresh that changed nothing redraws
     * nothing, so a button is never swapped out from under a tap.
     */
    function apply(newState) {
        if (!newState) {
            return;
        }

        const next = JSON.stringify(newState);
        if (next === lastState) {
            return;
        }

        lastState = next;
        state = newState;
        render();
    }

    /**
     * Sends one action. Returns the response body on success, or the error
     * body ({ ok: false, key, message }) on a refused action, after showing it.
     */
    async function send(name, body = {}) {
        if (busy) {
            return null;
        }

        busy = true;
        root.classList.add('opacity-70');

        try {
            const response = await axios.post(urls[name], body, { headers: { Accept: 'application/json' } });
            apply(response.data.state);
            notify(response.data.message);

            return response.data;
        } catch (error) {
            const data = error.response && error.response.data;

            if (data && data.state) {
                apply(data.state);
            }

            // The "keep enough for a minimum squad" limit is the one refusal
            // the console offers to override; the caller decides.
            if (data && data.key === 'reserve') {
                return data;
            }

            notify((data && data.message) || 'Something went wrong. Check your connection and try again.', 'error');

            return data || { ok: false };
        } finally {
            busy = false;
            root.classList.remove('opacity-70');
        }
    }

    async function confirmOverride(message) {
        if (window.Swal) {
            const result = await window.Swal.fire({
                title: 'Over the limit',
                text: `${message} Allow it anyway?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Override',
            });

            return result.isConfirmed;
        }

        return window.confirm(`${message}\n\nAllow it anyway?`);
    }

    async function bid(teamId) {
        const lot = state.lot;
        if (!lot) {
            return;
        }

        const body = {
            lot_id: lot.id,
            version: lot.version,
            team_id: teamId,
            amount: typedAmount(),
            key: uuid(),
            override: false,
        };

        let result = await send('bid', body);

        if (result && result.ok === false && result.key === 'reserve' && (await confirmOverride(result.message))) {
            // The state may have moved while the question was open.
            if (!state.lot || state.lot.id !== lot.id) {
                return;
            }

            result = await send('bid', { ...body, version: state.lot.version, key: uuid(), override: true });
        }

        if (result && result.ok) {
            el.amount.value = '';
            render();
        }
    }

    async function lotAction(name, extra = {}) {
        const lot = state.lot;
        if (!lot) {
            return;
        }

        const before = lot;
        const result = await send(name, { lot_id: lot.id, version: lot.version, ...extra });

        if (result && result.ok && name === 'sell' && before.leading_team) {
            notify(`${before.name} sold to ${before.leading_team.name} for ${pts(before.current_bid)} points.`);
        }
    }

    async function poll() {
        if (busy || document.visibilityState === 'hidden') {
            return;
        }

        try {
            const response = await axios.get(urls.state, { headers: { Accept: 'application/json' } });
            if (!busy) {
                apply(response.data.state);
            }
        } catch (error) {
            // A missed refresh is harmless; the next one catches up.
        }
    }

    // ----- Events -------------------------------------------------------------

    root.addEventListener('click', async (event) => {
        const target = event.target.closest('[data-action]');
        if (!target || target.disabled) {
            return;
        }

        const action = target.dataset.action;

        switch (action) {
            case 'random':
                await send('random');
                break;
            case 'call':
                await send('call', { lot_id: Number(target.dataset.lot) });
                el.search.value = '';
                renderResults();
                break;
            case 'bid':
                await bid(Number(target.dataset.team));
                break;
            case 'sell':
            case 'hold':
            case 'undo':
                await lotAction(action);
                break;
            case 'release':
                if (window.confirm('Put this player back among the waiting players? Any bids on them are dropped.')) {
                    await lotAction('release');
                }
                break;
            case 'reopen': {
                const sale = state.sold.find((row) => row.lot_id === Number(target.dataset.lot));
                const text = sale
                    ? `Reopen the sale of ${sale.name}? The player leaves ${sale.team || 'the team'} and goes back on the block with the last bid standing — sell again to keep the sale, or change it first.`
                    : 'Reopen this sale? The player leaves the team and goes back on the block.';
                if (window.confirm(text)) {
                    await send('reopen', { lot_id: Number(target.dataset.lot) });
                }
                break;
            }
            case 'take-back': {
                const sale = state.sold.find((row) => row.lot_id === Number(target.dataset.lot));
                const text = sale
                    ? `Take ${sale.name} back from ${sale.team || 'the team'}? ${pts(sale.amount)} points return to the team's purse, the bids are dropped and the player waits with the others.`
                    : 'Take this player back? The points return to the team and the player waits again.';
                if (window.confirm(text)) {
                    await send('take-back', { lot_id: Number(target.dataset.lot) });
                }
                break;
            }
            case 'next-round':
                if (window.confirm(`Start round ${state.auction.round + 1}? The ${state.counts.hold} players on hold come back to the waiting players.`)) {
                    await send('next-round');
                }
                break;
            case 'pause':
            case 'resume':
                await send(action);
                break;
            case 'refresh-pool':
                await send('pool');
                break;
            case 'chip':
                el.amount.value = target.dataset.amount;
                render();
                break;
            case 'clear-amount':
                el.amount.value = '';
                render();
                break;
            case 'edit-chips': {
                const answer = window.prompt('Quick amounts, separated by commas:', chips.join(', '));
                if (answer !== null) {
                    const list = answer
                        .split(',')
                        .map((part) => parseInt(part.trim(), 10))
                        .filter((n) => Number.isInteger(n) && n > 0)
                        .slice(0, 8);
                    saveChips(list.length ? list : DEFAULT_CHIPS);
                    renderChips();
                }
                break;
            }
            default:
                break;
        }
    });

    root.addEventListener('change', async (event) => {
        if (event.target.matches('[data-action="toggle-live"]')) {
            const show = event.target.checked;
            const result = await send('live-bids', { show });
            if (!result || !result.ok) {
                render();
            }
        }
    });

    // Remember which squads are open, so a redraw does not close them.
    root.addEventListener('toggle', (event) => {
        const squad = event.target.closest && event.target.closest('[data-squad]');
        if (squad) {
            const id = Number(squad.dataset.squad);
            squad.open ? openSquads.add(id) : openSquads.delete(id);
        }
    }, true);

    el.amount.addEventListener('input', renderTeams);
    el.search.addEventListener('input', renderResults);
    el.soldSearch.addEventListener('input', renderSold);

    el.walkIn.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = new FormData(el.walkIn);
        const result = await send('walk-in', { name: form.get('name'), phone: form.get('phone') });
        if (result && result.ok) {
            el.walkIn.reset();
        }
    });

    render();
    window.setInterval(poll, POLL_MS);
}
