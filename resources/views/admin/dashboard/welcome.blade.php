@extends('layouts.admin')

@section('title', __('Dashboard'))
@section('bare', '1')

{{-- Shown to a role that may enter the panel but has no dashboard of its own
     (no tournament dashboard, no auction). Deliberately static: no figures,
     no queries — the sidebar lists whatever the role is allowed to open. --}}
@section('content')
    @php
        $hour = (int) display_datetime(now(), 'G');
        $greeting = $hour < 12 ? __('Good morning') : ($hour < 17 ? __('Good afternoon') : __('Good evening'));
        $firstName = \Illuminate\Support\Str::before(trim(auth()->user()->name), ' ');
    @endphp

    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-navy-900 to-navy-800 p-5 text-white shadow-raised sm:p-7">
        <div class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full bg-brand opacity-30 blur-3xl" aria-hidden="true"></div>
        <div class="relative">
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-accent-dark">{{ display_datetime(now(), 'l, j F') }}</p>
            <h1 class="mt-1.5 break-words text-2xl font-bold tracking-tight sm:text-3xl">{{ $greeting }}, {{ $firstName }}</h1>
            <p class="mt-2 text-[13px] text-white/70">{{ __('You are signed in as :role.', ['role' => auth()->user()->role?->name]) }}</p>
        </div>
    </section>

    <x-admin.card :title="__('Welcome')" class="mt-6 max-w-2xl">
        <p class="text-[13px] leading-5 text-slate-600">
            {!! __('Use the sections in the sidebar, or press :key and type a few letters, to get to the pages your role can open.', ['key' => '<kbd class="adm-kbd">Ctrl K</kbd>']) !!}
        </p>
    </x-admin.card>
@endsection
