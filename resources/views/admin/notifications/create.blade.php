@extends('layouts.admin')

@section('title', __('New Notification'))

@section('content')
    <x-crud.back :href="route('admin.notifications.index')">{{ __('Notifications') }}</x-crud.back>

    <x-crud.form :action="route('admin.notifications.store')" :cancel="route('admin.notifications.index')" :submit="__('Save notification')">
        @include('admin.notifications._form')
    </x-crud.form>
@endsection
