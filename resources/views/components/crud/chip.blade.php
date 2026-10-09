{{-- A quick-filter chip that is a link. --}}
@props(['href', 'active' => false])

<a href="{{ $href }}" class="crud-chip" @if($active) aria-current="true" @endif>{{ $slot }}</a>
