@props(['icon' => null, 'table' => false, 'colspan' => 1])

@php
    $inner = 'flex flex-col items-center justify-center gap-2 px-4 py-8 text-center text-[13px] text-slate-400';
@endphp

@if($table)
    <tr>
        <td colspan="{{ $colspan }}" class="p-0">
            <div class="{{ $inner }}">
                @if($icon)<x-icon :name="$icon" class="h-6 w-6 text-slate-300" />@endif
                <p>{{ $slot }}</p>
                @isset($action){{ $action }}@endisset
            </div>
        </td>
    </tr>
@else
    <div {{ $attributes->merge(['class' => $inner]) }}>
        @if($icon)<x-icon :name="$icon" class="h-6 w-6 text-slate-300" />@endif
        <p>{{ $slot }}</p>
        @isset($action){{ $action }}@endisset
    </div>
@endif
