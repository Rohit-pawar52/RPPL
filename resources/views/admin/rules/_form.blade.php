{{-- Shared by create.blade.php and edit.blade.php. $rule is null on create. --}}
@php
    $rule = $rule ?? null;
    $ruleTypeOptions = $ruleTypes
        ->mapWithKeys(fn ($type) => [$type->id => $type->name.($type->is_active ? '' : ' (inactive)')])
        ->all();
@endphp

<x-form.select
    name="rule_type_id"
    label="Rule Type"
    placeholder="Choose a rule type"
    :options="$ruleTypeOptions"
    :value="$rule->rule_type_id ?? ''"
    required
/>

<x-form.input name="title" label="Title" :value="$rule->title ?? ''" maxlength="255" required />

<div class="mb-3.5">
    <label for="content" class="mb-1 block text-xs font-medium text-neutral-700">Content</label>
    <textarea
        id="content"
        name="content"
        rows="6"
        maxlength="5000"
        required
        class="w-full rounded-md border px-3 py-2 text-[13px] focus:outline-none focus:ring-2 {{ $errors->has('content') ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 theme-focus-ring' }}"
    >{{ old('content', $rule->content ?? '') }}</textarea>
    <p class="mt-1 text-[11px] text-neutral-400">Plain text, line breaks are preserved. No HTML.</p>
    @error('content')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="mb-3.5">
    <p class="mb-1 text-xs font-medium text-neutral-700">Image <span class="font-normal text-neutral-400">(optional)</span></p>

    <div class="flex items-center gap-3">
        <div class="flex h-16 w-28 shrink-0 items-center justify-center overflow-hidden rounded-md border border-neutral-200 bg-neutral-50 text-neutral-300">
            @if($rule?->image_path)
                <img
                    src="{{ Illuminate\Support\Facades\Storage::url($rule->image_path) }}"
                    alt="{{ $rule->title }}"
                    class="h-full w-full object-cover"
                />
            @else
                <x-icon name="book" class="h-6 w-6" />
            @endif
        </div>

        <div class="min-w-0">
            <label class="cursor-pointer text-[12px] font-medium theme-link">
                {{ $rule?->image_path ? 'Replace image' : 'Upload image' }}
                <input
                    type="file"
                    name="image"
                    accept="image/png,image/jpeg,image/webp"
                    class="hidden"
                    onchange="document.getElementById('image-filename').textContent = this.files[0]?.name ?? ''"
                />
            </label>
            <p id="image-filename" class="mt-1 max-w-[12rem] truncate text-[11px] text-neutral-500"></p>
            <p class="mt-1 text-[11px] text-neutral-400">
                JPG, PNG or WebP, up to 2 MB.
                @if($rule?->image_path)
                    Leave empty to keep the current image.
                @endif
            </p>

            @if($rule?->image_path)
                <label class="mt-1.5 flex items-center gap-1.5 text-[12px] text-neutral-600">
                    <input
                        type="checkbox"
                        name="remove_image"
                        value="1"
                        class="rounded border-neutral-300"
                        @checked(old('remove_image'))
                    />
                    Remove image
                </label>
            @endif
        </div>
    </div>

    @error('image')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <x-form.input
        name="sort_order"
        label="Sort Order"
        type="number"
        min="1"
        max="9999"
        :value="$rule->sort_order ?? 100"
    />
    <x-form.select
        name="status"
        label="Status"
        :options="['active' => 'Active', 'inactive' => 'Inactive']"
        :value="$rule->status ?? 'active'"
    />
</div>
<p class="-mt-2.5 mb-3.5 text-[11px] text-neutral-400">
    Lower sort order numbers are shown first within their rule type. Only Active rules under an Active rule type appear on the public website.
</p>

<div class="mb-3.5">
    <label class="flex items-center gap-2 text-[13px] text-neutral-700">
        <input
            type="checkbox"
            name="is_important"
            value="1"
            class="rounded border-neutral-300"
            @checked($errors->any() ? old('is_important') : ($rule->is_important ?? false))
        />
        Highlight as an especially important rule
    </label>
    @error('is_important')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
