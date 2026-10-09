@extends('layouts.admin')

@section('title', 'Edit Video')

@section('content')
    <x-crud.back :href="route('admin.videos.index')">Videos</x-crud.back>

    <x-crud.form :action="route('admin.videos.update', $video)" method="PUT" :cancel="route('admin.videos.index')" submit="Save changes" files>
        @include('admin.videos._form')
    </x-crud.form>
@endsection
