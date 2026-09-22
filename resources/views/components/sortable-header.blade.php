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

<a href="{{ $url }}" class="inline-flex items-center gap-0.5 hover:text-neutral-700">
    {{ $slot }}
    @if($isActive)
        <span class="text-neutral-400">{{ $direction === 'asc' ? '&uarr;' : '&darr;' }}</span>
    @endif
</a>
