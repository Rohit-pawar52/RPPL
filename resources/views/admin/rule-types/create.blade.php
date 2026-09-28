@extends('layouts.admin')

@section('title', 'Add Rule Type')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.rule-types.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to rule types
        </a>
    </div>

    <div class="max-w-lg rounded-lg border border-neutral-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.rule-types.store') }}" novalidate>
            @csrf

            @include('admin.rule-types._form')

            <div class="mt-2 flex items-center gap-2">
                <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
                    Save rule type
                </button>
                <a href="{{ route('admin.rule-types.index') }}" class="rounded-md border border-neutral-200 px-3 py-2 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
