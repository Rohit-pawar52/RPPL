@extends('layouts.admin')

@section('title', 'Add Video')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.videos.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to videos
        </a>
    </div>

    <div class="max-w-lg rounded-lg border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.videos.store') }}" enctype="multipart/form-data" novalidate>
            @csrf

            @include('admin.videos._form')

            <div class="mt-2 flex items-center gap-2">
                <x-admin.button>Save video</x-admin.button>
                <x-admin.button href="{{ route('admin.videos.index') }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
