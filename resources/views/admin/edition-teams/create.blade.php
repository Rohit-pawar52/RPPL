@extends('layouts.admin')

@section('title', 'Add Team to Edition')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.edition-teams.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to edition teams
        </a>
    </div>

    <div class="max-w-lg rounded-lg border border-slate-200 bg-white p-4">
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
                <x-admin.button>Add team</x-admin.button>
                <x-admin.button href="{{ route('admin.edition-teams.index') }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
