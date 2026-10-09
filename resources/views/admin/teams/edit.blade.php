@extends('layouts.admin')

@section('title', 'Edit Team')

@section('content')
    <x-crud.back :href="route('admin.teams.index')">Teams</x-crud.back>

    <x-crud.form :action="route('admin.teams.update', $team)" method="PUT" :cancel="route('admin.teams.index')" submit="Save changes" files>
        @include('admin.teams._form')
    </x-crud.form>
@endsection
