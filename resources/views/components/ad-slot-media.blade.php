{{--
    The picture or clip inside a sponsor slot (see ad-slot.blade.php). Fills
    its parent box without ever changing its size, ignores clicks and drags,
    and removes the whole slot if the file cannot be loaded.
--}}
@props(['ad', 'eager' => false])

@if($ad->isVideo())
    <video
        muted
        loop
        playsinline
        preload="none"
        disablepictureinpicture
        disableremoteplayback
        aria-hidden="true"
        tabindex="-1"
        data-ad-src="{{ $ad->mediaUrl() }}"
        @if($ad->posterUrl()) poster="{{ $ad->posterUrl() }}" @endif
        class="pointer-events-none h-full w-full select-none object-contain"
        style="width: 100%; height: 100%; object-fit: contain;"
        onerror="this.closest('[data-ad]').remove()"
    ></video>
@else
    <img
        src="{{ $ad->mediaUrl() }}"
        alt="{{ $ad->title }}"
        @unless($eager) loading="lazy" @endunless
        decoding="async"
        draggable="false"
        class="pointer-events-none h-full w-full select-none object-contain"
        style="width: 100%; height: 100%; object-fit: contain;"
        onerror="this.closest('[data-ad]').remove()"
    >
@endif
