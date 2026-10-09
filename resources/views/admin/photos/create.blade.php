@extends('layouts.admin')

@section('title', 'Add Photo')

@section('content')
    <x-crud.back :href="route('admin.photos.index')">Photos</x-crud.back>

    <x-crud.form :action="route('admin.photos.store')" :cancel="route('admin.photos.index')" submit="Save photo" files>
        @include('admin.photos._form')
    </x-crud.form>
@endsection
