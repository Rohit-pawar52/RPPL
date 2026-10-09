@extends('layouts.admin')

@section('title', 'Add Rule Type')

@section('content')
    <x-crud.back :href="route('admin.rule-types.index')">Rule types</x-crud.back>

    <x-crud.form :action="route('admin.rule-types.store')" :cancel="route('admin.rule-types.index')" submit="Save rule type">
        @include('admin.rule-types._form')
    </x-crud.form>
@endsection
