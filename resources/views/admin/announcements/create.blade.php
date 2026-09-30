@extends('layouts.admin')

@section('title', 'Add Announcement')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.announcements.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to announcements
        </a>
    </div>

    <div class="max-w-lg rounded-lg border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.announcements.store') }}" novalidate>
            @csrf

            @include('admin.announcements._form')

            <div class="mt-2 flex items-center gap-2">
                <x-admin.button>Save announcement</x-admin.button>
                <x-admin.button href="{{ route('admin.announcements.index') }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
