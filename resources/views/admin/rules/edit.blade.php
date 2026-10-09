@extends('layouts.admin')

@section('title', __('Edit Rule'))

@section('content')
    <x-crud.back :href="route('admin.rules.index')">{{ __('Rules') }}</x-crud.back>

    <x-crud.form :action="route('admin.rules.update', $rule)" method="PUT" :cancel="route('admin.rules.index')" :submit="__('Save changes')" files>
        @include('admin.rules._form')
    </x-crud.form>
@endsection
