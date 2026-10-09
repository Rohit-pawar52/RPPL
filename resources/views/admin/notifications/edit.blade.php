@extends('layouts.admin')

@section('title', __('Edit Notification'))

@section('content')
    <x-crud.back :href="route('admin.notifications.index')">{{ __('Notifications') }}</x-crud.back>

    <x-crud.form :action="route('admin.notifications.update', $notification)" method="PUT" :cancel="route('admin.notifications.index')" :submit="__('Save changes')">
        @include('admin.notifications._form')
    </x-crud.form>
@endsection
