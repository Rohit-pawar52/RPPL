@extends('layouts.admin')

@section('title', 'Edit Notification')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.notifications.show', $notification) }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to notification
        </a>
    </div>

    <div class="max-w-lg rounded-lg border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.notifications.update', $notification) }}" novalidate>
            @csrf
            @method('PUT')

            @include('admin.notifications._form')

            <div class="mt-2 flex items-center gap-2">
                <x-admin.button>Save changes</x-admin.button>
                <x-admin.button href="{{ route('admin.notifications.show', $notification) }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
