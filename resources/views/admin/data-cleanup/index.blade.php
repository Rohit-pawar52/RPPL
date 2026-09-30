@extends('layouts.admin')

@section('title', 'Data Cleanup')

@php
    $tabs = [
        'notifications' => 'Notifications',
        'registration-documents' => 'Registration Documents',
        'media-files' => 'Media Files',
        'system' => 'System',
    ];
@endphp

@section('subtitle', 'Bulk retention cleanup. Nothing runs automatically: each action shows an exact count first and needs confirmation. None of it can be undone.')

@section('content')
    {{-- Query-string tab selection (?tab=notifications) — mirrors
         admin.settings.index exactly, so a validation-failure redirect
         back to the referring URL naturally reopens the same tab. --}}
    <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
        @foreach($tabs as $tabKey => $tabLabel)
            <a
                href="{{ route('admin.data-cleanup.index', ['tab' => $tabKey]) }}"
                class="rounded-t-md px-3 py-2 text-[13px] font-medium {{ $activeTab === $tabKey ? 'border-b-2 border-green-600 text-green-700' : 'text-slate-500 hover:text-slate-700' }}"
            >
                {{ $tabLabel }}
            </a>
        @endforeach
    </div>

    <div class="max-w-3xl rounded-lg border border-slate-200 bg-white p-4">
        @include('admin.data-cleanup.tabs.' . str_replace('-', '_', $activeTab))
    </div>
@endsection
