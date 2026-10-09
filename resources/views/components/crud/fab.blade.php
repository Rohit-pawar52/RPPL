{{-- The primary "+ New" on a phone: a round button that stays in the corner
     while the list scrolls. From sm up the header button is used instead. --}}
@props(['href', 'label'])

<div class="crud-fab-space" aria-hidden="true"></div>
<a href="{{ $href }}" class="crud-fab" aria-label="{{ $label }}">
    <x-crud.glyph name="plus" class="h-5 w-5" />
    {{ $label }}
</a>
