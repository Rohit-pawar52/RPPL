@extends('layouts.admin')

@section('title', 'Financial Summary')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3 print:hidden">
        <a href="{{ route('admin.reports.index', $edition ? ['edition_id' => $edition->id] : []) }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to Reports
        </a>
        @if($edition)
            <button
                type="button"
                onclick="window.print()"
                class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
            >
                Print
            </button>
        @endif
    </div>

    @if(! $edition)
        <div class="rounded-lg border border-neutral-200 bg-white p-4">
            <h2 class="mb-2 text-sm font-semibold text-neutral-900">No edition available</h2>
            <p class="text-xs text-neutral-500">Create a tournament edition to generate a financial summary.</p>
        </div>
    @else
        <div class="rounded-lg border border-neutral-200 bg-white p-5">
            <div class="mb-4 flex items-center justify-between gap-3 border-b border-neutral-100 pb-3">
                <div>
                    <h1 class="text-base font-semibold text-neutral-900">Financial Summary &mdash; {{ $edition->name }}</h1>
                    <p class="text-[11px] text-neutral-400">Generated {{ $generatedAt->format('d M Y, h:i A') }}</p>
                </div>
            </div>

            <section class="mb-5">
                <h2 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Finance Ledger</h2>
                <dl class="grid grid-cols-3 gap-3 text-xs">
                    <div>
                        <dt class="text-neutral-400">Total Income</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-neutral-800">&#8377;{{ number_format($financeSummary['income'], 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-400">Total Expenses</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-neutral-800">&#8377;{{ number_format($financeSummary['expense'], 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-400">Balance</dt>
                        <dd class="mt-0.5 text-sm font-semibold {{ $financeSummary['balance'] < 0 ? 'text-red-600' : 'text-neutral-800' }}">
                            &#8377;{{ number_format($financeSummary['balance'], 2) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="mb-5">
                <h2 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Registration Payments</h2>
                <dl class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                    <div>
                        <dt class="text-neutral-400">Paid Registrations</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">{{ $paidRegistrations }}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-400">Paid Amount</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">&#8377;{{ number_format($paidRegistrationAmount, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-400">Pending Verification</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">{{ $pendingRegistrations }}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-400">Failed / Refunded</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">{{ $failedRegistrations }} / {{ $refundedRegistrations }}</dd>
                    </div>
                </dl>
                <p class="mt-2 text-[11px] text-neutral-400">
                    Registration payments are an operational collection figure and are not currently linked into the Finance ledger above.
                </p>
            </section>

            <section>
                <h2 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Contributions</h2>
                <dl class="grid grid-cols-3 gap-3 text-xs">
                    <div>
                        <dt class="text-neutral-400">Contribution Records</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">{{ $contributionCount }}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-400">Total Contributions</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">&#8377;{{ number_format($contributionTotal, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-400">Recognized Contributors</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">{{ $recognizedContributorsCount }}</dd>
                    </div>
                </dl>
                <p class="mt-2 text-[11px] text-neutral-400">
                    Total Contributions is already included within Finance Total Income above (every contribution has a matching income transaction) &mdash; shown separately for visibility only, never added on top.
                </p>
            </section>
        </div>
    @endif
@endsection
