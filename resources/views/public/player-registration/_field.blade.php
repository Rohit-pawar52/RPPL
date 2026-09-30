{{--
    One labelled public form field (text/email/date/select) in the RPPL
    public style. Same contract as <x-form.input> / <x-form.select>:
    id = name, old() repopulation, inline validation error.
    Expects: $name, $label; optional: $type, $value, $placeholder,
    $required, $autofocus, $autocomplete, $options (renders a <select>),
    $selectPlaceholder.
--}}
@php
    $type = $type ?? 'text';
    $value = $value ?? null;
    $hasError = $errors->has($name);
    $fieldClass = 'h-11 w-full rounded-lg border bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 '
        .($hasError ? 'border-red-400 focus:ring-red-100' : 'border-slate-300 focus:border-green-600 focus:ring-green-600/20');
@endphp

<div class="mb-4">
    <label for="{{ $name }}" class="mb-1 block text-xs font-semibold text-slate-700">{{ $label }}</label>

    @if(isset($options))
        <select id="{{ $name }}" name="{{ $name }}" class="{{ $fieldClass }}" @required($required ?? false)>
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
            @required($required ?? false)
            @if(! empty($autofocus)) autofocus @endif
        />
    @endif

    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
