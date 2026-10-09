@extends('layouts.admin')

@section('title', __('Add Team'))

@section('content')
    <x-crud.back :href="route('admin.teams.index')">{{ __('Teams') }}</x-crud.back>

    <x-crud.form :action="route('admin.teams.store')" :cancel="route('admin.teams.index')" :submit="__('Save team')" files>
        @include('admin.teams._form')
    </x-crud.form>
@endsection
