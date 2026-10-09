{{--
    A dashed drop-zone style file picker. The chosen filename is shown through
    a tiny inline handler; $current is optional text for an existing file.

    Props
      label    the label ("*" is added when `required` is passed)
      name     the input name; it is also the id
      help     a small hint under the zone
      current  text naming the file already saved ("Current: ...")
      prompt   the call to action (default "Choose file")
    Any other attribute (accept, required, ...) is passed to the <input type="file">.
--}}
@props(['label' => null, 'name', 'help' => null, 'current' => null, 'prompt' => 'Choose file'])

<div class="fld">
    @if($label)
        <p class="fld-label">{{ $label }}@if($attributes->has('required'))<span class="fld-req" aria-hidden="true">*</span>@endif</p>
    @endif

    <label for="{{ $name }}" class="fld-drop" @if($errors->has($name)) data-invalid @endif>
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand">
            <x-admin.icon name="upload" class="h-[18px] w-[18px]" />
        </span>
        <span class="min-w-0 flex-1">
            @if($current)
                <span class="block truncate text-slate-500">Current: {{ $current }}</span>
            @endif
            <span class="block font-semibold text-brand">{{ $prompt }}</span>
            <span data-file-name class="block truncate text-[11px] text-slate-500"></span>
        </span>
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="file"
            @if($errors->has($name)) aria-invalid="true" @endif
            {{ $attributes->merge(['class' => 'sr-only']) }}
            onchange="this.closest('label').querySelector('[data-file-name]').textContent = this.files[0]?.name ?? ''"
        />
    </label>

    @if($help)
        <p class="fld-help">{{ $help }}</p>
    @endif
    @error($name)
        <p class="fld-error">{{ $message }}</p>
    @enderror
</div>
