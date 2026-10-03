@extends('layouts.admin')

@section('title', 'Add Venue')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.venues.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to venues
        </a>
    </div>

    <div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.venues.store') }}" novalidate>
            @csrf

            @include('admin.venues._form')

            <div class="mt-2 flex items-center gap-2">
                <x-admin.button>Save venue</x-admin.button>
                <x-admin.button href="{{ route('admin.venues.index') }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
