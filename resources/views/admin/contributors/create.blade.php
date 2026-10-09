@extends('layouts.admin')

@section('title', __('Add Contributor'))

@section('content')
    <x-crud.back :href="route('admin.contributors.index')">{{ __('Contributors') }}</x-crud.back>

    <x-crud.form :action="route('admin.contributors.store')" :cancel="route('admin.contributors.index')" :submit="__('Save contributor')" files>
        @include('admin.contributors._form')
    </x-crud.form>
@endsection
