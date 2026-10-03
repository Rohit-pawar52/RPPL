@extends('layouts.admin')

@section('title', 'Add Match')

@section('content')
    @include('admin.editions._crumbs', ['edition' => $edition, 'section' => 'Matches'])

    <div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-4">
        <h1 class="mb-3 text-sm font-semibold text-slate-800">Add a match to {{ $edition->name }}</h1>

        <form method="POST" action="{{ route('admin.editions.matches.store', $edition) }}" id="season-match-form" novalidate>
            @csrf

            @include('admin.matches._form', ['fixedEdition' => $edition])

            <div class="mt-4 flex items-center gap-2">
                <x-admin.button type="submit">Save match</x-admin.button>
                <x-admin.button :href="route('admin.editions.matches.index', $edition)" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
