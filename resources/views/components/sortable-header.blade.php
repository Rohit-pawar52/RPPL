{{--
    A clickable <th> label that toggles asc/desc for one already
    allow-listed sort column, preserving every other current query
    param (search, filters, date range, page) via fullUrlWithQuery().
--}}
@props(['column', 'sort', 'direction'])

@php
    $isActive = $sort === $column;
    $nextDirection = $isActive && $direction === 'asc' ? 'desc' : 'asc';
    $url = request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDirection, 'page' => null]);
@endphp

{{--
    The arrow must be a real Unicode character (not an '&uarr;'/'&darr;'
    HTML entity string): {{ }} escapes its output via e(), so an entity
    string here would double-escape into literal on-page text like
    "&amp;darr;" instead of a glyph — same failure mode already fixed
    once for the '&middot;' page-title bug earlier this project.
--}}
<a href="{{ $url }}" class="group inline-flex items-center gap-1 rounded transition-colors hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand {{ $isActive ? 'text-brand' : '' }}" @if($isActive) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif>
    {{ $slot }}
    @if($isActive)
        <span class="text-brand" aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>
    @endif
</a>
