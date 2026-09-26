@extends('layouts.admin')

@section('title', 'Data Cleanup')

@php
    $tabs = [
        'notifications' => 'Notifications',
        'registration-documents' => 'Registration Documents',
        'system' => 'System',
    ];
@endphp

@section('content')
    <div class="mb-4">
        <p class="text-[13px] text-neutral-500">
            Bulk data retention cleanup. Nothing here runs automatically — every action below shows an exact affected-record count first and requires explicit confirmation. None of this can be undone.
        </p>
    </div>

    {{-- Query-string tab selection (?tab=notifications) — mirrors
         admin.settings.index exactly, so a validation-failure redirect
         back to the referring URL naturally reopens the same tab. --}}
    <div class="mb-4 flex flex-wrap gap-1 border-b border-neutral-200">
        @foreach($tabs as $tabKey => $tabLabel)
            <a
                href="{{ route('admin.data-cleanup.index', ['tab' => $tabKey]) }}"
                class="rounded-t-md px-3 py-2 text-[13px] font-medium {{ $activeTab === $tabKey ? 'border-b-2 theme-primary-border theme-primary-text' : 'text-neutral-500 hover:text-neutral-700' }}"
            >
                {{ $tabLabel }}
            </a>
        @endforeach
    </div>

    <div class="max-w-3xl rounded-lg border border-neutral-200 bg-white p-4">
        @include('admin.data-cleanup.tabs.' . str_replace('-', '_', $activeTab))
    </div>
@endsection
