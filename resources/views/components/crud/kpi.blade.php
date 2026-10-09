{{--
    One big number of a summary strip. tone: neutral | in (money in, green) |
    out (money out, red) | brand. Wish: x-stat-card could take a tone too.

        <x-crud.kpi label="Income" :value="money($in)" tone="in" icon="income" sub="12 entries" />
--}}
@props(['label', 'value', 'tone' => 'neutral', 'icon' => null, 'sub' => null])

<div {{ $attributes->class(['crud-kpi', 'crud-kpi-in' => $tone === 'in', 'crud-kpi-out' => $tone === 'out', 'crud-kpi-brand' => $tone === 'brand']) }}>
    <p class="crud-kpi-label">
        @if($icon)
            <span class="crud-kpi-icon">
                @if(in_array($icon, ['income', 'expense', 'receipt', 'clock', 'send', 'image', 'film', 'link', 'lock', 'alert', 'info', 'sparkle'], true))
                    <x-crud.glyph :name="$icon" class="h-4 w-4" />
                @else
                    <x-icon :name="$icon" class="h-4 w-4" />
                @endif
            </span>
        @endif
        <span class="min-w-0 truncate">{{ $label }}</span>
    </p>
    <p class="crud-kpi-value">{{ $value }}</p>
    @if($sub)<p class="crud-kpi-sub">{{ $sub }}</p>@endif
</div>
