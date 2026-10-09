@extends('layouts.admin')

@section('title', __('Add to Squad'))

@section('content')
    <x-crud.back :href="route('admin.team-players.index')">{{ __('Squads') }}</x-crud.back>

    <x-crud.form :action="route('admin.team-players.store')" :cancel="route('admin.team-players.index')" :submit="__('Add to squad')">
        @include('admin.team-players._form')
    </x-crud.form>
@endsection
