@extends('layouts.admin')

@section('title', 'Edit Transaction')

@section('content')
    <x-crud.back :href="route('admin.edition-transactions.index')">Ledger</x-crud.back>

    <x-crud.form :action="route('admin.edition-transactions.update', $transaction)" method="PUT" :cancel="route('admin.edition-transactions.index')" submit="Save changes">
        @include('admin.edition-transactions._form')
    </x-crud.form>
@endsection
