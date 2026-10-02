<?php

namespace App\Services\Analytics;

use App\Models\PageView;
use App\Services\Settings\DisplayTimezoneFormatter;
use App\Support\BotDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records public detail-page visits (match_view, edition_view, player_view)
 * for future analytics. Capture only — nothing here reads analytics back.
 *
 * What counts: a successful (200) browser GET of a tracked detail page,
 * after route-model binding. What never counts: HEAD/POST, redirects and
 * errors, AJAX/JSON requests, prefetches, logged-in admin/scorer staff and
 * (best-effort) bots. Which routes are tracked is decided in
 * App\Http\Middleware\RecordPageView; a "match view" is the Match Info page
 * only, not its scorecard/squads/live tabs, and the live-data polling
 * endpoint is never tracked.
 *
 * Visitors are identified by a first-party random-UUID cookie (rppl_vid),
 * and only an HMAC of it is stored. Repeat visits by the same visitor to
 * the same subject within DEDUPE_MINUTES of the last RECORDED view are not
 * recorded again; the player page's ?edition_id=/?page= variants are the
 * same subject because the subject is the player's id, not the URL.
 */
class PageViewRecorder
{
    public const VISITOR_COOKIE = 'rppl_vid';

    public const COOKIE_MINUTES = 60 * 24 * 365 * 2;

    public const DEDUPE_MINUTES = 30;

    public function __construct(private readonly DisplayTimezoneFormatter $displayTimezone) {}

    public function shouldTrack(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && $response->getStatusCode() === 200
            && ! $request->ajax()
            && ! $request->expectsJson()
            && ! $this->isPrefetch($request)
            && ! auth()->check()
            && ! BotDetector::isBot($request->userAgent());
    }

    /**
     * @return array{0: string, 1: bool} the visitor's raw id and whether it is new
     */
    public function visitorFor(Request $request): array
    {
        $existing = $request->cookie(self::VISITOR_COOKIE);

        if (is_string($existing) && Str::isUuid($existing)) {
            return [$existing, false];
        }

        return [(string) Str::uuid(), true];
    }

    public function cookieFor(string $visitorId): Cookie
    {
        return cookie(
            self::VISITOR_COOKIE,
            $visitorId,
            self::COOKIE_MINUTES,
            config('session.path', '/'),
            config('session.domain'),
            config('session.secure'),
            true,
            false,
            config('session.same_site', 'lax'),
        );
    }

    public function hash(string $visitorId): string
    {
        return hash_hmac('sha256', $visitorId, (string) config('app.key'));
    }

    /**
     * Writes one page_views row unless the same visitor already has a
     * recorded view of this subject within the dedupe window. Returns the
     * new row, or null when skipped.
     */
    public function record(string $eventType, int $subjectId, string $visitorId, ?Carbon $at = null): ?PageView
    {
        if (! in_array($eventType, PageView::EVENT_TYPES, true) || $subjectId < 1) {
            return null;
        }

        $at ??= now();
        $hash = $this->hash($visitorId);

        $alreadyCounted = PageView::query()
            ->where('visitor_hash', $hash)
            ->where('event_type', $eventType)
            ->where('subject_id', $subjectId)
            ->where('viewed_at', '>', $at->copy()->subMinutes(self::DEDUPE_MINUTES))
            ->exists();

        if ($alreadyCounted) {
            return null;
        }

        return PageView::create([
            'event_type' => $eventType,
            'subject_id' => $subjectId,
            'visitor_hash' => $hash,
            'viewed_at' => $at,
            'local_date' => $this->displayTimezone->format($at, 'Y-m-d'),
            'local_hour' => (int) $this->displayTimezone->format($at, 'G'),
        ]);
    }

    private function isPrefetch(Request $request): bool
    {
        return in_array(strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose'))), ['prefetch', 'prefetch;prerender'], true);
    }
}
