{{--
    Simple in-page image viewer shared by the Photos gallery and the News
    detail page. Any <a class="rppl-photo-link" href="FULL_IMAGE_URL"
    data-title="..."> on the page opens in the dialog below; without JS (or
    without <dialog> support) the link just opens the image itself.
--}}
<style>
    #rppl-photo-dialog::backdrop { background: rgba(8, 17, 28, 0.85); }
</style>
<dialog id="rppl-photo-dialog" aria-label="{{ __('directory.photos.title') }}" style="max-width:94vw;max-height:94vh;padding:0.75rem;border:0;border-radius:0.75rem;background:#fff;">
    <img id="rppl-photo-dialog-image" alt="" style="display:block;max-width:100%;max-height:78vh;margin:0 auto;border-radius:0.5rem;" />
    <p id="rppl-photo-dialog-title" class="mt-3 text-center text-[14px] font-semibold text-slate-900"></p>
    <form method="dialog" class="mt-3 text-center">
        <button type="submit" class="pub-btn-outline min-h-10 px-5">{{ __('directory.photos.close') }}</button>
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
