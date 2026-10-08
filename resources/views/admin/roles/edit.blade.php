@extends('layouts.admin')

@section('title', 'Edit Role')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.roles.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to roles
        </a>
    </div>

    <div class="max-w-4xl rounded-lg border border-slate-200 bg-white p-4">
        <form id="role-form" method="POST" action="{{ route('admin.roles.update', $role) }}" novalidate>
            @csrf
            @method('PUT')

            @include('admin.roles._form')

            <div class="mt-2 flex items-center gap-2">
                <x-admin.button>Save changes</x-admin.button>
                <x-admin.button href="{{ route('admin.roles.index') }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
