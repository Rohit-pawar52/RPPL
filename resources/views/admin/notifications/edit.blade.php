@extends('layouts.admin')

@section('title', 'Edit Notification')

@section('content')
    <x-crud.back :href="route('admin.notifications.index')">Notifications</x-crud.back>

    <x-crud.form :action="route('admin.notifications.update', $notification)" method="PUT" :cancel="route('admin.notifications.index')" submit="Save changes">
        @include('admin.notifications._form')
    </x-crud.form>
@endsection
