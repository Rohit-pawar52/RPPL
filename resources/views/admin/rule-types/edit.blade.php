@extends('layouts.admin')

@section('title', 'Edit Rule Type')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.rule-types.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to rule types
        </a>
    </div>

    <div class="max-w-lg rounded-lg border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.rule-types.update', $ruleType) }}" novalidate>
            @csrf
            @method('PUT')

            @include('admin.rule-types._form')

            <div class="mt-2 flex items-center gap-2">
                <x-admin.button>Save changes</x-admin.button>
                <x-admin.button href="{{ route('admin.rule-types.index') }}" variant="secondary">Cancel</x-admin.button>
            </div>
        </form>
    </div>
@endsection
