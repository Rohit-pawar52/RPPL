{{-- Dashed dropzone-style file picker. The chosen filename is shown via a
     tiny inline handler; $current is optional text for an existing file. --}}
@props(['label' => null, 'name', 'help' => null, 'current' => null, 'prompt' => 'Choose file'])

<div class="mb-3.5">
    @if($label)
        <p class="mb-1 text-xs font-medium text-slate-700">{{ $label }}@if($attributes->has('required'))<span class="text-red-500" aria-hidden="true"> *</span>@endif</p>
    @endif

    <label
        for="{{ $name }}"
        class="flex cursor-pointer flex-col gap-1 rounded-md border border-dashed bg-slate-50/60 px-3 py-3 text-[12px] hover:bg-slate-50 focus-within:ring-2 focus-within:ring-green-100 {{ $errors->has($name) ? 'border-red-400' : 'border-slate-300 focus-within:border-green-500' }}"
    >
        @if($current)
            <span class="truncate text-slate-600">Current: {{ $current }}</span>
        @endif
        <span class="font-medium text-green-700">{{ $prompt }}</span>
        <span data-file-name class="truncate text-[11px] text-slate-500"></span>
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="file"
            {{ $attributes->merge(['class' => 'sr-only']) }}
            onchange="this.closest('label').querySelector('[data-file-name]').textContent = this.files[0]?.name ?? ''"
        />
    </label>

    @if($help)
        <p class="mt-1 text-[11px] text-slate-400">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
