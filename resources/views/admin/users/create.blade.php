@extends('layouts.admin')

@section('title', __('Add User'))
@section('subtitle', __('Create a login for the admin panel. Choose a role to decide what it can do.'))

@section('actions')
    <x-admin.button href="{{ route('admin.users.index') }}" variant="secondary" icon="arrow-left">{{ __('Back to users') }}</x-admin.button>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.users.store') }}" novalidate class="max-w-3xl space-y-6">
        @csrf

        <x-admin.card :title="__('Who is it for?')">
            <div class="grid gap-x-4 sm:grid-cols-2">
                <x-form.input name="name" :label="__('Name')" :value="old('name')" required autofocus />
                <x-form.input name="email" :label="__('Email')" type="email" :value="old('email')" required />
            </div>

            <div class="sm:max-w-xs">
                <x-form.select
                    name="role_id"
                    :label="__('Role')"
                    :placeholder="__('Select role')"
                    :options="$roles->pluck('name', 'id')"
                    :value="old('role_id')"
                />
            </div>
            @can('viewAny', \App\Models\Role::class)
                <p class="-mt-2 text-[11px] text-slate-500">
                    {!! __('What each role may do is set under :link.', ['link' => '<a href="'.e(route('admin.roles.index')).'" class="font-medium text-link hover:text-link-hover hover:underline">'.e(__('Roles')).'</a>']) !!}
                </p>
            @endcan
        </x-admin.card>

        <x-admin.card :title="__('Sign-in password')">
            <div class="grid gap-x-4 sm:grid-cols-2">
                <x-form.input name="password" :label="__('Password')" type="password" required autocomplete="new-password" />
                <x-form.input name="password_confirmation" :label="__('Confirm Password')" type="password" required autocomplete="new-password" />
            </div>
        </x-admin.card>

        <x-admin.form-actions>
            <x-admin.button>{{ __('Save user') }}</x-admin.button>
            <x-admin.button href="{{ route('admin.users.index') }}" variant="secondary">{{ __('Cancel') }}</x-admin.button>
        </x-admin.form-actions>
    </form>
@endsection
