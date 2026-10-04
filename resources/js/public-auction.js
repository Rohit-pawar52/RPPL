import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * The public live auction page. It draws everything from one `state` object
 * the server builds (AuctionStateService::public()) and swaps in a fresh one
 * from the data endpoint — it never works anything out itself, and the state
 * holds nothing private (and, when the auction hides live bids, no bid at
 * all). All words come from the server in the visitor's language. With
 * data-big="1" it is the dark projector layout.
 *
 * Freshness: when Reverb is running, a "something changed" signal on the
 * public-auction channel makes the page fetch the new picture at once (the
 * signal carries no data). Polling is always there as the safety net — every
 * few seconds if the signal is not available, and only now and then while it
 * is connected.
 */
const REALTIME_POLL_MS = 20000;

const dataEl = document.getElementById('public-auction-data');
const root = document.getElementById('public-auction');

if (dataEl && root) {
    const { state: initial, texts: t, dataUrl, pollSeconds, big } = JSON.parse(dataEl.textContent);

    let state = initial;
    let lastJson = JSON.stringify(initial);
    let offline = false;
    const openSquads = new Set();

    // ----- Helpers ------------------------------------------------------------

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
        let digits = String(Math.abs(number));

        if (digits.length > 3) {
            const head = digits.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',');
            digits = `${head},${digits.slice(-3)}`;
        }

        return (number < 0 ? '-' : '') + digits;
    }

    /** Fills :placeholders in a server-supplied sentence. */
    function tr(key, params = {}) {
        return Object.entries(params).reduce(
            (text, [name, value]) => text.split(`:${name}`).join(value),
            t[key] ?? key,
        );
    }

    function initials(name) {
        const letters = String(name || '')
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => Array.from(part)[0].toUpperCase())
            .join('');

        return letters || '?';
    }

    /** Light page vs. dark projector: one class set each. */
    const k = big
        ? {
            panel: 'rounded-2xl bg-slate-900 ring-1 ring-white/10',
            title: 'text-white',
            muted: 'text-slate-400',
            soft: 'text-slate-300',
            tag: 'rounded-full bg-white/10 px-3 py-1 text-slate-200',
            tagSize: 'text-base',
            rule: 'divide-white/10',
            bar: 'bg-white/10',
            bid: 'bg-emerald-500/15 ring-1 ring-emerald-400/40 text-emerald-300',
            bidNumber: 'text-emerald-300',
            plain: 'bg-white/5 ring-1 ring-white/10 text-slate-300',
            sold: 'bg-emerald-600 text-white',
        }
        : {
            panel: 'pub-card',
            title: 'text-slate-900',
            muted: 'text-slate-500',
            soft: 'text-slate-600',
            tag: 'rounded-full bg-slate-100 px-2.5 py-0.5 text-slate-600',
            tagSize: 'text-[11px]',
            rule: 'divide-line',
            bar: 'bg-slate-100',
            bid: 'bg-green-50 ring-1 ring-green-200 text-green-700',
            bidNumber: 'text-green-800',
            plain: 'bg-slate-50 ring-1 ring-slate-200 text-slate-600',
            sold: 'bg-green-600 text-white',
        };

    // ----- Drawing --------------------------------------------------------------

    function header() {
        const { auction, counts } = state;
        const paused = auction.status === 'paused';
        const left = counts.waiting + counts.hold;

        const status = paused
            ? `<span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 ${big ? 'text-base' : 'text-[11px]'} font-semibold text-amber-800">${esc(t.paused)}</span>`
            : `<span class="inline-flex items-center gap-1.5 rounded-full bg-red-600 px-2.5 py-0.5 ${big ? 'text-base' : 'text-[11px]'} font-bold tracking-wide text-white"><span class="live-dot" aria-hidden="true"></span>${esc(t.live)}</span>`;

        const toCome = left === 1 ? t.to_come_one : tr('to_come', { count: left });

        return `
            <div class="mb-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        ${status}
                        <h1 class="${big ? 'text-4xl' : 'text-xl'} font-bold tracking-tight ${k.title}">${esc(auction.edition)} · ${esc(t.title)}</h1>
                    </div>
                    <p class="mt-1 ${big ? 'text-lg' : 'text-xs'} ${k.muted}">${esc(tr('round', { number: auction.round }))}${left > 0 ? ` · ${esc(toCome)}` : ''}${offline ? ` · <span class="text-amber-500">${esc(t.connection_lost)}</span>` : ''}</p>
                </div>
            </div>`;
    }

    function tags(lot) {
        const list = [
            lot.role ? tr('role_line', { role: lot.role }) : null,
            lot.batting ? tr('batting_hand', { hand: lot.batting }) : null,
            lot.bowling ? tr('bowling_arm', { arm: lot.bowling }) : null,
        ].filter(Boolean);

        return list.map((tag) => `<span class="${k.tag} ${k.tagSize} font-medium">${esc(tag)}</span>`).join('');
    }

    function pastLine(lot) {
        if (!lot.stats) {
            return `<p class="mt-2 ${big ? 'text-lg' : 'text-xs'} ${k.muted}">${esc(t.first_time)}</p>`;
        }

        const s = lot.stats;
        const line = tr('before', { matches: s.matches, runs: s.runs, wickets: s.wickets });
        const best = s.highest !== null ? ` (${tr('best_score', { value: s.highest })})` : '';

        return `<p class="mt-2 ${big ? 'text-lg' : 'text-xs'} ${k.soft}">${esc(line)}${esc(best)}</p>`;
    }

    function stage() {
        const { lot, auction, last_sale: sale } = state;
        const paused = auction.status === 'paused';

        const justSold = sale && lot
            ? `<div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg ${k.sold} px-4 py-2 ${big ? 'text-2xl' : 'text-sm'} font-semibold"><span class="rounded bg-white/20 px-2 py-0.5 text-xs font-bold tracking-wide">${esc(t.sold)}</span>${esc(sale.name)} → ${esc(sale.team || '')} · ${pts(sale.amount)} ${esc(t.pts)}</div>`
            : '';

        if (paused) {
            return `${justSold}<div class="${k.panel} ${big ? 'p-12' : 'p-8'} text-center"><p class="${big ? 'text-5xl' : 'text-2xl'} font-bold ${k.title}">${esc(t.paused)}</p><p class="mt-3 ${big ? 'text-2xl' : 'text-sm'} ${k.muted}">${esc(t.paused_hint)}</p></div>`;
        }

        if (lot) {
            const hasBid = lot.current_bid !== null;
            const where = lot.village ? `<p class="mt-1.5 ${big ? 'text-xl' : 'text-xs'} ${k.muted}">${esc(lot.village)}</p>` : '';
            const size = big ? 'h-56 w-56 text-6xl' : 'h-28 w-28 text-3xl';
            const photo = `<div class="relative flex ${size} shrink-0 items-center justify-center overflow-hidden rounded-xl ${big ? 'bg-white/10 text-white/40' : 'bg-slate-100 text-slate-400'} font-semibold">${esc(initials(lot.name))}${lot.photo ? `<img src="${esc(lot.photo)}" alt="" class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()" />` : ''}</div>`;

            let bidBox;
            if (lot.bids_hidden) {
                bidBox = `<div class="min-w-[12rem] rounded-xl ${k.plain} px-5 py-4 text-right"><p class="${big ? 'text-xl' : 'text-[11px]'} font-semibold uppercase tracking-wide opacity-70">${esc(t.base_price)}</p><p class="${big ? 'text-6xl' : 'text-3xl'} font-bold tabular-nums">${pts(lot.base_price)}</p><p class="${big ? 'text-xl' : 'text-xs'} opacity-70">${esc(t.bidding)}</p></div>`;
            } else {
                bidBox = `<div class="min-w-[12rem] rounded-xl ${hasBid ? k.bid : k.plain} px-5 py-4 text-right"><p class="${big ? 'text-xl' : 'text-[11px]'} font-semibold uppercase tracking-wide opacity-80">${esc(hasBid ? t.current_bid : t.base_price)}</p><p class="${big ? 'text-8xl' : 'text-4xl'} font-bold tabular-nums ${hasBid ? k.bidNumber : ''}">${pts(hasBid ? lot.current_bid : lot.base_price)}</p><p class="${big ? 'text-2xl' : 'text-sm'} ${hasBid ? 'font-semibold' : 'opacity-70'}">${esc(hasBid ? lot.leading_team : t.no_bid_yet)}</p></div>`;
            }

            const bids = !lot.bids_hidden && lot.bids.length
                ? `<ol class="mt-4 space-y-1 ${big ? 'text-xl' : 'text-[13px]'}">${lot.bids.map((bid, index) => `<li class="flex justify-between gap-3 ${index === 0 ? `font-semibold ${k.title}` : k.muted}"><span class="truncate">${esc(bid.team)}</span><span class="tabular-nums">${pts(bid.amount)}</span></li>`).join('')}</ol>`
                : '';

            return `${justSold}
                <div class="${k.panel} ${big ? 'p-8' : 'p-5'}">
                    <p class="${big ? 'text-lg' : 'text-[11px]'} font-semibold uppercase tracking-wide ${k.muted}">${esc(t.on_the_block)}</p>
                    <div class="mt-2 flex flex-wrap items-start gap-5">
                        ${photo}
                        <div class="min-w-0 flex-1">
                            <h2 class="break-words ${big ? 'text-7xl' : 'text-3xl'} font-bold tracking-tight ${k.title}">${esc(lot.name)}</h2>
                            <div class="mt-2 flex flex-wrap items-center gap-2">${tags(lot)}</div>
                            ${where}
                            ${pastLine(lot)}
                        </div>
                        ${bidBox}
                    </div>
                    ${bids}
                </div>`;
        }

        if (sale) {
            return `<div class="rounded-2xl ${k.sold} ${big ? 'p-12' : 'p-8'} text-center">
                <p class="${big ? 'text-3xl' : 'text-lg'} font-bold tracking-[0.3em]">${esc(t.sold)}</p>
                <p class="mt-3 ${big ? 'text-7xl' : 'text-4xl'} font-bold">${esc(sale.name)}</p>
                <p class="mt-3 ${big ? 'text-4xl' : 'text-xl'} font-semibold">${esc(tr('sold_to', { team: sale.team || '' }))}</p>
                <p class="mt-1 ${big ? 'text-6xl' : 'text-3xl'} font-bold tabular-nums">${esc(tr('for_points', { points: pts(sale.amount) }))}</p>
            </div>`;
        }

        return `<div class="${k.panel} ${big ? 'p-12' : 'p-8'} text-center"><p class="${big ? 'text-5xl' : 'text-2xl'} font-bold ${k.title}">${esc(t.waiting_next)}</p></div>`;
    }

    function teams() {
        const max = state.auction.max_squad;

        const cards = state.teams.map((team) => {
            const usedPct = team.purse > 0 ? Math.min(100, Math.round(((team.purse - team.left) / team.purse) * 100)) : 0;
            const leading = state.lot && state.lot.leading_team === team.name;
            const squad = !big && team.players.length
                ? `<details data-squad="${esc(team.name)}" class="mt-1.5" ${openSquads.has(team.name) ? 'open' : ''}><summary class="cursor-pointer text-[11px] ${k.muted}">${esc(t.squad)} (${team.players.length})</summary><ul class="mt-1 space-y-0.5 text-[11px] ${k.soft}">${team.players.map((p) => `<li class="flex justify-between gap-2"><span class="truncate">${esc(p.name)}</span><span class="tabular-nums">${p.amount === null ? '—' : pts(p.amount)}</span></li>`).join('')}</ul></details>`
                : '';

            return `
                <div class="${big ? 'rounded-xl bg-slate-900 p-4 ring-1' : 'rounded-lg border bg-white p-3'} ${leading ? (big ? 'ring-emerald-400' : 'border-green-400 ring-1 ring-green-300') : (big ? 'ring-white/10' : 'border-line')}">
                    <p class="truncate ${big ? 'text-2xl' : 'text-[13px]'} font-semibold ${k.title}" title="${esc(team.name)}">${esc(team.name)}</p>
                    <p class="mt-0.5 ${big ? 'text-4xl' : 'text-xl'} font-bold tabular-nums ${k.title}">${pts(team.left)} <span class="${big ? 'text-base' : 'text-[11px]'} font-medium ${k.muted}">${esc(t.left)}</span></p>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full ${k.bar}" aria-hidden="true"><div class="h-full rounded-full bg-emerald-500" style="width: ${usedPct}%"></div></div>
                    <p class="mt-1.5 ${big ? 'text-base' : 'text-[11px]'} ${k.muted}">${esc(tr('players_count', { count: team.count, max }))}${team.still_needed > 0 ? ` · ${esc(tr('needs_more', { count: team.still_needed }))}` : ''}</p>
                    ${squad}
                </div>`;
        });

        return `
            <section aria-label="${esc(t.teams)}">
                <h2 class="mb-2 ${big ? 'text-2xl' : 'text-sm'} font-semibold ${k.title}">${esc(t.teams)}</h2>
                <div class="grid gap-3 ${big ? 'grid-cols-1 sm:grid-cols-2' : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-1'}">${cards.join('')}</div>
            </section>`;
    }

    function sales() {
        const list = state.sales.slice(0, big ? 6 : 12);

        return `
            <section class="${k.panel} ${big ? 'p-6' : 'p-4'}" aria-label="${esc(t.recent_sales)}">
                <h2 class="${big ? 'text-2xl' : 'text-sm'} font-semibold ${k.title}">${esc(t.recent_sales)}</h2>
                ${list.length
                    ? `<ul class="mt-2 divide-y ${k.rule} ${big ? 'text-xl' : 'text-[13px]'}">${list.map((sale) => `<li class="flex items-center justify-between gap-3 py-2"><span class="min-w-0"><span class="block truncate font-medium ${k.title}">${esc(sale.name)}</span><span class="block truncate ${big ? 'text-base' : 'text-[11px]'} ${k.muted}">${esc(sale.team || '')}</span></span><span class="shrink-0 font-bold tabular-nums ${k.title}">${pts(sale.amount)}</span></li>`).join('')}</ul>`
                    : `<p class="mt-2 ${big ? 'text-lg' : 'text-xs'} ${k.muted}">${esc(t.no_sales)}</p>`}
            </section>`;
    }

    function hold() {
        if (!state.hold.length) {
            return '';
        }

        return `
            <section class="${k.panel} ${big ? 'p-6' : 'p-4'}" aria-label="${esc(t.on_hold)}">
                <h2 class="${big ? 'text-2xl' : 'text-sm'} font-semibold ${k.title}">${esc(t.on_hold)} <span class="${k.muted}">(${state.hold.length})</span></h2>
                <p class="mt-1.5 ${big ? 'text-lg' : 'text-[13px]'} leading-relaxed ${k.soft}">${state.hold.map(esc).join(' · ')}</p>
            </section>`;
    }

    function render() {
        root.innerHTML = `
            ${header()}
            <div class="grid items-start gap-4 ${big ? 'xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]' : 'lg:grid-cols-[minmax(0,1fr)_20rem]'}">
                <div class="min-w-0 space-y-4">
                    ${stage()}
                    ${sales()}
                    ${hold()}
                </div>
                ${teams()}
            </div>`;
    }

    // ----- Keeping it fresh --------------------------------------------------

    let echo = null;
    let lastFetch = 0;
    let signalTimer = null;

    function realtimeConnected() {
        try {
            return echo !== null && echo.connector.pusher.connection.state === 'connected';
        } catch (error) {
            return false;
        }
    }

    /**
     * Listens for the "auction changed" signal. Without Reverb configured (or
     * running) nothing happens and polling carries on alone.
     */
    function startRealtime() {
        const key = import.meta.env.VITE_REVERB_APP_KEY;
        const host = import.meta.env.VITE_REVERB_HOST;
        const port = import.meta.env.VITE_REVERB_PORT;
        const scheme = import.meta.env.VITE_REVERB_SCHEME;

        if (!key || !host) {
            return;
        }

        try {
            echo = new Echo({
                broadcaster: 'reverb',
                key,
                Pusher,
                wsHost: host,
                wsPort: port ?? 80,
                wssPort: port ?? 443,
                forceTLS: (scheme ?? 'https') === 'https',
                enabledTransports: ['ws', 'wss'],
            });

            echo.channel('public-auction').listen('.auction.updated', () => {
                // A burst of signals (one action can save several rows) is one fetch.
                window.clearTimeout(signalTimer);
                signalTimer = window.setTimeout(() => refresh(true), 120);
            });
        } catch (error) {
            echo = null;
        }
    }

    function apply(newState) {
        const next = JSON.stringify(newState);

        if (next === lastJson) {
            return;
        }

        lastJson = next;
        state = newState;
        render();
    }

    async function refresh(force = false) {
        if (document.visibilityState === 'hidden') {
            return;
        }

        // While the live signal is connected, the timer only double-checks now and then.
        if (!force && realtimeConnected() && Date.now() - lastFetch < REALTIME_POLL_MS) {
            return;
        }

        lastFetch = Date.now();

        try {
            const response = await window.fetch(dataUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });

            if (!response.ok) {
                throw new Error('bad status');
            }

            const body = await response.json();

            if (offline) {
                offline = false;
                lastJson = '';
            }

            if (body.state && body.state.auction.status === 'completed') {
                // Over: the results page is plain server-rendered HTML.
                window.location.reload();

                return;
            }

            if (body.state) {
                apply(body.state);
            }
        } catch (error) {
            if (!offline) {
                offline = true;
                render();
            }
        }
    }

    // Remember which squads are open so a refresh does not close them.
    root.addEventListener('toggle', (event) => {
        const squad = event.target.closest && event.target.closest('[data-squad]');
        if (squad) {
            squad.open ? openSquads.add(squad.dataset.squad) : openSquads.delete(squad.dataset.squad);
        }
    }, true);

    render();
    startRealtime();
    window.setInterval(() => refresh(), pollSeconds * 1000);
    document.addEventListener('visibilitychange', () => refresh(true));
}
