@extends('layouts.admin')

@section('title', __('Add Announcement'))

@section('content')
    <x-crud.back :href="route('admin.announcements.index')">{{ __('Announcements') }}</x-crud.back>

    <x-crud.form :action="route('admin.announcements.store')" :cancel="route('admin.announcements.index')" :submit="__('Save announcement')">
        @include('admin.announcements._form')
    </x-crud.form>
@endsection
