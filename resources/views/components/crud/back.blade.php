{{-- "<- Players": the way back from a form or a detail page. --}}
@props(['href'])

<a href="{{ $href }}" class="crud-back">
    <x-crud.glyph name="arrow-left" class="h-4 w-4" />
    {{ $slot }}
</a>
