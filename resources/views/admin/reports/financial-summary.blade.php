@extends('layouts.admin')

@section('title', 'Financial Summary')
@if($edition)
    @section('subtitle', $edition->name.' · Generated '.display_datetime($generatedAt, 'd M Y, h:i A'))
@endif

@section('actions')
    <x-admin.button :href="route('admin.reports.index', $edition ? ['edition_id' => $edition->id] : [])" variant="secondary" icon="arrow-left">Back to Reports</x-admin.button>
    @if($edition)
        <x-admin.button type="button" icon="printer" onclick="window.print()">Print</x-admin.button>
    @endif
@endsection

@section('content')
    @if(! $edition)
        <div class="adm-card">
            <x-admin.empty icon="document-chart" title="No edition available">
                Create a tournament edition to generate a financial summary.
            </x-admin.empty>
        </div>
    @else
        <div class="space-y-6">
            {{-- Finance ledger: the three numbers that matter, big --}}
            <section class="adm-card adm-card-body" aria-labelledby="fs-ledger">
                <h2 id="fs-ledger" class="adm-kicker mb-4">Finance Ledger</h2>
                <dl class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl bg-green-50 p-4">
                        <dt class="text-xs font-medium text-green-800">Total Income</dt>
                        <dd class="mt-1 text-2xl font-bold tabular-nums tracking-tight text-green-700">{{ money($financeSummary['income']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-red-50 p-4">
                        <dt class="text-xs font-medium text-red-800">Total Expenses</dt>
                        <dd class="mt-1 text-2xl font-bold tabular-nums tracking-tight text-red-600">{{ money($financeSummary['expense']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-100 p-4">
                        <dt class="text-xs font-medium text-slate-600">Balance</dt>
                        <dd class="mt-1 text-2xl font-bold tabular-nums tracking-tight {{ $financeSummary['balance'] < 0 ? 'text-red-600' : 'text-slate-900' }}">{{ money($financeSummary['balance']) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="adm-card adm-card-body" aria-labelledby="fs-reg">
                <h2 id="fs-reg" class="adm-kicker mb-4">Registration Payments</h2>
                <dl class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div>
                        <dt class="text-xs text-slate-500">Paid Registrations</dt>
                        <dd class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">{{ $paidRegistrations }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Paid Amount</dt>
                        <dd class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">{{ money($paidRegistrationAmount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Pending Verification</dt>
                        <dd class="mt-0.5 text-xl font-bold tabular-nums {{ $pendingRegistrations > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $pendingRegistrations }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Failed / Refunded</dt>
                        <dd class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">{{ $failedRegistrations }} / {{ $refundedRegistrations }}</dd>
                    </div>
                </dl>
                <p class="mt-4 text-[11px] leading-4 text-slate-500">
                    Registration payments are an operational collection figure and are not currently linked into the Finance ledger above.
                </p>
            </section>

            <section class="adm-card adm-card-body" aria-labelledby="fs-contrib">
                <h2 id="fs-contrib" class="adm-kicker mb-4">Contributions</h2>
                <dl class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                    <div>
                        <dt class="text-xs text-slate-500">Contribution Records</dt>
                        <dd class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">{{ $contributionCount }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Total Contributions</dt>
                        <dd class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">{{ money($contributionTotal) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Recognized Contributors</dt>
                        <dd class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">{{ $recognizedContributorsCount }}</dd>
                    </div>
                </dl>
                <p class="mt-4 text-[11px] leading-4 text-slate-500">
                    Total Contributions is already included within Finance Total Income above (every contribution has a matching income transaction) &mdash; shown separately for visibility only, never added on top.
                </p>
            </section>
        </div>
    @endif
@endsection
