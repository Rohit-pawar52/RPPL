@extends('layouts.admin')

@section('title', __('Add Video'))

@section('content')
    <x-crud.back :href="route('admin.videos.index')">{{ __('Videos') }}</x-crud.back>

    <x-crud.form :action="route('admin.videos.store')" :cancel="route('admin.videos.index')" :submit="__('Save video')" files>
        @include('admin.videos._form')
    </x-crud.form>
@endsection
