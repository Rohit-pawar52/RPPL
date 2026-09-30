/**
 * Admin sidebar behaviour — vanilla JS, no framework.
 *  - Below lg the sidebar is a drawer (toggle button, backdrop, Esc to
 *    close, focus moved in and returned, page scroll locked while open).
 *  - From lg up it can collapse to an icon rail; the choice is remembered
 *    in localStorage and mirrored on <html data-sidebar="collapsed"> (an
 *    inline script in the layout applies it before first paint).
 *  - Account dropdowns (<details data-dropdown>) close on an outside click.
 * All storage access is wrapped: private windows/blocked storage just mean
 * the preference isn't remembered.
 */
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

    const applyTooltips = () => {
        // Labels are hidden in the rail, so show them as native tooltips.
        sidebar.querySelectorAll('[data-tip]').forEach((element) => {
            if (isCollapsed() && DESKTOP.matches) {
                element.setAttribute('title', element.dataset.tip);
            } else {
                element.removeAttribute('title');
            }
        });

        if (collapseButton) {
            collapseButton.setAttribute('aria-pressed', isCollapsed() ? 'true' : 'false');
            collapseButton.dataset.tip = isCollapsed() ? 'Expand sidebar' : 'Collapse sidebar';
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

        applyTooltips();
    };

    collapseButton?.addEventListener('click', () => setCollapsed(!isCollapsed()));

    // In the rail a group's icon can't show its pages, so clicking it
    // expands the sidebar (the group itself opens through the native
    // <details> toggle).
    sidebar.querySelectorAll('.adm-group > summary').forEach((summary) => {
        summary.addEventListener('click', () => {
            if (isCollapsed() && DESKTOP.matches) {
                setCollapsed(false);
            }
        });
    });

    applyTooltips();
    DESKTOP.addEventListener('change', applyTooltips);

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
