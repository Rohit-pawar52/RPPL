/**
 * Generic delete-confirmation wiring for any form marked
 * data-confirm-delete, using the SweetAlert confirmAction() helper
 * already set up in flash.js. Reused by every admin module's delete
 * button — no per-module SweetAlert setup needed.
 */
export function initConfirmDeleteForms() {
    document.querySelectorAll('form[data-confirm-delete]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();

            window.confirmAction({
                title: form.dataset.confirmTitle,
                text: form.dataset.confirmText,
                confirmButtonText: 'Yes, delete',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
}

/**
 * Same pattern as initConfirmDeleteForms(), for a non-destructive but
 * still consequential action (e.g. Send/Resend notification, Phase B4)
 * — a separate attribute/function rather than generalizing the delete
 * one, since "Yes, delete" is never the right confirm-button label here.
 */
export function initConfirmActionForms() {
    document.querySelectorAll('form[data-confirm-action]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();

            window.confirmAction({
                title: form.dataset.confirmTitle,
                text: form.dataset.confirmText,
                confirmButtonText: form.dataset.confirmButtonText ?? 'Yes, continue',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
}
