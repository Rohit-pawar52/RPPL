{{-- Shared by create.blade.php and edit.blade.php. $announcement is null on create. --}}
@php
    $announcement = $announcement ?? null;
@endphp

<div class="mb-3.5">
    <label for="message" class="mb-1 block text-xs font-medium text-neutral-700">Message</label>
    <textarea
        id="message"
        name="message"
        rows="3"
        maxlength="500"
        required
        class="w-full rounded-md border px-3 py-2 text-[13px] focus:outline-none focus:ring-2 {{ $errors->has('message') ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 theme-focus-ring' }}"
    >{{ old('message', $announcement->message ?? '') }}</textarea>
    <p class="mt-1 text-[11px] text-neutral-400">Plain text only — emoji are welcome (e.g. 🏏 📢 ⚠️ 🎉). HTML is not supported and will display as-is.</p>
    @error('message')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <x-form.input
        name="starts_at"
        label="Start Date & Time"
        type="datetime-local"
        :value="$announcement?->starts_at ? display_datetime($announcement->starts_at, 'Y-m-d\TH:i') : old('starts_at', '')"
    />
    <x-form.input
        name="ends_at"
        label="End Date & Time"
        type="datetime-local"
        :value="$announcement?->ends_at ? display_datetime($announcement->ends_at, 'Y-m-d\TH:i') : old('ends_at', '')"
    />
</div>
<p class="-mt-2.5 mb-3.5 text-[11px] text-neutral-400">
    Both optional — leave blank for no boundary on that side. Times are entered and shown in the site's configured display timezone.
</p>

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <x-form.select
        name="is_active"
        label="Active"
        :options="['1' => 'Active', '0' => 'Inactive']"
        :value="$announcement ? ($announcement->is_active ? '1' : '0') : '1'"
    />
    <x-form.input
        name="sort_order"
        label="Display Order"
        type="number"
        min="0"
        max="9999"
        :value="$announcement->sort_order ?? 0"
    />
</div>
<p class="-mt-2.5 mb-3.5 text-[11px] text-neutral-400">
    Lower display order numbers appear first when multiple announcements are active at once.
</p>
