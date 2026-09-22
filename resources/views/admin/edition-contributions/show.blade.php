@extends('layouts.admin')

@section('title', 'Contribution Details')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.edition-contributions.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to contributions
        </a>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-neutral-900">
                {{ money($contribution->amount) }}
            </h2>
            @if($contribution->committeeMember)
                <a href="{{ route('admin.committee-members.show', $contribution->committeeMember) }}" class="text-xs font-medium theme-link hover:underline">
                    {{ $contribution->contributorName() }}
                </a>
            @else
                <a href="{{ route('admin.contributors.show', $contribution->contributor) }}" class="text-xs font-medium theme-link hover:underline">
                    {{ $contribution->contributorName() }}
                </a>
            @endif
        </div>

        <dl class="mt-3 grid grid-cols-2 gap-3 text-xs sm:grid-cols-3">
            <div>
                <dt class="text-neutral-400">Source</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $contribution->sourceLabel() }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Edition</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $contribution->edition->name }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Date</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $contribution->contributed_at->format('d M Y') }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Recorded by</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $contribution->createdBy->name }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Linked transaction</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">
                    @if($contribution->transaction)
                        <a href="{{ route('admin.edition-transactions.show', $contribution->transaction) }}" class="theme-link hover:underline">
                            {{ $contribution->transaction->category }}
                        </a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="col-span-2 sm:col-span-3">
                <dt class="text-neutral-400">Notes</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $contribution->notes ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <a
            href="{{ route('admin.edition-contributions.receipt', $contribution) }}"
            target="_blank"
            class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50"
        >
            <x-icon name="document-chart" class="h-4 w-4" />
            Receipt
        </a>
        <a
            href="{{ route('admin.edition-contributions.receipt.pdf', $contribution) }}"
            class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50"
        >
            <x-icon name="document-chart" class="h-4 w-4" />
            Download PDF
        </a>

        <form
            method="POST"
            action="{{ route('admin.edition-contributions.destroy', $contribution) }}"
            data-confirm-delete
            data-confirm-title="Delete this contribution?"
            data-confirm-text="This also removes its linked finance transaction. This cannot be undone."
        >
            @csrf
            @method('DELETE')
            <button
                type="submit"
                class="inline-flex items-center gap-1.5 rounded-md border border-red-200 px-3 py-1.5 text-[13px] font-medium text-red-600 hover:bg-red-50"
            >
                <x-icon name="trash" class="h-4 w-4" />
                Delete contribution
            </button>
        </form>
    </div>
@endsection
