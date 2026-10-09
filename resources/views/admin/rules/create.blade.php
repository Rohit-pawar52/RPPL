@extends('layouts.admin')

@section('title', 'Add Rule')

@section('content')
    <x-crud.back :href="route('admin.rules.index')">Rules</x-crud.back>

    <x-crud.form :action="route('admin.rules.store')" :cancel="route('admin.rules.index')" submit="Save rule" files>
        @include('admin.rules._form')
    </x-crud.form>
@endsection
