{{-- Shared by create.blade.php and edit.blade.php. $ruleType is null on create. --}}
@php
    $ruleType = $ruleType ?? null;
@endphp

<x-form.input name="name" label="Name" :value="$ruleType->name ?? ''" maxlength="255" required autofocus />

<x-form.input name="slug" label="Slug" :value="$ruleType->slug ?? ''" maxlength="255" required />
<p class="-mt-2.5 mb-3.5 text-[11px] text-slate-400">
    Used in the rule's category — lowercase, hyphens only, e.g. cricket-rules
</p>

<x-form.textarea name="description" label="Description (optional)" :value="$ruleType->description ?? ''" rows="3" maxlength="1000" />

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
<p class="-mt-2.5 mb-3.5 text-[11px] text-slate-400">
    Lower sort order numbers are shown first. An Inactive type hides all of its rules from the public website.
</p>
