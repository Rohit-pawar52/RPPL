@extends('layouts.admin')

@section('title', __('Edit Advertisement'))

@section('content')
    <x-crud.back :href="route('admin.advertisements.index')">{{ __('Advertisements') }}</x-crud.back>

    <x-crud.form :action="route('admin.advertisements.update', $advertisement)" method="PUT" :cancel="route('admin.advertisements.index')" :submit="__('Save changes')" files>
        @include('admin.advertisements._form')
    </x-crud.form>
@endsection
