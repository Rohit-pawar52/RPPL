{{--
    Round team crest used across the cricket pages (match cards, score header,
    points table, scorecard). Shows the team's logo; a team with no logo (or a
    logo that cannot be loaded) shows the site's default picture, like every
    other picture on the site (<x-media-image>).
        <x-mx.team-logo :team="$editionTeam->team" size="md" />
    size: xs 24px, sm 32px, md 40px, lg 48px, xl 56-64px. `dark` is for the navy
    score header.
--}}
@props(['team', 'size' => 'md', 'dark' => false])

@php
    $sizes = [
        'xs' => 'size-6',
        'sm' => 'size-8',
        'md' => 'size-10',
        'lg' => 'size-12',
        'xl' => 'size-14 sm:size-16',
    ];
@endphp

<span {{ $attributes->class(['mx-logo', $sizes[$size] ?? $sizes['md'], 'mx-logo-dark' => $dark]) }}>
    <x-media-image :path="$team->logo_path ?? null" kind="image" alt="" class="h-full w-full object-cover" loading="lazy" />
</span>
