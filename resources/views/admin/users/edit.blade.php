@extends('layouts.admin')

@section('title', __('Edit User'))
@section('subtitle', $targetUser->name.' · '.$targetUser->email)

@section('actions')
    <x-admin.button href="{{ route('admin.users.index') }}" variant="secondary" icon="arrow-left">{{ __('Back to users') }}</x-admin.button>
@endsection

@section('content')
    @php
        $isSelf = $targetUser->id === auth()->id();
    @endphp

    <form method="POST" action="{{ route('admin.users.update', $targetUser) }}" novalidate class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <x-admin.card :title="__('Account')">
            <div class="grid gap-x-4 sm:grid-cols-2">
                <x-form.input name="name" :label="__('Name')" :value="old('name', $targetUser->name)" required autofocus />
                <x-form.input name="email" :label="__('Email')" type="email" :value="old('email', $targetUser->email)" required />
            </div>

            @if($isSelf)
                {{-- Server-side rules always block a self-demotion/self-deactivation
                     regardless of what is submitted — these fields are simply
                     hidden here so the admin isn't offered an action that will
                     always be rejected. --}}
                <input type="hidden" name="role_id" value="{{ $targetUser->role_id }}" />
                <input type="hidden" name="is_active" value="1" />
                <div class="flex gap-2.5 rounded-lg bg-slate-50 px-3.5 py-3 text-xs text-slate-600">
                    <x-admin.icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                    <div class="space-y-0.5">
                        <p><span class="font-semibold text-slate-800">{{ __('Role') }}:</span> {{ $targetUser->role->name }} {{ __('(you cannot change your own role)') }}</p>
                        <p><span class="font-semibold text-slate-800">{{ __('Status') }}:</span> {{ __('Active') }} {{ __('(you cannot deactivate your own account)') }}</p>
                    </div>
                </div>
            @else
                <div class="grid gap-x-4 sm:grid-cols-2">
                    <x-form.select
                        name="role_id"
                        :label="__('Role')"
                        :placeholder="__('Select role')"
                        :options="$roles->pluck('name', 'id')"
                        :value="old('role_id', $targetUser->role_id)"
                    />

                    <x-form.select
                        name="is_active"
                        :label="__('Status')"
                        :options="['1' => __('Active'), '0' => __('Inactive')]"
                        :value="old('is_active', $targetUser->is_active ? '1' : '0')"
                        :help="__('An inactive user can no longer sign in.')"
                    />
                </div>
                @can('viewAny', \App\Models\Role::class)
                    <p class="-mt-2 text-[11px] text-slate-500">
                        {!! __('What each role may do is set under :link.', ['link' => '<a href="'.e(route('admin.roles.index')).'" class="font-medium text-link hover:text-link-hover hover:underline">'.e(__('Roles')).'</a>']) !!}
                    </p>
                @endcan
            @endif
        </x-admin.card>

        <x-admin.card :title="__('Change password')" :subtitle="__('Leave password blank to keep the current password.')">
            <div class="grid gap-x-4 sm:grid-cols-2">
                <x-form.input name="password" :label="__('New Password')" type="password" autocomplete="new-password" />
                <x-form.input name="password_confirmation" :label="__('Confirm New Password')" type="password" autocomplete="new-password" />
            </div>
        </x-admin.card>

        <x-admin.form-actions>
            <x-admin.button>{{ __('Save changes') }}</x-admin.button>
            <x-admin.button href="{{ route('admin.users.index') }}" variant="secondary">{{ __('Cancel') }}</x-admin.button>
        </x-admin.form-actions>
    </form>
@endsection
