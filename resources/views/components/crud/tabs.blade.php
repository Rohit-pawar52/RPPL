{{-- A row of page tabs (links): <x-crud.tabs><x-crud.tab :href :active>Label</x-crud.tab></x-crud.tabs> --}}
<nav class="crud-tabs" {{ $attributes }}>
    {{ $slot }}
</nav>
