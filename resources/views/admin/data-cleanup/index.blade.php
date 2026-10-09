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
    <x-crud.tabs aria-label="Data cleanup sections">
        @foreach($tabs as $tabKey => $tabLabel)
            <x-crud.tab :href="route('admin.data-cleanup.index', ['tab' => $tabKey])" :active="$activeTab === $tabKey">{{ $tabLabel }}</x-crud.tab>
        @endforeach
    </x-crud.tabs>

    <div class="crud-note crud-note-warn mb-4 flex items-start gap-2.5">
        <x-crud.glyph name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
        <span>Deleting here is permanent. Each action below shows how many records it would remove before you confirm.</span>
    </div>

    <div class="max-w-4xl space-y-4">
        @include('admin.data-cleanup.tabs.' . str_replace('-', '_', $activeTab))
    </div>
@endsection
