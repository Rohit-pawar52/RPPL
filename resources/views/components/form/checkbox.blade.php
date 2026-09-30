{{-- $checked is the fallback when there is no old input. Callers that need
     an explicit "off" value add their own hidden input before this one. --}}
@props(['label' => null, 'name', 'value' => '1', 'checked' => false, 'help' => null])

<div class="mb-3.5">
    <label for="{{ $name }}" class="inline-flex cursor-pointer items-center gap-2 text-[13px] text-slate-700">
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="checkbox"
            value="{{ $value }}"
            @checked(old($name, $checked))
            {{ $attributes->merge(['class' => 'h-4 w-4 rounded border-slate-300 text-green-600 focus:ring-2 focus:ring-green-500']) }}
        />
        <span>{{ $label ?? $slot }}</span>
    </label>

    @if($help)
        <p class="mt-1 text-[11px] text-slate-400">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
