@extends('layouts.admin')

@section('title', 'Reports')

@section('content')
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs text-neutral-500">Download or print reports for one edition.</p>

        <form method="GET" action="{{ route('admin.reports.index') }}" class="flex items-center gap-2">
            <select
                name="edition_id"
                onchange="this.form.submit()"
                class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring"
            >
                @foreach($editions as $option)
                    <option value="{{ $option->id }}" @selected($edition && $edition->id === $option->id)>
                        {{ $option->name }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    @if(! $edition)
        <div class="rounded-lg border border-neutral-200 bg-white p-4">
            <h2 class="mb-2 text-sm font-semibold text-neutral-900">No editions available yet</h2>
            <p class="text-xs text-neutral-500">Create a tournament edition to generate reports here.</p>
        </div>
    @else
        @php
            $user = auth()->user();
            // Only reports.view is needed to open this page; each report behind it is checked by its own
            // module (the exports) or needs finance as well (the Financial Summary page). A report is
            // listed only for a role that may actually open it, and a box with nothing left is not drawn.
            $canEditionSummary = $user->can('view', $edition);
            $canRegistrationsCsv = $user->can('viewAny', \App\Models\PlayerRegistration::class);
            $canTransactionsCsv = $user->can('viewAny', \App\Models\EditionTransaction::class);
            $canContributionsCsv = $user->can('viewAny', \App\Models\EditionContribution::class);
            $canFinancialSummary = $user->can('reports.view') && $user->can('finance.view');
            $canOpenMatches = $user->can('viewAny', \App\Models\GameMatch::class);
        @endphp

        {{-- A and B are short, so they sit side by side on wide screens. --}}
        @if($canEditionSummary || $canRegistrationsCsv)
        <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
        {{-- A. Tournament Reports --}}
        @if($canEditionSummary)
        <div class="rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Tournament Reports</h3>
            <div class="flex items-center justify-between gap-3 rounded-md border border-neutral-100 px-3 py-2.5 text-[13px]">
                <div class="min-w-0">
                    <p class="font-medium text-neutral-800">Edition Summary</p>
                    <p class="text-[11px] text-neutral-500">Overview, registrations, teams, matches, standings, and top player statistics &middot; PDF</p>
                </div>
                <a
                    href="{{ route('admin.editions.report.pdf', $edition) }}"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                >
                    <x-icon name="document-chart" class="h-3.5 w-3.5" />
                    Download PDF
                </a>
            </div>
        </div>
        @endif

        {{-- B. Registration Reports --}}
        @if($canRegistrationsCsv)
        <div class="rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Registration Reports</h3>
            <div class="flex items-center justify-between gap-3 rounded-md border border-neutral-100 px-3 py-2.5 text-[13px]">
                <div class="min-w-0">
                    <p class="font-medium text-neutral-800">Player Registrations</p>
                    <p class="text-[11px] text-neutral-500">Every registration for this edition, with payment status &middot; CSV</p>
                </div>
                <a
                    href="{{ route('admin.player-registrations.export', ['edition_id' => $edition->id]) }}"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                >
                    <x-icon name="document-chart" class="h-3.5 w-3.5" />
                    Export CSV
                </a>
            </div>
        </div>
        @endif

        </div>
        @endif

        {{-- C. Finance & Contributions --}}
        @if($canTransactionsCsv || $canContributionsCsv || $canFinancialSummary)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Finance &amp; Contributions</h3>
            <div class="space-y-2">
                @if($canTransactionsCsv)
                <div class="flex items-center justify-between gap-3 rounded-md border border-neutral-100 px-3 py-2.5 text-[13px]">
                    <div class="min-w-0">
                        <p class="font-medium text-neutral-800">Finance Transactions</p>
                        <p class="text-[11px] text-neutral-500">The full income/expense ledger for this edition &middot; CSV</p>
                    </div>
                    <a
                        href="{{ route('admin.edition-transactions.export', ['edition_id' => $edition->id]) }}"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <x-icon name="document-chart" class="h-3.5 w-3.5" />
                        Export CSV
                    </a>
                </div>
                @endif

                @if($canContributionsCsv)
                <div class="flex items-center justify-between gap-3 rounded-md border border-neutral-100 px-3 py-2.5 text-[13px]">
                    <div class="min-w-0">
                        <p class="font-medium text-neutral-800">Contributions</p>
                        <p class="text-[11px] text-neutral-500">
                            Individual committee/general contribution records for this edition &middot; CSV.
                            Individual receipts remain available from
                            <a href="{{ route('admin.edition-contributions.index', ['edition_id' => $edition->id]) }}" class="theme-link hover:underline">Edition Contributions</a>.
                        </p>
                    </div>
                    <a
                        href="{{ route('admin.edition-contributions.export', ['edition_id' => $edition->id]) }}"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <x-icon name="document-chart" class="h-3.5 w-3.5" />
                        Export CSV
                    </a>
                </div>
                @endif

                @if($canFinancialSummary)
                <div class="flex items-center justify-between gap-3 rounded-md border border-neutral-100 px-3 py-2.5 text-[13px]">
                    <div class="min-w-0">
                        <p class="font-medium text-neutral-800">Financial Summary</p>
                        <p class="text-[11px] text-neutral-500">Income, expenses, balance, registration payments, and contributions in one printable page &middot; HTML</p>
                    </div>
                    <a
                        href="{{ route('admin.reports.financial-summary', ['edition_id' => $edition->id]) }}"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <x-icon name="document-chart" class="h-3.5 w-3.5" />
                        View
                    </a>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- D. Match Reports --}}
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Match Reports</h3>
                @if($canOpenMatches)
                    <a href="{{ route('admin.matches.index', ['edition_id' => $edition->id]) }}" class="text-[11px] font-medium theme-link hover:underline">
                        View all matches &rarr;
                    </a>
                @endif
            </div>

            @forelse($recentMatches as $match)
                <div class="flex items-center justify-between gap-3 border-b border-neutral-100 py-2 text-[13px] last:border-b-0">
                    <div class="min-w-0">
                        @if($canOpenMatches)
                            <a href="{{ route('admin.matches.show', $match) }}" class="font-medium text-neutral-800 hover:underline">
                                {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                            </a>
                        @else
                            <span class="font-medium text-neutral-800">
                                {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                            </span>
                        @endif
                        <p class="text-[11px] text-neutral-500">{{ display_datetime($match->scheduled_at, 'd M Y') }}</p>
                    </div>
                    <a
                        href="{{ route('public.matches.scorecard.pdf', $match) }}"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <x-icon name="document-chart" class="h-3.5 w-3.5" />
                        Scorecard PDF
                    </a>
                </div>
            @empty
                <p class="py-4 text-center text-xs text-neutral-400">No completed matches yet.</p>
            @endforelse
        </div>
    @endif
@endsection
