{{--
    Phase 3.48 — the Finance tab bar, shared across five separate
    controllers/routes (Overview, Contributions, Ledger, Contributors,
    Committee). Styled identically to the Settings tab bar
    (resources/views/admin/settings/index.blade.php) so Finance reads as
    one consolidated admin area rather than five unrelated pages, even
    though each tab keeps its own URL. $edition (nullable) is threaded
    through so switching tabs keeps looking at the same edition.
--}}
@php
    $editionQuery = isset($edition) && $edition ? ['edition_id' => $edition->id] : [];
    $tabs = [
        'overview' => ['label' => 'Overview', 'route' => 'admin.finance.overview', 'active' => 'admin.finance.overview'],
        'contributions' => ['label' => 'Contributions', 'route' => 'admin.edition-contributions.index', 'active' => 'admin.edition-contributions.*'],
        'ledger' => ['label' => 'Ledger', 'route' => 'admin.edition-transactions.index', 'active' => 'admin.edition-transactions.*'],
        'contributors' => ['label' => 'Contributors', 'route' => 'admin.contributors.index', 'active' => 'admin.contributors.*'],
        'committee' => ['label' => 'Committee', 'route' => 'admin.finance.committee', 'active' => 'admin.finance.committee'],
    ];
@endphp

<div class="mb-4 flex flex-wrap gap-1 border-b border-neutral-200">
    @foreach($tabs as $tab)
        <a
            href="{{ route($tab['route'], $editionQuery) }}"
            class="rounded-t-md px-3 py-2 text-[13px] font-medium {{ request()->routeIs($tab['active']) ? 'border-b-2 theme-primary-border theme-primary-text' : 'text-neutral-500 hover:text-neutral-700' }}"
        >
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
