{{-- One label + value in a facts grid. `big` makes the value a large number.
        <dl class="crud-facts"><x-crud.fact label="Phone">{{ $phone }}</x-crud.fact></dl> --}}
@props(['label', 'big' => false])

<div {{ $attributes->class(['min-w-0']) }}>
    <dt class="crud-fact-label">{{ $label }}</dt>
    <dd @class(['crud-fact-value', 'crud-fact-big' => $big])>{{ $slot }}</dd>
</div>
