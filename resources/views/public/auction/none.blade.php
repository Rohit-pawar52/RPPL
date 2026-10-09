@extends('layouts.public')

@section('title', __('auction.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('auction.title')" />

    <div class="pc-empty mx-auto max-w-xl !py-14">
        <span class="pc-empty-icon h-16 w-16"><x-icon name="trophy" class="h-8 w-8" /></span>
        <p class="pc-empty-title text-base">{{ __('auction.none') }}</p>
        <p class="pc-empty-hint">{{ __('auction.none_hint') }}</p>
        <div class="mt-4 flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
            <a href="{{ route('public.matches.index') }}" class="btn btn-primary">{{ __('ux_public_content.auction.see_matches') }}</a>
            <a href="{{ route('public.teams.index') }}" class="btn btn-secondary">{{ __('ux_public_content.auction.see_teams') }}</a>
        </div>
    </div>
@endsection
