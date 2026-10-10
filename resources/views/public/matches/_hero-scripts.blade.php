{{-- Share button (data-mx-share) and "starts in" countdown (data-mx-countdown) of a match page. Safe to include before the elements it serves. --}}
<script>
    (function () {
        // Copy for a browser without the Clipboard API (or a page that is not https): a hidden field and the old copy command.
        function copyFallback(text) {
            var field = document.createElement('textarea');
            field.value = text;
            field.setAttribute('readonly', '');
            field.style.cssText = 'position:fixed;top:0;left:0;opacity:0;';
            document.body.appendChild(field);
            field.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            document.body.removeChild(field);
            return ok;
        }

        function copied(button) {
            var original = button.getAttribute('title');
            button.setAttribute('title', button.dataset.copied);
            button.classList.add('is-done');
            setTimeout(function () { button.setAttribute('title', original); button.classList.remove('is-done'); }, 1800);
        }

        // Share: the phone's share sheet when there is one, otherwise copy the link. One listener for the whole page,
        // so it works however late the button is drawn.
        document.addEventListener('click', function (event) {
            var button = event.target.closest ? event.target.closest('[data-mx-share]') : null;
            if (!button) { return; }

            var url = window.location.href;

            var copy = function () {
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(url).then(function () { copied(button); }, function () {
                        if (copyFallback(url)) { copied(button); }
                    });
                    return;
                }

                if (copyFallback(url)) { copied(button); }
            };

            // A phone / tablet opens its own share sheet; a computer copies the link (its "share" window is rarely what
            // people expect). If the sheet fails for any reason other than the person closing it, the link is copied.
            var touch = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

            if (navigator.share && touch) {
                navigator.share({ title: button.dataset.shareTitle, url: url }).catch(function (error) {
                    if (!error || error.name !== 'AbortError') { copy(); }
                });
                return;
            }

            copy();
        });

        // "Starts in 2d 4h" for a match that has not started.
        function startCountdowns() {
            document.querySelectorAll('[data-mx-countdown]').forEach(function (el) {
                var target = new Date(el.dataset.mxCountdown).getTime();
                if (isNaN(target)) { return; }
                var tick = function () {
                    var left = Math.max(0, Math.floor((target - Date.now()) / 1000));
                    var d = Math.floor(left / 86400), h = Math.floor((left % 86400) / 3600), m = Math.floor((left % 3600) / 60);
                    if (left < 60) { el.textContent = el.dataset.soon; return; }
                    el.textContent = (d ? d + el.dataset.day + ' ' : '') + (d || h ? h + el.dataset.hour + ' ' : '') + m + el.dataset.minute;
                };
                tick();
                setInterval(tick, 30000);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', startCountdowns);
        } else {
            startCountdowns();
        }
    })();
</script>
