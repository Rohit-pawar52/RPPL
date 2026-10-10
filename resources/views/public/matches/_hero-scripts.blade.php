{{-- Share button (data-mx-share) and "starts in" countdown (data-mx-countdown) of a match page. --}}
<script>
    (function () {
        // Share: the phone's share sheet when there is one, otherwise copy the link.
        document.querySelectorAll('[data-mx-share]').forEach(function (button) {
            button.addEventListener('click', function () {
                var url = window.location.href;
                if (navigator.share) {
                    navigator.share({ title: button.dataset.shareTitle, url: url }).catch(function () {});
                    return;
                }
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(function () {
                        var original = button.getAttribute('title');
                        button.setAttribute('title', button.dataset.copied);
                        button.classList.add('is-done');
                        setTimeout(function () { button.setAttribute('title', original); button.classList.remove('is-done'); }, 1800);
                    });
                }
            });
        });

        // "Starts in 2d 4h" for a match that has not started.
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
    })();
</script>
