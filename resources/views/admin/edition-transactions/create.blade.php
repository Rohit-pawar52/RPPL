@extends('layouts.admin')

@section('title', 'Add Transaction')

@section('content')
    <x-crud.back :href="route('admin.edition-transactions.index')">Ledger</x-crud.back>

    <x-crud.form :action="route('admin.edition-transactions.store')" :cancel="route('admin.edition-transactions.index')" submit="Save transaction">
        @include('admin.edition-transactions._form')
    </x-crud.form>
@endsection
