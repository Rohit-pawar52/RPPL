@extends('layouts.admin')

@section('title', __('Schedule Match'))

@section('subtitle', __('Pick the teams, the time and the place — everything else is pre-filled.'))

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.matches.index') }}" class="ops-back">
            <x-ops.icon name="arrow-left" class="h-3.5 w-3.5" />
            {{ __('Back to matches') }}
        </a>
    </div>

    <form method="POST" action="{{ route('admin.matches.store') }}" class="max-w-3xl" novalidate>
        @csrf

        @include('admin.matches._form')

        <div class="ops-savebar">
            <button type="submit" class="btn btn-primary btn-lg max-sm:flex-1">{{ __('Save match') }}</button>
            <a href="{{ route('admin.matches.index') }}" class="btn btn-secondary btn-lg">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
