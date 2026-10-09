@extends('layouts.admin')

@section('title', __('Add Photo'))

@section('content')
    <x-crud.back :href="route('admin.photos.index')">{{ __('Photos') }}</x-crud.back>

    <x-crud.form :action="route('admin.photos.store')" :cancel="route('admin.photos.index')" :submit="__('Save photo')" files>
        @include('admin.photos._form')
    </x-crud.form>
@endsection
