{{-- "Editions › RPPL Season 3 › Teams": where you are inside a season.
     Expects $edition; optional $section (the last, current crumb). --}}
<nav aria-label="Season" class="mb-3 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
    <a href="{{ route('admin.editions.index') }}" class="hover:text-slate-800 hover:underline">Editions</a>
    <span class="text-slate-300" aria-hidden="true">&rsaquo;</span>
    @if(! empty($section))
        <a href="{{ route('admin.editions.show', $edition) }}" class="hover:text-slate-800 hover:underline">{{ $edition->name }}</a>
        <span class="text-slate-300" aria-hidden="true">&rsaquo;</span>
        <span class="font-medium text-slate-800" aria-current="page">{{ $section }}</span>
    @else
        <span class="font-medium text-slate-800" aria-current="page">{{ $edition->name }}</span>
    @endif
</nav>
