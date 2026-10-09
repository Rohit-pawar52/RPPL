{{--
    Simple in-page image viewer shared by the Photos gallery and the News
    detail page. Any <a class="rppl-photo-link" href="FULL_IMAGE_URL"
    data-title="..."> on the page opens in the dialog below, with previous /
    next (buttons, arrow keys or a swipe) through every picture of the page;
    without JS (or without <dialog> support) the link just opens the image
    itself.
--}}
<dialog id="rppl-photo-dialog" aria-label="{{ __('directory.photos.title') }}">
    <div class="flex items-center justify-between gap-3 px-3 py-3 sm:px-5">
        <p id="rppl-photo-dialog-count" class="text-xs font-semibold tabular-nums text-white/70" aria-live="polite"></p>
        <form method="dialog">
            <button type="submit" class="pc-lb-btn" aria-label="{{ __('directory.photos.close') }}">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
            </button>
        </form>
    </div>

    <div class="relative flex min-h-0 flex-1 items-center justify-center px-3 sm:px-16" data-lightbox-stage>
        <button type="button" class="pc-lb-btn absolute left-2 top-1/2 z-10 -translate-y-1/2 sm:left-4" data-lightbox-prev aria-label="{{ __('ux_public_content.viewer.previous') }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
        </button>
        <img id="rppl-photo-dialog-image" alt="" class="max-h-full max-w-full select-none rounded-lg object-contain shadow-pop" />
        <button type="button" class="pc-lb-btn absolute right-2 top-1/2 z-10 -translate-y-1/2 sm:right-4" data-lightbox-next aria-label="{{ __('ux_public_content.viewer.next') }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7" /></svg>
        </button>
    </div>

    <p id="rppl-photo-dialog-title" class="px-4 pb-5 pt-3 text-center text-sm font-semibold text-white"></p>
</dialog>

<script>
    (function () {
        var dialog = document.getElementById('rppl-photo-dialog');
        if (! dialog || typeof dialog.showModal !== 'function') {
            return;
        }

        var links = Array.prototype.slice.call(document.querySelectorAll('a.rppl-photo-link'));
        var image = document.getElementById('rppl-photo-dialog-image');
        var title = document.getElementById('rppl-photo-dialog-title');
        var count = document.getElementById('rppl-photo-dialog-count');
        var prev = dialog.querySelector('[data-lightbox-prev]');
        var next = dialog.querySelector('[data-lightbox-next]');
        var current = 0;

        var show = function (index) {
            current = (index + links.length) % links.length;
            image.src = links[current].href;
            title.textContent = links[current].dataset.title || '';
            count.textContent = links.length > 1 ? (current + 1) + ' / ' + links.length : '';
            prev.hidden = next.hidden = links.length < 2;
        };

        links.forEach(function (link, index) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                show(index);
                dialog.showModal();
            });
        });

        prev.addEventListener('click', function () { show(current - 1); });
        next.addEventListener('click', function () { show(current + 1); });

        dialog.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft') { show(current - 1); }
            if (event.key === 'ArrowRight') { show(current + 1); }
        });

        // A swipe moves to the next / previous picture.
        var startX = null;
        dialog.addEventListener('touchstart', function (event) { startX = event.touches[0].clientX; }, { passive: true });
        dialog.addEventListener('touchend', function (event) {
            if (startX === null) { return; }
            var delta = event.changedTouches[0].clientX - startX;
            startX = null;
            if (Math.abs(delta) > 50 && links.length > 1) { show(current + (delta < 0 ? 1 : -1)); }
        }, { passive: true });

        // Clicking the dimmed area around the picture closes it.
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog || event.target.hasAttribute('data-lightbox-stage')) {
                dialog.close();
            }
        });
    })();
</script>
