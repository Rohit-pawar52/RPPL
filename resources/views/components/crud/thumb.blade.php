{{--
    A stored picture in a tidy frame (avatar, logo, cover): always shows the
    default picture when there is none or the file is gone (x-media-image).

        <x-crud.thumb :path="$player->photo_path" kind="user" />
        <x-crud.thumb :path="$ad->media_path" shape="wide" size="sm" fit="contain" />

    shape  circle | square | wide        size  xs | sm | md | lg | xl
    fit    cover (default) | contain     kind  user | image
--}}
@props(['path' => null, 'url' => null, 'kind' => 'image', 'shape' => 'circle', 'size' => 'md', 'fit' => 'cover', 'alt' => ''])

@php
    $dim = $shape === 'wide'
        ? ['xs' => 'h-8 w-12', 'sm' => 'h-10 w-16', 'md' => 'h-12 w-20', 'lg' => 'h-16 w-28', 'xl' => 'h-24 w-40']
        : ['xs' => 'h-8 w-8', 'sm' => 'h-10 w-10', 'md' => 'h-12 w-12', 'lg' => 'h-16 w-16', 'xl' => 'h-24 w-24'];
    $round = match ($shape) {
        'circle' => 'rounded-full',
        'wide' => 'rounded-lg',
        default => 'rounded-xl',
    };
    $imgClass = 'h-full w-full '.($fit === 'contain' ? 'object-contain' : 'object-cover');
@endphp

<span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center overflow-hidden border border-line bg-slate-50', $dim[$size] ?? $dim['md'], $round]) }}>
    <x-media-image :path="$path" :url="$url" :kind="$kind" :alt="$alt" loading="lazy" class="{{ $imgClass }}" />
</span>
