@extends('layouts.admin')

@section('title', 'Add News')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.news.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to news
        </a>
    </div>

    <div class="max-w-lg rounded-lg border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.news.store') }}" enctype="multipart/form-data" novalidate>
            @csrf

            @include('admin.news._form')

            <div class="mt-2 flex items-center gap-2">
                <x-admin.button>Save news</x-admin.button>
                <x-admin.button href="{{ route('admin.news.index') }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
