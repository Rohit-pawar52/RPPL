{{-- Public-only notice ticker (Phase 3.45) — included once from
     layouts.public, above the header. Renders nothing at all (not even
     an empty bar) when there are zero currently-active announcements,
     so the header never reserves dead space. Message text always goes
     through {{ }}, never {!! !!} — announcements are plain text only,
     emoji/Unicode render fine, a literal <script> tag is displayed as
     text, never executed. --}}
@if($activeAnnouncements->isNotEmpty())
    @php
        $combinedLength = $activeAnnouncements->sum(fn ($announcement) => mb_strlen($announcement->message));
        // A rough, JS-free way to keep scroll speed roughly proportional
        // to content length — clamped so a very short or very long
        // ticker still scrolls at a sane pace.
        $duration = max(15, min(60, (int) round($combinedLength * 0.18)));
    @endphp
    <div class="rppl-ticker" role="region" aria-label="Announcements">
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
