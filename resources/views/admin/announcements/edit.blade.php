@extends('layouts.admin')

@section('title', 'Edit Announcement')

@section('content')
    <x-crud.back :href="route('admin.announcements.index')">Announcements</x-crud.back>

    <x-crud.form :action="route('admin.announcements.update', $announcement)" method="PUT" :cancel="route('admin.announcements.index')" submit="Save changes">
        @include('admin.announcements._form')
    </x-crud.form>
@endsection
