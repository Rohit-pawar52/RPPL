/**
 * Admin quality-of-life behaviour, vanilla JS:
 *  - the Ctrl+K / Cmd+K (or "/") "jump to" palette: type a few letters, press
 *    Enter, land on any page or "create" task the signed-in user may open.
 *    Its entries are rendered by the layout as two JSON blocks
 *    (#admin-palette-pages and #admin-quick-actions), so it can only ever list
 *    what the navigation and permissions already allow.
 *  - a long form's first invalid field is scrolled into view with the error.
 * Nothing here is required for the pages to work.
 * Its texts go through t() (resources/js/i18n.js) so they follow the signed-in user's language.
 */
import { t } from './i18n';

export function initAdminUi() {
    initPalette();
    focusFirstError();
}

function readJson(id) {
    try {
        return JSON.parse(document.getElementById(id)?.textContent || '[]');
    } catch (e) {
        return [];
    }
}

function initPalette() {
    const dialog = document.getElementById('admin-palette');
    const input = document.getElementById('admin-palette-input');
    const list = document.getElementById('admin-palette-list');

    if (!dialog || !input || !list || typeof dialog.showModal !== 'function') {
        return;
    }

    const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
    document.querySelectorAll('[data-kbd]').forEach((kbd) => {
        kbd.textContent = isMac ? '⌘ K' : 'Ctrl K';
    });

    const actions = readJson('admin-quick-actions').map((item) => ({ ...item, kind: 'Create', meta: item.hint }));
    const pages = readJson('admin-palette-pages').map((item) => ({ ...item, kind: 'Go to', meta: item.group || '' }));
    const all = [...actions, ...pages];
    let shown = [];
    let active = 0;

    const kindLabel = (item) => (item.kind === 'Create' ? t('Create') : t('Go to'));

    const score = (item, query) => {
        const label = item.label.toLowerCase();
        const haystack = `${label} ${(item.meta || '').toLowerCase()} ${item.kind.toLowerCase()} ${kindLabel(item).toLowerCase()}`;

        if (label.startsWith(query)) {
            return 3;
        }
        if (label.split(/\s+/).some((word) => word.startsWith(query))) {
            return 2;
        }
        return haystack.includes(query) ? 1 : 0;
    };

    const render = () => {
        const query = input.value.trim().toLowerCase();

        shown = query
            ? all
                .map((item) => ({ item, s: score(item, query) }))
                .filter((entry) => entry.s > 0)
                .sort((a, b) => b.s - a.s)
                .map((entry) => entry.item)
            : all;
        active = 0;
        list.textContent = '';

        if (shown.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'px-3 py-8 text-center text-[13px] text-slate-500';
            empty.textContent = t('Nothing matches that. Try another word.');
            list.appendChild(empty);
            return;
        }

        let lastKind = null;

        shown.forEach((item, index) => {
            if (!query && item.kind !== lastKind) {
                const heading = document.createElement('p');
                heading.className = 'adm-menu-label';
                heading.textContent = item.kind === 'Create' ? t('Start something new') : t('Go to a page');
                list.appendChild(heading);
                lastKind = item.kind;
            }

            const row = document.createElement('a');
            row.href = item.url;
            row.id = `admin-palette-opt-${index}`;
            row.className = 'adm-palette-item';
            row.setAttribute('role', 'option');
            row.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
            row.dataset.index = String(index);

            const kind = document.createElement('span');
            kind.className = 'w-14 shrink-0 text-[10px] font-semibold uppercase tracking-wide text-slate-400';
            kind.textContent = kindLabel(item);

            const label = document.createElement('span');
            label.className = 'min-w-0 flex-1 truncate font-medium';
            label.textContent = item.label;

            const meta = document.createElement('span');
            meta.className = 'hidden shrink-0 truncate text-xs text-slate-400 sm:block sm:max-w-48';
            meta.textContent = item.meta || '';

            const go = document.createElement('span');
            go.className = 'adm-palette-go text-slate-400 opacity-0';
            go.textContent = '↵';

            row.append(kind, label, meta, go);
            row.addEventListener('mousemove', () => select(index));
            list.appendChild(row);
        });
    };

    const select = (index) => {
        const options = list.querySelectorAll('[role="option"]');

        if (options.length === 0) {
            return;
        }

        active = (index + options.length) % options.length;
        options.forEach((option, i) => option.setAttribute('aria-selected', i === active ? 'true' : 'false'));
        options[active].scrollIntoView({ block: 'nearest' });
        input.setAttribute('aria-activedescendant', options[active].id);
    };

    const open = () => {
        if (dialog.open) {
            return;
        }
        input.value = '';
        render();
        dialog.showModal();
        input.focus();
    };

    document.querySelectorAll('#admin-palette-open, [data-palette-open]').forEach((button) => button.addEventListener('click', open));
    dialog.querySelectorAll('[data-palette-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => {
        // A click on the dimmed area outside the box closes it.
        if (event.target === dialog) {
            dialog.close();
        }
    });

    input.addEventListener('input', render);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            select(active + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            select(active - 1);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const option = list.querySelectorAll('[role="option"]')[active];
            if (option) {
                window.location.href = option.href;
            }
        }
    });

    document.addEventListener('keydown', (event) => {
        const typing = event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable="true"]');

        if ((event.key === 'k' || event.key === 'K') && (event.ctrlKey || event.metaKey)) {
            event.preventDefault();
            dialog.open ? dialog.close() : open();
        } else if (event.key === '/' && !typing && !event.ctrlKey && !event.metaKey && !event.altKey) {
            event.preventDefault();
            open();
        }
    });
}

/** After a failed save the first field with an error is brought into view and focused. */
function focusFirstError() {
    const field = document.querySelector('main [aria-invalid="true"]');

    if (!field || document.activeElement?.matches?.('input, select, textarea')) {
        return;
    }

    field.scrollIntoView({ block: 'center', behavior: 'smooth' });

    if (typeof field.focus === 'function' && field.type !== 'file') {
        field.focus({ preventScroll: true });
    }
}
