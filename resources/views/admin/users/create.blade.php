@extends('layouts.admin')

@section('title', 'Add User')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.users.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to users
        </a>
    </div>

    <div class="max-w-lg rounded-lg border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.users.store') }}" novalidate>
            @csrf

            <x-form.input name="name" label="Name" :value="old('name')" required autofocus />
            <x-form.input name="email" label="Email" type="email" :value="old('email')" required />

            <x-form.select
                name="role_id"
                label="Role"
                placeholder="Select role"
                :options="$roles->pluck('name', 'id')"
                :value="old('role_id')"
            />

            <x-form.input name="password" label="Password" type="password" required />
            <x-form.input name="password_confirmation" label="Confirm Password" type="password" required />

            <div class="mt-2 flex items-center gap-2">
                <x-admin.button>Save user</x-admin.button>
                <x-admin.button href="{{ route('admin.users.index') }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
