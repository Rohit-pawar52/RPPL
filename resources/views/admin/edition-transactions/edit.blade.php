@extends('layouts.admin')

@section('title', 'Edit Transaction')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.edition-transactions.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to transactions
        </a>
    </div>

    <div class="max-w-lg rounded-lg border border-neutral-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.edition-transactions.update', $transaction) }}" novalidate>
            @csrf
            @method('PUT')

            @include('admin.edition-transactions._form')

            <div class="mt-2 flex items-center gap-2">
                <button type="submit" class="rounded-md bg-blue-600 px-3 py-2 text-[13px] font-medium text-white hover:bg-blue-500">
                    Save changes
                </button>
                <a href="{{ route('admin.edition-transactions.index') }}" class="rounded-md border border-neutral-200 px-3 py-2 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
