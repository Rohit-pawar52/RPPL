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

        const icon = SWAL_ICON_BY_FLASH_TYPE[type] ?? 'info';

        Swal.fire({
            icon,
            text: message,
            toast: true,
            position: 'top-end',
            // Errors stay a little longer so they can actually be read.
            timer: icon === 'error' ? 6000 : 3500,
            timerProgressBar: true,
            showConfirmButton: false,
            showCloseButton: true,
            customClass: { popup: 'rppl-toast', container: 'rppl-toast-container' },
        });
    });
}

/**
 * Reusable confirmation dialog for destructive or consequential actions.
 * Exposed globally as window.confirmAction so any Blade view can use it
 * without importing/duplicating SweetAlert setup. Its buttons are the
 * regular .btn family, so they follow the admin-set button colour and
 * shape; `danger: true` (used by the delete forms) makes the confirm
 * button red.
 *
 * @returns {Promise<import('sweetalert2').SweetAlertResult>}
 */
export function confirmAction({ title, text, confirmButtonText = 'Yes, continue', danger = false } = {}) {
    return Swal.fire({
        title: title ?? 'Are you sure?',
        text: text ?? 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        focusCancel: true,
        buttonsStyling: false,
        customClass: {
            popup: 'rppl-dialog',
            confirmButton: danger ? 'btn btn-danger' : 'btn btn-primary',
            cancelButton: 'btn btn-secondary',
        },
    });
}
