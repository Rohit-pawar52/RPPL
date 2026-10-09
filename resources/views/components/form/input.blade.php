{{--
    A labelled text-like field.

    Props
      label    the label (a "*" is added when the `required` attribute is passed)
      name     the field name; it is also the element id
      type     text (default) | email | number | date | password | ...
      value    the initial value (old() input wins)
      help     a small hint under the field
      prefix   (optional) a short text inside the left edge, e.g. a currency symbol
      reveal   (optional, default true) password fields get a show/hide button
    Any other attribute (placeholder, min, max, autofocus, data-*, class...) is passed to the <input>.
--}}
@props(['label' => null, 'name', 'type' => 'text', 'value' => null, 'help' => null, 'prefix' => null, 'reveal' => true])

@php
    $hasError = $errors->has($name);
    $isPassword = $type === 'password' && $reveal;
    $describedBy = $hasError ? $name.'-error' : ($help ? $name.'-help' : null);
@endphp

<div class="fld">
    @if($label)
        <label for="{{ $name }}" class="fld-label">{{ $label }}@if($attributes->has('required'))<span class="fld-req" aria-hidden="true">*</span>@endif</label>
    @endif

    <div @class(['relative' => $prefix || $isPassword])>
        @if($prefix)
            <span class="fld-addon text-[13px] font-medium" aria-hidden="true">{{ $prefix }}</span>
        @endif
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if($type !== 'password') value="{{ old($name, $value) }}" @endif
            @if($hasError) aria-invalid="true" @endif
            @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->merge(['class' => 'fld-control'.($prefix ? ' pl-8' : '').($isPassword ? ' pr-10' : '')]) }}
        />
        @if($isPassword)
            <button
                type="button"
                class="fld-reveal"
                aria-label="{{ __('Show password') }}"
                aria-pressed="false"
                onclick="var i=this.previousElementSibling,s=i.type==='password';i.type=s?'text':'password';this.setAttribute('aria-pressed',s);this.setAttribute('aria-label',s?@js(__('Hide password')):@js(__('Show password')));this.querySelector('[data-eye]').classList.toggle('hidden',s);this.querySelector('[data-eye-off]').classList.toggle('hidden',!s);"
            >
                <x-admin.icon name="eye" data-eye class="h-[18px] w-[18px]" />
                <x-admin.icon name="eye-off" data-eye-off class="hidden h-[18px] w-[18px]" />
            </button>
        @endif
    </div>

    @if($help)
        <p id="{{ $name }}-help" class="fld-help">{{ $help }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="fld-error">{{ $message }}</p>
    @enderror
</div>
