{{--
    A labelled group of radio buttons drawn as large tap targets (easier on a
    phone than a dropdown). Same contract as _field: id = name of the group,
    old() repopulation, inline validation error.
    Expects: $name, $label, $options (value => label); optional: $required.
    The inputs are visually hidden but still focusable, so the browser's
    "required" check can point at them.
--}}
@php
    $hasError = $errors->has($name);
@endphp

<fieldset class="mb-4">
    <legend class="mb-1 block text-xs font-semibold text-slate-700">{{ $label }}@if($required ?? false)<span class="text-red-500" aria-hidden="true"> *</span>@endif</legend>

    <div class="flex flex-wrap gap-2">
        @foreach($options as $optionValue => $optionLabel)
            <label class="relative">
                <input
                    type="radio"
                    name="{{ $name }}"
                    value="{{ $optionValue }}"
                    class="peer sr-only"
                    @checked((string) old($name) === (string) $optionValue)
                    @required($required ?? false)
                />
                <span class="inline-flex min-h-11 cursor-pointer items-center rounded-lg border bg-white px-4 text-sm text-slate-700 transition peer-checked:border-green-600 peer-checked:bg-green-50 peer-checked:font-semibold peer-checked:text-green-800 peer-focus-visible:ring-2 peer-focus-visible:ring-green-600/30 {{ $hasError ? 'border-red-400' : 'border-slate-300' }}">
                    {{ $optionLabel }}
                </span>
            </label>
        @endforeach
    </div>

    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</fieldset>
