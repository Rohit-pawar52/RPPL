<?php

namespace App\Services\Notification;

use App\Models\FcmToken;
use Illuminate\Database\QueryException;

/**
 * The single writer for guest FCM token subscription (Phase B1). Only
 * ever refreshes is_active/last_seen_at on an existing row — never
 * user_id/player_id — so a future identified-visitor association (set
 * only by a later phase, never by this service) can never be silently
 * cleared by an anonymous re-subscription of the same token. See the
 * Phase B1 audit report for why both ownership columns stay untouched
 * here.
 */
class FcmTokenSubscriptionService
{
    /**
     * token has a UNIQUE DB constraint, which is what makes this safe
     * under concurrency without a lock: if two simultaneous requests for
     * the same brand-new token both reach the create() branch, exactly
     * one INSERT wins and the other hits the UNIQUE constraint — caught
     * here and treated exactly like the existing-token branch, rather
     * than surfacing a raw 500. The same QueryException/23000 pattern
     * already used by PlayerRegistrationService::createRegistration()
     * and TeamPlayerService::createTeamPlayer().
     *
     * @return bool true when a new row was created, false when an
     *              existing row was refreshed — used only to pick the response
     *              status code, never anything a caller needs to branch on.
     */
    public function subscribe(string $token): bool
    {
        $existing = FcmToken::where('token', $token)->first();

        if ($existing) {
            $this->refresh($existing);

            return false;
        }

        try {
            FcmToken::create([
                'token' => $token,
                'is_active' => true,
                'last_seen_at' => now(),
            ]);

            return true;
        } catch (QueryException $e) {
            if ((int) $e->getCode() !== 23000) {
                throw $e;
            }

            $this->refresh(FcmToken::where('token', $token)->firstOrFail());

            return false;
        }
    }

    private function refresh(FcmToken $fcmToken): void
    {
        $fcmToken->update([
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
    }
}
