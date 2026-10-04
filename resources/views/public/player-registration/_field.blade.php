{{--
    One labelled public form field (text/email/number/select) in the RPPL
    public style. Same contract as <x-form.input> / <x-form.select>:
    id = name, old() repopulation, inline validation error.
    Expects: $name, $label; optional: $type, $value, $placeholder,
    $required (adds the * and the browser's required check), $autofocus,
    $autocomplete, $inputmode, $min, $max, $maxlength, $hint (small help
    text under the field), $options (renders a <select>),
    $selectPlaceholder.
    Compact (40 px) fields with a clear focus ring; an invalid field is
    outlined in red and announced to assistive technology.
--}}
@php
    $type = $type ?? 'text';
    $value = $value ?? null;
    $hasError = $errors->has($name);
    $fieldClass = 'h-10 w-full rounded-lg border bg-white px-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-4 '
        .($hasError ? 'border-red-400 focus:border-red-500 focus:ring-red-100' : 'border-slate-300 hover:border-slate-400 focus:border-green-600 focus:ring-green-600/15');
    $describedBy = trim((! empty($hint) ? $name.'-hint ' : '').($hasError ? $name.'-error' : ''));
@endphp

<div class="mb-3.5">
    <label for="{{ $name }}" class="mb-1 block text-xs font-semibold text-slate-700">{{ $label }}@if($required ?? false)<span class="text-red-500" aria-hidden="true"> *</span>@endif</label>

    @if(isset($options))
        <select
            id="{{ $name }}"
            name="{{ $name }}"
            class="{{ $fieldClass }}"
            @required($required ?? false)
            @if($hasError) aria-invalid="true" @endif
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        >
            @if(! empty($selectPlaceholder))
                <option value="" disabled @selected(! old($name, $value))>{{ $selectPlaceholder }}</option>
            @endif
            @foreach($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @else
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $value) }}"
            class="{{ $fieldClass }}"
            @if(! empty($placeholder)) placeholder="{{ $placeholder }}" @endif
            @if(! empty($autocomplete)) autocomplete="{{ $autocomplete }}" @endif
            @if(! empty($inputmode)) inputmode="{{ $inputmode }}" @endif
            @if(isset($min)) min="{{ $min }}" @endif
            @if(isset($max)) max="{{ $max }}" @endif
            @if(! empty($maxlength)) maxlength="{{ $maxlength }}" @endif
            @required($required ?? false)
            @if(! empty($autofocus)) autofocus @endif
            @if($hasError) aria-invalid="true" @endif
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        />
    @endif

    @if(! empty($hint))
        <p id="{{ $name }}-hint" class="mt-1 text-[11px] text-slate-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $name }}-error" class="mt-1 flex items-start gap-1 text-xs font-medium text-red-600">
            <svg class="mt-px h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 6a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1.25 1.25 0 100-2.5A1.25 1.25 0 0010 14z" clip-rule="evenodd" /></svg>
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
