@props(['label' => null, 'name', 'value' => null, 'help' => null, 'rows' => 3])

<div class="mb-3.5">
    @if($label)
        <label for="{{ $name }}" class="mb-1 block text-xs font-medium text-slate-700">{{ $label }}@if($attributes->has('required'))<span class="text-red-500" aria-hidden="true"> *</span>@endif</label>
    @endif

    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        {{ $attributes->merge([
            'class' => 'w-full rounded-md border bg-white px-3 py-2 text-[13px] text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 '
                . ($errors->has($name) ? 'border-red-400 focus:border-red-400 focus:ring-red-100' : 'border-slate-300 focus:border-green-500 focus:ring-green-100'),
        ]) }}
    >{{ old($name, $value) }}</textarea>

    @if($help)
        <p class="mt-1 text-[11px] text-slate-400">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
