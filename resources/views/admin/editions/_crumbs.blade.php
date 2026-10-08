{{-- "Editions › RPPL Season 3 › Teams": where you are inside a season.
     Expects $edition; optional $section (the last, current crumb).
     The season's own pages are opened with their module's permission (teams, squads, matches,
     registrations), not editions.view, so the first two crumbs are links only for a role that may
     open the editions list and the hub. --}}
@php $canOpenEditions = auth()->user()->can('viewAny', \App\Models\Edition::class); @endphp
<nav aria-label="Season" class="mb-3 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
    @if($canOpenEditions)
        <a href="{{ route('admin.editions.index') }}" class="hover:text-slate-800 hover:underline">Editions</a>
    @else
        <span>Editions</span>
    @endif
    <span class="text-slate-300" aria-hidden="true">&rsaquo;</span>
    @if(! empty($section))
        @if($canOpenEditions)
            <a href="{{ route('admin.editions.show', $edition) }}" class="hover:text-slate-800 hover:underline">{{ $edition->name }}</a>
        @else
            <span>{{ $edition->name }}</span>
        @endif
        <span class="text-slate-300" aria-hidden="true">&rsaquo;</span>
        <span class="font-medium text-slate-800" aria-current="page">{{ $section }}</span>
    @else
        <span class="font-medium text-slate-800" aria-current="page">{{ $edition->name }}</span>
    @endif
</nav>
