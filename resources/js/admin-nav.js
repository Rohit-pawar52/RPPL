/**
 * Minimal vanilla-JS mobile drawer toggle for the admin sidebar. No
 * framework (e.g. Alpine) is needed for this small amount of behavior.
 */
export function initAdminSidebar() {
    const sidebar = document.getElementById('admin-sidebar');
    const backdrop = document.getElementById('admin-backdrop');
    const toggle = document.getElementById('admin-sidebar-toggle');

    if (!sidebar || !toggle) {
        return;
    }

    const isOpen = () => !sidebar.classList.contains('-translate-x-full');

    const open = () => {
        sidebar.classList.remove('-translate-x-full');
        backdrop?.classList.remove('hidden');
        toggle.setAttribute('aria-expanded', 'true');
    };

    const close = () => {
        sidebar.classList.add('-translate-x-full');
        backdrop?.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', () => (isOpen() ? close() : open()));
    backdrop?.addEventListener('click', close);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            close();
        }
    });
}
