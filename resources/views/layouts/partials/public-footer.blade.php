{{--
    The public footer: who we are, quick links, contact (only when it is filled
    in under Settings), and the legal pages. Everything is plain links, so it
    works at any width: one column on a phone, up to four on a desktop.
--}}
@php
    $canSwitchLanguage = session()->isStarted();
    $locale = app()->getLocale();

    $tournament = [
        [route('public.matches.index'), __('public.nav.matches')],
        [route('public.teams.index'), __('public.nav.teams')],
        [route('public.players.index'), __('public.nav.players')],
        [route('public.editions.index'), __('public.nav.editions')],
        [route('public.venues.index'), __('public.nav.venues')],
    ];
    $explore = [
        [route('public.news.index'), __('directory.news.title')],
        [route('public.photos.index'), __('directory.photos.title')],
        [route('public.videos.index'), __('directory.videos.title')],
        [route('public.rules.index'), __('public.nav.rules')],
        [route('public.faqs'), __('public.nav.faqs')],
    ];
    $linkClass = 'inline-flex min-h-8 items-center text-[13px] text-slate-400 transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-dark';
@endphp

<footer class="mt-8 bg-navy-950 text-slate-300 lg:mt-12">
    <div class="mx-auto w-full max-w-6xl px-4 pb-6 pt-10 lg:px-6">
        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1.3fr] lg:gap-10">
            <div class="min-w-0 sm:col-span-2 lg:col-span-1">
                <a href="{{ route('public.home') }}" class="inline-flex items-center gap-2.5 text-white">
                    @if($branding->logoUrl)
                        <img src="{{ $branding->logoUrl }}" alt="" class="h-9 w-9 rounded-lg bg-white/10 object-contain" />
                    @else
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-accent-dark text-base font-bold text-navy-950">
                            {{ Illuminate\Support\Str::substr($branding->shortName, 0, 1) }}
                        </span>
                    @endif
                    <span class="text-base font-bold tracking-tight">{{ $branding->applicationName }}</span>
                </a>
                @if($branding->tagline)
                    <p class="mt-3 max-w-xs text-[13px] leading-relaxed text-slate-400">{{ $branding->tagline }}</p>
                @endif
                <a href="{{ route('public.player-registration.create') }}" class="btn btn-primary mt-5">
                    <x-icon name="user-plus" class="h-4 w-4" />
                    {{ __('ux_public_shell.nav.register_long') }}
                </a>
            </div>

            <nav aria-label="{{ __('ux_public_shell.footer.tournament') }}">
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-white/50">{{ __('ux_public_shell.footer.tournament') }}</h2>
                <ul class="mt-2 space-y-0.5">
                    @foreach($tournament as [$url, $label])
                        <li><a href="{{ $url }}" class="{{ $linkClass }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <nav aria-label="{{ __('ux_public_shell.footer.explore') }}">
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-white/50">{{ __('ux_public_shell.footer.explore') }}</h2>
                <ul class="mt-2 space-y-0.5">
                    @foreach($explore as [$url, $label])
                        <li><a href="{{ $url }}" class="{{ $linkClass }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>

            @if($branding->hasContactDetails())
                <div class="min-w-0 sm:col-span-2 lg:col-span-1">
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-white/50">{{ __('ux_public_shell.footer.contact') }}</h2>
                    <ul class="mt-2 space-y-1 text-[13px] text-slate-400">
                        @if($branding->contactEmail)
                            <li class="flex items-start gap-2.5">
                                <x-icon name="mail" class="mt-0.5 h-4 w-4 shrink-0 text-white/40" />
                                <a href="mailto:{{ $branding->contactEmail }}" class="break-all hover:text-white">{{ $branding->contactEmail }}</a>
                            </li>
                        @endif
                        @if($branding->contactPhone)
                            <li class="flex items-start gap-2.5">
                                <x-icon name="phone" class="mt-0.5 h-4 w-4 shrink-0 text-white/40" />
                                <a href="tel:{{ $branding->contactPhone }}" class="hover:text-white">{{ $branding->contactPhone }}</a>
                            </li>
                        @endif
                        @if($branding->contactWhatsapp)
                            <li class="flex items-start gap-2.5">
                                <x-icon name="chat" class="mt-0.5 h-4 w-4 shrink-0 text-white/40" />
                                @if($branding->whatsappLink())
                                    <a href="{{ $branding->whatsappLink() }}" class="hover:text-white" target="_blank" rel="noopener">WhatsApp</a>
                                @else
                                    <span>{{ $branding->contactWhatsapp }}</span>
                                @endif
                            </li>
                        @endif
                        @if($branding->contactAddress)
                            <li class="flex items-start gap-2.5">
                                <x-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-white/40" />
                                <span class="min-w-0">{{ $branding->contactAddress }}</span>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif
        </div>

        <div class="mt-8 flex flex-col gap-3 border-t border-white/10 pt-5 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between">
            @if($branding->footerText)
                <p>{{ $branding->footerText }}</p>
            @else
                <p>&copy; {{ display_datetime(now(), 'Y') }} {{ $branding->applicationName }}</p>
            @endif

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                @foreach($footerContentPages as $page)
                    <a href="{{ $page->publicUrl() }}" class="inline-flex min-h-8 items-center hover:text-white">{{ $page->title }}</a>
                @endforeach

                @if($canSwitchLanguage)
                    <div class="inline-flex items-center rounded-full bg-white/10 p-0.5 font-semibold" role="group" aria-label="{{ __('public.language.switch_language') }}">
                        @foreach(['en' => 'EN', 'hi' => __('public.language.hindi')] as $code => $short)
                            <form method="POST" action="{{ route('public.language.switch', $code) }}">
                                @csrf
                                <button type="submit" class="rounded-full px-2.5 py-1 transition-colors {{ $locale === $code ? 'bg-white text-navy-900' : 'text-white/70 hover:text-white' }}" @if($locale === $code) aria-current="true" @endif>
                                    {{ $short }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</footer>
