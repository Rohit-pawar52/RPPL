{{--
    A labelled group of radio buttons drawn as compact chips that are still
    easy to tap (easier on a phone than a dropdown) and wrap onto the next
    line when they do not fit. Same contract as _field: id = name of the
    group, old() repopulation, inline validation error.
    Expects: $name, $label, $options (value => label); optional: $required.
    The inputs are visually hidden but still focusable, so the browser's
    "required" check can point at them. The chosen one is tinted with the
    brand colour and shows a tick (.pc-choice in public-content.css).
--}}
@php
    $hasError = $errors->has($name);
@endphp

<fieldset id="{{ $name }}" class="mb-3.5 min-w-0" @if($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif>
    <legend class="pc-label">{{ $label }}@if($required ?? false)<span class="text-red-500" aria-hidden="true"> *</span>@endif</legend>

    <div class="flex flex-wrap gap-2">
        @foreach($options as $optionValue => $optionLabel)
            <label class="relative">
                <input
                    type="radio"
                    name="{{ $name }}"
                    value="{{ $optionValue }}"
                    class="peer sr-only"
                    @checked((string) old($name) === (string) $optionValue)
                    @required($required ?? false)
                />
                <span class="pc-choice {{ $hasError ? 'pc-choice-error' : '' }}">
                    <svg class="hidden h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd" /></svg>
                    {{ $optionLabel }}
                </span>
            </label>
        @endforeach
    </div>

    @error($name)
        <p id="{{ $name }}-error" class="mt-1 flex items-start gap-1 text-xs font-medium text-red-600">
            <svg class="mt-px h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 6a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1.25 1.25 0 100-2.5A1.25 1.25 0 0010 14z" clip-rule="evenodd" /></svg>
            <span>{{ $message }}</span>
        </p>
    @enderror
</fieldset>
