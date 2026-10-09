{{-- Shared by create.blade.php and edit.blade.php. $contributor is null on create. --}}
@php
    $contributor = $contributor ?? null;
@endphp

<div class="crud-grid">
    <div class="crud-main">
        <x-admin.card title="Contributor details">
            <x-form.input name="name" label="Name" :value="$contributor->name ?? ''" required autofocus />
            @php $villageHelp = 'Tells two people with the same name apart in every list, on the receipt and in the exports.'; @endphp
            @if($contributor)
                {{-- Optional when editing: a contributor recorded before villages were kept has none. --}}
                <x-form.input name="village" label="Village" :value="$contributor->village ?? ''" maxlength="100" placeholder="e.g. Shirur" :help="$villageHelp" />
            @else
                <x-form.input name="village" label="Village" :value="''" required maxlength="100" placeholder="e.g. Shirur" :help="$villageHelp" />
            @endif
            <x-form.input name="phone" label="Phone" type="tel" inputmode="tel" :value="$contributor->phone ?? ''" />
            <x-form.input name="address" label="Address (optional)" :value="$contributor->address ?? ''" maxlength="255" placeholder="Tehsil, district, landmark - anything else that helps" />

            @if(! $contributor && $errors->has('confirm_duplicate'))
                {{-- Shown only after the "already in the list" warning: the admin has to say it is somebody else. --}}
                <div class="crud-note crud-note-warn mb-3.5" role="alert">
                    <p>{{ $errors->first('confirm_duplicate') }}</p>
                    <label class="mt-2 flex items-center gap-2 text-[13px] font-medium text-slate-800">
                        <input type="checkbox" name="confirm_duplicate" value="1" class="rounded border-slate-300" @checked(old('confirm_duplicate'))>
                        This is a different person
                    </label>
                </div>
            @endif
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card title="Photo (optional)">
            <x-form.image-upload
                name="photo"
                :current="$contributor?->photo_path"
                kind="user"
                shape="circle"
                box-class="h-32 w-32 rounded-full"
                stack
                empty-text="Click the picture to add a photo"
                help="JPG, PNG or WebP."
            />
        </x-admin.card>

        @if($contributor)
            {{-- Only shown on edit: a newly added contributor defaults to
                 active without the admin having to choose it explicitly. --}}
            <x-admin.card title="Status">
                <x-form.select
                    name="is_active"
                    label="Status"
                    :options="['1' => 'Active', '0' => 'Inactive']"
                    :value="$contributor->is_active ? '1' : '0'"
                />
            </x-admin.card>
        @else
            <p class="crud-note">New contributors start as <strong class="font-semibold text-slate-700">Active</strong>.</p>
        @endif
    </div>
</div>
