@extends('layouts.admin')

@section('title', __('Edit Team'))

@section('content')
    <x-crud.back :href="route('admin.teams.index')">{{ __('Teams') }}</x-crud.back>

    <x-crud.form :action="route('admin.teams.update', $team)" method="PUT" :cancel="route('admin.teams.index')" :submit="__('Save changes')" files>
        @include('admin.teams._form')
    </x-crud.form>
@endsection
