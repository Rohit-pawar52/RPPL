@extends('layouts.admin')

@section('title', __('Add Player'))

@section('content')
    <x-crud.back :href="route('admin.players.index')">{{ __('Players') }}</x-crud.back>

    <x-crud.form :action="route('admin.players.store')" :cancel="route('admin.players.index')" :submit="__('Save player')" files>
        @include('admin.players._form')
    </x-crud.form>
@endsection
