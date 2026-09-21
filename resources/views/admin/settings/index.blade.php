@extends('layouts.admin')

@section('title', 'Settings')

@php
    $tabs = [
        'general' => 'General',
        'contact' => 'Contact',
        'system' => 'System',
        'payments' => 'Payments',
        'public-website' => 'Public Website',
    ];
@endphp

@section('content')
    <div class="mb-4">
        <p class="text-[13px] text-neutral-500">
            Site-wide configuration. Nothing here is consumed anywhere else on the site yet — saving a value only stores it.
        </p>
    </div>

    {{-- Query-string tab selection (?tab=general) — a validation-failure
         redirect back to the referring URL naturally reopens the same
         tab without any extra state to track. --}}
    <div class="mb-4 flex flex-wrap gap-1 border-b border-neutral-200">
        @foreach($tabs as $tabKey => $tabLabel)
            <a
                href="{{ route('admin.settings.index', ['tab' => $tabKey]) }}"
                class="rounded-t-md px-3 py-2 text-[13px] font-medium {{ $activeTab === $tabKey ? 'border-b-2 border-blue-600 text-blue-700' : 'text-neutral-500 hover:text-neutral-700' }}"
            >
                {{ $tabLabel }}
            </a>
        @endforeach
    </div>

    <div class="max-w-2xl rounded-lg border border-neutral-200 bg-white p-4">
        @include('admin.settings.tabs.' . $activeTab)
    </div>
@endsection
