@extends('layouts.admin')

@section('title', 'Add Venue')

@section('content')
    <x-crud.back :href="route('admin.venues.index')">Venues</x-crud.back>

    <x-crud.form :action="route('admin.venues.store')" :cancel="route('admin.venues.index')" submit="Save venue">
        @include('admin.venues._form')
    </x-crud.form>
@endsection
