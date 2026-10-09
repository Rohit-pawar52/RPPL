@extends('layouts.admin')

@section('title', 'Add Announcement')

@section('content')
    <x-crud.back :href="route('admin.announcements.index')">Announcements</x-crud.back>

    <x-crud.form :action="route('admin.announcements.store')" :cancel="route('admin.announcements.index')" submit="Save announcement">
        @include('admin.announcements._form')
    </x-crud.form>
@endsection
