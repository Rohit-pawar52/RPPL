@extends('layouts.admin')

@section('title', 'Edit User')
@section('subtitle', $targetUser->name.' · '.$targetUser->email)

@section('actions')
    <x-admin.button href="{{ route('admin.users.index') }}" variant="secondary" icon="arrow-left">Back to users</x-admin.button>
@endsection

@section('content')
    @php
        $isSelf = $targetUser->id === auth()->id();
    @endphp

    <form method="POST" action="{{ route('admin.users.update', $targetUser) }}" novalidate class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <x-admin.card title="Account">
            <div class="grid gap-x-4 sm:grid-cols-2">
                <x-form.input name="name" label="Name" :value="old('name', $targetUser->name)" required autofocus />
                <x-form.input name="email" label="Email" type="email" :value="old('email', $targetUser->email)" required />
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
                        <p><span class="font-semibold text-slate-800">Role:</span> {{ $targetUser->role->name }} (you cannot change your own role)</p>
                        <p><span class="font-semibold text-slate-800">Status:</span> Active (you cannot deactivate your own account)</p>
                    </div>
                </div>
            @else
                <div class="grid gap-x-4 sm:grid-cols-2">
                    <x-form.select
                        name="role_id"
                        label="Role"
                        placeholder="Select role"
                        :options="$roles->pluck('name', 'id')"
                        :value="old('role_id', $targetUser->role_id)"
                    />

                    <x-form.select
                        name="is_active"
                        label="Status"
                        :options="['1' => 'Active', '0' => 'Inactive']"
                        :value="old('is_active', $targetUser->is_active ? '1' : '0')"
                        help="An inactive user can no longer sign in."
                    />
                </div>
                @can('viewAny', \App\Models\Role::class)
                    <p class="-mt-2 text-[11px] text-slate-500">
                        What each role may do is set under <a href="{{ route('admin.roles.index') }}" class="font-medium text-link hover:text-link-hover hover:underline">Roles</a>.
                    </p>
                @endcan
            @endif
        </x-admin.card>

        <x-admin.card title="Change password" subtitle="Leave password blank to keep the current password.">
            <div class="grid gap-x-4 sm:grid-cols-2">
                <x-form.input name="password" label="New Password" type="password" autocomplete="new-password" />
                <x-form.input name="password_confirmation" label="Confirm New Password" type="password" autocomplete="new-password" />
            </div>
        </x-admin.card>

        <x-admin.form-actions>
            <x-admin.button>Save changes</x-admin.button>
            <x-admin.button href="{{ route('admin.users.index') }}" variant="secondary">Cancel</x-admin.button>
        </x-admin.form-actions>
    </form>
@endsection
