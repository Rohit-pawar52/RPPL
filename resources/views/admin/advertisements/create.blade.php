@extends('layouts.admin')

@section('title', 'Add Advertisement')

@section('content')
    <x-crud.back :href="route('admin.advertisements.index')">Advertisements</x-crud.back>

    <x-crud.form :action="route('admin.advertisements.store')" :cancel="route('admin.advertisements.index')" submit="Save advertisement" files>
        @include('admin.advertisements._form')
    </x-crud.form>
@endsection
