{{--
    A labelled group of radio buttons drawn as large tap targets (easier on a
    phone than a dropdown). Same contract as _field: id = name of the group,
    old() repopulation, inline validation error.
    Expects: $name, $label, $options (value => label); optional: $required,
    $columns (the grid classes; two across on a phone by default).
    The inputs are visually hidden but still focusable, so the browser's
    "required" check can point at them. The chosen one shows a tick.
--}}
@php
    $hasError = $errors->has($name);
    $columns = $columns ?? 'grid-cols-2 sm:grid-cols-3';
@endphp

<fieldset id="{{ $name }}" class="mb-5" @if($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif>
    <legend class="mb-1.5 block text-[13px] font-semibold text-slate-800">{{ $label }}@if($required ?? false)<span class="text-red-500" aria-hidden="true"> *</span>@endif</legend>

    <div class="grid gap-2 {{ $columns }}">
        @foreach($options as $optionValue => $optionLabel)
            <label class="relative block">
                <input
                    type="radio"
                    name="{{ $name }}"
                    value="{{ $optionValue }}"
                    class="peer sr-only"
                    @checked((string) old($name) === (string) $optionValue)
                    @required($required ?? false)
                />
                <span class="flex min-h-12 cursor-pointer items-center justify-center gap-1.5 rounded-xl border bg-white px-3 py-2 text-center text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-400 peer-checked:border-green-600 peer-checked:bg-green-50 peer-checked:font-semibold peer-checked:text-green-800 peer-checked:ring-1 peer-checked:ring-green-600 peer-focus-visible:ring-4 peer-focus-visible:ring-green-600/25 peer-checked:[&>svg]:block {{ $hasError ? 'border-red-400' : 'border-slate-300' }}">
                    <svg class="hidden h-4 w-4 shrink-0 text-green-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd" /></svg>
                    {{ $optionLabel }}
                </span>
            </label>
        @endforeach
    </div>

    @error($name)
        <p id="{{ $name }}-error" class="mt-1.5 flex items-start gap-1.5 text-xs font-medium text-red-600">
            <svg class="mt-px h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 6a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1.25 1.25 0 100-2.5A1.25 1.25 0 0010 14z" clip-rule="evenodd" /></svg>
            <span>{{ $message }}</span>
        </p>
    @enderror
</fieldset>
