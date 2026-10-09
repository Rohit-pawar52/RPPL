@extends('layouts.admin')

@section('title', __('Content Pages'))

@php
    $tabs = [
        \App\Models\ContentPage::TYPE_PRIVACY_POLICY => __('Privacy Policy'),
        \App\Models\ContentPage::TYPE_TERMS_CONDITIONS => __('Terms & Conditions'),
        \App\Models\ContentPage::TYPE_FAQS => __('FAQs'),
    ];
@endphp

@section('subtitle', __('Fixed public pages linked from the footer. Disabling one also removes its public URL.'))

@section('content')
    {{-- Query-string tab selection (?tab=privacy_policy) — a validation-
         failure redirect back to the referring URL naturally reopens the
         same tab without any extra state to track. --}}
    <x-crud.tabs :aria-label="__('Content pages')">
        @foreach($tabs as $tabKey => $tabLabel)
            <x-crud.tab :href="route('admin.content-pages.index', ['tab' => $tabKey])" :active="$activeTab === $tabKey">{{ $tabLabel }}</x-crud.tab>
        @endforeach
    </x-crud.tabs>

    @include('admin.content-pages._form', ['page' => $pages[$activeTab]])
@endsection
