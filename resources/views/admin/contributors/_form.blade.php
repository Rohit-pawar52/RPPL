{{-- Shared by create.blade.php and edit.blade.php. $contributor is null on create. --}}
@php
    $contributor = $contributor ?? null;
@endphp

<div class="crud-grid">
    <div class="crud-main">
        <x-admin.card :title="__('Contributor details')">
            <x-form.input name="name" :label="__('Name')" :value="$contributor->name ?? ''" required autofocus />
            @php $villageHelp = __('Tells two people with the same name apart in every list, on the receipt and in the exports.'); @endphp
            @if($contributor)
                {{-- Optional when editing: a contributor recorded before villages were kept has none. --}}
                <x-form.input name="village" :label="__('Village')" :value="$contributor->village ?? ''" maxlength="100" :placeholder="__('e.g. Shirur')" :help="$villageHelp" />
            @else
                <x-form.input name="village" :label="__('Village')" :value="''" required maxlength="100" :placeholder="__('e.g. Shirur')" :help="$villageHelp" />
            @endif
            <x-form.input name="phone" :label="__('Phone')" type="tel" inputmode="tel" :value="$contributor->phone ?? ''" />
            <x-form.input name="address" :label="__('Address (optional)')" :value="$contributor->address ?? ''" maxlength="255" :placeholder="__('Tehsil, district, landmark - anything else that helps')" />

            @if(! $contributor && $errors->has('confirm_duplicate'))
                {{-- Shown only after the "already in the list" warning: the admin has to say it is somebody else. --}}
                <div class="crud-note crud-note-warn mb-3.5" role="alert">
                    <p>{{ $errors->first('confirm_duplicate') }}</p>
                    <label class="mt-2 flex items-center gap-2 text-[13px] font-medium text-slate-800">
                        <input type="checkbox" name="confirm_duplicate" value="1" class="rounded border-slate-300" @checked(old('confirm_duplicate'))>
                        {{ __('This is a different person') }}
                    </label>
                </div>
            @endif
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card :title="__('Photo (optional)')">
            <x-form.image-upload
                name="photo"
                :current="$contributor?->photo_path"
                kind="user"
                shape="circle"
                box-class="h-32 w-32 rounded-full"
                stack
                :empty-text="__('Click the picture to add a photo')"
                :help="__('JPG, PNG or WebP.')"
            />
        </x-admin.card>

        @if($contributor)
            {{-- Only shown on edit: a newly added contributor defaults to
                 active without the admin having to choose it explicitly. --}}
            <x-admin.card :title="__('Status')">
                <x-form.select
                    name="is_active"
                    :label="__('Status')"
                    :options="['1' => __('Active'), '0' => __('Inactive')]"
                    :value="$contributor->is_active ? '1' : '0'"
                />
            </x-admin.card>
        @else
            <p class="crud-note">{!! __('New contributors start as :active.', ['active' => '<strong class="font-semibold text-slate-700">'.e(__('Active')).'</strong>']) !!}</p>
        @endif
    </div>
</div>
