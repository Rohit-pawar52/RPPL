@extends('layouts.admin')

@section('title', 'Add Role')
@section('subtitle', 'Name the role, then tick what a login with it may do.')

@section('actions')
    <x-admin.button href="{{ route('admin.roles.index') }}" variant="secondary" icon="arrow-left">Back to roles</x-admin.button>
@endsection

@section('content')
    <form id="role-form" method="POST" action="{{ route('admin.roles.store') }}" novalidate class="max-w-5xl">
        @csrf

        @include('admin.roles._form')

        <x-admin.form-actions>
            <x-admin.button>Save role</x-admin.button>
            <x-admin.button href="{{ route('admin.roles.index') }}" variant="secondary">Cancel</x-admin.button>
        </x-admin.form-actions>
    </form>
@endsection
