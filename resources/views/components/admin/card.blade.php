@props(['title' => null, 'flush' => false])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-slate-200 bg-white']) }}>
    @if($title || isset($actions))
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-2.5">
            @if($title)<h2 class="text-[13px] font-semibold text-slate-800">{{ $title }}</h2>@endif
            @isset($actions)<div class="flex items-center gap-2">{{ $actions }}</div>@endisset
        </div>
    @endif
    <div @class(['p-4' => ! $flush, 'overflow-x-auto' => $flush])>
        {{ $slot }}
    </div>
</div>
