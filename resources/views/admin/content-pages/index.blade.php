@extends('layouts.admin')

@section('title', 'Content Pages')

@php
    $tabs = [
        \App\Models\ContentPage::TYPE_PRIVACY_POLICY => 'Privacy Policy',
        \App\Models\ContentPage::TYPE_TERMS_CONDITIONS => 'Terms & Conditions',
        \App\Models\ContentPage::TYPE_FAQS => 'FAQs',
    ];
@endphp

@section('content')
    <div class="mb-4">
        <p class="text-[13px] text-neutral-500">
            Fixed public pages, linked from the site footer. Disabling a page removes both its footer link and its public URL (visitors get a 404).
        </p>
    </div>

    {{-- Query-string tab selection (?tab=privacy_policy) — a validation-
         failure redirect back to the referring URL naturally reopens the
         same tab without any extra state to track. --}}
    <div class="mb-4 flex flex-wrap gap-1 border-b border-neutral-200">
        @foreach($tabs as $tabKey => $tabLabel)
            <a
                href="{{ route('admin.content-pages.index', ['tab' => $tabKey]) }}"
                class="rounded-t-md px-3 py-2 text-[13px] font-medium {{ $activeTab === $tabKey ? 'border-b-2 theme-primary-border theme-primary-text' : 'text-neutral-500 hover:text-neutral-700' }}"
            >
                {{ $tabLabel }}
            </a>
        @endforeach
    </div>

    <div class="max-w-2xl rounded-lg border border-neutral-200 bg-white p-4">
        @include('admin.content-pages._form', ['page' => $pages[$activeTab]])
    </div>
@endsection
