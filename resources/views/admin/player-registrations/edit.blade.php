@extends('layouts.admin')

@section('title', 'Edit Registration')

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.player-registrations.show', $registration) }}" class="ops-back">
            <x-ops.icon name="arrow-left" class="h-3.5 w-3.5" />
            Back to the registration
        </a>
    </div>

    <form method="POST" action="{{ route('admin.player-registrations.update', $registration) }}" class="max-w-3xl" novalidate>
        @csrf
        @method('PUT')

        @include('admin.player-registrations._form')

        <div class="ops-savebar">
            <button type="submit" class="btn btn-primary btn-lg max-sm:flex-1">Save changes</button>
            <a href="{{ route('admin.player-registrations.show', $registration) }}" class="btn btn-secondary btn-lg">Cancel</a>
        </div>
    </form>
@endsection
