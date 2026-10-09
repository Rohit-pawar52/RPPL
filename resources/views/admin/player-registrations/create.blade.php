@extends('layouts.admin')

@section('title', 'Register Player')

@section('subtitle', 'For someone who registered on paper or on the phone. The public form does this by itself.')

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.player-registrations.index') }}" class="ops-back">
            <x-ops.icon name="arrow-left" class="h-3.5 w-3.5" />
            Back to registrations
        </a>
    </div>

    <form method="POST" action="{{ route('admin.player-registrations.store') }}" class="max-w-3xl" novalidate>
        @csrf

        @include('admin.player-registrations._form')

        <div class="ops-savebar">
            <button type="submit" class="btn btn-primary btn-lg max-sm:flex-1">Save registration</button>
            <a href="{{ route('admin.player-registrations.index') }}" class="btn btn-secondary btn-lg">Cancel</a>
        </div>
    </form>
@endsection
