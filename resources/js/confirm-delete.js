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
