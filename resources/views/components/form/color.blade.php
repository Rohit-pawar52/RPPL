{{--
    A native colour swatch paired with the real, editable #RRGGBB text field.
    The swatch is a quick visual picker; the text field (validated server-side
    with the strict hex rule) is what is submitted.

    Props
      label     the label
      name      the field name (the text input id); the swatch id is "{name}_swatch"
      value     the initial colour
      nullable  true = an empty text means "automatic": the swatch shows
                `fallback` and an "Auto" button clears the field
      fallback  the colour the swatch shows while a nullable field is empty
                (default #999999)
--}}
@props(['label' => null, 'name', 'value' => null, 'nullable' => false, 'fallback' => '#999999'])

@php
    $current = old($name, $value);
    $hasError = $errors->has($name);
@endphp

<div class="fld">
    @if($label)
        <label for="{{ $name }}" class="fld-label">{{ $label }}@if($attributes->has('required'))<span class="fld-req" aria-hidden="true">*</span>@endif
            @if($nullable)<span class="ml-1.5 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Optional</span>@endif
        </label>
    @endif

    <div class="flex items-center gap-2">
        <input
            type="color"
            id="{{ $name }}_swatch"
            value="{{ $current ?: $fallback }}"
            class="h-10 w-12 shrink-0 cursor-pointer rounded-lg border border-slate-300 bg-white p-1 transition hover:border-slate-400"
            onchange="document.getElementById('{{ $name }}').value = this.value"
            aria-label="{{ $label ?? $name }} picker"
            tabindex="-1"
        />
        <input
            type="text"
            id="{{ $name }}"
            name="{{ $name }}"
            value="{{ $current }}"
            maxlength="7"
            placeholder="{{ $nullable ? 'Auto' : '#2563EB' }}"
            oninput="if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) { document.getElementById('{{ $name }}_swatch').value = this.value; }@if($nullable) else if (this.value === '') { document.getElementById('{{ $name }}_swatch').value = '{{ $fallback }}'; }@endif"
            @if($hasError) aria-invalid="true" @endif
            {{ $attributes->merge(['class' => 'fld-control min-w-0 font-mono']) }}
        />
        @if($nullable)
            <button
                type="button"
                class="btn btn-ghost btn-sm shrink-0"
                title="Use the automatic colour"
                onclick="var t=document.getElementById('{{ $name }}');t.value='';document.getElementById('{{ $name }}_swatch').value='{{ $fallback }}';t.focus();"
            >Auto</button>
        @endif
    </div>

    @error($name)
        <p class="fld-error">{{ $message }}</p>
    @enderror
</div>
