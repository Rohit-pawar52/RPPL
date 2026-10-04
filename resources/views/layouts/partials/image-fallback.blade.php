{{--
    A picture that cannot be loaded (the file is gone, the link is broken,
    the file is damaged) must never show a broken-image icon or its alt text:
    it is swapped for one of the two default pictures in public/images -
    default-user.jpeg for a person (an <img data-fallback="user">), default.png
    for everything else. The server already picks the default when a path is
    empty or the file is missing (App\Support\Media); this covers what only
    the browser can tell.

    Inline and first in the <head> on purpose: it must be listening before any
    picture starts loading, so no failure is missed, and it needs no bundle.
    Sponsor ads are left alone (data-ad): a sponsor that cannot load removes
    its own slot instead.
--}}
<meta name="default-image" content="{{ \App\Support\Media::defaultUrl('image') }}">
<meta name="default-user-image" content="{{ \App\Support\Media::defaultUrl('user') }}">
<style>img { color: transparent; }</style>
<script>
    (function () {
        var meta = function (name) {
            var tag = document.querySelector('meta[name="' + name + '"]');
            return tag ? tag.content : '';
        };

        var swap = function (img) {
            if (!img || img.tagName !== 'IMG' || img.dataset.fallbackApplied || img.closest('[data-ad]') || img.closest('[data-no-fallback]')) { return; }

            var url = meta(img.dataset.fallback === 'user' ? 'default-user-image' : 'default-image');
            if (!url) { return; }

            img.dataset.fallbackApplied = '1';
            img.removeAttribute('srcset');
            img.alt = '';
            img.src = url;
        };

        // A picture that fails while the page is loading, or later.
        document.addEventListener('error', function (event) {
            swap(event.target);
        }, true);

        // One that had already failed before this page's scripts ran, or has no source at all.
        var sweep = function (root) {
            (root.querySelectorAll ? root.querySelectorAll('img') : []).forEach(function (img) {
                var src = img.getAttribute('src');
                if (!src || (img.complete && img.naturalWidth === 0)) { swap(img); }
            });
        };

        document.addEventListener('DOMContentLoaded', function () {
            sweep(document);

            // Pictures a page draws later (the live auction, for one).
            new MutationObserver(function (records) {
                records.forEach(function (record) {
                    record.addedNodes.forEach(function (node) {
                        if (node.nodeType !== 1) { return; }
                        if (node.tagName === 'IMG') { if (!node.getAttribute('src')) { swap(node); } } else { sweep(node); }
                    });
                });
            }).observe(document.body, { childList: true, subtree: true });
        });
    })();
</script>
