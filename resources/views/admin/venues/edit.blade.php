@extends('layouts.admin')

@section('title', 'Edit Venue')

@section('content')
    <x-crud.back :href="route('admin.venues.index')">Venues</x-crud.back>

    <x-crud.form :action="route('admin.venues.update', $venue)" method="PUT" :cancel="route('admin.venues.index')" submit="Save changes">
        @include('admin.venues._form')
    </x-crud.form>
@endsection
