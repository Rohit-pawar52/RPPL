{{-- The search box of a list toolbar (put it inside <x-table-filters>). Enter
     applies it. --}}
@props(['name' => 'search', 'value' => '', 'placeholder' => __('Search…')])

<div class="crud-search">
    <x-crud.glyph name="search" />
    <input
        type="search"
        name="{{ $name }}"
        value="{{ $value }}"
        placeholder="{!! $placeholder !!}"
        aria-label="{{ strip_tags(html_entity_decode($placeholder)) }}"
        autocomplete="off"
        class="crud-field"
    />
</div>
