{{--
    One public video card, shared by the homepage's Featured Videos
    section and the /videos index. Expects $video (App\Models\Video).

    Playback is plain native HTML5 and NEVER automatic: no autoplay
    attribute, no scripted play(). The browser's own Play control is the
    explicit user click. preload="metadata" keeps page load from pulling
    the whole file; poster shows the thumbnail until playback starts and
    is omitted entirely when there is no thumbnail (the element's own
    neutral surface shows instead — no generated thumbnails).
--}}
<article class="overflow-hidden rounded-lg border border-neutral-200 bg-white">
    <div class="aspect-video w-full bg-neutral-100">
        <video
            class="rppl-featured-video h-full w-full object-cover"
            controls
            preload="metadata"
            playsinline
            @if($video->thumbnail_path) poster="{{ Illuminate\Support\Facades\Storage::url($video->thumbnail_path) }}" @endif
        >
            <source src="{{ Illuminate\Support\Facades\Storage::url($video->video_path) }}" />
            {{ __('directory.videos.unsupported') }}
        </video>
    </div>
    <div class="p-3">
        <h3 class="truncate text-[13px] font-medium text-neutral-800">{{ $video->title }}</h3>
        @if($video->description)
            <p class="mt-0.5 line-clamp-2 text-[11px] text-neutral-500">{{ $video->description }}</p>
        @endif
    </div>
</article>

@once
    {{-- Only reacts to a play the user already started: pauses any other
         playing card so two clips never talk over each other. Never
         starts playback itself. Capture phase because media events
         don't bubble, and one document-level listener covers every card. --}}
    <script>
        document.addEventListener('play', function (event) {
            if (! event.target.classList || ! event.target.classList.contains('rppl-featured-video')) {
                return;
            }
            document.querySelectorAll('video.rppl-featured-video').forEach(function (other) {
                if (other !== event.target && ! other.paused) {
                    other.pause();
                }
            });
        }, true);
    </script>
@endonce
