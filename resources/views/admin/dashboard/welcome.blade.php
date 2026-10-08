@extends('layouts.admin')

@section('title', 'Dashboard')

{{-- Shown to a role that may enter the panel but has no dashboard of its own
     (no tournament dashboard, no auction). Deliberately static: no figures,
     no queries — the sidebar lists whatever the role is allowed to open. --}}
@section('content')
    <p class="mb-4 text-xs text-neutral-500">
        Welcome back, {{ auth()->user()->name }}. You are signed in as
        <span class="font-medium text-neutral-700">{{ auth()->user()->role?->name }}</span>.
    </p>

    <x-admin.card title="Welcome" class="max-w-xl">
        <p class="text-[13px] text-slate-600">
            Use the sections in the sidebar to get to the pages your role can open.
        </p>
    </x-admin.card>
@endsection
