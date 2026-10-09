@extends('layouts.admin')

@section('title', __('Edit Role'))
@section('subtitle', $role->name)

@section('actions')
    <x-admin.button href="{{ route('admin.roles.index') }}" variant="secondary" icon="arrow-left">{{ __('Back to roles') }}</x-admin.button>
@endsection

@section('content')
    <form id="role-form" method="POST" action="{{ route('admin.roles.update', $role) }}" novalidate class="max-w-5xl">
        @csrf
        @method('PUT')

        @include('admin.roles._form')

        <x-admin.form-actions>
            <x-admin.button>{{ __('Save changes') }}</x-admin.button>
            <x-admin.button href="{{ route('admin.roles.index') }}" variant="secondary">{{ __('Cancel') }}</x-admin.button>
        </x-admin.form-actions>
    </form>
@endsection
