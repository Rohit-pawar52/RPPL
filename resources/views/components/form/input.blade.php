@props(['label' => null, 'name', 'type' => 'text', 'value' => null])

<div class="mb-3.5">
    @if($label)
        <label for="{{ $name }}" class="mb-1 block text-xs font-medium text-neutral-700">{{ $label }}</label>
    @endif

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if($type !== 'password') value="{{ old($name, $value) }}" @endif
        {{ $attributes->merge([
            'class' => 'w-full rounded-md border px-3 py-2 text-[13px] focus:outline-none focus:ring-2 '
                . ($errors->has($name) ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 theme-focus-ring'),
        ]) }}
    />

    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
