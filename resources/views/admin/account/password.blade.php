@extends('layouts.admin')

@section('title', 'Change Password')
@section('subtitle', 'Choose a new password for your own account.')

@section('content')
    <div class="max-w-xl rounded-lg border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.account.password.update') }}" novalidate>
            @csrf
            @method('PUT')

            <x-form.input name="current_password" label="Current password" type="password" required autofocus autocomplete="current-password" />

            <x-form.input
                name="password"
                label="New password"
                type="password"
                required
                autocomplete="new-password"
                :help="'At least '.config('admin.password_min_length').' characters, and different from your current password.'"
            />

            <x-form.input name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />

            <div class="mt-2 flex items-center gap-2">
                <x-admin.button>Update password</x-admin.button>
                {{-- A role without panel access has no dashboard to go back to. --}}
                @can('access-admin-panel')
                    <x-admin.button href="{{ route('admin.dashboard') }}" variant="secondary">Cancel</x-admin.button>
                @endcan
            </div>
        </form>
    </div>
@endsection
