@extends('layouts.admin')

@section('title', 'New Notification')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.notifications.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to notifications
        </a>
    </div>

    <div class="max-w-lg rounded-lg border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.notifications.store') }}" novalidate>
            @csrf

            @include('admin.notifications._form')

            <div class="mt-2 flex items-center gap-2">
                <x-admin.button>Save notification</x-admin.button>
                <x-admin.button href="{{ route('admin.notifications.index') }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
