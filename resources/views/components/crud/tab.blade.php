@props(['href', 'active' => false])

<a href="{{ $href }}" class="crud-tab" @if($active) aria-current="page" @endif>{{ $slot }}</a>
