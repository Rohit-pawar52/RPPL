{{--
    A team's round badge for the home page and the shell: its stored logo, or the
    default picture when it has none (see <x-media-image>).
        <x-public.team-logo :team="$editionTeam->team" class="size-8" />
    Decorative (empty alt) - always shown beside the team's name.
--}}
@props(['team'])

<span {{ $attributes->class(['inline-flex shrink-0 overflow-hidden rounded-full bg-white ring-1 ring-line']) }}>
    <x-media-image :path="$team->logo_path" kind="image" alt="" class="h-full w-full object-cover" loading="lazy" decoding="async" />
</span>
