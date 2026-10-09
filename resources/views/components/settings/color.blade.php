{{--
    One colour of the theme form: a swatch (quick picker) next to the real,
    editable #RRGGBB text. With `optional`, an empty text means "automatic"
    (the site works the shade out itself) and a small "Auto" button clears it.
    Everything in the theme form is read by the live preview script in
    _theme.blade.php through the data-theme-input attribute.
    Props: name, label, value, optional, hint, fallback (the colour the swatch
    shows while an optional colour is empty).
--}}
@props(['name', 'label', 'value' => null, 'optional' => false, 'hint' => null, 'fallback' => '#999999'])

@php
    $current = old($name, $value);
    $hasError = $errors->has($name);
@endphp

<div class="min-w-0">
    <label for="{{ $name }}" class="mb-1 flex items-center justify-between gap-2 text-xs font-medium text-slate-700">
        <span>{{ $label }}</span>
        @if($optional)
            <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Optional') }}</span>
        @endif
    </label>

    <div class="flex items-center gap-2">
        <input
            type="color"
            id="{{ $name }}_swatch"
            value="{{ $current ?: $fallback }}"
            class="h-10 w-11 shrink-0 cursor-pointer rounded-lg border border-slate-300 bg-white p-0.5"
            data-theme-swatch="{{ $name }}"
            aria-label="{{ __(':label picker', ['label' => $label]) }}"
            tabindex="-1"
        />
        <input
            type="text"
            id="{{ $name }}"
            name="{{ $name }}"
            value="{{ $current }}"
            maxlength="7"
            placeholder="{{ $optional ? __('Auto') : '#15803D' }}"
            data-theme-input="{{ $name }}"
            @class([
                'h-10 w-full min-w-0 rounded-lg border bg-white px-3 font-mono text-[13px] focus:outline-none focus:ring-2',
                'border-red-400 focus:ring-red-100' => $hasError,
                'border-slate-300 focus:border-brand focus:ring-brand/20' => ! $hasError,
            ])
        />
        @if($optional)
            <button type="button" class="btn btn-ghost btn-sm shrink-0" data-theme-auto="{{ $name }}" title="{{ __('Use the automatic colour') }}">{{ __('Auto') }}</button>
        @endif
    </div>

    @if($hint)
        <p class="mt-1 text-[11px] text-slate-400">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
