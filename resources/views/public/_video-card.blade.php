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
<article class="pub-card flex flex-col overflow-hidden">
    <div class="pub-media aspect-video w-full bg-slate-900">
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
    <div class="p-4">
        <h3 class="line-clamp-2 break-words text-[14px] font-semibold leading-snug text-slate-900">{{ $video->title }}</h3>
        @if($video->description)
            <p class="mt-1 line-clamp-2 break-words text-[13px] leading-relaxed text-slate-500">{{ $video->description }}</p>
        @endif
        @if($video->created_at)
            <p class="pub-meta mt-2">{{ display_datetime($video->created_at, 'd M Y') }}</p>
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
