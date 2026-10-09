@extends('layouts.admin')

@section('title', __('Edit Player'))

@section('content')
    <x-crud.back :href="route('admin.players.index')">{{ __('Players') }}</x-crud.back>

    <x-crud.form :action="route('admin.players.update', $player)" method="PUT" :cancel="route('admin.players.index')" :submit="__('Save changes')" files>
        @include('admin.players._form')
    </x-crud.form>
@endsection
