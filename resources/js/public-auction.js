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
 * Two layouts: the normal page (the player on the block in a dark hero card,
 * the numbers, tabs for sold / upcoming / on hold / unsold players with the
 * bidding detail of every sale, and the teams) and, with data-big="1", the
 * dark projector layout that fills the screen and scales with it.
 *
 * Freshness: when Reverb is running, a "something changed" signal on the
 * public-auction channel makes the page fetch the new picture at once (the
 * signal carries no data). Polling is always there as the safety net — every
 * few seconds if the signal is not available, and only now and then while it
 * is connected.
 *
 * Colours: the brand colour comes from the admin-editable theme (bg-brand,
 * text-accent-dark, ...); green / red / amber stay only for their meaning
 * (SOLD, LIVE, paused).
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
    let lastBidKey = null;
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

    /** The address of the projector layout (the same page with ?display=big). */
    function bigUrl() {
        const url = new URL(window.location.href);
        url.searchParams.set('display', 'big');

        return url.pathname + url.search;
    }

    /** Light page vs. dark projector: one class set each. */
    const k = big
        ? {
            panel: 'rounded-2xl bg-white/5 ring-1 ring-white/10',
            tile: 'rounded-xl bg-white/5 px-[clamp(0.6rem,1.1vw,1.4rem)] py-[clamp(0.25rem,0.5vw,0.7rem)] ring-1 ring-white/10',
            title: 'text-white',
            muted: 'text-slate-400',
            soft: 'text-slate-300',
            tag: 'rounded-full bg-white/10 px-[0.9em] py-[0.25em] text-slate-100 ring-1 ring-white/10',
            rule: 'divide-white/10',
            bar: 'bg-white/10',
            fill: 'bg-accent-dark',
            bid: 'bg-brand/20 ring-1 ring-accent-dark/50 text-accent-dark',
            bidNumber: 'text-accent-dark',
            plain: 'bg-white/5 ring-1 ring-white/10 text-slate-300',
            sold: 'bg-green-600 text-white',
        }
        : {
            panel: 'pub-card',
            tile: 'rounded-xl border border-line bg-white px-3.5 py-3 shadow-card',
            title: 'text-slate-900',
            muted: 'text-slate-500',
            soft: 'text-slate-600',
            tag: 'rounded-full bg-white/10 px-2 py-0.5 text-[10.5px] text-slate-100 ring-1 ring-white/15',
            rule: 'divide-line',
            bar: 'bg-slate-100',
            fill: 'bg-brand',
            bid: 'bg-brand/25 ring-1 ring-accent-dark/50 text-accent-dark',
            bidNumber: 'text-accent-dark',
            plain: 'bg-white/10 ring-1 ring-white/15 text-slate-200',
            sold: 'bg-green-600 text-white',
        };

    /** Caption-sized and body-sized text for each layout (the projector one scales with the screen). */
    const caption = big ? 'text-[clamp(0.7rem,0.95vw,1.2rem)]' : 'text-[11px]';
    const body = big ? 'text-[clamp(0.95rem,1.45vw,1.9rem)]' : 'text-[13px]';

    const monitorIcon = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2" /><path d="M8 20h8M12 16v4" /></svg>';

    /** The inline style that hands a team's colour to the CSS as --tc; anything but #rrggbb is ignored. */
    function tcValue(color) {
        return /^#[0-9a-f]{6}$/i.test(color || '') ? color : '';
    }

    function tc(color) {
        return tcValue(color) ? `style="--tc:${tcValue(color)}"` : '';
    }

    /** True when a colour is light enough that white lettering on it would be hard to read. */
    function isLight(color) {
        const hex = tcValue(color);

        if (!hex) {
            return false;
        }

        const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));

        return (0.299 * r + 0.587 * g + 0.114 * b) > 170;
    }

    const crownIcon = '<svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 7l4.5 4L12 4l4.5 7L21 7l-2 11H5L3 7z" /></svg>';
    const shareIcon = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="2.5" /><circle cx="6" cy="12" r="2.5" /><circle cx="18" cy="19" r="2.5" /><path d="M8.2 10.8l7.6-4.4M8.2 13.2l7.6 4.4" /></svg>';
    const pinIcon = '<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6.5 7-11.5A7 7 0 0 0 5 9.5C5 14.5 12 21 12 21z" /><circle cx="12" cy="9.5" r="2.5" /></svg>';

    /** A team's round logo (its default picture when it has none). */
    function logo(src, sizeClass) {
        return `<span class="relative ${sizeClass} shrink-0 overflow-hidden rounded-full bg-white/10"><img src="${esc(src || '')}" alt="" data-fallback="image" class="h-full w-full object-cover" /></span>`;
    }

    // ----- The numbers -----------------------------------------------------------

    function header() {
        const { auction, counts } = state;
        const paused = auction.status === 'paused';
        const left = counts.waiting + counts.hold;

        const status = paused
            ? `<span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 ${big ? 'text-[clamp(0.8rem,1.1vw,1.4rem)]' : 'text-[10px]'} font-semibold text-amber-800">${esc(t.paused)}</span>`
            : `<span class="inline-flex items-center gap-1.5 rounded-full bg-red-600 px-2.5 py-0.5 ${big ? 'text-[clamp(0.8rem,1.1vw,1.4rem)]' : 'text-[10px]'} font-bold tracking-wide text-white"><span class="live-dot" aria-hidden="true"></span>${esc(t.live)}</span>`;

        const toCome = left === 1 ? t.to_come_one : tr('to_come', { count: left });
        const done = tr('progress', { done: counts.sold + counts.unsold, total: counts.total });

        const title = `
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    ${status}
                    <h1 class="${big ? 'text-[clamp(1.5rem,3vw,3.75rem)]' : 'text-base sm:text-xl'} font-bold tracking-tight ${k.title}">${esc(auction.edition)} · ${esc(t.title)}</h1>
                </div>
                <p class="mt-1 ${big ? 'text-[clamp(0.9rem,1.35vw,1.75rem)]' : 'text-xs'} ${k.muted}">${esc(tr('round', { number: auction.round }))}${left > 0 ? ` · ${esc(toCome)}` : ''}${big ? ` · ${esc(done)}` : ''}${offline ? ` · <span class="font-semibold text-amber-500">${esc(t.connection_lost)}</span>` : ''}</p>
            </div>`;

        if (!big) {
            return `
                <div class="mb-3 flex items-start justify-between gap-2">
                    ${title}
                    <div class="flex shrink-0 items-center gap-1.5">
                        <button type="button" data-share class="btn btn-secondary btn-sm min-h-9 px-2.5" aria-label="${esc(t.share)}" title="${esc(t.share)}">${shareIcon}</button>
                        <a href="${esc(bigUrl())}" class="btn btn-secondary btn-sm min-h-9 px-2.5" aria-label="${esc(t.big_screen)}" title="${esc(t.big_screen)}">${monitorIcon}</a>
                    </div>
                </div>`;
        }

        const pct = counts.total > 0 ? Math.round(((counts.sold + counts.unsold) / counts.total) * 100) : 0;

        return `
            <div class="mb-[clamp(0.5rem,1vw,1.25rem)]">
                <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
                    ${title}
                    <div class="flex flex-wrap gap-[clamp(0.4rem,0.7vw,0.9rem)]">${overviewItems().slice(0, 6).map((item) => tile(item)).join('')}</div>
                </div>
                <div class="mt-[clamp(0.4rem,0.7vw,0.9rem)] h-[0.35vw] min-h-1 overflow-hidden rounded-full ${k.bar}" aria-hidden="true"><div class="h-full rounded-full ${k.fill}" style="width: ${pct}%"></div></div>
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

    function tile([label, value, sub = ''], extra = '') {
        return `
            <div class="${k.tile} ${extra} min-w-0">
                <p class="${caption} font-semibold uppercase tracking-wide ${k.muted}">${esc(label)}</p>
                <p class="${big ? 'text-[clamp(1.2rem,2.2vw,2.9rem)]' : 'text-xl sm:text-2xl'} font-bold leading-tight tabular-nums ${k.title}">${esc(value)}</p>
                ${sub ? `<p class="truncate ${caption} ${k.muted}">${esc(sub)}</p>` : ''}
            </div>`;
    }

    function overview() {
        const { counts, stats } = state;
        const top = stats.highest;
        const pct = counts.total > 0 ? Math.round(((counts.sold + counts.unsold) / counts.total) * 100) : 0;

        const cell = (label, value, tone = k.title, sub = '', extra = '') => `
            <div class="min-w-0 bg-white px-2 py-2 text-center ${extra}">
                <p class="truncate text-[15px] font-extrabold leading-none tabular-nums ${tone}">${esc(value)}</p>
                <p class="mt-1 truncate text-[9.5px] font-medium uppercase tracking-wide ${k.muted}">${esc(label)}${sub ? ` · ${esc(sub)}` : ''}</p>
            </div>`;

        return `
            <section class="overflow-hidden rounded-xl border border-line shadow-card" aria-label="${esc(t.title)}">
                <div class="grid grid-cols-4 gap-px bg-line sm:grid-cols-7">
                    ${cell(t.sold_players, counts.sold, 'text-emerald-700')}
                    ${cell(t.upcoming, counts.waiting)}
                    ${cell(t.on_hold, counts.hold, 'text-amber-600')}
                    ${cell(t.unsold, counts.unsold, 'text-slate-500')}
                    ${cell(t.points_spent, pts(stats.points_spent))}
                    ${cell(t.average_price, stats.average > 0 ? pts(stats.average) : '—')}
                    ${cell(t.highest_sale, top ? pts(top.amount) : '—', k.title, top ? top.name : '', 'col-span-2 sm:col-span-1')}
                </div>
                <div class="h-1 bg-slate-100" title="${esc(tr('progress', { done: counts.sold + counts.unsold, total: counts.total }))}"><div class="h-full ${k.fill} transition-all duration-500" style="width: ${pct}%"></div></div>
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
        const size = big ? 'text-[clamp(0.9rem,1.35vw,1.75rem)]' : 'text-[11px]';

        if (!lot.stats) {
            return `<p class="mt-1.5 ${size} ${big ? k.muted : 'text-white/55'}">${esc(t.first_time)}</p>`;
        }

        const s = lot.stats;
        const line = tr('before', { matches: s.matches, runs: s.runs, wickets: s.wickets });
        const best = s.highest !== null ? ` (${tr('best_score', { value: s.highest })})` : '';

        return `<p class="mt-1.5 ${size} ${big ? k.soft : 'text-white/75'}">${esc(line)}${esc(best)}</p>`;
    }

    function photoBox(lot, sizeClass) {
        // The player's photo, or the default picture when there is none (or it fails to load).
        return `<div class="relative ${sizeClass} shrink-0 overflow-hidden rounded-2xl bg-white/10 ring-2 ring-white/15"><img src="${esc(lot.photo || '')}" alt="" data-fallback="user" class="h-full w-full object-cover object-top" /></div>`;
    }

    /** The sold banner above the player on the block: the team's colour on the normal page, green on the projector. */
    function justSoldBanner() {
        const { lot, last_sale: sale } = state;

        if (!sale || !lot) {
            return '';
        }

        if (big) {
            return `<div class="auc-pop mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl ${k.sold} px-4 py-2.5 text-[clamp(1.1rem,2vw,2.5rem)] font-semibold shadow-raised"><span class="rounded bg-white/20 px-2 py-0.5 text-xs font-bold tracking-wide">${esc(t.sold)}</span>${esc(sale.name)} → ${esc(sale.team || '')} · ${pts(sale.amount)} ${esc(t.pts)}</div>`;
        }

        const color = tcValue(sale.team_color) || '#16a34a';

        return `<div class="auc-pop mb-2.5 flex items-center gap-2 rounded-xl px-3 py-2 text-[12.5px] font-semibold text-white shadow-raised" style="background: linear-gradient(90deg, ${color}, color-mix(in srgb, ${color} 55%, #0b2e3f))"><span class="rounded bg-white/25 px-1.5 py-0.5 text-[10px] font-bold tracking-widest">${esc(t.sold)}</span><span class="min-w-0 flex-1 truncate">${esc(sale.name)} → ${esc(sale.team || '')}</span><span class="shrink-0 tabular-nums">${pts(sale.amount)} ${esc(t.pts)}</span></div>`;
    }

    /** The standing bid and who holds it, in the dark card. */
    function bidPanel(lot) {
        const hasBid = lot.current_bid !== null;
        const amount = lot.bids_hidden || !hasBid ? lot.base_price : lot.current_bid;
        const label = lot.bids_hidden || !hasBid ? t.base_price : t.current_bid;
        const key = `${lot.key}:${lot.current_bid}`;
        const bump = key !== lastBidKey;
        lastBidKey = key;

        let holder;
        if (lot.bids_hidden) {
            holder = `<span class="text-xs font-medium text-white/60">${esc(t.bidding)}</span>`;
        } else if (hasBid && lot.leading_team) {
            const top = (lot.bidders || [])[0];
            holder = `<span class="auc-chip inline-flex max-w-[11.5rem] items-center gap-1.5 rounded-full py-1 pl-1 pr-3 text-xs font-bold text-white" ${tc(lot.leading_color)}>${logo(top && top.logo, 'h-5 w-5')}<span class="truncate">${esc(lot.leading_team)}</span></span>`;
        } else {
            holder = `<span class="text-xs font-medium text-white/60">${esc(t.no_bid_yet)}</span>`;
        }

        return `
            <div class="mt-3 flex items-end justify-between gap-3 rounded-xl bg-black/25 px-3.5 py-3 ring-1 ring-white/10 lg:mt-0">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-white/55">${esc(label)}</p>
                    <p class="${bump ? 'auc-bump' : ''} text-4xl font-extrabold leading-none tabular-nums sm:text-5xl">${pts(amount)}<span class="ml-1 text-xs font-semibold text-white/45">${esc(t.pts)}</span></p>
                </div>
                <div class="min-w-0 shrink-0 text-right">${holder}</div>
            </div>`;
    }

    /** Every team that has bid on this player: a bar of its best bid against the leader's, in its own colour. */
    function bidBars(lot) {
        const bidders = lot.bidders || [];

        if (lot.bids_hidden || !bidders.length) {
            return '';
        }

        const leaderTop = bidders[0].top || 1;
        const count = lot.bid_count === 1 ? t.bids_one : tr('bids_count', { count: lot.bid_count });

        const rows = bidders.map((bidder, index) => {
            const lead = index === 0;
            const width = Math.max(10, Math.round((bidder.top / leaderTop) * 100));

            return `
                <li class="flex items-center gap-2" ${tc(bidder.color)}>
                    <span class="relative h-6 w-6 shrink-0 overflow-hidden rounded-full bg-white/10 ring-2 ring-[color:var(--tc,#94a3b8)]"><img src="${esc(bidder.logo || '')}" alt="" data-fallback="image" class="h-full w-full object-cover" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2 text-[11px] leading-none">
                            <span class="flex min-w-0 items-center gap-1.5 font-semibold ${lead ? 'text-white' : 'text-white/65'}"><span class="truncate">${esc(bidder.team)}</span>${lead ? `<span class="auc-lead">${crownIcon}${esc(t.leading_badge)}</span>` : ''}</span>
                            <span class="shrink-0 font-bold tabular-nums ${lead ? 'text-white' : 'text-white/65'}">${pts(bidder.top)}</span>
                        </div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full transition-all duration-500" style="width: ${width}%; background: var(--tc, #94a3b8)"></div></div>
                    </div>
                    <span class="w-6 shrink-0 text-right text-[10px] tabular-nums text-white/45">×${bidder.count}</span>
                </li>`;
        }).join('');

        const recent = lot.bids.length > 1
            ? `<div class="mt-2.5 flex items-center gap-1.5 overflow-x-auto pb-0.5 text-[10.5px] [scrollbar-width:none]"><span class="shrink-0 text-white/45">${esc(t.latest_bids)}</span>${lot.bids.slice(0, 6).map((bid) => `<span class="auc-chip shrink-0 rounded-full px-2 py-0.5 font-semibold tabular-nums text-white" ${tc(bid.color)}>${pts(bid.amount)}</span>`).join('')}</div>`
            : '';

        return `
            <div class="mt-3 border-t border-white/10 pt-3">
                <div class="mb-2 flex items-center justify-between text-[10px] font-semibold uppercase tracking-widest text-white/50"><span>${esc(t.bids)}</span><span>${esc(count)}</span></div>
                <ol class="space-y-2">${rows}</ol>
                ${recent}
            </div>`;
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
               <p class="mt-2 truncate text-[clamp(1.1rem,2.2vw,2.8rem)] font-semibold ${hasBid ? 'text-white' : 'opacity-70'}">${esc(hasBid ? lot.leading_team : t.no_bid_yet)}</p>`;

        const ladderBox = ladder.length
            ? `<div class="min-w-0 self-stretch border-l border-white/10 pl-[clamp(0.75rem,1.5vw,2rem)]"><p class="${label} opacity-60">${esc(t.bids)}</p><ol class="mt-2 space-y-1.5 ${body}">${ladder.map((bid, index) => `<li class="flex justify-between gap-3 ${index === 0 ? 'font-semibold text-white' : 'text-slate-400'}"><span class="flex min-w-0 items-center gap-2"><i class="inline-block h-[0.7em] w-[0.7em] shrink-0 rounded-full" style="background: ${tcValue(bid.color) || '#94a3b8'}"></i><span class="truncate">${esc(bid.team)}</span></span><span class="tabular-nums">${pts(bid.amount)}</span></li>`).join('')}</ol></div>`
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

    /** The player on the block on the normal page: a compact dark card that takes the colour of the leading team. */
    function normalLot(lot) {
        const where = lot.village ? `<p class="mt-1 flex items-center gap-1 text-[11px] text-white/65">${pinIcon}<span class="truncate">${esc(lot.village)}</span></p>` : '';

        return `
            <div class="auc-stage p-3.5 sm:p-4" ${tc(lot.leading_color)}>
                <div class="flex items-center justify-between text-[10px] font-semibold uppercase tracking-[0.18em] text-white/55">
                    <span class="inline-flex items-center gap-1.5"><span class="live-dot" aria-hidden="true"></span>${esc(t.on_the_block)}</span>
                    <span>${esc(tr('round_short', { number: lot.round }))}</span>
                </div>
                <div class="mt-2.5 grid gap-3 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:items-start lg:gap-5">
                    <div class="flex items-start gap-3">
                        ${photoBox(lot, 'h-[4.5rem] w-[4.5rem] sm:h-24 sm:w-24 lg:h-28 lg:w-28')}
                        <div class="min-w-0 flex-1">
                            <h2 class="break-words text-xl font-extrabold leading-tight tracking-tight sm:text-2xl">${esc(lot.name)}</h2>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1">${tags(lot)}</div>
                            ${where}
                            ${pastLine(lot)}
                        </div>
                    </div>
                    <div class="min-w-0">
                        ${bidPanel(lot)}
                        ${bidBars(lot)}
                    </div>
                </div>
            </div>`;
    }

    function stage() {
        const { lot, auction, last_sale: sale } = state;
        const paused = auction.status === 'paused';
        const fill = big ? 'flex flex-1 flex-col items-center justify-center' : '';

        if (paused) {
            return `${justSoldBanner()}<div class="${big ? `${k.panel} ${fill} p-12` : 'rounded-2xl border border-amber-200 bg-amber-50 p-5'} text-center"><p class="${big ? 'text-[clamp(2.5rem,5vw,6rem)]' : 'text-lg'} font-bold ${big ? k.title : 'text-amber-900'}">${esc(t.paused)}</p><p class="mt-2 ${big ? 'text-[clamp(1.1rem,2vw,2.5rem)]' : 'text-[13px]'} ${big ? k.muted : 'text-amber-800'}">${esc(t.paused_hint)}</p></div>`;
        }

        if (lot) {
            return `${justSoldBanner()}${big ? bigLot(lot) : normalLot(lot)}`;
        }

        if (sale && big) {
            return `<div class="auc-pop rounded-2xl ${k.sold} ${fill} p-12 text-center shadow-raised">
                <p class="text-[clamp(1.5rem,2.6vw,3.5rem)] font-bold tracking-[0.3em]">${esc(t.sold)}</p>
                <p class="mt-3 text-[clamp(3rem,6.5vw,8rem)] font-bold">${esc(sale.name)}</p>
                <p class="mt-3 text-[clamp(1.5rem,3.4vw,4.5rem)] font-semibold">${esc(tr('sold_to', { team: sale.team || '' }))}</p>
                <p class="mt-1 text-[clamp(2.5rem,5.5vw,7rem)] font-bold tabular-nums">${esc(tr('for_points', { points: pts(sale.amount) }))}</p>
            </div>`;
        }

        if (sale) {
            return `<div class="auc-stage auc-pop p-5 text-center" ${tc(sale.team_color)}>
                <p class="text-[11px] font-bold tracking-[0.35em] text-white/70">${esc(t.sold)}</p>
                <p class="mt-1.5 break-words text-2xl font-extrabold leading-tight sm:text-3xl">${esc(sale.name)}</p>
                <p class="mt-2 inline-flex max-w-full items-center gap-1.5 rounded-full px-3 py-1 text-[13px] font-bold auc-chip"><span class="truncate">${esc(tr('sold_to', { team: sale.team || '' }))}</span></p>
                <p class="mt-2 text-3xl font-extrabold tabular-nums">${pts(sale.amount)}<span class="ml-1 text-xs font-semibold text-white/50">${esc(t.pts)}</span></p>
            </div>`;
        }

        const hourglass = '<svg class="mx-auto mb-2 h-7 w-7 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h12M6 21h12M7 3c0 5 5 5 5 9s-5 4-5 9M17 3c0 5-5 5-5 9s5 4 5 9" /></svg>';

        return `<div class="${k.panel} ${fill} ${big ? 'p-12' : 'p-5'} text-center">${big ? '' : hourglass}<p class="${big ? 'text-[clamp(2rem,4.2vw,5.5rem)]' : 'text-base sm:text-lg'} font-bold ${k.title}">${esc(t.waiting_next)}</p></div>`;
    }

    // ----- The teams ---------------------------------------------------------------------

    /** "Bat 2 · Bowl 1 · AR 0 · WK 1": what each team has and still lacks. */
    function roleLine(team) {
        const short = t.roles_short || {};

        return Object.keys(short)
            .map((role) => `${esc(short[role])} <b class="tabular-nums ${(team.roles && team.roles[role]) ? k.soft : 'text-amber-600'}">${(team.roles && team.roles[role]) || 0}</b>`)
            .join(' · ');
    }

    function teams() {
        const max = state.auction.max_squad;
        const min = Math.min(state.auction.min_squad || 0, max);
        const chevron = '<svg class="h-4 w-4 shrink-0 opacity-70 transition group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>';

        const rows = state.teams.map((team) => {
            const usedPct = team.purse > 0 ? Math.min(100, Math.round(((team.purse - team.left) / team.purse) * 100)) : 0;
            const leading = state.lot && state.lot.leading_team === team.name;
            const bought = team.players.filter((p) => p.amount !== null);
            const earlier = team.players.filter((p) => p.amount === null);

            // One dot per squad place: bought (the team's colour), still needed to reach the minimum (amber ring), spare.
            const dots = Array.from({ length: max }, (_, i) => `<span class="auc-dot ${i < team.count ? 'auc-dot-on' : i < min ? 'auc-dot-need' : ''}"></span>`).join('');

            // What this auction bought (with the price), then the players who were already in the squad.
            const boughtList = bought.length
                ? `<ul class="space-y-0.5">${bought.map((p) => `<li class="flex justify-between gap-2"><span class="truncate font-medium ${k.title}">${esc(p.name)}${p.village ? ` <span class="font-normal opacity-60">· ${esc(p.village)}</span>` : ''}</span><span class="shrink-0 font-semibold tabular-nums ${k.title}">${pts(p.amount)}</span></li>`).join('')}</ul>`
                : '';
            const earlierList = earlier.length
                ? `<p class="mt-2 text-[10px] font-semibold uppercase tracking-wide ${k.muted}">${esc(t.squad_earlier)} (${earlier.length})</p><p class="mt-0.5 max-h-24 overflow-y-auto leading-relaxed ${k.muted}">${earlier.map((p) => esc(p.name)).join(' · ')}</p>`
                : '';

            return `
                <details class="group auc-team overflow-hidden rounded-xl shadow-card ${leading ? 'auc-glow' : ''}" ${tc(team.color)} data-squad="${esc(team.name)}" ${openSquads.has(team.name) ? 'open' : ''}>
                    <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                        <div class="auc-team-head ${isLight(team.color) ? 'auc-team-head-light' : ''}">
                            <span class="h-8 w-8 shrink-0 overflow-hidden rounded-full bg-white ring-2 ring-white/70"><img src="${esc(team.logo || '')}" alt="" data-fallback="image" class="h-full w-full object-cover" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-1.5"><span class="truncate text-[13px] font-bold leading-tight" title="${esc(team.name)}">${esc(team.name)}</span>${leading ? `<span class="auc-lead auc-lead-on" title="${esc(t.current_bid)}">${crownIcon}</span>` : ''}</span>
                                <span class="block text-[10px] font-medium leading-tight opacity-80">${esc(tr('players_count', { count: team.count, max }))}</span>
                            </span>
                            <span class="shrink-0 text-right leading-tight"><span class="block text-[15px] font-extrabold tabular-nums">${pts(team.left)}</span><span class="block text-[9px] font-semibold uppercase tracking-wide opacity-75">${esc(t.left)}</span></span>
                            ${chevron}
                            <span class="auc-team-used" aria-hidden="true"><span style="width: ${Math.max(usedPct, usedPct > 0 ? 3 : 0)}%"></span></span>
                        </div>
                        <div class="flex items-center gap-2 px-3 py-1.5">
                            <span class="flex flex-1 flex-wrap gap-[3px]" aria-hidden="true">${dots}</span>
                            ${team.still_needed > 0 ? `<span class="shrink-0 text-[10px] font-semibold text-amber-600">${esc(tr('needs_more', { count: team.still_needed }))}</span>` : ''}
                        </div>
                    </summary>
                    <div class="border-t border-black/5 bg-white/80 px-3 py-2 text-[10.5px] ${k.soft}">
                        <div class="flex flex-wrap gap-1">${[['batter', 'Bat'], ['bowler', 'Bowl'], ['all_rounder', 'AR'], ['wicket_keeper', 'WK']].map(([role]) => {
                            const label = (t.roles_short || {})[role] || role;
                            const n = (team.roles && team.roles[role]) || 0;

                            return `<span class="rounded-full px-2 py-0.5 text-[10px] font-semibold ${n ? 'bg-slate-100 text-slate-700' : 'bg-amber-50 text-amber-700'}">${esc(label)} <b class="tabular-nums">${n}</b></span>`;
                        }).join('')}<span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700">${esc(t.spent)} <b class="tabular-nums">${pts(team.spent)}</b></span></div>
                        <div class="mt-1.5 max-h-56 overflow-y-auto pr-0.5">${boughtList}${earlierList}</div>
                    </div>
                </details>`;
        });

        return `
            <section aria-label="${esc(t.teams)}" class="order-2 lg:order-none lg:col-start-2 lg:row-span-3 lg:row-start-1 lg:sticky lg:top-20">
                <h2 class="mb-1.5 flex items-baseline justify-between text-sm font-bold tracking-tight ${k.title}"><span>${esc(t.teams)}</span><span class="text-[10px] font-medium ${k.muted}">${esc(t.left)}</span></h2>
                <div class="space-y-2">${rows.join('')}</div>
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

            const frame = `flex min-h-0 min-w-0 flex-col justify-between overflow-hidden rounded-xl bg-white/5 px-[clamp(0.6rem,1vw,1.3rem)] py-[clamp(0.35rem,0.7vw,0.9rem)] ring-1 ${leading ? 'ring-2 ring-accent-dark' : 'ring-white/10'}`;
            const squad = `${team.count}/${max}`;
            const squadColour = team.still_needed > 0 ? 'text-amber-300' : 'text-slate-400';
            const bar = `<div class="h-[0.4vw] min-h-1 flex-1 overflow-hidden rounded-full bg-white/10" aria-hidden="true"><div class="h-full rounded-full ${k.fill}" style="width: ${usedPct}%"></div></div>`;

            // Many teams: narrower cards, so the name gets the whole first line.
            if (compact) {
                return `
                <div class="${frame}" style="border-left: 0.35vw solid ${tcValue(team.color) || 'transparent'}" title="${esc(tr('players_count', { count: team.count, max }))}">
                    <p class="shrink-0 truncate text-[clamp(0.8rem,1.2vw,1.6rem)] font-semibold leading-tight text-white">${esc(team.name)}</p>
                    <p class="shrink-0 whitespace-nowrap text-[clamp(1.05rem,1.65vw,2.2rem)] font-bold leading-none tabular-nums text-white">${pts(team.left)} <span class="text-[clamp(0.65rem,0.85vw,1.1rem)] font-medium text-slate-400">${esc(t.left)}</span></p>
                    <div class="flex shrink-0 items-center gap-2">${bar}<span class="shrink-0 text-[clamp(0.65rem,0.9vw,1.2rem)] font-medium leading-none tabular-nums ${squadColour}">${squad}</span></div>
                </div>`;
            }

            return `
                <div class="${frame}" style="border-left: 0.35vw solid ${tcValue(team.color) || 'transparent'}" title="${esc(tr('players_count', { count: team.count, max }))}">
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
                    ? `<ul class="mt-2 divide-y ${k.rule} ${body}">${list.map((sale) => `<li class="flex items-center justify-between gap-3 py-[0.35vw]"><span class="min-w-0"><span class="block truncate font-medium ${k.title}">${esc(sale.name)}</span><span class="flex items-center gap-[0.5vw] truncate ${caption} ${k.muted}"><i class="inline-block h-[0.8em] w-[0.8em] shrink-0 rounded-full" style="background: ${tcValue(sale.team_color) || '#94a3b8'}"></i>${esc(sale.team || '')}</span></span><span class="shrink-0 font-bold tabular-nums ${k.title}">${pts(sale.amount)}</span></li>`).join('')}</ul>`
                    : `<p class="mt-2 ${body} ${k.muted}">${esc(t.no_sales)}</p>`}
            </section>`;
    }

    // ----- Sold / upcoming / on hold / unsold -----------------------------------------------

    /** "Mumbai Indians" -> "MI", "Pune" -> "PUN": a short tag for a team inside a chip. */
    function abbr(name) {
        const words = String(name || '').trim().split(/\s+/).filter(Boolean);
        const tag = words.length > 1 ? words.map((word) => word[0]).join('') : (words[0] || '').slice(0, 3);

        return tag.toUpperCase().slice(0, 4);
    }

    function ladderHtml(sig) {
        const ladder = ladders.get(sig);
        let inner;

        if (Array.isArray(ladder) && ladder.length) {
            const last = ladder.length - 1;
            const chips = ladder.map((bid, index) => `<span class="${index === last ? 'auc-chip-win' : 'auc-chip-soft'} inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px]" ${tc(bid.color)} title="${esc(bid.team)}"><b class="font-bold">${esc(abbr(bid.team))}</b><span class="tabular-nums">${pts(bid.amount)}</span></span>`);
            const teamsSeen = [];
            ladder.forEach((bid) => {
                if (!teamsSeen.find((team) => team.team === bid.team)) {
                    teamsSeen.push(bid);
                }
            });

            inner = `
                <div class="flex flex-wrap items-center gap-x-1 gap-y-1.5">${chips.join('<span class="text-[10px] text-slate-300" aria-hidden="true">›</span>')}</div>
                <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[10.5px] text-slate-500">${teamsSeen.map((bid) => `<span class="inline-flex items-center gap-1" ${tc(bid.color)}><i class="inline-block h-2 w-2 rounded-full" style="background: var(--tc, #94a3b8)"></i>${esc(bid.team)}</span>`).join('')}</p>`;
        } else if (ladder === 'error') {
            inner = `<p class="text-amber-600">${esc(t.connection_lost)}</p>`;
        } else {
            inner = `<p class="text-slate-400">${esc(t.loading)}</p>`;
        }

        return `<div class="border-t border-dashed border-line bg-slate-50 px-3 py-2 text-[11.5px]"><p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">${esc(t.bid_history)}</p>${inner}</div>`;
    }

    function saleRow(sale, number) {
        const hasBids = sale.bids !== null && sale.bids > 0;
        const bidsLabel = hasBids ? (sale.bids === 1 ? t.bids_one : tr('bids_count', { count: sale.bids })) : '';
        const stripe = `border-left: 3px solid ${tcValue(sale.team_color) || 'transparent'}`;
        const summary = `
            <span class="w-5 shrink-0 text-center text-[11px] tabular-nums text-slate-400">${number}</span>
            <span class="min-w-0 flex-1"><span class="block truncate text-[13px] font-semibold text-slate-900">${esc(sale.name)}</span><span class="block truncate text-[11px] text-slate-500">${esc([sale.village, sale.team, sale.role].filter(Boolean).join(' · '))}</span></span>
            <span class="shrink-0 text-right"><span class="block text-[13px] font-bold tabular-nums text-slate-900">${pts(sale.amount)} <span class="text-[10px] font-normal text-slate-400">${esc(t.pts)}</span></span>${hasBids ? `<span class="block text-[10px] text-slate-500">${esc(bidsLabel)}</span>` : ''}</span>`;

        if (!hasBids) {
            return `<li class="flex items-center gap-2.5 px-3 py-2" style="${stripe}">${summary}</li>`;
        }

        const sig = `${sale.key}:${sale.amount}:${sale.bids}`;

        return `
            <li style="${stripe}">
                <details class="group" data-sale="${sale.key}" data-sig="${esc(sig)}" ${openSales.has(String(sale.key)) ? 'open' : ''}>
                    <summary class="flex cursor-pointer list-none items-center gap-2.5 px-3 py-2 transition hover:bg-hover [&::-webkit-details-marker]:hidden">${summary}<span class="shrink-0 text-slate-400 transition group-open:rotate-180" aria-hidden="true">▾</span></summary>
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

            return `<li class="flex items-baseline justify-between gap-2 border-b border-line py-2 text-[13px]"><span class="truncate text-slate-900">${esc(name)}</span>${role ? `<span class="shrink-0 text-[11px] text-slate-400">${esc(role)}</span>` : ''}</li>`;
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

        const buttons = definitions.map(([id, label, count]) => `<button type="button" role="tab" aria-selected="${tab === id}" data-tab="${id}" class="-mb-px whitespace-nowrap border-b-2 px-3 py-2.5 text-[12.5px] font-semibold transition focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand ${tab === id ? 'border-brand text-brand' : 'border-transparent text-slate-500 hover:text-slate-900'}">${esc(label)} <span class="ml-0.5 rounded-full px-1.5 text-[11px] tabular-nums ${tab === id ? 'bg-brand-soft text-brand' : 'bg-slate-100 text-slate-500'}">${count}</span></button>`).join('');

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
            <div class="grid grid-cols-[minmax(0,1fr)] items-start gap-3 lg:grid-cols-[minmax(0,1fr)_19rem] lg:grid-rows-[auto_auto_1fr]">
                <div class="order-1 min-w-0 lg:col-start-1 lg:row-start-1">${stage()}</div>
                ${teams()}
                <div class="order-3 min-w-0 lg:col-start-1 lg:row-start-2">${overview()}</div>
                <div class="order-4 min-w-0 lg:col-start-1 lg:row-start-3">${lists()}</div>
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

    // Share the live page: the phone's own share sheet when there is one, else copy the link.
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-share]');
        if (!button) {
            return;
        }

        const url = `${window.location.origin}${window.location.pathname}`;

        try {
            if (navigator.share) {
                await navigator.share({ title: document.title, url });

                return;
            }

            await navigator.clipboard.writeText(url);
            const label = button.textContent;
            button.textContent = t.link_copied;
            window.setTimeout(() => { button.textContent = label; }, 1600);
        } catch (error) {
            // Cancelled or not allowed: nothing to do.
        }
    });
}
