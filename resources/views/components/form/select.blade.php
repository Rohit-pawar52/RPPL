@props(['label' => null, 'name', 'options' => [], 'value' => null, 'placeholder' => null])

<div class="mb-3.5">
    @if($label)
        <label for="{{ $name }}" class="mb-1 block text-xs font-medium text-neutral-700">{{ $label }}</label>
    @endif

    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->merge([
            'class' => 'w-full rounded-md border bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 '
                . ($errors->has($name) ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 focus:ring-blue-100'),
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

    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
