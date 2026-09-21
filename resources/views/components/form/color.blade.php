{{-- A native color swatch paired with the real, editable #RRGGBB text
     field (Phase 3.44B4) — the swatch is a quick visual picker, but the
     text field (validated server-side via the strict hex regex, same
     as every other Settings field) is what actually submits and stays
     precisely readable/editable, per the same inline-onchange pattern
     already used for file inputs elsewhere in this form. --}}
@props(['label' => null, 'name', 'value' => null])

<div class="mb-3.5">
    @if($label)
        <label for="{{ $name }}" class="mb-1 block text-xs font-medium text-neutral-700">{{ $label }}</label>
    @endif

    <div class="flex items-center gap-2">
        <input
            type="color"
            id="{{ $name }}_swatch"
            value="{{ old($name, $value) }}"
            class="h-9 w-11 shrink-0 cursor-pointer rounded border border-neutral-300 bg-white p-0.5"
            onchange="document.getElementById('{{ $name }}').value = this.value"
            aria-hidden="true"
            tabindex="-1"
        />
        <input
            type="text"
            id="{{ $name }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            maxlength="7"
            placeholder="#2563EB"
            oninput="if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) { document.getElementById('{{ $name }}_swatch').value = this.value; }"
            {{ $attributes->merge([
                'class' => 'w-full rounded-md border px-3 py-2 text-[13px] font-mono focus:outline-none focus:ring-2 '
                    . ($errors->has($name) ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 theme-focus-ring'),
            ]) }}
        />
    </div>

    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
