@extends('layouts.admin')

@section('title', 'Add Rule')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.rules.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to rules
        </a>
    </div>

    <div class="max-w-lg rounded-lg border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.rules.store') }}" enctype="multipart/form-data" novalidate>
            @csrf

            @include('admin.rules._form')

            <div class="mt-2 flex items-center gap-2">
                <x-admin.button>Save rule</x-admin.button>
                <x-admin.button href="{{ route('admin.rules.index') }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
