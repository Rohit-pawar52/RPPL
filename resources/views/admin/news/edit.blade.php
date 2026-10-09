@extends('layouts.admin')

@section('title', 'Edit News')

@section('content')
    <x-crud.back :href="route('admin.news.index')">News</x-crud.back>

    <x-crud.form :action="route('admin.news.update', $news)" method="PUT" :cancel="route('admin.news.index')" submit="Save changes" files>
        @include('admin.news._form')
    </x-crud.form>
@endsection
