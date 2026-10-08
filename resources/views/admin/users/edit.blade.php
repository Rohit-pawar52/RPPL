@extends('layouts.admin')

@section('title', 'Edit User')

@section('content')
    @php
        $isSelf = $targetUser->id === auth()->id();
    @endphp

    <div class="mb-4">
        <a href="{{ route('admin.users.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to users
        </a>
    </div>

    <div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.users.update', $targetUser) }}" novalidate>
            @csrf
            @method('PUT')

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
                <div class="mb-3.5 text-xs text-slate-500">
                    <p><span class="font-medium text-slate-700">Role:</span> {{ $targetUser->role->name }} (you cannot change your own role)</p>
                    <p><span class="font-medium text-slate-700">Status:</span> Active (you cannot deactivate your own account)</p>
                </div>
            @else
                <x-form.select
                    name="role_id"
                    label="Role"
                    placeholder="Select role"
                    :options="$roles->pluck('name', 'id')"
                    :value="old('role_id', $targetUser->role_id)"
                />
                @can('viewAny', \App\Models\Role::class)
                    <p class="-mt-2 mb-3.5 text-[11px] text-slate-400">
                        What each role may do is set under <a href="{{ route('admin.roles.index') }}" class="underline hover:text-slate-600">Roles</a>.
                    </p>
                @endcan

                <x-form.select
                    name="is_active"
                    label="Status"
                    :options="['1' => 'Active', '0' => 'Inactive']"
                    :value="old('is_active', $targetUser->is_active ? '1' : '0')"
                />
            @endif

            <x-form.input name="password" label="New Password" type="password" />
            <x-form.input name="password_confirmation" label="Confirm New Password" type="password" />
            <p class="-mt-2 mb-3.5 text-xs text-slate-400">Leave password blank to keep the current password.</p>

            <div class="mt-2 flex items-center gap-2">
                <x-admin.button>Save changes</x-admin.button>
                <x-admin.button href="{{ route('admin.users.index') }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
