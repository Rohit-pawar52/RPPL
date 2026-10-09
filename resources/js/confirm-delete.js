import { t } from './i18n';

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
                confirmButtonText: t('Yes, delete'),
                danger: true,
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
                confirmButtonText: form.dataset.confirmButtonText ?? t('Yes, continue'),
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
}

/**
 * Generic "submit lock": once a form has actually been submitted, its
 * submit button(s) are disabled and show a loading label so a double
 * click can't post twice. Runs in the bubbling phase on document, so any
 * handler that intercepted the submit (confirm dialogs call
 * preventDefault and later form.submit(), which fires no submit event)
 * is respected automatically. Opt out per form with `data-no-lock`; GET
 * forms and forms opening in another tab/window are never locked.
 * Buttons are restored on bfcache restore and after a safety timeout
 * (covers downloads, where the page stays put).
 */
const LOCK_RESTORE_MS = 10000;

function lockSubmittedForm(event) {
    const form = event.target;

    if (event.defaultPrevented
        || !(form instanceof HTMLFormElement)
        || form.method.toLowerCase() === 'get'
        || form.hasAttribute('data-no-lock')
        || (form.target && form.target !== '_self')
        || event.submitter?.formTarget === '_blank'
        || event.submitter?.hasAttribute('data-no-lock')) {
        return;
    }

    // Disable on the next tick: the browser builds the form data right
    // after this event, and a disabled submitter would be left out of it.
    setTimeout(() => {
        const buttons = form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]');

        buttons.forEach((button) => {
            if (button.disabled) {
                return;
            }

            const isSubmitter = button === event.submitter;
            const original = button.tagName === 'INPUT' ? button.value : button.innerHTML;

            if (isSubmitter && button.tagName === 'BUTTON' && !button.querySelector('svg')) {
                button.textContent = button.dataset.loadingText ?? t('Saving…');
            } else if (isSubmitter && button.tagName === 'INPUT') {
                button.value = button.dataset.loadingText ?? t('Saving…');
            }

            button.disabled = true;
            button.classList.add('opacity-60', 'cursor-not-allowed');

            const restore = () => {
                if (button.tagName === 'INPUT') {
                    button.value = original;
                } else {
                    button.innerHTML = original;
                }
                button.disabled = false;
                button.classList.remove('opacity-60', 'cursor-not-allowed');
            };

            window.addEventListener('pageshow', (e) => e.persisted && restore(), { once: true });
            setTimeout(restore, LOCK_RESTORE_MS);
        });
    }, 0);
}

document.addEventListener('submit', lockSubmittedForm);
