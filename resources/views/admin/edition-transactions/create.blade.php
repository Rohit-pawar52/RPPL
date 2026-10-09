@extends('layouts.admin')

@section('title', __('Add Transaction'))

@section('content')
    <x-crud.back :href="route('admin.edition-transactions.index')">{{ __('Ledger') }}</x-crud.back>

    <x-crud.form :action="route('admin.edition-transactions.store')" :cancel="route('admin.edition-transactions.index')" :submit="__('Save transaction')">
        @include('admin.edition-transactions._form')
    </x-crud.form>
@endsection
