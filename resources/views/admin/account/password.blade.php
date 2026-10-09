@extends('layouts.admin')

@section('title', __('Change Password'))
@section('subtitle', __('Choose a new password for your own account.'))

@section('content')
    @if(! empty($publicPassword))
        {{-- Amber stays fixed in the theme because it carries meaning (see the README). --}}
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-[13px] leading-5 text-amber-900" role="alert">
            <p class="font-semibold">{{ __('This login still uses a publicly known password.') }}</p>
            <p class="mt-1">{{ __("Anyone who has read the project's instructions could sign in with it. Choose a new password below; nothing else in the admin panel opens until you do.") }}</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3 lg:items-start">
        <x-admin.card :title="__('Your password')" class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.account.password.update') }}" novalidate class="max-w-lg">
                @csrf
                @method('PUT')

                <x-form.input name="current_password" :label="__('Current password')" type="password" required autofocus autocomplete="current-password" />

                <x-form.input
                    name="password"
                    :label="__('New password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :help="__('At least :count characters, and different from your current password.', ['count' => config('admin.password_min_length')])"
                />

                <x-form.input name="password_confirmation" :label="__('Confirm new password')" type="password" required autocomplete="new-password" />

                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <x-admin.button>{{ __('Update password') }}</x-admin.button>
                    {{-- A role without panel access has no dashboard to go back to. --}}
                    @can('access-admin-panel')
                        <x-admin.button href="{{ route('admin.dashboard') }}" variant="secondary">{{ __('Cancel') }}</x-admin.button>
                    @endcan
                </div>
            </form>
        </x-admin.card>

        <aside class="rounded-xl border border-line bg-brand-soft p-4 text-[13px] leading-5 text-slate-700 sm:p-5">
            <p class="flex items-center gap-2 font-semibold text-slate-900"><x-admin.icon name="lock" class="h-4 w-4 text-brand" /> {{ __('A good password') }}</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-slate-600">
                <li>{{ __('is long: a few words you can remember beat a short jumble') }}</li>
                <li>{{ __('is not used on any other site') }}</li>
                <li>{{ __('is never shared in a message or chat') }}</li>
            </ul>
        </aside>
    </div>
@endsection
