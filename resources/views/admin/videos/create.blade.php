@extends('layouts.admin')

@section('title', 'Add Video')

@section('content')
    <x-crud.back :href="route('admin.videos.index')">Videos</x-crud.back>

    <x-crud.form :action="route('admin.videos.store')" :cancel="route('admin.videos.index')" submit="Save video" files>
        @include('admin.videos._form')
    </x-crud.form>
@endsection
