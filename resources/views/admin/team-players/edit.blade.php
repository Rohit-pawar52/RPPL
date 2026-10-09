@extends('layouts.admin')

@section('title', 'Edit Squad Player')

@section('content')
    <x-crud.back :href="route('admin.team-players.index')">Squads</x-crud.back>

    <x-crud.form :action="route('admin.team-players.update', $teamPlayer)" method="PUT" :cancel="route('admin.team-players.index')" submit="Save changes">
        @include('admin.team-players._form')
    </x-crud.form>
@endsection
