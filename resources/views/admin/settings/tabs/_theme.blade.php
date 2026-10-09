{{--
    The theme block of Settings > General: every colour and the button shape,
    stored in the database and applied site-wide through CSS variables
    (layouts/partials/theme-vars) - so the look can be changed here with no
    code and no rebuild. The preview on the right is built from the SAME
    classes the site uses (.btn, .pub-link, ...) and re-computes the same
    derived shades while you type, so what you see is what you get.
    Expects $settings (SettingsService).
--}}
@php
    $shape = old('button_shape', $settings->get('general.button_shape')) ?: 'rounded';
    $shapes = ['square' => __('Square'), 'rounded' => __('Rounded'), 'pill' => __('Pill')];
@endphp

<fieldset class="rounded-xl border border-line bg-white" id="theme-form">
    <legend class="sr-only">{{ __('Theme') }}</legend>

    <div class="border-b border-line px-4 py-3">
        <h2 class="text-sm font-semibold text-slate-900">{{ __('Theme: colours, buttons and hover') }}</h2>
        <p class="mt-0.5 text-[12px] text-slate-500">
            {!! __('Change how the whole site looks without touching code. Anything left :auto is worked out from the main colours.', ['auto' => '<b>'.e(__('Auto')).'</b>']) !!}
        </p>
    </div>

    <div class="grid gap-6 p-4 xl:grid-cols-[minmax(0,1fr)_19rem]">
        <div class="space-y-5">
            <div>
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Brand') }}</p>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <x-settings.color name="primary_color" :label="__('Primary colour')" :value="$settings->get('general.primary_color')" :hint="__('Links, active tabs and menu items.')" />
                    <x-settings.color name="secondary_color" :label="__('Secondary colour')" :value="$settings->get('general.secondary_color')" :hint="__('Quiet accents.')" />
                    <x-settings.color name="header_color" :label="__('Header colour')" :value="$settings->get('general.header_color')" :hint="__('Site header, footer and admin sidebar.')" />
                </div>
            </div>

            <div>
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Buttons') }}</p>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <x-settings.color name="button_color" :label="__('Button colour')" :value="$settings->get('general.button_color')" />
                    <x-settings.color name="button_hover_color" :label="__('Button hover colour')" :value="$settings->get('general.button_hover_color')" optional fallback="{{ $settings->get('general.button_color') }}" :hint="__('Auto = a darker shade.')" />
                    <x-settings.color name="button_text_color" :label="__('Button text colour')" :value="$settings->get('general.button_text_color')" optional fallback="#ffffff" :hint="__('Auto = black or white, whichever reads better.')" />
                </div>

                <div class="mt-3">
                    <p class="mb-1 text-xs font-medium text-slate-700">{{ __('Button shape') }}</p>
                    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('Button shape') }}">
                        @foreach($shapes as $value => $label)
                            <label class="relative">
                                <input type="radio" name="button_shape" value="{{ $value }}" class="peer sr-only" data-theme-shape @checked($shape === $value) />
                                <span class="flex h-10 cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 text-[13px] font-medium text-slate-700 transition hover:border-slate-400 peer-checked:border-brand peer-checked:bg-brand-soft peer-checked:text-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand/30">
                                    <span class="h-4 w-8 border-2 border-current {{ $value === 'square' ? 'rounded-sm' : ($value === 'pill' ? 'rounded-full' : 'rounded-md') }}" aria-hidden="true"></span>
                                    {{ $label }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('button_shape')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Links and hover') }}</p>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <x-settings.color name="link_hover_color" :label="__('Link hover colour')" :value="$settings->get('general.link_hover_color')" optional fallback="{{ $settings->get('general.primary_color') }}" :hint="__('Auto = a darker primary.')" />
                    <x-settings.color name="hover_color" :label="__('Hover highlight')" :value="$settings->get('general.hover_color')" optional fallback="#f0f7f2" :hint="__('Rows, menu items and outline buttons when hovered. Auto = a light tint of the primary colour.')" />
                </div>
            </div>

            <div>
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Announcement ticker (public website)') }}</p>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <x-settings.color name="announcement_background_color" :label="__('Ticker background')" :value="$settings->get('general.announcement_background_color')" />
                    <x-settings.color name="announcement_text_color" :label="__('Ticker text')" :value="$settings->get('general.announcement_text_color')" />
                </div>
            </div>
        </div>

        {{-- Live preview: the real classes, with the form's values written onto this box as CSS variables. --}}
        <aside class="order-first xl:order-none xl:sticky xl:top-20 xl:self-start" aria-label="{{ __('Preview') }}">
            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Preview') }}</p>

            <div id="theme-preview" class="overflow-hidden rounded-xl border border-line bg-surface shadow-card">
                <div class="px-3 py-2 text-[11px] font-medium" data-preview-ticker>{{ __('Announcement ticker') }}</div>
                <div class="flex items-center justify-between gap-2 bg-navy-900 px-3 py-2.5 text-white">
                    <span class="text-[13px] font-semibold">{{ $settings->get('general.short_name') }}</span>
                    <span class="flex gap-3 text-[12px] text-slate-300"><span class="text-accent-dark">{{ __('Matches') }}</span><span>{{ __('Teams') }}</span><span>{{ __('Players') }}</span></span>
                </div>

                <div class="space-y-3 p-3">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-primary btn-sm" tabindex="-1">{{ __('Primary') }}</button>
                        <button type="button" class="btn btn-secondary btn-sm" tabindex="-1">{{ __('Secondary') }}</button>
                        <button type="button" class="btn btn-soft btn-sm" tabindex="-1">{{ __('Soft') }}</button>
                    </div>
                    <p class="text-[13px] text-slate-600">
                        {!! __('A paragraph with a :link.', ['link' => '<a href="#theme-form" class="font-medium text-link hover:text-link-hover hover:underline" tabindex="-1">'.e(__('link you can hover')).'</a>']) !!}
                    </p>
                    <ul class="overflow-hidden rounded-lg border border-line bg-white text-[13px]">
                        <li class="border-b border-line px-3 py-2 text-slate-700">{{ __('A table row') }}</li>
                        <li class="bg-hover px-3 py-2 text-slate-700">{{ __('The same row, hovered') }}</li>
                    </ul>
                    <p class="text-[11px] text-slate-400">{{ __('Hover the buttons and the link to see their hover colours.') }}</p>
                </div>
            </div>
        </aside>
    </div>
</fieldset>

<script>
    (function () {
        var form = document.getElementById('theme-form');
        var preview = document.getElementById('theme-preview');
        if (!form || !preview) { return; }

        var hex = /^#[0-9A-Fa-f]{6}$/;
        var input = function (name) { return form.querySelector('[data-theme-input="' + name + '"]'); };
        var value = function (name, fallback) {
            var el = input(name);
            return el && hex.test(el.value.trim()) ? el.value.trim() : fallback;
        };
        var optional = function (name) { var el = input(name); return el && hex.test(el.value.trim()) ? el.value.trim() : null; };

        // Same colour of text the server picks for a button: black or white, whichever has more contrast.
        var contrast = function (color) {
            var n = parseInt(color.slice(1), 16);
            var luminance = (0.2126 * ((n >> 16) & 255) + 0.7152 * ((n >> 8) & 255) + 0.0722 * (n & 255)) / 255;
            return luminance > 0.6 ? '#000000' : '#ffffff';
        };

        var update = function () {
            var primary = value('primary_color', '#15803d');
            var button = value('button_color', '#15803d');
            var header = value('header_color', '#0b2e3f');
            var set = function (name, v) { preview.style.setProperty(name, v); };

            // The site's utility colours (--color-action ...) are computed from the --rppl-* variables
            // where they are declared, so the preview box needs both written onto it.
            var both = function (name, alias, v) { set('--rppl-' + name, v); set('--color-' + alias, v); };

            var linkHover = optional('link_hover_color') || 'color-mix(in srgb, ' + primary + ' 80%, black)';
            var buttonHover = optional('button_hover_color') || 'color-mix(in srgb, ' + button + ' 85%, black)';
            var hoverBg = optional('hover_color') || 'color-mix(in srgb, ' + primary + ' 7%, white)';

            both('primary', 'brand', primary);
            both('primary-hover', 'brand-hover', 'color-mix(in srgb, ' + primary + ' 85%, black)');
            both('primary-soft', 'brand-soft', 'color-mix(in srgb, ' + primary + ' 12%, white)');
            set('--color-link', primary);
            both('link-hover', 'link-hover', linkHover);
            both('button', 'action', button);
            both('button-hover', 'action-hover', buttonHover);
            both('button-fg', 'action-fg', optional('button_text_color') || contrast(button));
            both('hover-bg', 'hover', hoverBg);
            both('accent-on-dark', 'accent-dark', 'color-mix(in srgb, ' + primary + ' 55%, white)');
            set('--color-navy-900', header);

            var shape = form.querySelector('[data-theme-shape]:checked');
            set('--rppl-radius-btn', shape ? { square: '0.25rem', pill: '9999px' }[shape.value] || '0.625rem' : '0.625rem');

            var ticker = preview.querySelector('[data-preview-ticker]');
            ticker.style.backgroundColor = value('announcement_background_color', '#15803d');
            ticker.style.color = value('announcement_text_color', '#ffffff');
        };

        form.addEventListener('input', function (event) {
            var name = event.target.getAttribute('data-theme-input') || event.target.getAttribute('data-theme-swatch');
            if (event.target.hasAttribute('data-theme-swatch')) {
                // The swatch writes its colour into the text field.
                var text = input(name);
                if (text) { text.value = event.target.value.toUpperCase(); }
            } else if (name) {
                // The text field keeps its swatch in step.
                var swatch = form.querySelector('[data-theme-swatch="' + name + '"]');
                if (swatch && hex.test(event.target.value.trim())) { swatch.value = event.target.value.trim(); }
            }
            update();
        });

        form.addEventListener('change', function (event) {
            if (event.target.hasAttribute('data-theme-shape')) { update(); }
        });

        form.querySelectorAll('[data-theme-auto]').forEach(function (button) {
            button.addEventListener('click', function () {
                var text = input(button.getAttribute('data-theme-auto'));
                if (text) { text.value = ''; }
                update();
            });
        });

        update();
    })();
</script>
