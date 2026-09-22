@extends('layouts.admin')

@section('title', 'Add Team to Edition')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.edition-teams.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to edition teams
        </a>
    </div>

    <div class="max-w-lg rounded-lg border border-neutral-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.edition-teams.store') }}" novalidate>
            @csrf

            <x-form.select
                name="edition_id"
                label="Edition"
                placeholder="Select an edition"
                :options="$editions->pluck('name', 'id')"
                required
            />
            <x-form.select
                name="team_id"
                label="Team"
                placeholder="Select a team"
                :options="$teams->pluck('name', 'id')"
                required
            />

            <div class="mt-2 flex items-center gap-2">
                <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
                    Add team
                </button>
                <a href="{{ route('admin.edition-teams.index') }}" class="rounded-md border border-neutral-200 px-3 py-2 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
