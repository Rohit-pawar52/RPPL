{{-- Shared by create.blade.php and edit.blade.php. $player is null on create. --}}
@php
    $player = $player ?? null;
    $label = fn (string $value) => ucwords(__(str_replace('_', ' ', $value)));
@endphp

<div class="crud-grid">
    <div class="crud-main">
        <x-admin.card :title="__('Personal details')">
            <x-form.input name="name" :label="__('Full name')" :value="$player->name ?? ''" required autofocus />

            <div class="crud-cols">
                <x-form.input
                    name="date_of_birth"
                    :label="__('Date of birth')"
                    type="date"
                    :value="optional($player?->date_of_birth)->format('Y-m-d')"
                    max="{{ display_datetime(now(), 'Y-m-d') }}"
                />
                <x-form.input name="phone" :label="__('Phone')" type="tel" inputmode="tel" :value="$player->phone ?? ''" maxlength="20" />
            </div>

            <x-form.input name="email" :label="__('Email')" type="email" :value="$player->email ?? ''" />
        </x-admin.card>

        <x-admin.card :title="__('Cricket profile')">
            <div class="crud-cols-3">
                <x-form.select
                    name="primary_role"
                    :label="__('Primary role')"
                    :placeholder="__('Not set')"
                    :options="collect(\App\Models\Player::PRIMARY_ROLES)->mapWithKeys(fn ($role) => [$role => $label($role)])"
                    :value="$player->primary_role ?? ''"
                />
                <x-form.select
                    name="batting_style"
                    :label="__('Batting style')"
                    :placeholder="__('Not set')"
                    :options="collect(\App\Models\Player::BATTING_STYLES)->mapWithKeys(fn ($style) => [$style => $label($style)])"
                    :value="$player->batting_style ?? ''"
                />
                <x-form.select
                    name="bowling_style"
                    :label="__('Bowling style')"
                    :placeholder="__('Not set')"
                    :options="collect(\App\Models\Player::BOWLING_STYLES)->mapWithKeys(fn ($style) => [$style => $label($style)])"
                    :value="$player->bowling_style ?? ''"
                />
            </div>
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card :title="__('Photo')">
            <x-form.image-upload
                name="photo"
                :current="$player?->photo_path"
                kind="user"
                shape="circle"
                box-class="h-32 w-32 rounded-full"
                stack
                :empty-text="__('Click the picture to add a photo')"
                :help="__('JPG, PNG or WebP.')"
            />
        </x-admin.card>

        @if($player)
            {{-- Only shown on edit: a newly created player defaults to
                 active without the admin having to choose it explicitly. --}}
            <x-admin.card :title="__('Status')">
                <x-form.select
                    name="is_active"
                    :label="__('Status')"
                    :options="['1' => __('Active'), '0' => __('Inactive')]"
                    :value="$player->is_active ? '1' : '0'"
                    :help="__('Inactive players are hidden from new squads and the public directory.')"
                />
            </x-admin.card>
        @else
            <p class="crud-note">{!! __('New players start as :active. You can switch them off later from the edit page.', ['active' => '<strong class="font-semibold text-slate-700">'.e(__('Active')).'</strong>']) !!}</p>
        @endif
    </div>
</div>
