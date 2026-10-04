{{--
    A file picker drawn as a compact tap-or-drop area, with the chosen file's
    name, size and (for a picture) a thumbnail shown in its place. The real
    <input type="file"> lies invisibly over the whole area, so tapping,
    dropping and the browser's own "required" check all still work; the
    script in create.blade.php fills in the chosen state and the too-large
    message (data-upload / data-file-* hooks).
    Expects: $name, $label, $accept, $hint, $emptyText, $icon ('camera' or
    'receipt'); optional: $required (default true).
--}}
@php
    $hasError = $errors->has($name);
    $required = $required ?? true;
@endphp

<div class="mb-3.5" data-upload>
    <label for="{{ $name }}" class="mb-1 block text-xs font-semibold text-slate-700">{{ $label }}@if($required)<span class="text-red-500" aria-hidden="true"> *</span>@endif</label>

    <div class="relative rounded-lg border-2 border-dashed px-3 py-2.5 transition focus-within:border-green-600 focus-within:ring-4 focus-within:ring-green-600/15 {{ $hasError ? 'border-red-400 bg-red-50/40' : 'border-slate-300 bg-slate-50/70 hover:border-green-500 hover:bg-green-50/40' }}">
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="file"
            accept="{{ $accept }}"
            class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
            @required($required)
            @if($hasError) aria-invalid="true" @endif
        />

        <div data-upload-empty class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white text-slate-500 shadow-sm ring-1 ring-slate-200">
                @if($icon === 'camera')
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8.5A2.5 2.5 0 015.5 6h1.2a1 1 0 00.8-.4l.9-1.2A1 1 0 019 4h6a1 1 0 01.8.4l.9 1.2a1 1 0 00.8.4h1A2.5 2.5 0 0120 8.5v8A2.5 2.5 0 0117.5 19h-11A2.5 2.5 0 014 16.5v-8z" /><circle cx="12" cy="12.5" r="3.5" /></svg>
                @else
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z" /><path d="M9 8h6M9 12h6" /></svg>
                @endif
            </span>
            <span class="min-w-0">
                <span class="block text-[13px] font-semibold leading-snug text-slate-800">{{ $emptyText }}</span>
                <span class="block text-[11px] leading-snug text-slate-500">{{ $hint }}</span>
            </span>
        </div>

        <div data-upload-chosen class="hidden items-center gap-3">
            <img data-file-preview="{{ $name }}" alt="" class="hidden h-10 w-10 shrink-0 rounded-lg border border-line bg-white object-cover" />
            <span class="min-w-0 flex-1">
                <span data-file-name class="block truncate text-[13px] font-semibold text-slate-800"></span>
                <span data-file-size class="block text-[11px] text-slate-500"></span>
            </span>
            <span class="shrink-0 rounded-md border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 shadow-sm">{{ __('registration.upload.change') }}</span>
        </div>
    </div>

    <p class="mt-1 text-xs font-medium text-red-600" data-file-note="{{ $name }}"></p>
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 flex items-start gap-1 text-xs font-medium text-red-600">
            <svg class="mt-px h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 6a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1.25 1.25 0 100-2.5A1.25 1.25 0 0010 14z" clip-rule="evenodd" /></svg>
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
