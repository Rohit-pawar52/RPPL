@extends('layouts.admin')

@section('title', 'Edit Team')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.teams.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to teams
        </a>
    </div>

    <div class="max-w-2xl rounded-lg border border-neutral-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.teams.update', $team) }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')

            @include('admin.teams._form')

            <div class="mt-4 flex items-center gap-2">
                <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
                    Save changes
                </button>
                <a href="{{ route('admin.teams.index') }}" class="rounded-md border border-neutral-200 px-3 py-2 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
