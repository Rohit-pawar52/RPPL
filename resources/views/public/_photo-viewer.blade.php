{{--
    Simple in-page image viewer shared by the Photos gallery and the News
    detail page. Any <a class="rppl-photo-link" href="FULL_IMAGE_URL"
    data-title="..."> on the page opens in the dialog below; without JS (or
    without <dialog> support) the link just opens the image itself.
--}}
<dialog id="rppl-photo-dialog" style="max-width:94vw;max-height:94vh;padding:0.75rem;border:0;border-radius:0.5rem;">
    <img id="rppl-photo-dialog-image" alt="" style="display:block;max-width:100%;max-height:80vh;margin:0 auto;" />
    <p id="rppl-photo-dialog-title" class="mt-2 text-center text-[13px] font-medium text-neutral-800"></p>
    <form method="dialog" class="mt-2 text-center">
        <button type="submit" class="rounded-md border border-neutral-300 px-3 py-1 text-[12px] font-medium text-neutral-600">{{ __('directory.photos.close') }}</button>
    </form>
</dialog>

<script>
    (function () {
        var dialog = document.getElementById('rppl-photo-dialog');
        if (! dialog || typeof dialog.showModal !== 'function') {
            return;
        }
        document.querySelectorAll('a.rppl-photo-link').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                document.getElementById('rppl-photo-dialog-image').src = link.href;
                document.getElementById('rppl-photo-dialog-title').textContent = link.dataset.title || '';
                dialog.showModal();
            });
        });
        // Clicking the dimmed area outside the image closes it.
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    })();
</script>
