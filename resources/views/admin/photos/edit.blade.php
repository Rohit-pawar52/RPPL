@extends('layouts.admin')

@section('title', 'Edit Photo')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.photos.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to photos
        </a>
    </div>

    <div class="max-w-lg rounded-lg border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.photos.update', $photo) }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')

            @include('admin.photos._form')

            <div class="mt-2 flex items-center gap-2">
                <x-admin.button>Save changes</x-admin.button>
                <x-admin.button href="{{ route('admin.photos.index') }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
