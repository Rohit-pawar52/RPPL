@extends('layouts.guest')

@section('title', 'Login')

@section('content')
    <h1 class="text-xl font-bold tracking-tight text-slate-900">Sign in</h1>
    <p class="mb-6 mt-1 text-[13px] text-slate-500">Enter your admin or scorer credentials.</p>

    <form method="POST" action="{{ route('admin.login.store') }}" novalidate>
        @csrf

        <x-form.input
            name="email"
            type="email"
            label="Email address"
            autofocus
            required
            autocomplete="username"
            inputmode="email"
        />

        <x-form.input
            name="password"
            type="password"
            label="Password"
            required
            autocomplete="current-password"
        />

        <div class="-mt-1 mb-5">
            <x-form.checkbox name="remember" label="Keep me signed in on this device" />
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block">
            Sign in
        </button>
    </form>
@endsection
