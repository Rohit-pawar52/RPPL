{{--
    The search box of the public directories (teams, players) and the rules.
    It is still a plain GET form (Enter searches everyone, with or without
    JavaScript); on top of that, typing filters what is already on the page
    instantly - no page load, no tap.

    Expects: $action (where the form goes), $placeholder; optional: $search
    (the term the server searched for), $target (css selector of the box that
    holds the items; every item carries data-filter-text="lowercase words"),
    $empty (css selector of the "nothing matches" box to reveal), $name (the
    query-string key, default "search").
--}}
@php
    $search = $search ?? '';
    $name = $name ?? 'search';
@endphp

<form method="GET" action="{{ $action }}" role="search" class="pc-search" data-pc-search data-searched="{{ $search !== '' ? '1' : '0' }}" @if(! empty($target)) data-target="{{ $target }}" @endif @if(! empty($empty)) data-empty="{{ $empty }}" @endif>
    <svg class="pc-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></svg>
    <input
        type="search"
        name="{{ $name }}"
        value="{{ $search }}"
        placeholder="{{ $placeholder }}"
        aria-label="{{ $placeholder }}"
        autocomplete="off"
        enterkeyhint="search"
        class="pc-search-input"
    />
    <div class="pc-search-actions">
        <button type="button" data-pc-clear class="pc-search-clear" aria-label="{{ __('ux_public_content.search.clear') }}" @if($search === '') hidden @endif>
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
        </button>
        <button type="submit" class="btn btn-primary btn-sm">{{ __('directory.common.search') }}</button>
    </div>
</form>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-pc-search]').forEach(function (form) {
                var input = form.querySelector('input[type="search"]');
                var clear = form.querySelector('[data-pc-clear]');
                var scope = form.dataset.target ? document.querySelector(form.dataset.target) : null;
                var empty = form.dataset.empty ? document.querySelector(form.dataset.empty) : null;
                var items = scope ? Array.prototype.slice.call(scope.querySelectorAll('[data-filter-text]')) : [];

                var apply = function () {
                    var term = input.value.trim().toLowerCase();
                    var shown = 0;

                    clear.hidden = term === '';
                    items.forEach(function (item) {
                        var match = term === '' || item.dataset.filterText.indexOf(term) !== -1;
                        item.hidden = !match;
                        if (match) { shown++; }
                    });

                    if (empty) {
                        empty.hidden = term === '' || shown > 0 || items.length === 0;
                        var echo = empty.querySelector('[data-pc-term]');
                        if (echo) { echo.textContent = input.value.trim(); }
                    }
                };

                input.addEventListener('input', apply);
                clear.addEventListener('click', function () {
                    input.value = '';
                    apply();
                    if (form.dataset.searched === '1') {
                        window.location.href = form.getAttribute('action');
                        return;
                    }
                    input.focus();
                });

                // The "search everyone" button of the empty box.
                document.querySelectorAll('[data-pc-submit="' + (form.dataset.empty || '') + '"]').forEach(function (button) {
                    button.addEventListener('click', function () { form.requestSubmit(); });
                });

                apply();
            });
        });
    </script>
@endonce
