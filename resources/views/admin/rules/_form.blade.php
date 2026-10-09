{{-- Shared by create.blade.php and edit.blade.php. $rule is null on create. --}}
@php
    $rule = $rule ?? null;
    $ruleTypeOptions = $ruleTypes
        ->mapWithKeys(fn ($type) => [$type->id => $type->name.($type->is_active ? '' : ' ('.__('inactive').')')])
        ->all();
@endphp

<div class="crud-grid">
    <div class="crud-main">
        <x-admin.card :title="__('The rule')">
            <x-form.select
                name="rule_type_id"
                :label="__('Rule Type')"
                :placeholder="__('Choose a rule type')"
                :options="$ruleTypeOptions"
                :value="$rule->rule_type_id ?? ''"
                required
            />

            <x-form.input name="title" :label="__('Title')" :value="$rule->title ?? ''" maxlength="255" required autofocus />

            <x-form.textarea name="content" :label="__('Content')" :value="$rule->content ?? ''" rows="8" maxlength="5000" required :help="__('Plain text, line breaks are preserved. No HTML.')" />
        </x-admin.card>

        <x-admin.card :title="__('Image (optional)')">
            <x-form.image-upload
                name="image"
                :current="$rule?->image_path"
                kind="image"
                shape="wide"
                box-class="h-28 w-44 rounded-xl"
                :empty-text="__('Click the picture to choose an image')"
                :help="__('JPG, PNG or WebP, up to 2 MB.').($rule?->image_path ? ' '.__('Leave it alone to keep the current image.') : '')"
                remove-name="remove_image"
                :remove-label="__('Remove image')"
            />
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card :title="__('Display')">
            <x-form.select
                name="status"
                :label="__('Status')"
                :options="['active' => __('Active'), 'inactive' => __('Inactive')]"
                :value="$rule->status ?? 'active'"
                :help="__('Only Active rules under an Active rule type appear on the public website.')"
            />
            <x-form.input
                name="sort_order"
                :label="__('Sort Order')"
                type="number"
                min="1"
                max="9999"
                :value="$rule->sort_order ?? 100"
                :help="__('Lower numbers are shown first within their rule type.')"
            />
            <x-form.checkbox name="is_important" :label="__('Highlight as an especially important rule')" :checked="$errors->any() ? (bool) old('is_important') : ($rule->is_important ?? false)" />
        </x-admin.card>
    </div>
</div>
