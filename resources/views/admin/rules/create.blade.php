@extends('layouts.admin')

@section('title', __('Add Rule'))

@section('content')
    <x-crud.back :href="route('admin.rules.index')">{{ __('Rules') }}</x-crud.back>

    <x-crud.form :action="route('admin.rules.store')" :cancel="route('admin.rules.index')" :submit="__('Save rule')" files>
        @include('admin.rules._form')
    </x-crud.form>
@endsection
