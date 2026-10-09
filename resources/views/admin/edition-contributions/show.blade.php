@extends('layouts.admin')

@section('title', __('Contribution Details'))

@section('content')
    <x-crud.back :href="route('admin.edition-contributions.index')">{{ __('Contributions') }}</x-crud.back>

    <div class="space-y-4 lg:space-y-5">
        <section class="crud-profile">
            <div class="crud-profile-band" aria-hidden="true"></div>
            <div class="crud-profile-body">
                <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end sm:gap-4">
                    <span class="crud-profile-avatar crud-profile-avatar-square">
                        <span class="flex h-24 w-24 items-center justify-center rounded-xl bg-green-50 text-green-600">
                            <x-crud.glyph name="income" class="h-10 w-10" />
                        </span>
                    </span>
                    <div class="min-w-0 pb-1">
                        <h2 class="crud-profile-name tabular-nums text-green-600!">+{{ money($contribution->amount) }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('from') }}
                            @can('view', $contribution->contributor)
                                <a href="{{ route('admin.contributors.show', $contribution->contributor) }}" class="crud-link">{{ $contribution->contributorName() }}</a>
                            @else
                                <span class="font-medium text-slate-700">{{ $contribution->contributorName() }}</span>
                            @endcan
                            @if($contribution->contributorVillage())
                                ({{ $contribution->contributorVillage() }})
                            @endif
                            &middot; {{ $contribution->contributed_at->format('d M Y') }}
                        </p>
                    </div>
                </div>

                <div class="crud-profile-actions">
                    <x-admin.button :href="route('admin.edition-contributions.receipt', $contribution)" target="_blank" variant="secondary" icon="document-chart">{{ __('Receipt') }}</x-admin.button>
                    <x-admin.button :href="route('admin.edition-contributions.receipt.pdf', $contribution)" variant="secondary" icon="document-chart">{{ __('Download PDF') }}</x-admin.button>
                </div>
            </div>
        </section>

        <x-admin.card :title="__('Details')">
            <dl class="crud-facts">
                <x-crud.fact :label="__('Source')">{{ __($contribution->sourceLabel()) }}</x-crud.fact>
                <x-crud.fact :label="__('Edition')">{{ $contribution->edition->name }}</x-crud.fact>
                <x-crud.fact :label="__('Date')">{{ $contribution->contributed_at->format('d M Y') }}</x-crud.fact>
                <x-crud.fact :label="__('Recorded by')">{{ $contribution->createdBy->name }}</x-crud.fact>
                <x-crud.fact :label="__('Linked transaction')">
                    @if($contribution->transaction)
                        <a href="{{ route('admin.edition-transactions.show', $contribution->transaction) }}" class="crud-link">{{ $contribution->transaction->category }}</a>
                    @else
                        —
                    @endif
                </x-crud.fact>
                <x-crud.fact :label="__('Notes')" class="col-span-2 sm:col-span-3">{{ $contribution->notes ?? '—' }}</x-crud.fact>
            </dl>
        </x-admin.card>

        <section class="crud-danger">
            <div class="crud-danger-head">
                <span class="crud-danger-icon"><x-crud.glyph name="alert" class="h-4 w-4" /></span>
                <div class="min-w-0 flex-1">
                    <h3 class="crud-card-title">{{ __('Delete this contribution') }}</h3>
                    <p class="crud-hint">{{ __('This also removes its linked finance transaction. This cannot be undone.') }}</p>
                </div>
                <form
                    method="POST"
                    action="{{ route('admin.edition-contributions.destroy', $contribution) }}"
                    data-confirm-delete
                    data-confirm-title="{{ __('Delete this contribution?') }}"
                    data-confirm-text="{{ __('This also removes its linked finance transaction. This cannot be undone.') }}"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger-soft btn-sm">
                        <x-icon name="trash" class="h-4 w-4" />
                        {{ __('Delete contribution') }}
                    </button>
                </form>
            </div>
        </section>
    </div>
@endsection
