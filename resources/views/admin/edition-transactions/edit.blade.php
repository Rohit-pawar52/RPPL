@extends('layouts.admin')

@section('title', __('Edit Transaction'))

@section('content')
    <x-crud.back :href="route('admin.edition-transactions.index')">{{ __('Ledger') }}</x-crud.back>

    <x-crud.form :action="route('admin.edition-transactions.update', $transaction)" method="PUT" :cancel="route('admin.edition-transactions.index')" :submit="__('Save changes')">
        @include('admin.edition-transactions._form')
    </x-crud.form>
@endsection
