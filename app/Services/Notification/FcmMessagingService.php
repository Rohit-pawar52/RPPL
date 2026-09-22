<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

/**
 * The one place this application talks to Firebase Cloud Messaging
 * (Phase B4). Wraps kreait/firebase-php's sendMulticast()/
 * MulticastSendReport behind a small, plain-array result — callers
 * (SendNotificationJob) never touch a Kreait class directly, and tests
 * mock this whole service rather than constructing real Firebase SDK
 * objects or requiring real credentials (see the Phase B4 report).
 *
 * BATCH_LIMIT = 500 is kreait/firebase-php's own documented per-call
 * multicast limit (Kreait\Firebase\Contract\Messaging::BATCH_MESSAGE_LIMIT
 * — deprecated as a public constant in 7.5.0, but still the actual FCM
 * server-side limit sendMulticast() enforces internally). Verified
 * against the installed 7.24.1 source, not guessed or copied from
 * ShopStack (which never chunked at all — see the Phase B4 report).
 */
class FcmMessagingService
{
    public const BATCH_LIMIT = 500;

    public function __construct(private readonly Messaging $messaging) {}

    /**
     * Sends one notification to every token in $tokens, chunked to
     * BATCH_LIMIT per Firebase call.
     *
     * Never throws for a chunk-level failure (the whole HTTP call
     * failing — credentials, network, malformed request — rather than
     * an individual token being rejected): every token in that chunk is
     * counted as failed (attempted but unconfirmed) and the next chunk
     * is still attempted, since a single chunk's outage says nothing
     * about the others. A chunk failure never marks any token
     * permanently invalid — there is no per-token evidence for that in
     * this case. Only Kreait's OWN per-token classification
     * (MulticastSendReport::invalidTokens()/unknownTokens(), never a
     * string-matched guess of our own) ever populates invalid_tokens —
     * unknownTokens() is backed by the structured NotFound exception
     * type (an unregistered/gone token), invalidTokens() by Kreait's
     * own invalid-token detection on the report.
     *
     * @param  list<string>  $tokens
     * @return array{attempted: int, success: int, failure: int, invalid_tokens: list<string>}
     */
    public function sendToTokens(array $tokens, string $title, string $body, string $actionUrl): array
    {
        $message = CloudMessage::new()
            ->withNotification(FirebaseNotification::create($title, $body))
            ->withData(['action_url' => $actionUrl]);

        $attempted = 0;
        $success = 0;
        $failure = 0;
        $invalidTokens = [];

        foreach (array_chunk($tokens, self::BATCH_LIMIT) as $chunk) {
            $attempted += count($chunk);

            try {
                $report = $this->messaging->sendMulticast($message, $chunk);

                $success += $report->successes()->count();
                $failure += $report->failures()->count();
                $invalidTokens = [...$invalidTokens, ...$report->invalidTokens(), ...$report->unknownTokens()];
            } catch (FirebaseException $e) {
                // Never log the tokens themselves — only the count and a
                // generic error message.
                Log::warning('FCM multicast chunk failed.', [
                    'chunk_size' => count($chunk),
                    'error' => $e->getMessage(),
                ]);

                $failure += count($chunk);
            }
        }

        return [
            'attempted' => $attempted,
            'success' => $success,
            'failure' => $failure,
            'invalid_tokens' => array_values(array_unique($invalidTokens)),
        ];
    }
}
