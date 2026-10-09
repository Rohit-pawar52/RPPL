@extends('layouts.admin')

@section('title', 'Edit Rule')

@section('content')
    <x-crud.back :href="route('admin.rules.index')">Rules</x-crud.back>

    <x-crud.form :action="route('admin.rules.update', $rule)" method="PUT" :cancel="route('admin.rules.index')" submit="Save changes" files>
        @include('admin.rules._form')
    </x-crud.form>
@endsection
