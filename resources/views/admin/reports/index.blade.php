@extends('layouts.admin')

@section('title', 'Reports')
@section('subtitle', 'Download or print reports for one edition.')

@if($editions->isNotEmpty())
@section('actions')
    <form method="GET" action="{{ route('admin.reports.index') }}" class="max-sm:w-full">
        <label for="report-edition" class="sr-only">Edition</label>
        <select id="report-edition" name="edition_id" onchange="this.form.submit()" class="fld-control sm:w-64">
            @foreach($editions as $option)
                <option value="{{ $option->id }}" @selected($edition && $edition->id === $option->id)>
                    {{ $option->name }}
                </option>
            @endforeach
        </select>
    </form>
@endsection
@endif

@section('content')
    @if(! $edition)
        <div class="adm-card">
            <x-admin.empty icon="document-chart" title="No editions available yet">
                Create a tournament edition to generate reports here.
                @can('create', \App\Models\Edition::class)
                    <x-slot:action>
                        <x-admin.button :href="route('admin.editions.create')" icon="plus">Create an edition</x-admin.button>
                    </x-slot:action>
                @endcan
            </x-admin.empty>
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

        <div class="space-y-8">
            {{-- A. Tournament and registrations --}}
            @if($canEditionSummary || $canRegistrationsCsv)
                <section aria-labelledby="rep-tournament">
                    <h2 id="rep-tournament" class="adm-kicker mb-3">Tournament &amp; Registrations</h2>
                    <div class="grid gap-3 lg:grid-cols-2">
                        @if($canEditionSummary)
                            @include('admin.reports._item', [
                                'icon' => 'trophy',
                                'name' => 'Edition Summary',
                                'text' => e('Overview, registrations, teams, matches, standings, and top player statistics'),
                                'format' => 'PDF',
                                'href' => route('admin.editions.report.pdf', $edition),
                                'cta' => 'Download PDF',
                            ])
                        @endif
                        @if($canRegistrationsCsv)
                            @include('admin.reports._item', [
                                'icon' => 'clipboard',
                                'name' => 'Player Registrations',
                                'text' => e('Every registration for this edition, with payment status'),
                                'format' => 'CSV',
                                'href' => route('admin.player-registrations.export', ['edition_id' => $edition->id]),
                                'cta' => 'Export CSV',
                            ])
                        @endif
                    </div>
                </section>
            @endif

            {{-- B. Finance & Contributions --}}
            @if($canTransactionsCsv || $canContributionsCsv || $canFinancialSummary)
                <section aria-labelledby="rep-finance">
                    <h2 id="rep-finance" class="adm-kicker mb-3">Finance &amp; Contributions</h2>
                    <div class="grid gap-3 lg:grid-cols-2">
                        @if($canTransactionsCsv)
                            @include('admin.reports._item', [
                                'icon' => 'currency',
                                'name' => 'Finance Transactions',
                                'text' => e('The full income/expense ledger for this edition'),
                                'format' => 'CSV',
                                'href' => route('admin.edition-transactions.export', ['edition_id' => $edition->id]),
                                'cta' => 'Export CSV',
                            ])
                        @endif
                        @if($canContributionsCsv)
                            @include('admin.reports._item', [
                                'icon' => 'star',
                                'name' => 'Contributions',
                                'text' => e('Individual committee/general contribution records for this edition. Individual receipts remain available from ').'<a href="'.e(route('admin.edition-contributions.index', ['edition_id' => $edition->id])).'" class="font-medium text-link hover:text-link-hover hover:underline">Edition Contributions</a>.',
                                'format' => 'CSV',
                                'href' => route('admin.edition-contributions.export', ['edition_id' => $edition->id]),
                                'cta' => 'Export CSV',
                            ])
                        @endif
                        @if($canFinancialSummary)
                            @include('admin.reports._item', [
                                'icon' => 'document-chart',
                                'name' => 'Financial Summary',
                                'text' => e('Income, expenses, balance, registration payments, and contributions in one printable page'),
                                'format' => 'HTML',
                                'href' => route('admin.reports.financial-summary', ['edition_id' => $edition->id]),
                                'cta' => 'View',
                            ])
                        @endif
                    </div>
                </section>
            @endif

            {{-- C. Match scorecards --}}
            <section aria-labelledby="rep-matches">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 id="rep-matches" class="adm-kicker">Match Reports</h2>
                    @if($canOpenMatches)
                        <a href="{{ route('admin.matches.index', ['edition_id' => $edition->id]) }}" class="text-xs font-semibold text-link hover:text-link-hover">View all matches &rarr;</a>
                    @endif
                </div>

                <div class="adm-card adm-card-flush">
                    @forelse($recentMatches as $match)
                        <div class="flex flex-col gap-2 border-b border-line px-4 py-3 last:border-b-0 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <div class="min-w-0">
                                @if($canOpenMatches)
                                    <a href="{{ route('admin.matches.show', $match) }}" class="block truncate text-[13px] font-semibold text-slate-900 hover:text-link">
                                        {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                                    </a>
                                @else
                                    <span class="block truncate text-[13px] font-semibold text-slate-900">
                                        {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                                    </span>
                                @endif
                                <p class="mt-0.5 text-[11px] text-slate-500">{{ display_datetime($match->scheduled_at, 'd M Y') }}</p>
                            </div>
                            <a href="{{ route('public.matches.scorecard.pdf', $match) }}" class="btn btn-secondary btn-sm shrink-0 max-sm:w-full">
                                <x-admin.icon name="download" class="h-4 w-4" />
                                Scorecard PDF
                            </a>
                        </div>
                    @empty
                        <x-admin.empty icon="trophy">No completed matches yet.</x-admin.empty>
                    @endforelse
                </div>
            </section>
        </div>
    @endif
@endsection
