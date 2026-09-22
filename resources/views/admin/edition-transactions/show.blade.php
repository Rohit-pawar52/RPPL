@extends('layouts.admin')

@section('title', 'Transaction Details')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.edition-transactions.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to transactions
        </a>
        @unless($transaction->contribution)
            <a
                href="{{ route('admin.edition-transactions.edit', $transaction) }}"
                class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
            >
                <x-icon name="pencil" class="h-3.5 w-3.5" />
                Edit
            </a>
        @endunless
    </div>

    @if($transaction->contribution)
        <div class="mb-4 rounded-md border border-neutral-200 bg-neutral-50 px-3 py-2 text-xs text-neutral-600">
            This transaction is managed by a committee contribution and cannot be modified directly.
        </div>
    @endif

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-neutral-900">
                {{ money($transaction->amount) }}
            </h2>
            <x-status-badge :status="$transaction->type" />
        </div>

        <dl class="mt-3 grid grid-cols-2 gap-3 text-xs sm:grid-cols-3">
            <div>
                <dt class="text-neutral-400">Edition</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $transaction->edition->name }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Date</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $transaction->transaction_date->format('d M Y') }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Category</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $transaction->category ?? '—' }}</dd>
            </div>
            <div class="col-span-2 sm:col-span-3">
                <dt class="text-neutral-400">Description</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $transaction->description ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Recorded by</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $transaction->createdBy->name }}</dd>
            </div>
        </dl>
    </div>
@endsection
