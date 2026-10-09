// Admin-panel text for JavaScript. English is the source language: write t('Saving...') and, when the
// signed-in user works in Hindi, the page has already put the Hindi text in window.RPPL_T (see
// resources/views/layouts/partials/admin-js-strings.blade.php). A phrase without a translation is
// shown as written. ":name" placeholders are filled from the second argument:
//   t('Sold to :team for :amount', { team: 'MI', amount: '1,000' })
export function t(text, replacements = {}) {
    const dictionary = window.RPPL_T || {};
    let result = Object.prototype.hasOwnProperty.call(dictionary, text) ? dictionary[text] : text;

    Object.entries(replacements).forEach(([name, value]) => {
        result = result.split(`:${name}`).join(String(value));
    });

    return result;
}

window.rpplT = t;
