@extends('layouts.admin')

@section('title', 'Transaction Details')

@section('content')
    @php $isIncome = $transaction->type === 'income'; @endphp

    <x-crud.back :href="route('admin.edition-transactions.index')">Ledger</x-crud.back>

    <div class="space-y-4 lg:space-y-5">
        @if($transaction->contribution)
            <div class="crud-note crud-note-warn flex flex-wrap items-center justify-between gap-3">
                <span>
                    <span class="font-semibold">Created from contribution.</span>
                    It is locked from manual editing/deletion here — a contribution and its ledger transaction are always changed together, only via the contribution itself.
                </span>
                <a href="{{ route('admin.edition-contributions.show', $transaction->contribution) }}" class="crud-link shrink-0 whitespace-nowrap">View Contribution &rarr;</a>
            </div>
        @endif

        <section class="crud-profile">
            <div class="crud-profile-band" aria-hidden="true"></div>
            <div class="crud-profile-body">
                <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end sm:gap-4">
                    <span class="crud-profile-avatar crud-profile-avatar-square">
                        <span class="flex h-24 w-24 items-center justify-center rounded-xl {{ $isIncome ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' }}">
                            <x-crud.glyph :name="$isIncome ? 'income' : 'expense'" class="h-10 w-10" />
                        </span>
                    </span>
                    <div class="min-w-0 pb-1">
                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                            <h2 class="crud-profile-name tabular-nums {{ $isIncome ? 'text-green-600!' : 'text-red-600!' }}">{{ $isIncome ? '+' : '−' }}{{ money($transaction->amount) }}</h2>
                            <x-status-badge :status="$transaction->type" />
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ $transaction->category ?: 'No category' }} &middot; {{ $transaction->transaction_date->format('d M Y') }}</p>
                    </div>
                </div>

                @unless($transaction->contribution)
                    <div class="crud-profile-actions">
                        <x-admin.button :href="route('admin.edition-transactions.edit', $transaction)" icon="pencil">Edit</x-admin.button>
                    </div>
                @endunless
            </div>
        </section>

        <x-admin.card title="Details">
            <dl class="crud-facts">
                <x-crud.fact label="Edition">{{ $transaction->edition->name }}</x-crud.fact>
                <x-crud.fact label="Date">{{ $transaction->transaction_date->format('d M Y') }}</x-crud.fact>
                <x-crud.fact label="Category">{{ $transaction->category ?? '—' }}</x-crud.fact>
                <x-crud.fact label="Recorded by">{{ $transaction->createdBy->name }}</x-crud.fact>
                <x-crud.fact label="Description" class="col-span-2 sm:col-span-4">{{ $transaction->description ?? '—' }}</x-crud.fact>
            </dl>
        </x-admin.card>
    </div>
@endsection
