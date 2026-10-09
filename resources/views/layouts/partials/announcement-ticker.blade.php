{{-- Public-only notice ticker (Phase 3.45) — included once from
     layouts.public, above the header. Renders nothing at all (not even
     an empty bar) when there are zero currently-active announcements,
     so the header never reserves dead space. Message text always goes
     through {{ }}, never {!! !!} — announcements are plain text only,
     emoji/Unicode render fine, a literal <script> tag is displayed as
     text, never executed. A small megaphone chip sits over the left edge
     so the bar reads as "news" at a glance. --}}
@if($activeAnnouncements->isNotEmpty())
    @php
        $combinedLength = $activeAnnouncements->sum(fn ($announcement) => mb_strlen($announcement->message));
        // A rough, JS-free way to keep scroll speed roughly proportional
        // to content length — clamped so a very short or very long
        // ticker still scrolls at a sane pace.
        $duration = max(15, min(60, (int) round($combinedLength * 0.18)));
    @endphp
    <div class="rppl-ticker relative" role="region" aria-label="{{ __('directory.announcements.label') }}">
        <span class="pointer-events-none absolute inset-y-0 left-0 z-10 flex items-center px-3 shadow-[8px_0_8px_-4px_rgb(0_0_0_/_0.12)]" style="background-color: var(--rppl-announcement-bg);" aria-hidden="true">
            <x-icon name="megaphone" class="h-4 w-4" />
        </span>
        <div class="rppl-ticker-track" style="--rppl-ticker-duration: {{ $duration }}s;">
            <span class="rppl-ticker-content">
                @foreach($activeAnnouncements as $announcement)
                    <span class="rppl-ticker-item">{{ $announcement->message }}</span>
                    <span class="rppl-ticker-separator" aria-hidden="true">&bull;</span>
                @endforeach
            </span>
            {{-- An exact second copy, purely for the seamless CSS loop
                 (translateX(-50%) — see app.css). Hidden from assistive
                 tech so the message is never announced twice, and
                 removed entirely (not just visually) under
                 prefers-reduced-motion. --}}
            <span class="rppl-ticker-content rppl-ticker-content-duplicate" aria-hidden="true">
                @foreach($activeAnnouncements as $announcement)
                    <span class="rppl-ticker-item">{{ $announcement->message }}</span>
                    <span class="rppl-ticker-separator" aria-hidden="true">&bull;</span>
                @endforeach
            </span>
        </div>
    </div>
@endif
