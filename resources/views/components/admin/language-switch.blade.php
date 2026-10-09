{{--
    English / Hindi switch for the admin panel. A POST (it saves the choice on the
    signed-in user's account and in a cookie), so each side is its own tiny form.
        <x-admin.language-switch />            on a light bar
        <x-admin.language-switch dark />       on the navy sign-in backdrop
--}}
@props(['dark' => false])

@php
    $current = app()->getLocale();
@endphp

<div {{ $attributes->class(['inline-flex shrink-0 items-center rounded-full p-0.5 text-[11px] font-semibold', 'bg-white/10 text-white/70' => $dark, 'bg-slate-100 text-slate-500' => ! $dark]) }} role="group" aria-label="{{ __('Language') }}">
    @foreach(['en' => 'EN', 'hi' => 'हिन्दी'] as $code => $label)
        <form method="POST" action="{{ route('admin.language.switch', $code) }}" class="contents">
            @csrf
            <button
                type="submit"
                lang="{{ $code }}"
                aria-pressed="{{ $current === $code ? 'true' : 'false' }}"
                @class([
                    'min-h-7 rounded-full px-2.5 py-1 transition-colors',
                    'bg-white text-slate-900 shadow-sm' => $current === $code && ! $dark,
                    'bg-white text-navy-950' => $current === $code && $dark,
                    'hover:text-slate-800' => $current !== $code && ! $dark,
                    'hover:text-white' => $current !== $code && $dark,
                ])
            >{{ $label }}</button>
        </form>
    @endforeach
</div>
