{{--
    One tick box with its label.

    $checked is the fallback when there is no old input. Callers that need an
    explicit "off" value add their own hidden input before this one.

    Props
      label    the text beside the box (or put it in the slot)
      name     the field name; it is also the element id
      value    the submitted value (default "1")
      checked  ticked when there is no old input
      help     a small hint under the box
--}}
@props(['label' => null, 'name', 'value' => '1', 'checked' => false, 'help' => null])

<div class="fld">
    <label for="{{ $name }}" class="fld-check">
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="checkbox"
            value="{{ $value }}"
            @checked(old($name, $checked))
            {{ $attributes }}
        />
        <span>{{ $label ?? $slot }}</span>
    </label>

    @if($help)
        <p class="fld-help ml-7">{{ $help }}</p>
    @endif
    @error($name)
        <p class="fld-error ml-7">{{ $message }}</p>
    @enderror
</div>
