@extends('layouts.admin')

@section('title', 'Content Pages')

@php
    $tabs = [
        \App\Models\ContentPage::TYPE_PRIVACY_POLICY => 'Privacy Policy',
        \App\Models\ContentPage::TYPE_TERMS_CONDITIONS => 'Terms & Conditions',
        \App\Models\ContentPage::TYPE_FAQS => 'FAQs',
    ];
@endphp

@section('subtitle', "Fixed public pages linked from the footer. Disabling one also removes its public URL.")

@section('content')

    {{-- Query-string tab selection (?tab=privacy_policy) — a validation-
         failure redirect back to the referring URL naturally reopens the
         same tab without any extra state to track. --}}
    <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
        @foreach($tabs as $tabKey => $tabLabel)
            <a
                href="{{ route('admin.content-pages.index', ['tab' => $tabKey]) }}"
                class="rounded-t-md px-3 py-2 text-[13px] font-medium {{ $activeTab === $tabKey ? 'border-b-2 border-green-600 text-green-700' : 'text-slate-500 hover:text-slate-700' }}"
            >
                {{ $tabLabel }}
            </a>
        @endforeach
    </div>

    <div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-4">
        @include('admin.content-pages._form', ['page' => $pages[$activeTab]])
    </div>
@endsection
