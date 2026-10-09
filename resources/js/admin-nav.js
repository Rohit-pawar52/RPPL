/**
 * Admin sidebar behaviour — vanilla JS, no framework.
 *  - Below lg the sidebar is a drawer (toggle button, backdrop, Esc to
 *    close, focus moved in and returned, page scroll locked while open).
 *  - From lg up it can collapse to an icon rail; the choice is remembered
 *    in localStorage and mirrored on <html data-sidebar="collapsed"> (an
 *    inline script in the layout applies it before first paint). In the
 *    rail every link shows its name in a floating label on hover/focus.
 *  - Account dropdowns (<details data-dropdown>) close on an outside click.
 * All storage access is wrapped: private windows/blocked storage just mean
 * the preference isn't remembered.
 */
import { t } from './i18n';

const STORAGE_KEY = 'rppl.admin.sidebar';
const DESKTOP = window.matchMedia('(min-width: 1024px)');

export function initAdminSidebar() {
    const sidebar = document.getElementById('admin-sidebar');
    const backdrop = document.getElementById('admin-backdrop');
    const toggle = document.getElementById('admin-sidebar-toggle');
    const collapseButton = document.getElementById('admin-sidebar-collapse');
    const root = document.documentElement;

    initDropdowns();

    if (!sidebar) {
        return;
    }

    // ----- Collapsed rail (desktop) -----
    const isCollapsed = () => root.dataset.sidebar === 'collapsed';
    const isRail = () => isCollapsed() && DESKTOP.matches;
    const tips = initRailTips(sidebar, isRail);

    const syncCollapseButton = () => {
        if (collapseButton) {
            collapseButton.setAttribute('aria-pressed', isCollapsed() ? 'true' : 'false');
            collapseButton.dataset.tip = isCollapsed() ? t('Expand sidebar') : t('Collapse sidebar');
            collapseButton.setAttribute('aria-label', collapseButton.dataset.tip);
        }
    };

    const setCollapsed = (collapsed) => {
        if (collapsed) {
            root.dataset.sidebar = 'collapsed';
        } else {
            delete root.dataset.sidebar;
        }

        try {
            if (collapsed) {
                localStorage.setItem(STORAGE_KEY, 'collapsed');
            } else {
                localStorage.removeItem(STORAGE_KEY);
            }
        } catch (e) {
            // Preference just isn't remembered.
        }

        syncCollapseButton();
        tips.hide();
    };

    collapseButton?.addEventListener('click', () => setCollapsed(!isCollapsed()));

    // In the rail a group's icon can't show its pages, so clicking it
    // expands the sidebar (the group itself opens through the native
    // <details> toggle).
    sidebar.querySelectorAll('.adm-group > summary').forEach((summary) => {
        summary.addEventListener('click', () => {
            if (isRail()) {
                setCollapsed(false);
            }
        });
    });

    syncCollapseButton();
    DESKTOP.addEventListener('change', () => {
        syncCollapseButton();
        tips.hide();
    });

    // ----- Drawer (below lg) -----
    if (!toggle) {
        return;
    }

    const isOpen = () => !sidebar.classList.contains('-translate-x-full');

    const open = () => {
        sidebar.classList.remove('-translate-x-full');
        backdrop?.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        toggle.setAttribute('aria-expanded', 'true');
        sidebar.querySelector('a[aria-current="page"], nav a')?.focus();
    };

    const close = (returnFocus = true) => {
        sidebar.classList.add('-translate-x-full');
        backdrop?.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        toggle.setAttribute('aria-expanded', 'false');
        if (returnFocus) {
            toggle.focus();
        }
    };

    toggle.addEventListener('click', () => (isOpen() ? close() : open()));
    backdrop?.addEventListener('click', () => close());
    sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => close(false)));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen() && !DESKTOP.matches) {
            close();
        }
    });

    // Growing past the breakpoint while the drawer is open: reset it.
    DESKTOP.addEventListener('change', (event) => {
        if (event.matches && isOpen()) {
            close(false);
        }
    });
}

/**
 * One floating label (position: fixed, so the sidebar's own scrolling and
 * clipping never cuts it off) shared by every sidebar link while the
 * sidebar is an icon rail.
 */
function initRailTips(sidebar, isRail) {
    const tip = document.createElement('div');
    tip.className = 'sb-tip';
    tip.setAttribute('role', 'tooltip');
    document.body.appendChild(tip);

    const hide = () => tip.removeAttribute('data-show');

    const show = (element) => {
        const text = element.dataset.tip;

        if (!text || !isRail()) {
            return;
        }

        const box = element.getBoundingClientRect();
        tip.textContent = text;
        tip.style.left = `${Math.round(box.right + 10)}px`;
        tip.style.top = `${Math.round(box.top + (box.height - tip.offsetHeight) / 2)}px`;
        tip.setAttribute('data-show', '');
    };

    const target = (event) => (event.target instanceof Element ? event.target.closest('[data-tip]') : null);

    sidebar.addEventListener('mouseover', (event) => {
        const element = target(event);
        element ? show(element) : hide();
    });
    sidebar.addEventListener('mouseleave', hide);
    sidebar.addEventListener('focusin', (event) => {
        const element = target(event);
        element ? show(element) : hide();
    });
    sidebar.addEventListener('focusout', hide);
    sidebar.querySelector('.sb-nav')?.addEventListener('scroll', hide, { passive: true });

    return { hide };
}

function initDropdowns() {
    const dropdowns = document.querySelectorAll('details[data-dropdown]');

    if (dropdowns.length === 0) {
        return;
    }

    document.addEventListener('click', (event) => {
        dropdowns.forEach((dropdown) => {
            if (dropdown.open && !dropdown.contains(event.target)) {
                dropdown.open = false;
            }
        });
    });

    // Opening one menu closes any other.
    dropdowns.forEach((dropdown) => {
        dropdown.addEventListener('toggle', () => {
            if (!dropdown.open) {
                return;
            }

            dropdowns.forEach((other) => {
                if (other !== dropdown) {
                    other.open = false;
                }
            });
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            dropdowns.forEach((dropdown) => {
                if (dropdown.open) {
                    dropdown.open = false;
                    dropdown.querySelector('summary')?.focus();
                }
            });
        }
    });
}
