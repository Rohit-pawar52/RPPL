@extends('layouts.admin')

@section('title', __('Add Venue'))

@section('content')
    <x-crud.back :href="route('admin.venues.index')">{{ __('Venues') }}</x-crud.back>

    <x-crud.form :action="route('admin.venues.store')" :cancel="route('admin.venues.index')" :submit="__('Save venue')">
        @include('admin.venues._form')
    </x-crud.form>
@endsection
