@extends('layouts.admin')

@section('title', __('Edit News'))

@section('content')
    <x-crud.back :href="route('admin.news.index')">{{ __('News') }}</x-crud.back>

    <x-crud.form :action="route('admin.news.update', $news)" method="PUT" :cancel="route('admin.news.index')" :submit="__('Save changes')" files>
        @include('admin.news._form')
    </x-crud.form>
@endsection
