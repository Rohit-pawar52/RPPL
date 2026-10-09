@extends('layouts.admin')

@section('title', 'Add Player')

@section('content')
    <x-crud.back :href="route('admin.players.index')">Players</x-crud.back>

    <x-crud.form :action="route('admin.players.store')" :cancel="route('admin.players.index')" submit="Save player" files>
        @include('admin.players._form')
    </x-crud.form>
@endsection
