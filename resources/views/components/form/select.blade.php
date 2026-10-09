{{--
    A labelled <select>.

    Props
      label        the label ("*" is added when `required` is passed)
      name         the field name; it is also the element id
      options      [value => label]
      value        the selected value (old() input wins)
      placeholder  a disabled first option shown while nothing is chosen
      help         a small hint under the field
    Any other attribute (required, onchange, data-*, class...) is passed to the <select>.
--}}
@props(['label' => null, 'name', 'options' => [], 'value' => null, 'placeholder' => null, 'help' => null])

@php
    $hasError = $errors->has($name);
    $describedBy = $hasError ? $name.'-error' : ($help ? $name.'-help' : null);
@endphp

<div class="fld">
    @if($label)
        <label for="{{ $name }}" class="fld-label">{{ $label }}@if($attributes->has('required'))<span class="fld-req" aria-hidden="true">*</span>@endif</label>
    @endif

    <select
        id="{{ $name }}"
        name="{{ $name }}"
        @if($hasError) aria-invalid="true" @endif
        @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->merge(['class' => 'fld-control']) }}
    >
        @if($placeholder)
            <option value="" disabled @selected(! old($name, $value))>{{ $placeholder }}</option>
        @endif

        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

    @if($help)
        <p id="{{ $name }}-help" class="fld-help">{{ $help }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="fld-error">{{ $message }}</p>
    @enderror
</div>
