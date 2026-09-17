import Swal from 'sweetalert2';

const SWAL_ICON_BY_FLASH_TYPE = {
    success: 'success',
    error: 'error',
    warning: 'warning',
    info: 'info',
};

/**
 * Reads `window.flash` (populated inline by the admin/guest layouts from
 * the current request's session flash data) and shows each present
 * message as a SweetAlert2 toast. This is the ONE place flash messages
 * are turned into SweetAlert popups, so no view needs to duplicate this
 * wiring.
 */
export function initFlashMessages() {
    const flash = window.flash;

    if (!flash) {
        return;
    }

    Object.entries(flash).forEach(([type, message]) => {
        if (!message) {
            return;
        }

        Swal.fire({
            icon: SWAL_ICON_BY_FLASH_TYPE[type] ?? 'info',
            text: message,
            toast: true,
            position: 'top-end',
            timer: 3500,
            timerProgressBar: true,
            showConfirmButton: false,
        });
    });
}

/**
 * Reusable confirmation dialog for future destructive actions (delete
 * flows, etc). Exposed globally as window.confirmAction so any Blade
 * view can use it without importing/duplicating SweetAlert setup.
 *
 * @returns {Promise<import('sweetalert2').SweetAlertResult>}
 */
export function confirmAction({ title, text, confirmButtonText = 'Yes, continue' } = {}) {
    return Swal.fire({
        title: title ?? 'Are you sure?',
        text: text ?? 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        focusCancel: true,
    });
}
