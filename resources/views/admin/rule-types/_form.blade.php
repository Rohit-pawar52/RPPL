{{-- Shared by create.blade.php and edit.blade.php. $ruleType is null on create. --}}
@php
    $ruleType = $ruleType ?? null;
@endphp

<x-form.input name="name" label="Name" :value="$ruleType->name ?? ''" maxlength="255" required autofocus />

<x-form.input name="slug" label="Slug" :value="$ruleType->slug ?? ''" maxlength="255" required />
<p class="-mt-2.5 mb-3.5 text-[11px] text-neutral-400">
    Used in the rule's category — lowercase, hyphens only, e.g. cricket-rules
</p>

<div class="mb-3.5">
    <label for="description" class="mb-1 block text-xs font-medium text-neutral-700">Description <span class="font-normal text-neutral-400">(optional)</span></label>
    <textarea
        id="description"
        name="description"
        rows="3"
        maxlength="1000"
        class="w-full rounded-md border px-3 py-2 text-[13px] focus:outline-none focus:ring-2 {{ $errors->has('description') ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 theme-focus-ring' }}"
    >{{ old('description', $ruleType->description ?? '') }}</textarea>
    @error('description')
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
        :value="$ruleType->sort_order ?? 100"
    />
    <x-form.select
        name="is_active"
        label="Status"
        :options="['1' => 'Active', '0' => 'Inactive']"
        :value="$ruleType ? ($ruleType->is_active ? '1' : '0') : '1'"
    />
</div>
<p class="-mt-2.5 mb-3.5 text-[11px] text-neutral-400">
    Lower sort order numbers are shown first. An Inactive type hides all of its rules from the public website.
</p>
