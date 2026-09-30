@extends('layouts.admin')

@section('title', 'Register Player')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.player-registrations.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to registrations
        </a>
    </div>

    <div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.player-registrations.store') }}" novalidate>
            @csrf

            @include('admin.player-registrations._form')

            <div class="mt-4 flex items-center gap-2">
                <x-admin.button>Save registration</x-admin.button>
                <x-admin.button href="{{ route('admin.player-registrations.index') }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
