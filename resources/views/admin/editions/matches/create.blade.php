@extends('layouts.admin')

@section('title', __('Add Match'))

@section('content')
    @include('admin.editions._crumbs', ['edition' => $edition, 'section' => __('Matches')])

    <h2 class="mb-3 text-base font-semibold tracking-tight text-slate-900">{{ __('Add a match to :name', ['name' => $edition->name]) }}</h2>

    <form method="POST" action="{{ route('admin.editions.matches.store', $edition) }}" id="season-match-form" class="max-w-3xl" novalidate>
        @csrf

        @include('admin.matches._form', ['fixedEdition' => $edition])

        <div class="ops-savebar">
            <button type="submit" class="btn btn-primary btn-lg max-sm:flex-1">{{ __('Save match') }}</button>
            <a href="{{ route('admin.editions.matches.index', $edition) }}" class="btn btn-secondary btn-lg">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
