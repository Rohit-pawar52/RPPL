@extends('layouts.admin')

@section('title', 'Edit Edition')

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.editions.show', $edition) }}" class="ops-back">
            <x-ops.icon name="arrow-left" class="h-3.5 w-3.5" />
            Back to {{ $edition->name }}
        </a>
    </div>

    <form method="POST" action="{{ route('admin.editions.update', $edition) }}" class="max-w-3xl" novalidate>
        @csrf
        @method('PUT')

        @include('admin.editions._form')

        <div class="ops-savebar">
            <button type="submit" class="btn btn-primary btn-lg max-sm:flex-1">Save changes</button>
            <a href="{{ route('admin.editions.show', $edition) }}" class="btn btn-secondary btn-lg">Cancel</a>
        </div>
    </form>
@endsection
