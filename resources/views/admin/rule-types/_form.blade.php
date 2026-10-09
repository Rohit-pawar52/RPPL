{{-- Shared by create.blade.php and edit.blade.php. $ruleType is null on create. --}}
@php
    $ruleType = $ruleType ?? null;
@endphp

<div class="crud-grid">
    <div class="crud-main">
        <x-admin.card :title="__('Rule type')">
            <x-form.input name="name" :label="__('Name')" :value="$ruleType->name ?? ''" maxlength="255" required autofocus />

            <x-form.input name="slug" :label="__('Slug')" :value="$ruleType->slug ?? ''" maxlength="255" required :help="__('Used in the rule\'s category — lowercase, hyphens only, e.g. cricket-rules')" />

            <x-form.textarea name="description" :label="__('Description (optional)')" :value="$ruleType->description ?? ''" rows="3" maxlength="1000" />
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card :title="__('Display')">
            <x-form.select
                name="is_active"
                :label="__('Status')"
                :options="['1' => __('Active'), '0' => __('Inactive')]"
                :value="$ruleType ? ($ruleType->is_active ? '1' : '0') : '1'"
                :help="__('An Inactive type hides all of its rules from the public website.')"
            />
            <x-form.input
                name="sort_order"
                :label="__('Sort Order')"
                type="number"
                min="1"
                max="9999"
                :value="$ruleType->sort_order ?? 100"
                :help="__('Lower numbers are shown first.')"
            />
        </x-admin.card>
    </div>
</div>
