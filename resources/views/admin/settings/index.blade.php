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
            Site-wide configuration: branding, contact details, system options, payment details and public-website text.
        </p>
    </div>

    {{-- Query-string tab selection (?tab=general) — a validation-failure
         redirect back to the referring URL naturally reopens the same
         tab without any extra state to track. --}}
    <div class="mb-4 flex flex-wrap gap-1 border-b border-neutral-200">
        @foreach($tabs as $tabKey => $tabLabel)
            <a
                href="{{ route('admin.settings.index', ['tab' => $tabKey]) }}"
                class="rounded-t-md px-3 py-2 text-[13px] font-medium {{ $activeTab === $tabKey ? 'border-b-2 theme-primary-border theme-primary-text' : 'text-neutral-500 hover:text-neutral-700' }}"
            >
                {{ $tabLabel }}
            </a>
        @endforeach
    </div>

    @if($activeTab === 'general')
        {{-- The General tab is made of its own cards. --}}
        <div class="max-w-5xl">
            @include('admin.settings.tabs.general')
        </div>
    @else
        <div class="{{ $activeTab === 'payments' ? 'max-w-5xl' : 'max-w-2xl' }} rounded-xl border border-line bg-white p-4 sm:p-5">
            @include('admin.settings.tabs.' . $activeTab)
        </div>
    @endif
@endsection
