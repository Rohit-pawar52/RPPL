@props(['label' => null, 'name', 'options' => [], 'value' => null, 'placeholder' => null, 'help' => null])

<div class="mb-3.5">
    @if($label)
        <label for="{{ $name }}" class="mb-1 block text-xs font-medium text-slate-700">{{ $label }}@if($attributes->has('required'))<span class="text-red-500" aria-hidden="true"> *</span>@endif</label>
    @endif

    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->merge([
            'class' => 'h-10 w-full rounded-md border bg-white px-3 text-[13px] text-slate-800 focus:outline-none focus:ring-2 disabled:bg-slate-50 disabled:text-slate-500 '
                . ($errors->has($name) ? 'border-red-400 focus:border-red-400 focus:ring-red-100' : 'border-slate-300 focus:border-green-500 focus:ring-green-100'),
        ]) }}
    >
        @if($placeholder)
            <option value="" disabled @selected(! old($name, $value))>{{ $placeholder }}</option>
        @endif

        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

    @if($help)
        <p class="mt-1 text-[11px] text-slate-400">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
