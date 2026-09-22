@extends('layouts.guest')

@section('title', 'Login')

@section('content')
    <h1 class="mb-1 text-lg font-semibold text-neutral-900">Sign in</h1>
    <p class="mb-5 text-xs text-neutral-500">Enter your admin or scorer credentials.</p>

    <form method="POST" action="{{ route('admin.login.store') }}" novalidate>
        @csrf

        <x-form.input
            name="email"
            type="email"
            label="Email address"
            autofocus
            required
            autocomplete="username"
        />

        <x-form.input
            name="password"
            type="password"
            label="Password"
            required
            autocomplete="current-password"
        />

        <button
            type="submit"
            class="mt-2 w-full rounded-md theme-button px-3 py-2 text-[13px] font-medium"
        >
            Sign in
        </button>
    </form>
@endsection
