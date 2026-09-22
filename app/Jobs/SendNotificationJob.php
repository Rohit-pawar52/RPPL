<?php

namespace App\Jobs;

use App\Models\FcmToken;
use App\Models\NotificationSend;
use App\Services\Notification\FcmMessagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends ONE NotificationSend's immutable snapshot content to every
 * currently active FcmToken (queried when THIS job begins, never
 * persisted as a token list — see the Phase B4 report), via
 * FcmMessagingService, and records the resulting attempted/success/
 * failure counts.
 *
 * $tries = 1, no backoff — DELIBERATE, not an oversight. Push delivery
 * is not idempotent: if this job partially or fully succeeded and were
 * then automatically retried after a worker crash, some devices could
 * receive the same visible notification twice. V1 prefers a human
 * clicking Resend (which creates a brand-new NotificationSend row,
 * never touching this one) over an automatic whole-broadcast retry.
 *
 * KNOWN V1 LIMITATION: a worker/process crash after Firebase has
 * accepted a chunk but before this job persists completed_at cannot be
 * made perfectly exactly-once without substantially more per-token
 * idempotency machinery (e.g. per-token delivery rows). V1 deliberately
 * does not add that complexity — see the Phase B4 report.
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public NotificationSend $send) {}

    public function handle(): void
    {
        // Idempotency guard: locks only long enough to check/claim this
        // row is not already completed — released immediately, never
        // held across a Firebase network call. This does not, and
        // cannot, provide perfect exactly-once delivery (see the class
        // docblock) — it only guards against this exact job instance
        // accidentally running its send logic twice.
        $alreadyCompleted = DB::transaction(function () {
            $locked = NotificationSend::whereKey($this->send->id)->lockForUpdate()->firstOrFail();

            return $locked->completed_at !== null;
        });

        if ($alreadyCompleted) {
            return;
        }

        $tokens = FcmToken::active()->pluck('token')->all();

        if ($tokens === []) {
            // A successfully completed zero-audience send — not a
            // failure, and no Firebase call is made at all.
            $this->send->update([
                'attempted_count' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'completed_at' => now(),
            ]);

            return;
        }

        // Resolved lazily, and only here — never via constructor/method
        // injection on handle() itself — so the zero-token path above
        // never attempts to resolve Firebase/credentials at all.
        $result = app(FcmMessagingService::class)->sendToTokens(
            $tokens,
            title: $this->send->title_snapshot,
            body: $this->send->message_snapshot,
            actionUrl: $this->send->action_url_snapshot ?? '/',
        );

        if ($result['invalid_tokens'] !== []) {
            FcmToken::whereIn('token', $result['invalid_tokens'])->update(['is_active' => false]);
        }

        $this->send->update([
            'attempted_count' => $result['attempted'],
            'success_count' => $result['success'],
            'failure_count' => $result['failure'],
            'completed_at' => now(),
        ]);
    }

    /**
     * Reached only if handle() itself threw (an unexpected exception,
     * not an ordinary per-token/per-chunk Firebase failure — those are
     * already handled inside FcmMessagingService and never throw).
     * Deliberately does nothing to the NotificationSend row: with
     * $tries = 1, there is no later attempt to reconcile against, and
     * the actual outcome (which, if any, tokens were reached) is
     * unknown at this point — leaving completed_at null (a permanently
     * "Queued/Processing"-looking row) is preferable to fabricating
     * counts or guessing at completion. An admin can always click
     * Resend. Never logs raw tokens or credentials.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('SendNotificationJob failed before completion.', [
            'notification_send_id' => $this->send->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
