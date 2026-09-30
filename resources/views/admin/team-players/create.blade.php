@extends('layouts.admin')

@section('title', 'Add Player to Squad')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.team-players.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to squads
        </a>
    </div>

    <div class="max-w-lg rounded-lg border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.team-players.store') }}" novalidate>
            @csrf

            @include('admin.team-players._form')

            <div class="mt-2 flex items-center gap-2">
                <x-admin.button>Add to squad</x-admin.button>
                <x-admin.button href="{{ route('admin.team-players.index') }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
