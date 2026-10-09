{{--
    A labelled <textarea>.

    Props
      label  the label ("*" is added when `required` is passed)
      name   the field name; it is also the element id
      value  the initial text (old() input wins)
      help   a small hint under the field
      rows   visible rows (default 3)
    Any other attribute (placeholder, maxlength, data-*, class...) is passed to the <textarea>.
--}}
@props(['label' => null, 'name', 'value' => null, 'help' => null, 'rows' => 3])

@php
    $hasError = $errors->has($name);
    $describedBy = $hasError ? $name.'-error' : ($help ? $name.'-help' : null);
@endphp

<div class="fld">
    @if($label)
        <label for="{{ $name }}" class="fld-label">{{ $label }}@if($attributes->has('required'))<span class="fld-req" aria-hidden="true">*</span>@endif</label>
    @endif

    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if($hasError) aria-invalid="true" @endif
        @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->merge(['class' => 'fld-control']) }}
    >{{ old($name, $value) }}</textarea>

    @if($help)
        <p id="{{ $name }}-help" class="fld-help">{{ $help }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="fld-error">{{ $message }}</p>
    @enderror
</div>
