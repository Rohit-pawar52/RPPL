@extends('layouts.admin')

@section('title', __('Edit Venue'))

@section('content')
    <x-crud.back :href="route('admin.venues.index')">{{ __('Venues') }}</x-crud.back>

    <x-crud.form :action="route('admin.venues.update', $venue)" method="PUT" :cancel="route('admin.venues.index')" :submit="__('Save changes')">
        @include('admin.venues._form')
    </x-crud.form>
@endsection
