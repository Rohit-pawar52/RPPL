@extends('layouts.admin')

@section('title', __('Add News'))

@section('content')
    <x-crud.back :href="route('admin.news.index')">{{ __('News') }}</x-crud.back>

    <x-crud.form :action="route('admin.news.store')" :cancel="route('admin.news.index')" :submit="__('Save news')" files>
        @include('admin.news._form')
    </x-crud.form>
@endsection
