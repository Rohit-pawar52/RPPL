{{--
    The slim hero band at the top of the homepage: the season, its state, the
    two things most visitors come for (Matches / Register - or, once
    registration is over, "my registration") and a few numbers. Small on
    purpose: on a phone the live match must stay on the first screen.
    Expects $edition (or null), $standings.
--}}
@php
    $registration = $edition?->publicRegistrationState();
    $registrationOpen = $registration === \App\Models\Edition::REGISTRATION_STATE_OPEN;
    $closesOn = $registrationOpen && $edition->registration_closes_at ? display_datetime($edition->registration_closes_at, 'd M') : null;
    $opensOn = $registration === \App\Models\Edition::REGISTRATION_STATE_NOT_YET_OPEN && $edition->registration_opens_at ? display_datetime($edition->registration_opens_at, 'd M') : null;

    $teamCount = $standings->count();
    $playedCount = (int) round($standings->sum('played') / 2);
    $leader = $playedCount > 0 ? $standings->first() : null;
    $leaderTeam = $leader ? ($leader['edition_team']?->team) : null;

    $ghost = 'btn btn-lg bg-white/10 text-white ring-1 ring-inset ring-white/20 hover:bg-white/20 focus-visible:outline-white';
@endphp

<section class="ps-hero" aria-label="{{ $edition?->name ?? $branding->applicationName }}">
    <div class="px-5 py-6 sm:px-8 sm:py-8 lg:flex lg:items-end lg:justify-between lg:gap-10">
        <div class="min-w-0 lg:max-w-xl">
            <div class="flex flex-wrap items-center gap-2">
                @if($edition)
                    <x-public.status-pill :status="$edition->status" />
                @endif
                @if($registrationOpen)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-0.5 text-[11px] font-semibold text-accent-dark ring-1 ring-inset ring-white/15">
                        <span class="live-dot" aria-hidden="true"></span>
                        {{ __('ux_public_shell.home.registration_open') }}@if($closesOn) <span class="font-medium text-white/60">&middot; {{ __('ux_public_shell.home.closes', ['date' => $closesOn]) }}</span>@endif
                    </span>
                @elseif($opensOn)
                    <span class="inline-flex items-center rounded-full bg-white/10 px-2.5 py-0.5 text-[11px] font-semibold text-white/80 ring-1 ring-inset ring-white/15">
                        {{ __('ux_public_shell.home.opens', ['date' => $opensOn]) }}
                    </span>
                @endif
            </div>

            <h1 class="mt-3 break-words text-[1.75rem] font-bold leading-tight tracking-tight sm:text-4xl">{{ $edition?->name ?? $branding->applicationName }}</h1>
            @if($edition)
                <p class="mt-1 text-sm text-white/65">{{ $branding->applicationName }}</p>
            @elseif($branding->tagline)
                <p class="mt-1 text-sm text-white/65">{{ $branding->tagline }}</p>
            @endif

            <div class="mt-5 flex flex-wrap gap-2.5">
                @if($registrationOpen || ! $edition)
                    <a href="{{ route('public.player-registration.create') }}" class="btn btn-primary btn-lg">
                        <x-icon name="user-plus" class="h-5 w-5" />
                        {{ __('ux_public_shell.home.register_now') }}
                    </a>
                    <a href="{{ route('public.matches.index') }}" class="{{ $ghost }}">{{ __('ux_public_shell.home.cta_matches') }}</a>
                @else
                    <a href="{{ route('public.matches.index') }}" class="btn btn-primary btn-lg">
                        <x-icon name="calendar" class="h-5 w-5" />
                        {{ __('ux_public_shell.home.cta_matches') }}
                    </a>
                    <a href="{{ route('public.player-registration.status') }}" class="{{ $ghost }}">{{ __('ux_public_shell.nav.check_status') }}</a>
                @endif
            </div>
        </div>

        @if($edition && $teamCount > 0)
            <dl class="mt-6 hidden gap-3 sm:grid sm:grid-cols-3 lg:mt-0 lg:w-[26rem] lg:shrink-0">
                <div class="ps-hero-stat">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-white/55">{{ __('ux_public_shell.home.stat_teams') }}</dt>
                    <dd class="mt-0.5 text-2xl font-bold tabular-nums">{{ $teamCount }}</dd>
                </div>
                <div class="ps-hero-stat">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-white/55">{{ __('ux_public_shell.home.stat_played') }}</dt>
                    <dd class="mt-0.5 text-2xl font-bold tabular-nums">{{ $playedCount }}</dd>
                </div>
                <div class="ps-hero-stat min-w-0">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-white/55">{{ __('ux_public_shell.home.stat_leader') }}</dt>
                    <dd class="mt-1 truncate text-base font-bold leading-8">{{ $leaderTeam ? ($leaderTeam->short_name ?: $leaderTeam->name) : '—' }}</dd>
                </div>
            </dl>
        @endif
    </div>
</section>
