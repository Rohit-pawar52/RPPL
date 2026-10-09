{{--
    The Finance tab bar, shared across five separate controllers/routes
    (Overview, Contributions, Ledger, Contributors, Committee). One tab bar
    so Finance reads as one consolidated admin area even though each tab
    keeps its own URL. $edition (nullable) is threaded through so switching
    tabs keeps looking at the same edition.

    Each tab is a page of its own module with its own permission, so a
    role only gets the tabs it may open (e.g. one that may see
    contributors but not finance is not offered Overview, Contributions
    or Ledger). 'ability' is what a Gate call takes: a permission key, or
    a policy ability and the model it is asked about.
--}}
@php
    $editionQuery = isset($edition) && $edition ? ['edition_id' => $edition->id] : [];
    $tabs = [
        'overview' => ['label' => 'Overview', 'route' => 'admin.finance.overview', 'active' => 'admin.finance.overview', 'ability' => ['finance.view']],
        'contributions' => ['label' => 'Contributions', 'route' => 'admin.edition-contributions.index', 'active' => 'admin.edition-contributions.*', 'ability' => ['viewAny', \App\Models\EditionContribution::class]],
        'ledger' => ['label' => 'Ledger', 'route' => 'admin.edition-transactions.index', 'active' => 'admin.edition-transactions.*', 'ability' => ['viewAny', \App\Models\EditionTransaction::class]],
        'contributors' => ['label' => 'Contributors', 'route' => 'admin.contributors.index', 'active' => 'admin.contributors.*', 'ability' => ['viewAny', \App\Models\Contributor::class]],
        'committee' => ['label' => 'Committee', 'route' => 'admin.finance.committee', 'active' => 'admin.finance.committee', 'ability' => ['viewAny', \App\Models\EditionCommitteeMember::class]],
    ];
    $tabs = array_filter($tabs, fn (array $tab) => auth()->user()->can(...$tab['ability']));
@endphp

@if($tabs !== [])
    <x-crud.tabs aria-label="Finance sections">
        @foreach($tabs as $tab)
            <x-crud.tab :href="route($tab['route'], $editionQuery)" :active="request()->routeIs($tab['active'])">{{ $tab['label'] }}</x-crud.tab>
        @endforeach
    </x-crud.tabs>
@endif
