@extends('layouts.admin')

@section('title', 'Edit Advertisement')

@section('content')
    <x-crud.back :href="route('admin.advertisements.index')">Advertisements</x-crud.back>

    <x-crud.form :action="route('admin.advertisements.update', $advertisement)" method="PUT" :cancel="route('admin.advertisements.index')" submit="Save changes" files>
        @include('admin.advertisements._form')
    </x-crud.form>
@endsection
