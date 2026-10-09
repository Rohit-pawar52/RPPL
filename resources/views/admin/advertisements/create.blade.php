@extends('layouts.admin')

@section('title', __('Add Advertisement'))

@section('content')
    <x-crud.back :href="route('admin.advertisements.index')">{{ __('Advertisements') }}</x-crud.back>

    <x-crud.form :action="route('admin.advertisements.store')" :cancel="route('admin.advertisements.index')" :submit="__('Save advertisement')" files>
        @include('admin.advertisements._form')
    </x-crud.form>
@endsection
