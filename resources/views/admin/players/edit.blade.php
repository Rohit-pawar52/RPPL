@extends('layouts.admin')

@section('title', 'Edit Player')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.players.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to players
        </a>
    </div>

    <div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.players.update', $player) }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')

            @include('admin.players._form')

            <div class="mt-4 flex items-center gap-2">
                <x-admin.button>Save changes</x-admin.button>
                <x-admin.button href="{{ route('admin.players.index') }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
