@extends('layouts.admin')

@section('title', 'Edit Photo')

@section('content')
    <x-crud.back :href="route('admin.photos.index')">Photos</x-crud.back>

    <x-crud.form :action="route('admin.photos.update', $photo)" method="PUT" :cancel="route('admin.photos.index')" submit="Save changes" files>
        @include('admin.photos._form')
    </x-crud.form>
@endsection
