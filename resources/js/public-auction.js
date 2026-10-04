import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * The public live auction page. It draws everything from one `state` object
 * the server builds (AuctionStateService::public()) and swaps in a fresh one
 * from the data endpoint — it never works anything out itself (apart from
 * showing the numbers it is given), and the state holds nothing private (and,
 * when the auction hides live bids, no bid at all). All words come from the
 * server in the visitor's language.
 *
 * Two layouts: the normal page (the numbers, the player on the block, tabs
 * for sold / upcoming / on hold / unsold players with the bidding detail of
 * every sale, and the teams) and, with data-big="1", the dark projector
 * layout that fills the screen and scales with it.
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
    const { state: initial, texts: t, dataUrl, saleUrl, pollSeconds, big } = JSON.parse(dataEl.textContent);

    let state = initial;
    let lastJson = JSON.stringify(initial);
    let offline = false;
    let tab = 'sold';
    const openSquads = new Set();
    const openSales = new Set();
    /** How the bidding went for a sale ("key:amount:bids" -> list of bids | 'loading' | 'error'). */
    const ladders = new Map();

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

    /** Light page vs. dark projector: one class set each. */
    const k = big
        ? {
            panel: 'rounded-2xl bg-slate-900 ring-1 ring-white/10',
            tile: 'rounded-xl bg-slate-900 px-[clamp(0.6rem,1.1vw,1.4rem)] py-[clamp(0.25rem,0.5vw,0.7rem)] ring-1 ring-white/10',
            title: 'text-white',
            muted: 'text-slate-400',
            soft: 'text-slate-300',
            tag: 'rounded-full bg-white/10 px-[0.9em] py-[0.25em] text-slate-200',
            rule: 'divide-white/10',
            bar: 'bg-white/10',
            bid: 'bg-emerald-500/15 ring-1 ring-emerald-400/40 text-emerald-300',
            bidNumber: 'text-emerald-300',
            plain: 'bg-white/5 ring-1 ring-white/10 text-slate-300',
            sold: 'bg-emerald-600 text-white',
        }
        : {
            panel: 'pub-card',
            tile: 'pub-card px-3.5 py-3',
            title: 'text-slate-900',
            muted: 'text-slate-500',
            soft: 'text-slate-600',
            tag: 'rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] text-slate-600',
            rule: 'divide-line',
            bar: 'bg-slate-100',
            bid: 'bg-green-50 ring-1 ring-green-200 text-green-700',
            bidNumber: 'text-green-800',
            plain: 'bg-slate-50 ring-1 ring-slate-200 text-slate-600',
            sold: 'bg-green-600 text-white',
        };

    /** Caption-sized and body-sized text for each layout (the projector one scales with the screen). */
    const caption = big ? 'text-[clamp(0.7rem,0.95vw,1.2rem)]' : 'text-[11px]';
    const body = big ? 'text-[clamp(0.95rem,1.45vw,1.9rem)]' : 'text-[13px]';

    // ----- The numbers -----------------------------------------------------------

    function header() {
        const { auction, counts } = state;
        const paused = auction.status === 'paused';
        const left = counts.waiting + counts.hold;

        const status = paused
            ? `<span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 ${big ? 'text-[clamp(0.8rem,1.1vw,1.4rem)]' : 'text-[11px]'} font-semibold text-amber-800">${esc(t.paused)}</span>`
            : `<span class="inline-flex items-center gap-1.5 rounded-full bg-red-600 px-2.5 py-0.5 ${big ? 'text-[clamp(0.8rem,1.1vw,1.4rem)]' : 'text-[11px]'} font-bold tracking-wide text-white"><span class="live-dot" aria-hidden="true"></span>${esc(t.live)}</span>`;

        const toCome = left === 1 ? t.to_come_one : tr('to_come', { count: left });
        const done = tr('progress', { done: counts.sold + counts.unsold, total: counts.total });

        const title = `
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    ${status}
                    <h1 class="${big ? 'text-[clamp(1.5rem,3vw,3.75rem)]' : 'text-xl'} font-bold tracking-tight ${k.title}">${esc(auction.edition)} · ${esc(t.title)}</h1>
                </div>
                <p class="mt-1 ${big ? 'text-[clamp(0.9rem,1.35vw,1.75rem)]' : 'text-xs'} ${k.muted}">${esc(tr('round', { number: auction.round }))}${left > 0 ? ` · ${esc(toCome)}` : ''}${big ? ` · ${esc(done)}` : ''}${offline ? ` · <span class="text-amber-500">${esc(t.connection_lost)}</span>` : ''}</p>
            </div>`;

        if (!big) {
            return `<div class="mb-4">${title}</div>`;
        }

        const pct = counts.total > 0 ? Math.round(((counts.sold + counts.unsold) / counts.total) * 100) : 0;

        return `
            <div class="mb-[clamp(0.5rem,1vw,1.25rem)]">
                <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
                    ${title}
                    <div class="flex flex-wrap gap-[clamp(0.4rem,0.7vw,0.9rem)]">${overviewItems().slice(0, 6).map(tile).join('')}</div>
                </div>
                <div class="mt-[clamp(0.4rem,0.7vw,0.9rem)] h-[0.35vw] min-h-1 overflow-hidden rounded-full ${k.bar}" aria-hidden="true"><div class="h-full rounded-full bg-emerald-500" style="width: ${pct}%"></div></div>
            </div>`;
    }

    /** "N of M players done" with a bar. */
    function progress(spacing = '') {
        const { counts } = state;
        const done = counts.sold + counts.unsold;
        const pct = counts.total > 0 ? Math.round((done / counts.total) * 100) : 0;

        return `
            <div class="${spacing}">
                <div class="h-1.5 overflow-hidden rounded-full ${k.bar}" aria-hidden="true"><div class="h-full rounded-full bg-emerald-500" style="width: ${pct}%"></div></div>
                <p class="mt-1 ${caption} ${k.muted}">${esc(tr('progress', { done, total: counts.total }))}</p>
            </div>`;
    }

    /** [label, value, sub-line] for each headline number. */
    function overviewItems() {
        const { counts, stats } = state;
        const top = stats.highest;

        return [
            [t.sold_players, counts.sold],
            [t.upcoming, counts.waiting],
            [t.on_hold, counts.hold],
            [t.unsold, counts.unsold],
            [t.points_spent, pts(stats.points_spent)],
            [t.highest_sale, top ? pts(top.amount) : '—', top ? [top.name, top.team].filter(Boolean).join(' · ') : ''],
            [t.average_price, stats.average > 0 ? pts(stats.average) : '—'],
        ];
    }

    function tile([label, value, sub = '']) {
        return `
            <div class="${k.tile} min-w-0">
                <p class="${caption} font-semibold uppercase tracking-wide ${k.muted}">${esc(label)}</p>
                <p class="${big ? 'text-[clamp(1.2rem,2.2vw,2.9rem)]' : 'text-xl'} font-bold leading-tight tabular-nums ${k.title}">${esc(value)}</p>
                ${sub ? `<p class="max-w-[16rem] truncate ${caption} ${k.muted}">${esc(sub)}</p>` : ''}
            </div>`;
    }

    function overview() {
        return `
            <section class="mb-4" aria-label="${esc(t.title)}">
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4 lg:grid-cols-7">${overviewItems().map(tile).join('')}</div>
                ${progress('mt-3')}
            </section>`;
    }

    // ----- The player on the block ---------------------------------------------------

    function tags(lot) {
        const list = [
            lot.role ? tr('role_line', { role: lot.role }) : null,
            lot.batting ? tr('batting_hand', { hand: lot.batting }) : null,
            lot.bowling ? tr('bowling_arm', { arm: lot.bowling }) : null,
        ].filter(Boolean);

        return list.map((tag) => `<span class="${k.tag} ${big ? 'text-[clamp(0.85rem,1.3vw,1.7rem)]' : ''} font-medium">${esc(tag)}</span>`).join('');
    }

    function pastLine(lot) {
        const size = big ? 'text-[clamp(0.9rem,1.35vw,1.75rem)]' : 'text-xs';

        if (!lot.stats) {
            return `<p class="mt-2 ${size} ${k.muted}">${esc(t.first_time)}</p>`;
        }

        const s = lot.stats;
        const line = tr('before', { matches: s.matches, runs: s.runs, wickets: s.wickets });
        const best = s.highest !== null ? ` (${tr('best_score', { value: s.highest })})` : '';

        return `<p class="mt-2 ${size} ${k.soft}">${esc(line)}${esc(best)}</p>`;
    }

    function photoBox(lot, sizeClass) {
        // The player's photo, or the default picture when there is none (or it fails to load).
        return `<div class="relative ${sizeClass} shrink-0 overflow-hidden rounded-xl ${big ? 'bg-white/10' : 'bg-slate-100'}"><img src="${esc(lot.photo || '')}" alt="" data-fallback="user" class="h-full w-full object-cover" /></div>`;
    }

    /** The standing bid, in the normal page. */
    function bidBox(lot) {
        const hasBid = lot.current_bid !== null;

        if (lot.bids_hidden) {
            return `<div class="min-w-[12rem] rounded-xl ${k.plain} px-5 py-4 text-right"><p class="text-[11px] font-semibold uppercase tracking-wide opacity-70">${esc(t.base_price)}</p><p class="text-3xl font-bold tabular-nums">${pts(lot.base_price)}</p><p class="text-xs opacity-70">${esc(t.bidding)}</p></div>`;
        }

        return `<div class="min-w-[12rem] rounded-xl ${hasBid ? k.bid : k.plain} px-5 py-4 text-right"><p class="text-[11px] font-semibold uppercase tracking-wide opacity-80">${esc(hasBid ? t.current_bid : t.base_price)}</p><p class="text-4xl font-bold tabular-nums ${hasBid ? k.bidNumber : ''}">${pts(hasBid ? lot.current_bid : lot.base_price)}</p><p class="text-sm ${hasBid ? 'font-semibold' : 'opacity-70'}">${esc(hasBid ? lot.leading_team : t.no_bid_yet)}</p></div>`;
    }

    function justSoldBanner() {
        const { lot, last_sale: sale } = state;

        if (!sale || !lot) {
            return '';
        }

        return `<div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg ${k.sold} px-4 py-2 ${big ? 'text-[clamp(1.1rem,2vw,2.5rem)]' : 'text-sm'} font-semibold"><span class="rounded bg-white/20 px-2 py-0.5 text-xs font-bold tracking-wide">${esc(t.sold)}</span>${esc(sale.name)} → ${esc(sale.team || '')} · ${pts(sale.amount)} ${esc(t.pts)}</div>`;
    }

    /** The player on the block on the projector: name across the full width, the bid beneath. */
    function bigLot(lot) {
        const hasBid = lot.current_bid !== null;
        const ladder = lot.bids_hidden ? [] : lot.bids.slice(0, 5);
        const label = 'text-[clamp(0.9rem,1.3vw,1.7rem)] font-semibold uppercase tracking-[0.15em]';

        const numbers = lot.bids_hidden
            ? `<p class="${label} opacity-70">${esc(t.base_price)}</p>
               <p class="text-[clamp(3.5rem,9vw,12rem)] font-extrabold leading-none tabular-nums">${pts(lot.base_price)}</p>
               <p class="mt-2 text-[clamp(1.1rem,2.2vw,2.8rem)] font-semibold opacity-70">${esc(t.bidding)}</p>`
            : `<p class="${label} opacity-80">${esc(hasBid ? t.current_bid : t.base_price)}</p>
               <p class="text-[clamp(3.5rem,9vw,12rem)] font-extrabold leading-none tabular-nums ${hasBid ? k.bidNumber : ''}">${pts(hasBid ? lot.current_bid : lot.base_price)}</p>
               <p class="mt-2 truncate text-[clamp(1.1rem,2.2vw,2.8rem)] font-semibold ${hasBid ? '' : 'opacity-70'}">${esc(hasBid ? lot.leading_team : t.no_bid_yet)}</p>`;

        const ladderBox = ladder.length
            ? `<div class="min-w-0 self-stretch border-l border-white/10 pl-[clamp(0.75rem,1.5vw,2rem)]"><p class="${label} opacity-60">${esc(t.bids)}</p><ol class="mt-2 space-y-1.5 ${body}">${ladder.map((bid, index) => `<li class="flex justify-between gap-3 ${index === 0 ? 'font-semibold text-white' : 'text-slate-400'}"><span class="truncate">${esc(bid.team)}</span><span class="tabular-nums">${pts(bid.amount)}</span></li>`).join('')}</ol></div>`
            : '';

        return `
            <div class="${k.panel} flex min-h-0 flex-1 flex-col p-[clamp(1rem,2vw,2.75rem)]">
                <div class="flex items-center justify-between gap-4">
                    <p class="${label} ${k.muted}">${esc(t.on_the_block)}</p>
                    <p class="${label} ${k.muted}">${esc(tr('round_short', { number: lot.round }))}</p>
                </div>
                <div class="mt-[clamp(0.5rem,1.2vw,1.75rem)] flex items-center gap-[clamp(1rem,2vw,3rem)]">
                    ${photoBox(lot, 'h-[clamp(6.5rem,12vw,16rem)] w-[clamp(6.5rem,12vw,16rem)] text-[clamp(2rem,4.5vw,6rem)]')}
                    <div class="min-w-0 flex-1">
                        <h2 class="break-words text-[clamp(2.25rem,4.8vw,6.25rem)] font-extrabold leading-[1.05] tracking-tight ${k.title}">${esc(lot.name)}</h2>
                        <div class="mt-[0.6vw] flex flex-wrap items-center gap-2">${tags(lot)}</div>
                        ${lot.village ? `<p class="mt-[0.5vw] text-[clamp(0.9rem,1.5vw,1.9rem)] ${k.muted}">${esc(lot.village)}</p>` : ''}
                        ${pastLine(lot)}
                    </div>
                </div>
                <div class="mt-auto pt-[clamp(0.75rem,1.4vw,2rem)]">
                    <div class="grid items-end gap-x-[clamp(1rem,2vw,3rem)] rounded-2xl ${lot.bids_hidden ? k.plain : (hasBid ? k.bid : k.plain)} p-[clamp(0.9rem,1.8vw,2.5rem)] ${ladder.length ? 'grid-cols-[minmax(0,1fr)_minmax(0,0.6fr)]' : ''}">
                        <div class="min-w-0">${numbers}</div>
                        ${ladderBox}
                    </div>
                </div>
            </div>`;
    }

    function normalLot(lot) {
        const where = lot.village ? `<p class="mt-1.5 text-xs ${k.muted}">${esc(lot.village)}</p>` : '';

        const bids = !lot.bids_hidden && lot.bids.length
            ? `<ol class="mt-4 space-y-1 text-[13px]">${lot.bids.map((bid, index) => `<li class="flex justify-between gap-3 ${index === 0 ? `font-semibold ${k.title}` : k.muted}"><span class="truncate">${esc(bid.team)}</span><span class="tabular-nums">${pts(bid.amount)}</span></li>`).join('')}</ol>`
            : '';

        return `
            <div class="${k.panel} p-5">
                <p class="text-[11px] font-semibold uppercase tracking-wide ${k.muted}">${esc(t.on_the_block)}</p>
                <div class="mt-2 flex flex-wrap items-start gap-5">
                    ${photoBox(lot, 'h-28 w-28 text-3xl')}
                    <div class="min-w-0 flex-1">
                        <h2 class="break-words text-3xl font-bold tracking-tight ${k.title}">${esc(lot.name)}</h2>
                        <div class="mt-2 flex flex-wrap items-center gap-2">${tags(lot)}</div>
                        ${where}
                        ${pastLine(lot)}
                    </div>
                    ${bidBox(lot)}
                </div>
                ${bids}
            </div>`;
    }

    function stage() {
        const { lot, auction, last_sale: sale } = state;
        const paused = auction.status === 'paused';
        const fill = big ? 'flex flex-1 flex-col items-center justify-center' : '';

        if (paused) {
            return `${justSoldBanner()}<div class="${k.panel} ${fill} ${big ? 'p-12' : 'p-8'} text-center"><p class="${big ? 'text-[clamp(2.5rem,5vw,6rem)]' : 'text-2xl'} font-bold ${k.title}">${esc(t.paused)}</p><p class="mt-3 ${big ? 'text-[clamp(1.1rem,2vw,2.5rem)]' : 'text-sm'} ${k.muted}">${esc(t.paused_hint)}</p></div>`;
        }

        if (lot) {
            return `${justSoldBanner()}${big ? bigLot(lot) : normalLot(lot)}`;
        }

        if (sale) {
            return `<div class="rounded-2xl ${k.sold} ${fill} ${big ? 'p-12' : 'p-8'} text-center">
                <p class="${big ? 'text-[clamp(1.5rem,2.6vw,3.5rem)]' : 'text-lg'} font-bold tracking-[0.3em]">${esc(t.sold)}</p>
                <p class="mt-3 ${big ? 'text-[clamp(3rem,6.5vw,8rem)]' : 'text-4xl'} font-bold">${esc(sale.name)}</p>
                <p class="mt-3 ${big ? 'text-[clamp(1.5rem,3.4vw,4.5rem)]' : 'text-xl'} font-semibold">${esc(tr('sold_to', { team: sale.team || '' }))}</p>
                <p class="mt-1 ${big ? 'text-[clamp(2.5rem,5.5vw,7rem)]' : 'text-3xl'} font-bold tabular-nums">${esc(tr('for_points', { points: pts(sale.amount) }))}</p>
            </div>`;
        }

        return `<div class="${k.panel} ${fill} ${big ? 'p-12' : 'p-8'} text-center"><p class="${big ? 'text-[clamp(2rem,4.2vw,5.5rem)]' : 'text-2xl'} font-bold ${k.title}">${esc(t.waiting_next)}</p></div>`;
    }

    // ----- The teams ---------------------------------------------------------------------

    function teams() {
        const max = state.auction.max_squad;

        const cards = state.teams.map((team) => {
            const usedPct = team.purse > 0 ? Math.min(100, Math.round(((team.purse - team.left) / team.purse) * 100)) : 0;
            const leading = state.lot && state.lot.leading_team === team.name;
            const top = team.players.find((p) => p.amount !== null);
            const squad = team.players.length
                ? `<details data-squad="${esc(team.name)}" class="mt-1.5" ${openSquads.has(team.name) ? 'open' : ''}><summary class="cursor-pointer text-[11px] ${k.muted}">${esc(t.squad)} (${team.players.length})</summary><ul class="mt-1 space-y-0.5 text-[11px] ${k.soft}">${team.players.map((p) => `<li class="flex justify-between gap-2"><span class="truncate">${esc(p.name)}</span><span class="tabular-nums">${p.amount === null ? '—' : pts(p.amount)}</span></li>`).join('')}</ul></details>`
                : '';

            return `
                <div class="rounded-lg border bg-white p-3 ${leading ? 'border-green-400 ring-1 ring-green-300' : 'border-line'}">
                    <p class="truncate text-[13px] font-semibold ${k.title}" title="${esc(team.name)}">${esc(team.name)}</p>
                    <p class="mt-0.5 text-xl font-bold tabular-nums ${k.title}">${pts(team.left)} <span class="text-[11px] font-medium ${k.muted}">${esc(t.left)}</span></p>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full ${k.bar}" aria-hidden="true"><div class="h-full rounded-full bg-emerald-500" style="width: ${usedPct}%"></div></div>
                    <p class="mt-1.5 text-[11px] ${k.muted}">${esc(tr('players_count', { count: team.count, max }))}${team.still_needed > 0 ? ` · ${esc(tr('needs_more', { count: team.still_needed }))}` : ''}</p>
                    <p class="mt-0.5 truncate text-[11px] ${k.muted}">${esc(t.spent)} ${pts(team.spent)}${top ? ` · ${esc(t.top_buy)}: ${esc(top.name)} (${pts(top.amount)})` : ''}</p>
                    ${squad}
                </div>`;
        });

        return `
            <section aria-label="${esc(t.teams)}">
                <h2 class="mb-2 text-sm font-semibold ${k.title}">${esc(t.teams)}</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-1">${cards.join('')}</div>
            </section>`;
    }

    /** The teams on the projector: a grid that fills the height left by the stage. */
    function bigTeams() {
        const max = state.auction.max_squad;
        const compact = state.teams.length > 8;
        const columns = compact ? 'grid-cols-3' : 'grid-cols-2';

        const cards = state.teams.map((team) => {
            const usedPct = team.purse > 0 ? Math.min(100, Math.round(((team.purse - team.left) / team.purse) * 100)) : 0;
            const leading = state.lot && state.lot.leading_team === team.name;

            const frame = `flex min-h-0 min-w-0 flex-col justify-between overflow-hidden rounded-xl bg-slate-900 px-[clamp(0.6rem,1vw,1.3rem)] py-[clamp(0.35rem,0.7vw,0.9rem)] ring-1 ${leading ? 'ring-2 ring-emerald-400' : 'ring-white/10'}`;
            const squad = `${team.count}/${max}`;
            const squadColour = team.still_needed > 0 ? 'text-amber-300' : 'text-slate-400';
            const bar = `<div class="h-[0.4vw] min-h-1 flex-1 overflow-hidden rounded-full bg-white/10" aria-hidden="true"><div class="h-full rounded-full bg-emerald-500" style="width: ${usedPct}%"></div></div>`;

            // Many teams: narrower cards, so the name gets the whole first line.
            if (compact) {
                return `
                <div class="${frame}" title="${esc(tr('players_count', { count: team.count, max }))}">
                    <p class="shrink-0 truncate text-[clamp(0.8rem,1.2vw,1.6rem)] font-semibold leading-tight text-white">${esc(team.name)}</p>
                    <p class="shrink-0 whitespace-nowrap text-[clamp(1.05rem,1.65vw,2.2rem)] font-bold leading-none tabular-nums text-white">${pts(team.left)} <span class="text-[clamp(0.65rem,0.85vw,1.1rem)] font-medium text-slate-400">${esc(t.left)}</span></p>
                    <div class="flex shrink-0 items-center gap-2">${bar}<span class="shrink-0 text-[clamp(0.65rem,0.9vw,1.2rem)] font-medium leading-none tabular-nums ${squadColour}">${squad}</span></div>
                </div>`;
            }

            return `
                <div class="${frame}" title="${esc(tr('players_count', { count: team.count, max }))}">
                    <div class="flex shrink-0 items-baseline justify-between gap-2">
                        <p class="min-w-0 truncate text-[clamp(0.9rem,1.4vw,1.9rem)] font-semibold leading-tight text-white">${esc(team.name)}</p>
                        <p class="shrink-0 text-[clamp(0.75rem,1.1vw,1.45rem)] font-medium leading-tight tabular-nums ${squadColour}">${squad}</p>
                    </div>
                    <p class="shrink-0 text-[clamp(1.2rem,2.3vw,3rem)] font-bold leading-none tabular-nums text-white">${pts(team.left)} <span class="text-[clamp(0.7rem,0.95vw,1.2rem)] font-medium text-slate-400">${esc(t.left)}</span></p>
                    <div class="flex shrink-0">${bar}</div>
                </div>`;
        });

        return `
            <section class="flex min-h-0 flex-1 flex-col" aria-label="${esc(t.teams)}">
                <h2 class="mb-[0.6vw] text-[clamp(1.1rem,1.7vw,2.2rem)] font-semibold text-white">${esc(t.teams)}</h2>
                <div class="grid min-h-0 flex-1 auto-rows-fr gap-[clamp(0.4rem,0.8vw,1rem)] ${columns}">${cards.join('')}</div>
            </section>`;
    }

    /** The latest sales, on the projector. */
    function bigSales() {
        const list = state.sales.slice(0, 2);

        return `
            <section class="${k.panel} shrink-0 overflow-hidden p-[clamp(0.6rem,1.1vw,1.4rem)]" aria-label="${esc(t.recent_sales)}">
                <h2 class="text-[clamp(1.1rem,1.7vw,2.2rem)] font-semibold ${k.title}">${esc(t.recent_sales)}</h2>
                ${list.length
                    ? `<ul class="mt-2 divide-y ${k.rule} ${body}">${list.map((sale) => `<li class="flex items-center justify-between gap-3 py-[0.35vw]"><span class="min-w-0"><span class="block truncate font-medium ${k.title}">${esc(sale.name)}</span><span class="block truncate ${caption} ${k.muted}">${esc(sale.team || '')}</span></span><span class="shrink-0 font-bold tabular-nums ${k.title}">${pts(sale.amount)}</span></li>`).join('')}</ul>`
                    : `<p class="mt-2 ${body} ${k.muted}">${esc(t.no_sales)}</p>`}
            </section>`;
    }

    // ----- Sold / upcoming / on hold / unsold -----------------------------------------------

    function ladderHtml(sig) {
        const ladder = ladders.get(sig);
        let inner;

        if (Array.isArray(ladder)) {
            inner = `<ol class="space-y-0.5">${[...ladder].reverse().map((bid, index) => `<li class="flex justify-between gap-3 ${index === 0 ? 'font-semibold text-slate-900' : 'text-slate-500'}"><span class="truncate">${esc(bid.team)}</span><span class="tabular-nums">${pts(bid.amount)}</span></li>`).join('')}</ol>`;
        } else if (ladder === 'error') {
            inner = `<p class="text-amber-600">${esc(t.connection_lost)}</p>`;
        } else {
            inner = `<p class="text-slate-400">${esc(t.loading)}</p>`;
        }

        return `<div class="border-t border-dashed border-line bg-slate-50 py-2.5 pl-[3.25rem] pr-4 text-[12px]"><p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">${esc(t.bid_history)}</p>${inner}</div>`;
    }

    function saleRow(sale, number) {
        const hasBids = sale.bids !== null && sale.bids > 0;
        const bidsLabel = hasBids ? (sale.bids === 1 ? t.bids_one : tr('bids_count', { count: sale.bids })) : '';
        const summary = `
            <span class="w-6 shrink-0 text-center text-xs tabular-nums text-slate-400">${number}</span>
            <span class="min-w-0 flex-1"><span class="block truncate text-[13px] font-medium text-slate-900">${esc(sale.name)}</span><span class="block truncate text-[11px] text-slate-500">${esc([sale.team, sale.role].filter(Boolean).join(' · '))}</span></span>
            <span class="shrink-0 text-right"><span class="block text-[13px] font-bold tabular-nums text-slate-900">${pts(sale.amount)} <span class="text-[11px] font-normal text-slate-400">${esc(t.pts)}</span></span>${hasBids ? `<span class="block text-[11px] text-slate-500">${esc(bidsLabel)}</span>` : ''}</span>`;

        if (!hasBids) {
            return `<li class="flex items-center gap-3 px-4 py-2.5">${summary}</li>`;
        }

        const sig = `${sale.key}:${sale.amount}:${sale.bids}`;

        return `
            <li>
                <details class="group" data-sale="${sale.key}" data-sig="${esc(sig)}" ${openSales.has(String(sale.key)) ? 'open' : ''}>
                    <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-2.5 hover:bg-slate-50 [&::-webkit-details-marker]:hidden">${summary}<span class="shrink-0 text-slate-400 transition group-open:rotate-180" aria-hidden="true">▾</span></summary>
                    ${ladderHtml(sig)}
                </details>
            </li>`;
    }

    function nameList(names, emptyText) {
        if (!names.length) {
            return `<p class="pub-empty">${esc(emptyText)}</p>`;
        }

        return `<ul class="grid gap-x-8 px-4 py-2 sm:grid-cols-2 lg:grid-cols-3">${names.map((player) => {
            const name = typeof player === 'string' ? player : player.name;
            const role = typeof player === 'string' ? null : player.role;

            return `<li class="flex items-baseline justify-between gap-2 border-b border-line py-1.5 text-[13px]"><span class="truncate text-slate-900">${esc(name)}</span>${role ? `<span class="shrink-0 text-[11px] text-slate-400">${esc(role)}</span>` : ''}</li>`;
        }).join('')}</ul>`;
    }

    function lists() {
        const { counts, sales } = state;
        const definitions = [
            ['sold', t.sold_players, counts.sold],
            ['upcoming', t.upcoming, counts.waiting],
            ['hold', t.on_hold, counts.hold],
            ['unsold', t.unsold, counts.unsold],
        ];

        const buttons = definitions.map(([id, label, count]) => `<button type="button" role="tab" aria-selected="${tab === id}" data-tab="${id}" class="whitespace-nowrap border-b-2 px-3 py-2.5 text-[13px] font-semibold ${tab === id ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800'}">${esc(label)} <span class="tabular-nums ${tab === id ? '' : 'text-slate-400'}">${count}</span></button>`).join('');

        let content;
        if (tab === 'upcoming') {
            content = nameList(state.upcoming, t.no_upcoming);
        } else if (tab === 'hold') {
            content = nameList(state.hold, t.no_hold);
        } else if (tab === 'unsold') {
            content = nameList(state.unsold, t.no_unsold_yet);
        } else {
            content = sales.length
                ? `<ul class="divide-y ${k.rule}">${sales.map((sale, index) => saleRow(sale, sales.length - index)).join('')}</ul>`
                : `<p class="pub-empty">${esc(t.no_sales)}</p>`;
        }

        return `
            <section class="pub-card overflow-hidden">
                <div role="tablist" class="flex overflow-x-auto border-b border-line px-2">${buttons}</div>
                ${content}
            </section>`;
    }

    function render() {
        if (big) {
            root.innerHTML = `
                ${header()}
                <div class="grid min-h-0 flex-1 gap-[clamp(0.75rem,1.4vw,1.75rem)] xl:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]">
                    <div class="flex min-h-0 flex-col">${stage()}</div>
                    <div class="flex min-h-0 flex-col gap-[clamp(0.5rem,1vw,1.25rem)]">${bigTeams()}${bigSales()}</div>
                </div>`;

            return;
        }

        root.innerHTML = `
            ${header()}
            ${overview()}
            <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="min-w-0 space-y-4">
                    ${stage()}
                    ${lists()}
                </div>
                ${teams()}
            </div>`;
    }

    /** Fetches how the bidding went for a sold player, the first time its row is opened. */
    async function loadLadder(sig) {
        if (ladders.has(sig) || !saleUrl) {
            return;
        }

        ladders.set(sig, 'loading');

        try {
            const response = await window.fetch(saleUrl.replace('__LOT__', sig.split(':')[0]), {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });

            if (!response.ok) {
                throw new Error('bad status');
            }

            ladders.set(sig, (await response.json()).bids);
        } catch (error) {
            ladders.set(sig, 'error');
            // Opening the row again after a moment tries again.
            window.setTimeout(() => ladders.delete(sig), 10000);
        }

        render();
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

    // Remember which squads and sales are open so a refresh does not close them.
    root.addEventListener('toggle', (event) => {
        const target = event.target;

        if (!target.closest) {
            return;
        }

        const squad = target.closest('[data-squad]');
        if (squad) {
            squad.open ? openSquads.add(squad.dataset.squad) : openSquads.delete(squad.dataset.squad);
        }

        const sale = target.closest('[data-sale]');
        if (sale) {
            if (sale.open) {
                openSales.add(sale.dataset.sale);
                loadLadder(sale.dataset.sig);
            } else {
                openSales.delete(sale.dataset.sale);
            }
        }
    }, true);

    root.addEventListener('click', (event) => {
        const button = event.target.closest && event.target.closest('[data-tab]');

        if (button) {
            tab = button.dataset.tab;
            render();
        }
    });

    render();
    startRealtime();
    window.setInterval(() => refresh(), pollSeconds * 1000);
    document.addEventListener('visibilitychange', () => refresh(true));
}
