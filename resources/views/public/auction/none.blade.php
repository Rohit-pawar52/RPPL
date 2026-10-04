@extends('layouts.public')

@section('title', __('auction.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('auction.title')" />

    <div class="pub-card px-4 py-10 text-center">
        <x-icon name="trophy" class="mx-auto h-5 w-5 text-slate-300" />
        <p class="mt-2 text-[13px] text-slate-600">{{ __('auction.none') }}</p>
        <p class="pub-meta mt-0.5">{{ __('auction.none_hint') }}</p>
    </div>
@endsection
