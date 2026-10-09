@extends('layouts.admin')

@section('title', 'Edit Contributor')

@section('content')
    <x-crud.back :href="route('admin.contributors.index')">Contributors</x-crud.back>

    <x-crud.form :action="route('admin.contributors.update', $contributor)" method="PUT" :cancel="route('admin.contributors.index')" submit="Save changes" files>
        @include('admin.contributors._form')
    </x-crud.form>
@endsection
