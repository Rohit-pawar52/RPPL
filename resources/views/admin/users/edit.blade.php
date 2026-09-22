@extends('layouts.admin')

@section('title', 'Edit User')

@section('content')
    @php
        $isSelf = $targetUser->id === auth()->id();
    @endphp

    <div class="mb-4">
        <a href="{{ route('admin.users.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to users
        </a>
    </div>

    <div class="max-w-lg rounded-lg border border-neutral-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.users.update', $targetUser) }}" novalidate>
            @csrf
            @method('PUT')

            <x-form.input name="name" label="Name" :value="old('name', $targetUser->name)" required autofocus />
            <x-form.input name="email" label="Email" type="email" :value="old('email', $targetUser->email)" required />

            @if($isSelf)
                {{-- Server-side rules always block a self-demotion/self-deactivation
                     regardless of what is submitted — these fields are simply
                     hidden here so the admin isn't offered an action that will
                     always be rejected. --}}
                <input type="hidden" name="role_id" value="{{ $targetUser->role_id }}" />
                <input type="hidden" name="is_active" value="1" />
                <div class="mb-3.5 text-xs text-neutral-500">
                    <p><span class="font-medium text-neutral-700">Role:</span> {{ $targetUser->role->name }} (you cannot change your own role)</p>
                    <p><span class="font-medium text-neutral-700">Status:</span> Active (you cannot deactivate your own account)</p>
                </div>
            @else
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
                />
            @endif

            <x-form.input name="password" label="New Password" type="password" />
            <x-form.input name="password_confirmation" label="Confirm New Password" type="password" />
            <p class="-mt-2 mb-3.5 text-xs text-neutral-400">Leave password blank to keep the current password.</p>

            <div class="mt-2 flex items-center gap-2">
                <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
                    Save changes
                </button>
                <a href="{{ route('admin.users.index') }}" class="rounded-md border border-neutral-200 px-3 py-2 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
