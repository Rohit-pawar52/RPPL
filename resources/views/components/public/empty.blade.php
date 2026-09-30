{{-- Empty-state message inside a card or grid: <x-public.empty>No news available yet.</x-public.empty> --}}
<p {{ $attributes->class(['pub-empty']) }}>{{ $slot }}</p>
