@extends('layouts.admin')

@section('title', __('Edit Rule Type'))

@section('content')
    <x-crud.back :href="route('admin.rule-types.index')">{{ __('Rule types') }}</x-crud.back>

    <x-crud.form :action="route('admin.rule-types.update', $ruleType)" method="PUT" :cancel="route('admin.rule-types.index')" :submit="__('Save changes')">
        @include('admin.rule-types._form')
    </x-crud.form>
@endsection
