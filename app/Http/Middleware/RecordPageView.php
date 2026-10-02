<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use App\Services\Analytics\PageViewRecorder;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Applied only to the three public detail routes (see routes/web.php):
 *
 *   RecordPageView::class.':match_view'    public.matches.show
 *   RecordPageView::class.':edition_view'  public.editions.show
 *   RecordPageView::class.':player_view'   public.players.show
 *
 * It runs AFTER the controller, so only a successfully rendered page (200,
 * after route-model binding) is considered — 404s, redirects and the other
 * match tabs/endpoints are never routed through it. The database write is
 * deferred until after the response has been sent and is strictly best
 * effort: any analytics failure is reported and swallowed, never shown to
 * the visitor.
 */
class RecordPageView
{
    /**
     * Route parameter that carries each event's subject.
     */
    private const SUBJECT_PARAMETERS = [
        PageView::MATCH_VIEW => 'match',
        PageView::EDITION_VIEW => 'edition',
        PageView::PLAYER_VIEW => 'player',
    ];

    public function __construct(private readonly PageViewRecorder $recorder) {}

    public function handle(Request $request, Closure $next, string $eventType): Response
    {
        $response = $next($request);

        try {
            if (! $this->recorder->shouldTrack($request, $response)) {
                return $response;
            }

            $subject = $request->route(self::SUBJECT_PARAMETERS[$eventType] ?? '_');
            $subjectId = $subject instanceof Model ? (int) $subject->getKey() : (int) $subject;

            if ($subjectId < 1) {
                return $response;
            }

            [$visitorId, $isNew] = $this->recorder->visitorFor($request);

            if ($isNew) {
                $response->headers->setCookie($this->recorder->cookieFor($visitorId));
            }

            app()->terminating(function () use ($eventType, $subjectId, $visitorId) {
                try {
                    $this->recorder->record($eventType, $subjectId, $visitorId);
                } catch (Throwable $e) {
                    report($e);
                }
            });
        } catch (Throwable $e) {
            report($e);
        }

        return $response;
    }
}
