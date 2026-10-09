@extends('layouts.admin')

@section('title', __('Create Edition'))

@section('subtitle', __('A season has its own teams, registrations, squads, matches and auction.'))

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.editions.index') }}" class="ops-back">
            <x-ops.icon name="arrow-left" class="h-3.5 w-3.5" />
            {{ __('Back to editions') }}
        </a>
    </div>

    <form method="POST" action="{{ route('admin.editions.store') }}" class="max-w-3xl" novalidate>
        @csrf

        @include('admin.editions._form')

        <div class="ops-savebar">
            <button type="submit" class="btn btn-primary btn-lg max-sm:flex-1">{{ __('Save edition') }}</button>
            <a href="{{ route('admin.editions.index') }}" class="btn btn-secondary btn-lg">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
