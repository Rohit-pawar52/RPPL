{{-- A full-screen viewer for the photo / payment screenshot thumbnails: any link
     with data-lightbox (href = the full picture, data-caption = the label) opens
     here instead of a new tab, so a screenshot can be read and the next one
     opened in one tap. The picture is added by the script (never written here),
     and without JS the link simply opens the picture. --}}
<dialog id="ops-lightbox" class="m-auto max-h-[94vh] w-[min(96vw,56rem)] overflow-hidden rounded-2xl border-0 bg-navy-950 p-0 text-white shadow-pop backdrop:bg-navy-950/80 backdrop:backdrop-blur-sm">
    <div class="flex items-center justify-between gap-3 px-4 py-3">
        <p id="ops-lightbox-caption" class="min-w-0 truncate text-sm font-semibold"></p>
        <div class="flex shrink-0 items-center gap-2">
            <a id="ops-lightbox-open" href="#" target="_blank" rel="noopener" class="btn btn-sm border border-white/20 text-white hover:bg-white/10">{{ __('Open original') }}</a>
            <button type="button" id="ops-lightbox-close" class="btn btn-sm btn-icon border border-white/20 text-white hover:bg-white/10" aria-label="{{ __('Close') }}">
                <x-ops.icon name="x" class="h-4 w-4" />
            </button>
        </div>
    </div>
    <div id="ops-lightbox-stage" class="flex max-h-[80vh] items-center justify-center overflow-auto bg-black/30 p-2" style="touch-action: pan-x pan-y pinch-zoom;"></div>
</dialog>

<script>
    (function () {
        if (window.__opsLightbox) { return; }
        window.__opsLightbox = true;
        var dialog = document.getElementById('ops-lightbox');
        if (!dialog || typeof dialog.showModal !== 'function') { return; }
        var stage = document.getElementById('ops-lightbox-stage');
        document.addEventListener('click', function (event) {
            var link = event.target.closest('[data-lightbox]');
            if (link) {
                event.preventDefault();
                stage.textContent = '';
                var img = document.createElement('img');
                img.src = link.getAttribute('href');
                img.alt = link.dataset.caption || '';
                img.className = 'max-h-[78vh] max-w-full rounded-lg object-contain';
                img.setAttribute('data-fallback', 'image');
                stage.appendChild(img);
                document.getElementById('ops-lightbox-caption').textContent = link.dataset.caption || '';
                document.getElementById('ops-lightbox-open').href = link.getAttribute('href');
                dialog.showModal();
                return;
            }
            if (event.target === dialog || event.target.closest('#ops-lightbox-close')) { dialog.close(); }
        });
        dialog.addEventListener('close', function () { stage.textContent = ''; });
    })();
</script>
