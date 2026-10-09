{{--
    Empty-state message inside a card or grid: <x-public.empty>No news available yet.</x-public.empty>
    Optional: `icon` (a small icon above the line) and an `action` slot (the next step, e.g. a button):
        <x-public.empty icon="calendar">No matches yet.<x-slot:action><a class="btn btn-secondary btn-sm" href="…">…</a></x-slot:action></x-public.empty>
--}}
@props(['icon' => null])

<div {{ $attributes->class(['pub-empty flex flex-col items-center gap-2 !text-slate-500']) }}>
    @if($icon)
        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400"><x-icon :name="$icon" class="h-5 w-5" /></span>
    @endif
    <p>{{ $slot }}</p>
    @isset($action)
        <div class="mt-1">{{ $action }}</div>
    @endisset
</div>
